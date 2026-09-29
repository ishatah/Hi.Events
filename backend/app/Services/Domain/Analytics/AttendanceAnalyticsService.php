<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Analytics;

use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Services\Domain\Analytics\DTO\DwellTimeDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * Attendance, no-show and dwell reporting, derived from the access log.
 *
 * Where a zone has no EXIT access point, dwell time and true occupancy are not computable.
 * The report says so rather than printing a number derived from entries alone — an
 * average dwell calculated without exits is not a long stay, it is a missing measurement,
 * and an operations decision made on it would be worse than no decision.
 *
 * @see docs/arzo-master-plan/54-attendance-intelligence.md
 */
class AttendanceAnalyticsService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function eventAttendance(int $eventId): array
    {
        $expected = $this->databaseManager->table('attendees')
            ->where('event_id', $eventId)
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('deleted_at')
            ->count();

        $arrived = $this->databaseManager->table('attendee_check_ins')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->distinct()
            ->count('attendee_id');

        return [
            'expected' => $expected,
            'arrived' => $arrived,
            'no_shows' => max(0, $expected - $arrived),
            // Guarded because an event with no sold tickets would otherwise divide by zero
            // and report a rate rather than an empty result.
            'attendance_rate' => $expected > 0 ? round($arrived / $expected, 4) : null,
        ];
    }

    /**
     * Arrivals bucketed by clock hour, in the event's own timezone.
     *
     * UTC buckets would put a Doha morning rush in the small hours, which is the kind of
     * chart that gets a staffing decision wrong.
     *
     * @return array<int, array{hour: string, arrivals: int}>
     */
    public function arrivalCurve(int $eventId): array
    {
        $timezone = (string) ($this->databaseManager->table('events')
            ->where('id', $eventId)
            ->value('timezone') ?: 'UTC');

        $rows = $this->databaseManager->table('attendee_check_ins')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->selectRaw(
                "to_char(date_trunc('hour', created_at AT TIME ZONE 'UTC' AT TIME ZONE ?), 'YYYY-MM-DD HH24:00') as hour,
                 count(distinct attendee_id) as arrivals",
                [$timezone]
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return $rows->map(static fn (object $row): array => [
            'hour' => (string) $row->hour,
            'arrivals' => (int) $row->arrivals,
        ])->all();
    }

    /**
     * The busiest arrival hour, which is what a staffing review compares a roster against.
     *
     * @return array{hour: string, arrivals: int}|null
     */
    public function peakArrivalHour(int $eventId): ?array
    {
        $curve = $this->arrivalCurve($eventId);

        if ($curve === []) {
            return null;
        }

        usort($curve, static fn (array $a, array $b): int => $b['arrivals'] <=> $a['arrivals']);

        return $curve[0];
    }

    /**
     * Dwell time for a zone, or an explicit statement that it cannot be measured.
     */
    public function zoneDwellTime(int $zoneId, int $eventId): DwellTimeDTO
    {
        if (! $this->zoneHasExitPoint($zoneId)) {
            return new DwellTimeDTO(
                measurable: false,
                reason: __('This zone has no exit access point, so dwell time cannot be measured.'),
            );
        }

        // Pairs each entry with that credential's next exit. A visitor who entered and never
        // left contributes nothing rather than an open-ended stay.
        $rows = $this->databaseManager->select(
            'WITH ordered AS (
                SELECT credential_id, direction, occurred_at,
                       LEAD(occurred_at) OVER (PARTITION BY credential_id ORDER BY occurred_at) AS next_at,
                       LEAD(direction) OVER (PARTITION BY credential_id ORDER BY occurred_at) AS next_direction
                FROM access_logs
                WHERE zone_id = ? AND event_id = ? AND result = ? AND credential_id IS NOT NULL
            )
            SELECT EXTRACT(EPOCH FROM (next_at - occurred_at)) AS seconds
            FROM ordered
            WHERE direction = ? AND next_direction = ?',
            [$zoneId, $eventId, AccessResult::GRANTED->value, AccessDirection::ENTRY->value, AccessDirection::EXIT->value]
        );

        if ($rows === []) {
            return new DwellTimeDTO(
                measurable: true,
                sampleSize: 0,
                reason: __('No completed visits have been recorded for this zone yet.'),
            );
        }

        $durations = array_map(static fn (object $row): float => (float) $row->seconds, $rows);
        sort($durations);

        return new DwellTimeDTO(
            measurable: true,
            sampleSize: count($durations),
            averageSeconds: (int) round(array_sum($durations) / count($durations)),
            medianSeconds: (int) round($this->median($durations)),
        );
    }

    /**
     * Attendance for one session, including who registered and did not come.
     *
     * @return array<string, mixed>
     */
    public function sessionAttendance(int $sessionId): array
    {
        $registered = $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('status', 'REGISTERED')
            ->whereNull('deleted_at')
            ->count();

        $attended = $this->databaseManager->table('session_attendance')
            ->where('session_id', $sessionId)
            ->where('direction', AccessDirection::ENTRY->value)
            ->distinct()
            ->count('attendee_id');

        $capacity = $this->databaseManager->table('sessions')
            ->where('id', $sessionId)
            ->value('capacity');

        return [
            'registered' => $registered,
            'attended' => $attended,
            'no_shows' => max(0, $registered - $attended),
            'capacity' => $capacity !== null ? (int) $capacity : null,
            'no_show_rate' => $registered > 0 ? round(max(0, $registered - $attended) / $registered, 4) : null,
            // Walk-ins are attendees who came without registering. A session showing more
            // attended than registered is not a data error; it is a popular session.
            'walk_ins' => max(0, $attended - $registered),
            'utilisation' => $capacity !== null && (int) $capacity > 0
                ? round($attended / (int) $capacity, 4)
                : null,
        ];
    }

    /**
     * Sessions ranked by how many registrations went unused.
     *
     * The list a programme review acts on: a session with 200 registrations and 40 in the
     * room was scheduled against something more popular.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionNoShowRanking(int $eventId, int $limit = 20): array
    {
        $sessions = $this->databaseManager->table('sessions')
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->select(['id', 'title'])
            ->get();

        $ranked = [];

        foreach ($sessions as $session) {
            $stats = $this->sessionAttendance((int) $session->id);

            if ($stats['registered'] === 0) {
                continue;
            }

            $ranked[] = [
                'session_id' => (int) $session->id,
                'title' => (string) $session->title,
                'registered' => $stats['registered'],
                'attended' => $stats['attended'],
                'no_shows' => $stats['no_shows'],
                'no_show_rate' => $stats['no_show_rate'],
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['no_shows'] <=> $a['no_shows']);

        return array_slice($ranked, 0, $limit);
    }

    /**
     * Demographics, from the person record rather than the order.
     *
     * Counts only; nothing here identifies an individual, and a bucket with a single person
     * in it is suppressed because "one attendee from Bahrain, aged 45" is not aggregate data.
     *
     * @return array<string, mixed>
     */
    public function demographics(int $eventId, int $suppressionThreshold = 3): array
    {
        $byCountry = $this->databaseManager->table('credentials')
            ->join('persons', 'persons.id', '=', 'credentials.person_id')
            ->where('credentials.event_id', $eventId)
            ->whereNotNull('persons.nationality')
            ->selectRaw('persons.nationality as bucket, count(distinct persons.id) as total')
            ->groupBy('persons.nationality')
            ->orderByDesc('total')
            ->get();

        $byCompany = $this->databaseManager->table('credentials')
            ->join('persons', 'persons.id', '=', 'credentials.person_id')
            ->where('credentials.event_id', $eventId)
            ->whereNotNull('persons.company')
            ->selectRaw('persons.company as bucket, count(distinct persons.id) as total')
            ->groupBy('persons.company')
            ->orderByDesc('total')
            ->get();

        return [
            'by_nationality' => $this->suppressSmallBuckets($byCountry, $suppressionThreshold),
            'by_company' => $this->suppressSmallBuckets($byCompany, $suppressionThreshold),
            'suppression_threshold' => $suppressionThreshold,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array{bucket: string, total: int}>
     */
    private function suppressSmallBuckets(Collection $rows, int $threshold): array
    {
        $kept = [];
        $suppressed = 0;

        foreach ($rows as $row) {
            if ((int) $row->total < $threshold) {
                $suppressed += (int) $row->total;

                continue;
            }

            $kept[] = ['bucket' => (string) $row->bucket, 'total' => (int) $row->total];
        }

        if ($suppressed > 0) {
            // Reported as a total rather than dropped, so the numbers still add up and
            // nobody reconciles a chart against a roster and concludes data is missing.
            $kept[] = ['bucket' => __('Other (too few to report)'), 'total' => $suppressed];
        }

        return $kept;
    }

    /**
     * A zone can only report dwell if something there records people leaving.
     *
     * access_points.direction is ENTRY, EXIT or BIDIRECTIONAL — the column's own vocabulary,
     * which is wider than the ENTRY/EXIT pair a scan resolves to.
     */
    private function zoneHasExitPoint(int $zoneId): bool
    {
        return $this->databaseManager->table('access_points')
            ->where('zone_id', $zoneId)
            ->whereIn('direction', [AccessDirection::EXIT->value, 'BIDIRECTIONAL'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @param  array<int, float>  $sorted
     */
    private function median(array $sorted): float
    {
        $count = count($sorted);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($sorted[$middle - 1] + $sorted[$middle]) / 2
            : $sorted[$middle];
    }
}
