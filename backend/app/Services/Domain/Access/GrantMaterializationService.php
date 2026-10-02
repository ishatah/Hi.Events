<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Access;

use HiEvents\DomainObjects\Generated\AccessGrantDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\AccessRuleDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\AccreditationTypeRuleDomainObjectAbstract;
use HiEvents\Repository\Interfaces\AccessGrantRepositoryInterface;
use HiEvents\Repository\Interfaces\AccessRuleRepositoryInterface;
use HiEvents\Repository\Interfaces\AccreditationTypeRuleRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Turns rules into grants at the moment a credential is issued.
 *
 * Denormalising is deliberate: a door scan must not evaluate the whole rule set, and an
 * offline device has to carry its own grants. The cost is that grants are a snapshot — a
 * rule changed after issue does not retroactively alter existing credentials until they
 * are rematerialised.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class GrantMaterializationService
{
    public function __construct(
        private readonly AccessGrantRepositoryInterface $accessGrantRepository,
        private readonly AccessRuleRepositoryInterface $accessRuleRepository,
        private readonly AccreditationTypeRuleRepositoryInterface $accreditationTypeRuleRepository,
    ) {}

    /**
     * @param  array<int, int>|null  $approvedZoneIds  Overrides the type's zones when an
     *                                                 approver granted less than was asked for.
     * @return int number of grants created
     */
    public function materialize(
        int $credentialId,
        int $eventId,
        ?int $accreditationTypeId = null,
        ?array $approvedZoneIds = null,
        array $subjects = [],
    ): int {
        $this->revokeExisting($credentialId);

        $created = 0;

        foreach ($this->typeRules($accreditationTypeId) as $rule) {
            $zoneId = $rule->getZoneId();

            // An approver may grant fewer zones than the type would imply. Press asking for
            // backstage and receiving hall-only is the normal case, not an exception.
            if ($approvedZoneIds !== null && $zoneId !== null && ! in_array($zoneId, $approvedZoneIds, true)) {
                continue;
            }

            $this->createGrant($credentialId, [
                AccessGrantDomainObjectAbstract::ZONE_ID => $zoneId,
                AccessGrantDomainObjectAbstract::ROOM_ID => $rule->getRoomId(),
                AccessGrantDomainObjectAbstract::SESSION_ID => $rule->getSessionId(),
                AccessGrantDomainObjectAbstract::STARTS_AT => $rule->getStartsAt(),
                AccessGrantDomainObjectAbstract::ENDS_AT => $rule->getEndsAt(),
                AccessGrantDomainObjectAbstract::DAYS_OF_WEEK => $rule->getDaysOfWeek(),
                AccessGrantDomainObjectAbstract::TIME_FROM => $rule->getTimeFrom(),
                AccessGrantDomainObjectAbstract::TIME_TO => $rule->getTimeTo(),
                AccessGrantDomainObjectAbstract::ALLOW_REENTRY => $rule->getAllowReentry() ?? true,
                AccessGrantDomainObjectAbstract::MAX_ENTRIES => $rule->getMaxEntries(),
            ]);

            $created++;
        }

        if ($accreditationTypeId !== null) {
            $subjects['ACCREDITATION_TYPE'] = $accreditationTypeId;
        }

        foreach ($this->eventAllowRules($eventId) as $rule) {
            if (! $this->ruleAppliesToHolder($rule, $subjects)) {
                continue;
            }

            if ($rule->getTargetType() !== 'ZONE' || $rule->getTargetId() === null) {
                continue;
            }

            $zoneId = (int) $rule->getTargetId();

            if ($approvedZoneIds !== null && ! in_array($zoneId, $approvedZoneIds, true)) {
                continue;
            }

            $this->createGrant($credentialId, [
                AccessGrantDomainObjectAbstract::ZONE_ID => $zoneId,
                AccessGrantDomainObjectAbstract::SOURCE_RULE_ID => $rule->getId(),
                AccessGrantDomainObjectAbstract::STARTS_AT => $rule->getStartsAt(),
                AccessGrantDomainObjectAbstract::ENDS_AT => $rule->getEndsAt(),
                AccessGrantDomainObjectAbstract::DAYS_OF_WEEK => $rule->getDaysOfWeek(),
                AccessGrantDomainObjectAbstract::TIME_FROM => $rule->getTimeFrom(),
                AccessGrantDomainObjectAbstract::TIME_TO => $rule->getTimeTo(),
                AccessGrantDomainObjectAbstract::ALLOW_REENTRY => $rule->getAllowReentry() ?? true,
                AccessGrantDomainObjectAbstract::MAX_ENTRIES => $rule->getMaxEntries(),
                AccessGrantDomainObjectAbstract::MIN_REENTRY_SECONDS => $rule->getMinReentrySeconds(),
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * A rule names the holders it concerns. Granting every event-wide ALLOW rule to every
     * credential would hand a press-only zone to general admission, so a rule whose
     * subject this credential does not match is not materialised for it.
     *
     * @param  array<string, int|null>  $subjects
     */
    private function ruleAppliesToHolder(object $rule, array $subjects): bool
    {
        $subjectType = (string) ($rule->getSubjectType() ?? 'ALL');

        if ($subjectType === 'ALL') {
            return true;
        }

        if (! array_key_exists($subjectType, $subjects)) {
            return false;
        }

        $subjectId = $rule->getSubjectId();

        if ($subjectId === null) {
            return $subjects[$subjectType] !== null;
        }

        return $subjects[$subjectType] === (int) $subjectId;
    }

    /**
     * @return iterable<object>
     */
    private function typeRules(?int $accreditationTypeId): iterable
    {
        if ($accreditationTypeId === null) {
            return [];
        }

        return $this->accreditationTypeRuleRepository->findWhere([
            AccreditationTypeRuleDomainObjectAbstract::ACCREDITATION_TYPE_ID => $accreditationTypeId,
        ]);
    }

    /**
     * @return iterable<object>
     */
    private function eventAllowRules(int $eventId): iterable
    {
        return $this->accessRuleRepository->findWhere([
            AccessRuleDomainObjectAbstract::EVENT_ID => $eventId,
            AccessRuleDomainObjectAbstract::EFFECT => 'ALLOW',
            AccessRuleDomainObjectAbstract::IS_ACTIVE => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createGrant(int $credentialId, array $attributes): void
    {
        // The CHECK constraint requires exactly one target, so a rule that names none is
        // skipped rather than allowed to fail the insert.
        $targets = array_filter([
            $attributes[AccessGrantDomainObjectAbstract::ZONE_ID] ?? null,
            $attributes[AccessGrantDomainObjectAbstract::ROOM_ID] ?? null,
            $attributes[AccessGrantDomainObjectAbstract::SESSION_ID] ?? null,
        ]);

        if (count($targets) !== 1) {
            return;
        }

        $this->accessGrantRepository->create(array_merge([
            AccessGrantDomainObjectAbstract::SHORT_ID => 'ag_'.Str::lower(Str::random(20)),
            AccessGrantDomainObjectAbstract::CREDENTIAL_ID => $credentialId,
            AccessGrantDomainObjectAbstract::STATUS => 'ACTIVE',
        ], array_filter($attributes, static fn ($value): bool => $value !== null)));
    }

    public function revokeExisting(int $credentialId): void
    {
        $this->accessGrantRepository->deleteWhere([
            AccessGrantDomainObjectAbstract::CREDENTIAL_ID => $credentialId,
        ]);
    }
}
