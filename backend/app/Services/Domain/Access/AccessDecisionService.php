<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\DomainObjects\Status\CredentialStatus;
use HiEvents\Services\Domain\Access\DTO\AccessContextDTO;
use HiEvents\Services\Domain\Access\DTO\AccessDecisionDTO;

/**
 * Decides whether a credential may pass an access point.
 *
 * This is deliberately a pure function of its context: no database, no clock, no
 * container. Purity is what allows the identical logic to run server-side and on an
 * offline device, and it is the only realistic way to guarantee the two agree. Any change
 * here must keep that property.
 *
 * Offline devices evaluate the same steps but cannot know global zone occupancy or scans
 * at other doors, so they skip capacity and pass a null occupancy. That divergence is
 * accepted and bounded: a door that stops working when the network drops is worse than
 * one that occasionally over-admits.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class AccessDecisionService
{
    public function decide(AccessContextDTO $context): AccessDecisionDTO
    {
        // 1. The identifier resolved to nothing.
        if ($context->credential === null) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_NO_CREDENTIAL,
                reason: 'No credential matches the scanned identifier.',
            );
        }

        $credentialId = (int) $context->credential->id;

        // 2. The credential itself must be usable.
        $status = CredentialStatus::tryFrom((string) $context->credential->status);

        if ($status === null || ! $status->permitsAccess()) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_REVOKED,
                credentialId: $credentialId,
                reason: sprintf('Credential status is %s.', (string) $context->credential->status),
            );
        }

        if (! $this->withinCredentialValidity($context)) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_TIME_WINDOW,
                credentialId: $credentialId,
                reason: 'Outside the credential validity period.',
            );
        }

        // 3. A grant must exist for this target.
        $candidateGrants = $this->grantsForTarget($context);

        if ($candidateGrants === []) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_NO_GRANT,
                credentialId: $credentialId,
                reason: 'The credential has no grant for this zone or access point.',
            );
        }

        // 4. Narrow to grants whose window includes now.
        $activeGrants = array_values(array_filter(
            $candidateGrants,
            fn (object $grant): bool => $this->grantWindowIncludes($grant, $context->occurredAt, $context->venueTimezone)
        ));

        if ($activeGrants === []) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_TIME_WINDOW,
                credentialId: $credentialId,
                reason: 'A grant exists but not for this time.',
            );
        }

        $grant = $activeGrants[0];
        $grantId = (int) $grant->id;

        // 5. Explicit DENY rules win, by priority, before any allow is considered.
        $denyRule = $this->firstMatchingDenyRule($context);

        if ($denyRule !== null) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_RULE,
                credentialId: $credentialId,
                matchedGrantId: $grantId,
                matchedRuleId: (int) $denyRule->id,
                reason: sprintf('Denied by rule "%s".', (string) ($denyRule->name ?? 'unnamed')),
            );
        }

        // An exit is a record of leaving. It is not gated on entry limits, capacity or
        // anti-passback: refusing to let somebody out would be both wrong and unsafe.
        if ($context->direction === AccessDirection::EXIT) {
            return new AccessDecisionDTO(
                result: AccessResult::GRANTED,
                credentialId: $credentialId,
                matchedGrantId: $grantId,
            );
        }

        // 6. Lifetime entry cap, counted from the log rather than the advisory counter.
        $maxEntries = $grant->max_entries ?? null;

        if ($maxEntries !== null && (int) $maxEntries > 0 && $context->entryCountForZone >= (int) $maxEntries) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_MAX_ENTRIES,
                credentialId: $credentialId,
                matchedGrantId: $grantId,
                reason: sprintf('Entry limit of %d reached.', (int) $maxEntries),
            );
        }

        // 7. Anti-passback: two entries with no intervening exit means a shared badge.
        $antiPassbackDenial = $this->antiPassbackDenial($context, $grant);

        if ($antiPassbackDenial !== null) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_ANTIPASSBACK,
                credentialId: $credentialId,
                matchedGrantId: $grantId,
                reason: $antiPassbackDenial,
            );
        }

        // 8. Capacity. Skipped offline, where occupancy is unknowable.
        if ($this->exceedsCapacity($context)) {
            return new AccessDecisionDTO(
                result: AccessResult::DENIED_CAPACITY,
                credentialId: $credentialId,
                matchedGrantId: $grantId,
                reason: sprintf('Zone is at capacity (%d).', (int) $context->zoneCapacity),
            );
        }

        // 9.
        return new AccessDecisionDTO(
            result: AccessResult::GRANTED,
            credentialId: $credentialId,
            matchedGrantId: $grantId,
        );
    }

    private function withinCredentialValidity(AccessContextDTO $context): bool
    {
        $credential = $context->credential;
        $now = $context->occurredAt;

        if (! empty($credential->valid_from) && $now->lt($this->toCarbon($credential->valid_from, $now))) {
            return false;
        }

        if (! empty($credential->valid_until) && $now->gt($this->toCarbon($credential->valid_until, $now))) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, object>
     */
    private function grantsForTarget(AccessContextDTO $context): array
    {
        return array_values(array_filter($context->grants, function (object $grant) use ($context): bool {
            if ((string) ($grant->status ?? 'ACTIVE') !== 'ACTIVE') {
                return false;
            }

            if (! empty($grant->access_point_id)) {
                return (int) $grant->access_point_id === $context->accessPointId;
            }

            if (! empty($grant->zone_id) && $context->zoneId !== null) {
                return (int) $grant->zone_id === $context->zoneId;
            }

            return false;
        }));
    }

    /**
     * Absolute windows are instants and compare directly. Day-of-week and wall-clock
     * windows are what a human wrote on a schedule, so they are read in the venue's own
     * timezone: 09:00-17:00 at a Doha venue evaluated in UTC opens three hours late, and a
     * day-of-week filter evaluated in UTC changes day at the wrong moment.
     */
    private function grantWindowIncludes(object $grant, CarbonInterface $now, string $venueTimezone): bool
    {
        if (! empty($grant->starts_at) && $now->lt($this->toCarbon($grant->starts_at, $now))) {
            return false;
        }

        if (! empty($grant->ends_at) && $now->gt($this->toCarbon($grant->ends_at, $now))) {
            return false;
        }

        $local = $now->copy()->setTimezone($venueTimezone);

        $daysOfWeek = $this->decodeArray($grant->days_of_week ?? null);

        if ($daysOfWeek !== [] && ! in_array((int) $local->dayOfWeekIso, array_map('intval', $daysOfWeek), true)) {
            return false;
        }

        return $this->withinDailyWindow(
            $grant->time_from ?? null,
            $grant->time_to ?? null,
            $local
        );
    }

    /**
     * Wall-clock daily window. A window that wraps midnight (22:00 to 02:00) is treated as
     * spanning the boundary rather than as an empty set.
     */
    private function withinDailyWindow(?string $from, ?string $to, CarbonInterface $now): bool
    {
        if ($from === null && $to === null) {
            return true;
        }

        $current = $now->format('H:i:s');
        $from ??= '00:00:00';
        $to ??= '23:59:59';

        if ($from <= $to) {
            return $current >= $from && $current <= $to;
        }

        return $current >= $from || $current <= $to;
    }

    private function firstMatchingDenyRule(AccessContextDTO $context): ?object
    {
        foreach ($context->rules as $rule) {
            if ((string) ($rule->effect ?? 'ALLOW') !== 'DENY') {
                continue;
            }

            if (! (bool) ($rule->is_active ?? true)) {
                continue;
            }

            if (! $this->ruleAppliesToHolder($rule, $context)) {
                continue;
            }

            if (! $this->ruleTargetsThisPoint($rule, $context)) {
                continue;
            }

            if (! $this->grantWindowIncludes($rule, $context->occurredAt, $context->venueTimezone)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    private function ruleAppliesToHolder(object $rule, AccessContextDTO $context): bool
    {
        $subjectType = (string) ($rule->subject_type ?? 'ALL');

        if ($subjectType === 'ALL') {
            return true;
        }

        if (! array_key_exists($subjectType, $context->subjects)) {
            return false;
        }

        $subjectId = $rule->subject_id ?? null;

        if ($subjectId === null) {
            return $context->subjects[$subjectType] !== null;
        }

        return $context->subjects[$subjectType] === (int) $subjectId;
    }

    private function ruleTargetsThisPoint(object $rule, AccessContextDTO $context): bool
    {
        return match ((string) ($rule->target_type ?? '')) {
            'EVENT' => true,
            'ZONE' => $context->zoneId !== null && (int) $rule->target_id === $context->zoneId,
            'ACCESS_POINT' => (int) $rule->target_id === $context->accessPointId,
            default => false,
        };
    }

    private function antiPassbackDenial(AccessContextDTO $context, object $grant): ?string
    {
        $last = $context->lastLogForZone;

        if ($last === null) {
            return null;
        }

        $lastWasEntry = (string) ($last->direction ?? '') === AccessDirection::ENTRY->value;

        if (! $lastWasEntry) {
            return null;
        }

        if (! (bool) ($grant->allow_reentry ?? true)) {
            return 'Re-entry is not permitted and the credential is already inside.';
        }

        $cooldown = $grant->min_reentry_seconds ?? null;

        if ($cooldown !== null && (int) $cooldown > 0 && ! empty($last->occurred_at)) {
            $elapsed = $this->toCarbon($last->occurred_at, $context->occurredAt)
                ->diffInSeconds($context->occurredAt, absolute: true);

            if ($elapsed < (int) $cooldown) {
                return sprintf('Re-entry attempted %ds after the last entry; %ds required.', $elapsed, (int) $cooldown);
            }
        }

        return null;
    }

    private function exceedsCapacity(AccessContextDTO $context): bool
    {
        return $context->zoneCapacity !== null
            && $context->zoneOccupancy !== null
            && $context->zoneOccupancy >= $context->zoneCapacity;
    }

    private function toCarbon(mixed $value, CarbonInterface $reference): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return $reference->copy()->setTimestamp(strtotime((string) $value) ?: $reference->getTimestamp());
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
