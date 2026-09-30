<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Queue;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\DomainObjects\Enums\QueueState;
use HiEvents\Services\Domain\Queue\DTO\QueueEstimateDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Derives door throughput and a wait estimate from the scan stream.
 *
 * Nothing is entered by hand and no sensor is required: `access_logs` already records which
 * access point a scan hit and when, which is the whole data requirement.
 *
 * Every figure here is an estimate and is returned as one. A wait time presented as fact
 * will be wrong and will be quoted back at the operator, so the DTO carries a state
 * (FLOWING / BUSY / CONGESTED) and a range rather than a single confident number.
 *
 * @see docs/arzo-master-plan/20-queue-management.md
 */
class QueueEstimationService
{
    private const DEFAULT_WINDOW_SECONDS = 300;

    private const MIN_SCANS_FOR_SERVICE_TIME = 3;

    private const MAX_CREDIBLE_WAIT_SECONDS = 3600;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function estimate(
        int $accessPointId,
        int $windowSeconds = self::DEFAULT_WINDOW_SECONDS,
        ?CarbonImmutable $now = null,
    ): QueueEstimateDTO {
        $windowEnd = $now ?? CarbonImmutable::now();
        $windowStart = $windowEnd->subSeconds($windowSeconds);

        $scans = $this->databaseManager->table('access_logs')
            ->where('access_point_id', $accessPointId)
            ->where('direction', 'ENTRY')
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->orderBy('occurred_at')
            ->select(['occurred_at', 'result'])
            ->get();

        $granted = $scans
            ->filter(static fn (object $scan): bool => AccessResult::tryFrom((string) $scan->result)?->isGranted() === true)
            ->count();

        // A gate denying many scans is slower, not faster: each denial consumes staff time.
        // Counting only successes would show a congested door as quiet.
        $denied = $scans->count() - $granted;

        $minutes = $windowSeconds / 60;
        $throughputPerMinute = $minutes > 0 ? $granted / $minutes : 0.0;
        $arrivalsPerMinute = $minutes > 0 ? $scans->count() / $minutes : 0.0;

        $medianServiceSeconds = $this->medianGapSeconds($scans);
        $queueDepth = $this->queueDepth($arrivalsPerMinute, $throughputPerMinute, $minutes);

        return new QueueEstimateDTO(
            accessPointId: $accessPointId,
            windowStart: $windowStart->toIso8601String(),
            windowEnd: $windowEnd->toIso8601String(),
            scansGranted: $granted,
            scansDenied: $denied,
            throughputPerMinute: round($throughputPerMinute, 2),
            arrivalsPerMinute: round($arrivalsPerMinute, 2),
            medianServiceSeconds: $medianServiceSeconds,
            estimatedQueueDepth: $queueDepth,
            estimatedWaitSecondsLow: $this->waitSeconds($queueDepth, $throughputPerMinute, 0.7),
            estimatedWaitSecondsHigh: $this->waitSeconds($queueDepth, $throughputPerMinute, 1.4),
            state: $this->state($queueDepth, $arrivalsPerMinute, $throughputPerMinute),
            deniedShare: $scans->count() > 0 ? round($denied / $scans->count(), 3) : 0.0,
        );
    }

    /**
     * Persists one window so the arrival curve survives, which is what makes staffing
     * planning and post-event analysis possible at all.
     */
    public function capture(
        int $accessPointId,
        int $eventId,
        int $windowSeconds = 60,
        ?CarbonImmutable $now = null,
    ): QueueEstimateDTO {
        $estimate = $this->estimate($accessPointId, $windowSeconds, $now);

        $this->databaseManager->table('access_point_throughput_snapshots')->insert([
            'access_point_id' => $accessPointId,
            'event_id' => $eventId,
            'window_start' => $estimate->windowStart,
            'window_end' => $estimate->windowEnd,
            'scans_granted' => $estimate->scansGranted,
            'scans_denied' => $estimate->scansDenied,
            'median_service_seconds' => $estimate->medianServiceSeconds,
            'estimated_queue_depth' => $estimate->estimatedQueueDepth,
            'created_at' => now(),
        ]);

        return $estimate;
    }

    /**
     * Every door of one event, so an operator can see which gate is idle while another
     * is congested and move staff rather than only learn that a queue exists.
     *
     * @return Collection<int, QueueEstimateDTO>
     */
    public function estimateForEvent(
        int $eventId,
        int $windowSeconds = self::DEFAULT_WINDOW_SECONDS,
        ?CarbonImmutable $now = null,
    ): Collection {
        return $this->databaseManager->table('access_points')
            ->join('zones', 'zones.id', '=', 'access_points.zone_id')
            ->join('event_venues', 'event_venues.venue_id', '=', 'zones.venue_id')
            ->where('event_venues.event_id', $eventId)
            ->whereNull('access_points.deleted_at')
            ->whereNull('zones.deleted_at')
            ->distinct()
            ->pluck('access_points.id')
            ->map(fn ($accessPointId): QueueEstimateDTO => $this->estimate(
                (int) $accessPointId,
                $windowSeconds,
                $now
            ))
            ->sortByDesc(static fn (QueueEstimateDTO $estimate): int => $estimate->estimatedQueueDepth)
            ->values();
    }

    /**
     * A linear extrapolation of the arrival trend, which is explainable and debuggable at
     * 2am. A model might do marginally better; nobody can argue with this one at a gate.
     *
     * @return array{trend_per_minute: float, projected_depth: int, minutes_ahead: int, basis_windows: int}
     */
    public function projectDepth(int $accessPointId, int $minutesAhead = 10): array
    {
        $recent = $this->databaseManager->table('access_point_throughput_snapshots')
            ->where('access_point_id', $accessPointId)
            ->orderByDesc('window_start')
            ->limit(10)
            ->get(['scans_granted', 'scans_denied', 'estimated_queue_depth', 'window_start'])
            ->reverse()
            ->values();

        if ($recent->count() < 2) {
            return [
                'trend_per_minute' => 0.0,
                'projected_depth' => (int) ($recent->last()->estimated_queue_depth ?? 0),
                'minutes_ahead' => $minutesAhead,
                'basis_windows' => $recent->count(),
            ];
        }

        $first = (int) ($recent->first()->estimated_queue_depth ?? 0);
        $last = (int) ($recent->last()->estimated_queue_depth ?? 0);
        $spanMinutes = max(
            1.0,
            CarbonImmutable::parse((string) $recent->first()->window_start)
                ->diffInSeconds(CarbonImmutable::parse((string) $recent->last()->window_start), true) / 60
        );

        $trend = ($last - $first) / $spanMinutes;

        return [
            'trend_per_minute' => round($trend, 2),
            'projected_depth' => max(0, (int) round($last + ($trend * $minutesAhead))),
            'minutes_ahead' => $minutesAhead,
            'basis_windows' => $recent->count(),
        ];
    }

    /**
     * @param  Collection<int, object>  $scans
     */
    private function medianGapSeconds(Collection $scans): ?float
    {
        if ($scans->count() < self::MIN_SCANS_FOR_SERVICE_TIME) {
            return null;
        }

        $gaps = [];
        $previous = null;

        foreach ($scans as $scan) {
            $occurredAt = CarbonImmutable::parse((string) $scan->occurred_at);

            if ($previous !== null) {
                $gaps[] = (float) $previous->diffInSeconds($occurredAt, true);
            }

            $previous = $occurredAt;
        }

        if ($gaps === []) {
            return null;
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);

        $median = count($gaps) % 2 === 1
            ? $gaps[$middle]
            : ($gaps[$middle - 1] + $gaps[$middle]) / 2;

        return round($median, 2);
    }

    private function queueDepth(float $arrivalsPerMinute, float $throughputPerMinute, float $minutes): int
    {
        $backlogPerMinute = $arrivalsPerMinute - $throughputPerMinute;

        if ($backlogPerMinute <= 0) {
            return 0;
        }

        return (int) round($backlogPerMinute * $minutes);
    }

    private function waitSeconds(int $queueDepth, float $throughputPerMinute, float $factor): ?int
    {
        if ($queueDepth === 0) {
            return 0;
        }

        // Without a throughput figure the wait is unknown rather than infinite, and saying
        // so beats printing a number derived from nothing.
        if ($throughputPerMinute <= 0) {
            return null;
        }

        // Capped because the arithmetic keeps going long after the figure stops being
        // useful: a door barely moving with a large backlog computes to several hours,
        // which reads as broken rather than urgent. Over an hour, the number no longer
        // tells an operator anything the CONGESTED state has not already said.
        return min(
            self::MAX_CREDIBLE_WAIT_SECONDS,
            (int) round(($queueDepth / $throughputPerMinute) * 60 * $factor)
        );
    }

    private function state(int $queueDepth, float $arrivalsPerMinute, float $throughputPerMinute): QueueState
    {
        if ($arrivalsPerMinute === 0.0) {
            return QueueState::IDLE;
        }

        if ($throughputPerMinute <= 0) {
            return QueueState::CONGESTED;
        }

        $ratio = $arrivalsPerMinute / $throughputPerMinute;

        return match (true) {
            $queueDepth >= 40 || $ratio >= 1.5 => QueueState::CONGESTED,
            $queueDepth >= 10 || $ratio >= 1.1 => QueueState::BUSY,
            default => QueueState::FLOWING,
        };
    }
}
