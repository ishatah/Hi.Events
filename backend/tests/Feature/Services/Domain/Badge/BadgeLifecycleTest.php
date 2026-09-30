<?php

namespace Tests\Feature\Services\Domain\Badge;

use HiEvents\DomainObjects\Status\BadgePrintJobStatus;
use HiEvents\DomainObjects\Status\BadgeStatus;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Badge\BadgeIssuanceService;
use HiEvents\Services\Domain\Badge\BadgePrintJobService;
use HiEvents\Services\Domain\Badge\BadgeTemplatePresetService;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BadgeLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private BadgeIssuanceService $issuance;

    private BadgePrintJobService $printJobs;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $credentialId;

    private int $templateId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));

        $this->issuance = app(BadgeIssuanceService::class);
        $this->printJobs = app(BadgePrintJobService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->credentialId = $this->makeCredential();
        $this->templateId = $this->makeTemplate();
    }

    public function test_a_badge_is_issued_with_a_rendered_pdf(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);

        $badge = DB::table('badges')->where('id', $badgeId)->first();

        $this->assertSame(BadgeStatus::PENDING->value, $badge->status);
        $this->assertNotNull($badge->rendered_pdf_path);
        $this->assertNotNull($badge->snapshot);
        $this->assertSame(0, (int) $badge->print_count);
    }

    public function test_a_second_badge_for_the_same_credential_is_refused(): void
    {
        $this->issuance->issue($this->credentialId);

        $this->expectExceptionMessage('This credential already has a badge. Reprint it instead.');
        $this->issuance->issue($this->credentialId);
    }

    public function test_a_badge_cannot_be_issued_for_a_revoked_credential(): void
    {
        DB::table('credentials')->where('id', $this->credentialId)->update(['status' => 'REVOKED']);

        $this->expectExceptionMessage('A badge can only be issued for an active credential.');
        $this->issuance->issue($this->credentialId);
    }

    public function test_a_reprint_creates_a_new_badge_linked_to_the_old_one(): void
    {
        $original = $this->issuance->issue($this->credentialId);

        $reprint = $this->issuance->reprint($original, $this->userId);

        $this->assertNotSame($original, $reprint);
        $this->assertSame(
            BadgeStatus::REPLACED->value,
            DB::table('badges')->where('id', $original)->value('status')
        );
        $this->assertSame(
            $original,
            (int) DB::table('badges')->where('id', $reprint)->value('replaces_badge_id'),
            'A reprinted badge must be traceable to the one it replaces.'
        );
    }

    public function test_a_voided_badge_cannot_be_reprinted(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $this->issuance->void($badgeId, $this->userId, 'Lost');

        $this->expectExceptionMessage('A voided or replaced badge cannot be reprinted.');
        $this->issuance->reprint($badgeId, $this->userId);
    }

    public function test_voiding_requires_a_reason(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);

        $this->expectExceptionMessage('A void reason is required.');
        $this->issuance->void($badgeId, $this->userId, '  ');
    }

    public function test_voiding_cancels_queued_print_jobs(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        $this->issuance->void($badgeId, $this->userId, 'Wrong name spelling');

        $this->assertSame(
            BadgePrintJobStatus::CANCELLED->value,
            DB::table('badge_print_jobs')->where('id', $printJobId)->value('status'),
            'A void must not leave a job that still reaches paper.'
        );
    }

    public function test_badge_history_lists_every_badge_for_a_credential(): void
    {
        $first = $this->issuance->issue($this->credentialId);
        $second = $this->issuance->reprint($first, $this->userId);

        $history = $this->issuance->historyFor($this->credentialId);

        $this->assertCount(2, $history);
        $this->assertSame($first, (int) $history[0]->id);
        $this->assertSame($second, (int) $history[1]->id);
    }

    public function test_a_print_job_is_claimed_once(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $this->printJobs->queue($badgeId, 'PRINTER-1');

        $first = $this->printJobs->claimNext('PRINTER-1');
        $second = $this->printJobs->claimNext('PRINTER-1');

        $this->assertNotNull($first);
        $this->assertSame(BadgePrintJobStatus::SENT->value, $first->status);
        $this->assertSame(1, (int) $first->attempts);
        $this->assertNull($second, 'A claimed job must not be handed out twice.');
    }

    public function test_confirming_marks_the_badge_printed(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        $this->printJobs->claimNext('PRINTER-1');
        $this->printJobs->confirm($printJobId, $this->userId);

        $badge = DB::table('badges')->where('id', $badgeId)->first();

        $this->assertSame(BadgeStatus::PRINTED->value, $badge->status);
        $this->assertSame(1, (int) $badge->print_count);
        $this->assertNotNull($badge->printed_at);
        $this->assertSame(
            BadgePrintJobStatus::CONFIRMED->value,
            DB::table('badge_print_jobs')->where('id', $printJobId)->value('status')
        );
    }

    public function test_a_failure_returns_the_job_to_the_queue_for_retry(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        $this->printJobs->claimNext('PRINTER-1');
        $this->printJobs->fail($printJobId, 'Paper jam');

        $job = DB::table('badge_print_jobs')->where('id', $printJobId)->first();

        $this->assertSame(
            BadgePrintJobStatus::QUEUED->value,
            $job->status,
            'A jam must clear itself once the printer is fixed.'
        );
        $this->assertSame('Paper jam', $job->last_error);
    }

    public function test_repeated_failures_stop_at_failed_rather_than_looping(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->printJobs->claimNext('PRINTER-1');
            $this->printJobs->fail($printJobId, 'Printer offline');
        }

        $this->assertSame(
            BadgePrintJobStatus::FAILED->value,
            DB::table('badge_print_jobs')->where('id', $printJobId)->value('status'),
            'Past the attempt ceiling a job needs a human, not another loop.'
        );
    }

    public function test_a_manual_retry_resets_the_attempt_count(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->printJobs->claimNext('PRINTER-1');
            $this->printJobs->fail($printJobId, 'Printer offline');
        }

        $this->printJobs->retry($printJobId);

        $job = DB::table('badge_print_jobs')->where('id', $printJobId)->first();

        $this->assertSame(BadgePrintJobStatus::QUEUED->value, $job->status);
        $this->assertSame(0, (int) $job->attempts);
        $this->assertNull($job->last_error);
    }

    public function test_a_confirmed_job_cannot_be_retried(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        $this->printJobs->claimNext('PRINTER-1');
        $this->printJobs->confirm($printJobId, $this->userId);

        $this->expectExceptionMessage('A confirmed print job cannot be retried.');
        $this->printJobs->retry($printJobId);
    }

    public function test_a_replayed_queue_request_does_not_create_a_second_job(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $clientId = (string) Str::uuid();

        $first = $this->printJobs->queue($badgeId, 'PRINTER-1', $clientId);
        $second = $this->printJobs->queue($badgeId, 'PRINTER-1', $clientId);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('badge_print_jobs')->where('badge_id', $badgeId)->count());
    }

    public function test_a_voided_badge_cannot_be_queued(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $this->issuance->void($badgeId, $this->userId, 'Damaged');

        $this->expectExceptionMessage('A voided or replaced badge cannot be printed.');
        $this->printJobs->queue($badgeId, 'PRINTER-1');
    }

    public function test_a_stalled_job_is_reported(): void
    {
        $badgeId = $this->issuance->issue($this->credentialId);
        $printJobId = $this->printJobs->queue($badgeId, 'PRINTER-1');

        $this->printJobs->claimNext('PRINTER-1');

        DB::table('badge_print_jobs')
            ->where('id', $printJobId)
            ->update(['sent_at' => now()->subMinutes(10)]);

        $stalled = $this->printJobs->stalledJobs();

        $this->assertCount(1, $stalled);
        $this->assertSame($printJobId, (int) $stalled[0]->id);
    }

    public function test_issuing_without_a_template_is_refused(): void
    {
        DB::table('badge_templates')->where('id', $this->templateId)->update(['is_default' => false]);

        $this->expectExceptionMessage('No badge template is available for this event.');
        $this->issuance->issue($this->credentialId);
    }

    private function makeTemplate(): int
    {
        $preset = app(BadgeTemplatePresetService::class)->find('A6_PORTRAIT_STANDARD');

        return (int) DB::table('badge_templates')->insertGetId([
            'short_id' => 'bt_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'name' => $preset['name'],
            'width_mm' => $preset['width_mm'],
            'height_mm' => $preset['height_mm'],
            'orientation' => $preset['orientation'],
            'dpi' => $preset['dpi'],
            'layout' => json_encode($preset['layout']),
            'is_default' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCredential(): int
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Badge',
            'last_name' => 'Holder',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'STAFF',
            'name' => 'Staff',
            'requires_approval' => false,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accreditationId = (int) DB::table('accreditations')->insertGetId([
            'short_id' => 'ac_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_type_id' => $typeId,
            'status' => 'APPROVED',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $identifier = app(CredentialIdentifierService::class)->generate();

        return (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'STAFF',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => app(CredentialIdentifierService::class)->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Badge Lifecycle Organizer',
            'email' => 'bl-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Badge Lifecycle Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(6),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
