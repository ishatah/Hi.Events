<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Services\Domain\Access\DTO\AccessContextDTO;
use HiEvents\Services\Domain\Access\DTO\AccessDecisionDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Gathers the context for a scan, delegates the verdict to the pure decision function,
 * and records the outcome.
 *
 * All the impure work lives here so AccessDecisionService can stay a pure function and be
 * compiled for offline devices. Queries use the query builder directly rather than
 * repositories because these are aggregates and existence checks, not entity reads.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class AccessScanService
{
    public function __construct(
        private readonly AccessDecisionService $accessDecisionService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function scan(
        int $eventId,
        string $identifier,
        int $accessPointId,
        ?AccessDirection $direction = null,
        ?int $operatorUserId = null,
        ?int $deviceId = null,
        ?string $clientGeneratedId = null,
        ?Carbon $occurredAt = null,
        string $identifierType = 'QR',
        string $source = 'SCAN',
    ): AccessDecisionDTO {
        $occurredAt ??= Carbon::now();

        // Replaying a queued offline scan must be a no-op, not a second admission.
        if ($clientGeneratedId !== null) {
            $existing = $this->databaseManager->table('access_logs')
                ->where('client_generated_id', $clientGeneratedId)
                ->first();

            if ($existing !== null) {
                return new AccessDecisionDTO(
                    result: AccessResult::from((string) $existing->result),
                    credentialId: $existing->credential_id !== null ? (int) $existing->credential_id : null,
                    reason: 'Already recorded; replay ignored.',
                );
            }
        }

        $context = $this->buildContext(
            eventId: $eventId,
            identifier: $identifier,
            accessPointId: $accessPointId,
            direction: $direction,
            occurredAt: $occurredAt,
        );

        $direction = $context->direction;
        $zoneId = $context->zoneId;

        $decision = $this->accessDecisionService->decide($context);

        $this->recordLog(
            eventId: $eventId,
            decision: $decision,
            accessPointId: $accessPointId,
            zoneId: $zoneId,
            direction: $direction,
            identifier: $identifier,
            identifierType: $identifierType,
            operatorUserId: $operatorUserId,
            clientGeneratedId: $clientGeneratedId,
            occurredAt: $occurredAt,
            source: $source,
            isOfflineReplay: $clientGeneratedId !== null && $occurredAt->lt(Carbon::now()->subMinutes(2)),
        );

        return $decision;
    }

    /**
     * The same verdict a door would give, without recording it.
     *
     * An organizer building a rule set with priorities and both effects will produce
     * contradictions, and the only trustworthy answer to "would this badge get in?" is the
     * one the door itself would reach. So this shares the decision path exactly and differs
     * only in not writing a log.
     */
    public function simulate(
        int $eventId,
        string $identifier,
        int $accessPointId,
        ?AccessDirection $direction = null,
        ?Carbon $occurredAt = null,
    ): AccessDecisionDTO {
        return $this->accessDecisionService->decide($this->buildContext(
            eventId: $eventId,
            identifier: $identifier,
            accessPointId: $accessPointId,
            direction: $direction,
            occurredAt: $occurredAt ?? Carbon::now(),
        ));
    }

    private function buildContext(
        int $eventId,
        string $identifier,
        int $accessPointId,
        ?AccessDirection $direction,
        Carbon $occurredAt,
    ): AccessContextDTO {
        $accessPoint = $this->databaseManager->table('access_points')
            ->where('id', $accessPointId)
            ->whereNull('deleted_at')
            ->first();

        $zoneId = $accessPoint?->zone_id !== null ? (int) $accessPoint->zone_id : null;

        $credential = $this->databaseManager->table('credentials')
            ->where('event_id', $eventId)
            ->where('identifier_hash', hash('sha256', $identifier))
            ->first();

        return new AccessContextDTO(
            credential: $credential,
            grants: $credential !== null ? $this->grantsFor((int) $credential->id) : [],
            rules: $this->activeRulesFor($eventId),
            accessPointId: $accessPointId,
            zoneId: $zoneId,
            direction: $direction ?? $this->defaultDirectionFor($accessPoint),
            occurredAt: $occurredAt,
            subjects: $credential !== null ? $this->subjectsFor($credential) : [],
            venueTimezone: $this->timezoneFor($zoneId, $eventId),
            lastLogForZone: $credential !== null && $zoneId !== null
                ? $this->lastLogFor((int) $credential->id, $zoneId)
                : null,
            entryCountForZone: $credential !== null && $zoneId !== null
                ? $this->entryCountFor((int) $credential->id, $zoneId)
                : 0,
            zoneOccupancy: $zoneId !== null ? $this->occupancyFor($zoneId, $eventId) : null,
            zoneCapacity: $zoneId !== null ? $this->capacityFor($zoneId) : null,
        );
    }

    /**
     * The identities a rule can name this credential by. A rule whose subject is not one
     * of these does not concern the holder, so the key being absent is meaningful and not
     * the same as a null value.
     *
     * @return array<string, int|null>
     */
    private function subjectsFor(object $credential): array
    {
        $subjects = ['CREDENTIAL' => (int) $credential->id];

        if ($credential->accreditation_id !== null) {
            $accreditation = $this->databaseManager->table('accreditations')
                ->where('id', $credential->accreditation_id)
                ->whereNull('deleted_at')
                ->first();

            if ($accreditation !== null) {
                $subjects['ACCREDITATION_TYPE'] = (int) $accreditation->accreditation_type_id;
            }
        }

        if ($credential->attendee_id !== null) {
            $attendee = $this->databaseManager->table('attendees')
                ->where('id', $credential->attendee_id)
                ->whereNull('deleted_at')
                ->first();

            if ($attendee !== null) {
                $subjects['PRODUCT'] = (int) $attendee->product_id;
            }
        }

        return $subjects;
    }

    /**
     * Daily wall-clock windows are meaningless without the venue's own timezone: a
     * 09:00-17:00 rule in Doha evaluated in UTC opens three hours late.
     */
    private function timezoneFor(?int $zoneId, int $eventId): string
    {
        if ($zoneId !== null) {
            $timezone = $this->databaseManager->table('zones')
                ->join('venues', 'venues.id', '=', 'zones.venue_id')
                ->where('zones.id', $zoneId)
                ->value('venues.timezone');

            if ($timezone !== null) {
                return (string) $timezone;
            }
        }

        $timezone = $this->databaseManager->table('events')
            ->where('id', $eventId)
            ->value('timezone');

        return $timezone !== null ? (string) $timezone : 'UTC';
    }

    private function defaultDirectionFor(?object $accessPoint): AccessDirection
    {
        return match ((string) ($accessPoint?->direction ?? 'BIDIRECTIONAL')) {
            'EXIT' => AccessDirection::EXIT,
            default => AccessDirection::ENTRY,
        };
    }

    /**
     * @return array<int, object>
     */
    private function grantsFor(int $credentialId): array
    {
        return $this->databaseManager->table('access_grants')
            ->where('credential_id', $credentialId)
            ->where('status', 'ACTIVE')
            ->get()
            ->all();
    }

    /**
     * @return array<int, object>
     */
    private function activeRulesFor(int $eventId): array
    {
        return $this->databaseManager->table('access_rules')
            ->where('event_id', $eventId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('priority')
            ->get()
            ->all();
    }

    private function lastLogFor(int $credentialId, int $zoneId): ?object
    {
        return $this->databaseManager->table('access_logs')
            ->where('credential_id', $credentialId)
            ->where('zone_id', $zoneId)
            ->where('result', AccessResult::GRANTED->value)
            ->orderByDesc('occurred_at')
            ->first();
    }

    private function entryCountFor(int $credentialId, int $zoneId): int
    {
        return (int) $this->databaseManager->table('access_logs')
            ->where('credential_id', $credentialId)
            ->where('zone_id', $zoneId)
            ->where('direction', AccessDirection::ENTRY->value)
            ->where('result', AccessResult::GRANTED->value)
            ->count();
    }

    /**
     * Entries minus exits per credential, counting only those currently inside.
     *
     * Derived rather than stored: a counter drifts under offline replay, because queued
     * scans arrive out of order.
     */
    private function occupancyFor(int $zoneId, int $eventId): int
    {
        $rows = $this->databaseManager->table('access_logs')
            ->selectRaw('credential_id, SUM(CASE WHEN direction = ? THEN -1 ELSE 1 END) AS net_inside', [AccessDirection::EXIT->value])
            ->where('zone_id', $zoneId)
            ->where('event_id', $eventId)
            ->where('result', AccessResult::GRANTED->value)
            ->whereNotNull('credential_id')
            ->groupBy('credential_id')
            ->get();

        return $rows->filter(static fn (object $row): bool => (int) $row->net_inside > 0)->count();
    }

    private function capacityFor(int $zoneId): ?int
    {
        $capacity = $this->databaseManager->table('zones')
            ->where('id', $zoneId)
            ->value('capacity');

        return $capacity !== null ? (int) $capacity : null;
    }

    private function recordLog(
        int $eventId,
        AccessDecisionDTO $decision,
        int $accessPointId,
        ?int $zoneId,
        AccessDirection $direction,
        string $identifier,
        string $identifierType,
        ?int $operatorUserId,
        ?string $clientGeneratedId,
        Carbon $occurredAt,
        string $source,
        bool $isOfflineReplay,
    ): void {
        $this->databaseManager->table('access_logs')->insert([
            'short_id' => 'al_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'credential_id' => $decision->credentialId,
            'access_point_id' => $accessPointId,
            'zone_id' => $zoneId,
            'occurred_at' => $occurredAt,
            'recorded_at' => Carbon::now(),
            'direction' => $direction->value,
            'result' => $decision->result->value,
            'raw_identifier' => $identifier,
            'identifier_type' => $identifierType,
            'operator_user_id' => $operatorUserId,
            'client_generated_id' => $clientGeneratedId,
            'is_offline_replay' => $isOfflineReplay,
            'source' => $source,
            'created_at' => Carbon::now(),
        ]);
    }
}
