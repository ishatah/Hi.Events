<?php

namespace Tests\Unit\Services\Domain\Access;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\AccessDirection;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Services\Domain\Access\AccessDecisionService;
use HiEvents\Services\Domain\Access\DTO\AccessContextDTO;
use Tests\TestCase;

/**
 * The access decision is a pure function, so it is exhaustively table-testable and must
 * be. The same logic will be compiled for offline devices, and two implementations that
 * disagree at a door is the worst possible outcome.
 *
 * @see docs/arzo-master-plan/24-access-control.md
 */
class AccessDecisionServiceTest extends TestCase
{
    private AccessDecisionService $service;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AccessDecisionService;
        $this->now = Carbon::parse('2030-06-15 10:00:00', 'UTC');
    }

    public function test_an_unknown_identifier_is_denied(): void
    {
        $decision = $this->service->decide($this->context(credential: null, grants: []));

        $this->assertSame(AccessResult::DENIED_NO_CREDENTIAL, $decision->result);
        $this->assertFalse($decision->isGranted());
    }

    public function test_a_revoked_credential_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(['status' => 'REVOKED']),
            grants: [$this->grant()],
        ));

        $this->assertSame(AccessResult::DENIED_REVOKED, $decision->result);
    }

    public function test_a_suspended_credential_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(['status' => 'SUSPENDED']),
            grants: [$this->grant()],
        ));

        $this->assertSame(AccessResult::DENIED_REVOKED, $decision->result);
    }

    public function test_a_credential_before_its_validity_period_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(['valid_from' => '2030-06-16 00:00:00']),
            grants: [$this->grant()],
        ));

        $this->assertSame(AccessResult::DENIED_TIME_WINDOW, $decision->result);
    }

    public function test_a_credential_after_its_validity_period_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(['valid_until' => '2030-06-14 00:00:00']),
            grants: [$this->grant()],
        ));

        $this->assertSame(AccessResult::DENIED_TIME_WINDOW, $decision->result);
    }

    public function test_no_grant_for_the_zone_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['zone_id' => 999])],
        ));

        $this->assertSame(AccessResult::DENIED_NO_GRANT, $decision->result);
    }

    public function test_an_inactive_grant_is_ignored(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['status' => 'REVOKED'])],
        ));

        $this->assertSame(AccessResult::DENIED_NO_GRANT, $decision->result);
    }

    public function test_a_valid_grant_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
        $this->assertTrue($decision->isGranted());
        $this->assertSame(1, $decision->matchedGrantId);
    }

    public function test_a_grant_matched_by_access_point_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['zone_id' => null, 'access_point_id' => 7])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_grant_outside_its_date_window_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['ends_at' => '2030-06-14 23:59:59'])],
        ));

        $this->assertSame(AccessResult::DENIED_TIME_WINDOW, $decision->result);
    }

    public function test_a_grant_outside_its_daily_window_is_denied(): void
    {
        // Scan is at 10:00; the grant only permits 18:00-22:00.
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['time_from' => '18:00:00', 'time_to' => '22:00:00'])],
        ));

        $this->assertSame(AccessResult::DENIED_TIME_WINDOW, $decision->result);
    }

    public function test_a_grant_inside_its_daily_window_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['time_from' => '08:00:00', 'time_to' => '22:00:00'])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_daily_window_wrapping_midnight_is_honoured(): void
    {
        // 22:00-02:00 must span the boundary rather than evaluate as an empty set.
        $lateNight = Carbon::parse('2030-06-15 23:30:00', 'UTC');

        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['time_from' => '22:00:00', 'time_to' => '02:00:00'])],
            occurredAt: $lateNight,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_grant_for_another_day_of_week_is_denied(): void
    {
        // 2030-06-15 is a Saturday (ISO 6); the grant permits Monday only.
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['days_of_week' => [1]])],
        ));

        $this->assertSame(AccessResult::DENIED_TIME_WINDOW, $decision->result);
    }

    public function test_days_of_week_accepts_a_json_string(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['days_of_week' => '[6]'])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_deny_rule_overrides_a_valid_grant(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'DENY', 'target_type' => 'ZONE', 'target_id' => 42])],
        ));

        $this->assertSame(AccessResult::DENIED_RULE, $decision->result);
        $this->assertSame(1, $decision->matchedRuleId);
    }

    public function test_an_event_wide_deny_rule_applies(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'DENY', 'target_type' => 'EVENT', 'target_id' => null])],
        ));

        $this->assertSame(AccessResult::DENIED_RULE, $decision->result);
    }

    public function test_a_deny_rule_for_another_zone_does_not_apply(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'DENY', 'target_type' => 'ZONE', 'target_id' => 999])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_an_inactive_deny_rule_does_not_apply(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'DENY', 'is_active' => false])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_an_allow_rule_is_not_treated_as_a_denial(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'ALLOW'])],
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_the_entry_limit_is_enforced_from_the_log_count(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['max_entries' => 2])],
            entryCountForZone: 2,
        ));

        $this->assertSame(AccessResult::DENIED_MAX_ENTRIES, $decision->result);
    }

    public function test_below_the_entry_limit_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['max_entries' => 3])],
            entryCountForZone: 2,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_zero_entry_limit_means_unlimited(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['max_entries' => 0])],
            entryCountForZone: 500,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_re_entry_is_denied_when_the_grant_forbids_it(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['allow_reentry' => false])],
            lastLogForZone: $this->log(['direction' => 'ENTRY']),
        ));

        $this->assertSame(AccessResult::DENIED_ANTIPASSBACK, $decision->result);
    }

    public function test_re_entry_after_an_exit_is_granted_even_when_forbidden(): void
    {
        // The credential is outside, so this is a first entry rather than a passback.
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['allow_reentry' => false])],
            lastLogForZone: $this->log(['direction' => 'EXIT']),
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_re_entry_inside_the_cooldown_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['min_reentry_seconds' => 300])],
            lastLogForZone: $this->log([
                'direction' => 'ENTRY',
                'occurred_at' => $this->now->copy()->subSeconds(60)->toDateTimeString(),
            ]),
        ));

        $this->assertSame(AccessResult::DENIED_ANTIPASSBACK, $decision->result);
    }

    public function test_re_entry_after_the_cooldown_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['min_reentry_seconds' => 300])],
            lastLogForZone: $this->log([
                'direction' => 'ENTRY',
                'occurred_at' => $this->now->copy()->subSeconds(600)->toDateTimeString(),
            ]),
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_a_full_zone_is_denied(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            zoneOccupancy: 100,
            zoneCapacity: 100,
        ));

        $this->assertSame(AccessResult::DENIED_CAPACITY, $decision->result);
    }

    public function test_a_zone_below_capacity_is_granted(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            zoneOccupancy: 99,
            zoneCapacity: 100,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_capacity_is_skipped_when_occupancy_is_unknown_offline(): void
    {
        // An offline device cannot know global occupancy. It must still admit rather than
        // fail closed, and the divergence is reconciled later.
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            zoneOccupancy: null,
            zoneCapacity: 100,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_an_exit_is_granted_despite_the_entry_limit(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['max_entries' => 1])],
            direction: AccessDirection::EXIT,
            entryCountForZone: 5,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_an_exit_is_granted_despite_capacity_and_anti_passback(): void
    {
        // Refusing to let somebody out would be both wrong and unsafe.
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant(['allow_reentry' => false])],
            direction: AccessDirection::EXIT,
            lastLogForZone: $this->log(['direction' => 'ENTRY']),
            zoneOccupancy: 500,
            zoneCapacity: 100,
        ));

        $this->assertSame(AccessResult::GRANTED, $decision->result);
    }

    public function test_an_exit_still_requires_a_grant(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [],
            direction: AccessDirection::EXIT,
        ));

        $this->assertSame(AccessResult::DENIED_NO_GRANT, $decision->result);
    }

    public function test_a_deny_rule_still_applies_to_an_exit(): void
    {
        $decision = $this->service->decide($this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [$this->rule(['effect' => 'DENY', 'target_type' => 'EVENT'])],
            direction: AccessDirection::EXIT,
        ));

        $this->assertSame(AccessResult::DENIED_RULE, $decision->result);
    }

    public function test_the_decision_is_deterministic(): void
    {
        $context = $this->context(credential: $this->credential(), grants: [$this->grant()]);

        $first = $this->service->decide($context);
        $second = $this->service->decide($context);

        $this->assertSame($first->result, $second->result);
        $this->assertSame($first->matchedGrantId, $second->matchedGrantId);
    }

    /**
     * @param  array<int, object>  $grants
     * @param  array<int, object>  $rules
     */
    private function context(
        ?object $credential,
        array $grants,
        array $rules = [],
        AccessDirection $direction = AccessDirection::ENTRY,
        ?Carbon $occurredAt = null,
        ?object $lastLogForZone = null,
        int $entryCountForZone = 0,
        ?int $zoneOccupancy = null,
        ?int $zoneCapacity = null,
        array $subjects = [],
        string $venueTimezone = 'UTC',
    ): AccessContextDTO {
        return new AccessContextDTO(
            credential: $credential,
            grants: $grants,
            rules: $rules,
            accessPointId: 7,
            zoneId: 42,
            direction: $direction,
            occurredAt: $occurredAt ?? $this->now,
            subjects: $subjects,
            venueTimezone: $venueTimezone,
            lastLogForZone: $lastLogForZone,
            entryCountForZone: $entryCountForZone,
            zoneOccupancy: $zoneOccupancy,
            zoneCapacity: $zoneCapacity,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function credential(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 11,
            'status' => 'ACTIVE',
            'valid_from' => null,
            'valid_until' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function grant(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'status' => 'ACTIVE',
            'zone_id' => 42,
            'room_id' => null,
            'session_id' => null,
            'access_point_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'days_of_week' => null,
            'time_from' => null,
            'time_to' => null,
            'max_entries' => null,
            'allow_reentry' => true,
            'min_reentry_seconds' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rule(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'name' => 'Test rule',
            'effect' => 'DENY',
            'is_active' => true,
            'target_type' => 'ZONE',
            'target_id' => 42,
            'starts_at' => null,
            'ends_at' => null,
            'days_of_week' => null,
            'time_from' => null,
            'time_to' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function log(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'direction' => 'ENTRY',
            'occurred_at' => $this->now->copy()->subHour()->toDateTimeString(),
        ], $overrides);
    }

    public function test_a_deny_rule_for_another_accreditation_type_does_not_deny_this_holder(): void
    {
        $context = $this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [(object) [
                'id' => 900,
                'name' => 'Subject rule 900',
                'effect' => 'DENY',
                'is_active' => true,
                'subject_type' => 'ACCREDITATION_TYPE',
                'subject_id' => 5,
                'target_type' => 'ZONE',
                'target_id' => 42,
            ]],
            subjects: ['ACCREDITATION_TYPE' => 6],
        );

        $decision = $this->service->decide($context);

        $this->assertTrue($decision->isGranted());
        $this->assertNull($decision->matchedRuleId);
    }

    public function test_a_deny_rule_for_this_accreditation_type_denies(): void
    {
        $context = $this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [(object) [
                'id' => 901,
                'name' => 'Subject rule 901',
                'effect' => 'DENY',
                'is_active' => true,
                'subject_type' => 'ACCREDITATION_TYPE',
                'subject_id' => 6,
                'target_type' => 'ZONE',
                'target_id' => 42,
            ]],
            subjects: ['ACCREDITATION_TYPE' => 6],
        );

        $decision = $this->service->decide($context);

        $this->assertFalse($decision->isGranted());
        $this->assertSame(901, $decision->matchedRuleId);
    }

    public function test_a_deny_rule_subject_all_denies_every_holder(): void
    {
        $context = $this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [(object) [
                'id' => 902,
                'name' => 'Subject rule 902',
                'effect' => 'DENY',
                'is_active' => true,
                'subject_type' => 'ALL',
                'subject_id' => null,
                'target_type' => 'ZONE',
                'target_id' => 42,
            ]],
            subjects: ['PRODUCT' => 3],
        );

        $this->assertFalse($this->service->decide($context)->isGranted());
    }

    public function test_a_deny_rule_naming_a_subject_type_the_holder_lacks_does_not_apply(): void
    {
        $context = $this->context(
            credential: $this->credential(),
            grants: [$this->grant()],
            rules: [(object) [
                'id' => 903,
                'name' => 'Subject rule 903',
                'effect' => 'DENY',
                'is_active' => true,
                'subject_type' => 'ACCREDITATION_TYPE',
                'subject_id' => null,
                'target_type' => 'ZONE',
                'target_id' => 42,
            ]],
            subjects: ['PRODUCT' => 3],
        );

        $this->assertTrue($this->service->decide($context)->isGranted());
    }

    public function test_a_daily_window_uses_the_venue_timezone_not_utc(): void
    {
        $grant = $this->grant(['time_from' => '09:00:00', 'time_to' => '17:00:00']);

        $inDoha = $this->context(
            credential: $this->credential(),
            grants: [$grant],
            occurredAt: Carbon::parse('2030-06-01T07:30:00Z'),
            venueTimezone: 'Asia/Qatar',
        );

        $this->assertTrue($this->service->decide($inDoha)->isGranted());

        $sameInstantInUtc = $this->context(
            credential: $this->credential(),
            grants: [$grant],
            occurredAt: Carbon::parse('2030-06-01T07:30:00Z'),
            venueTimezone: 'UTC',
        );

        $this->assertFalse($this->service->decide($sameInstantInUtc)->isGranted());
    }

    public function test_day_of_week_is_evaluated_in_the_venue_timezone(): void
    {
        // 2030-06-01T22:30Z is a Saturday in UTC but already Sunday in Asia/Qatar.
        $sundayOnly = $this->grant(['days_of_week' => [7]]);

        $context = $this->context(
            credential: $this->credential(),
            grants: [$sundayOnly],
            occurredAt: Carbon::parse('2030-06-01T22:30:00Z'),
            venueTimezone: 'Asia/Qatar',
        );

        $this->assertTrue($this->service->decide($context)->isGranted());

        $utcContext = $this->context(
            credential: $this->credential(),
            grants: [$sundayOnly],
            occurredAt: Carbon::parse('2030-06-01T22:30:00Z'),
            venueTimezone: 'UTC',
        );

        $this->assertFalse($this->service->decide($utcContext)->isGranted());
    }
}
