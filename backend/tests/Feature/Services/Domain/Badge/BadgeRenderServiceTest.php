<?php

namespace Tests\Feature\Services\Domain\Badge;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Badge\BadgeQrCodeService;
use HiEvents\Services\Domain\Badge\BadgeRenderService;
use HiEvents\Services\Domain\Badge\BadgeTemplatePresetService;
use HiEvents\Services\Domain\Credential\CredentialIdentifierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BadgeRenderServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BadgeRenderService $renderService;

    private BadgeTemplatePresetService $presetService;

    private BadgeQrCodeService $qrService;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $venueId;

    private int $credentialId;

    private string $identifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderService = app(BadgeRenderService::class);
        $this->presetService = app(BadgeTemplatePresetService::class);
        $this->qrService = app(BadgeQrCodeService::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;

        $this->eventId = $this->makeEvent();
        $this->venueId = $this->makeVenue();
        [$this->credentialId, $this->identifier] = $this->makeCredential();
    }

    public function test_every_preset_declares_usable_physical_dimensions(): void
    {
        foreach ($this->presetService->all() as $key => $preset) {
            $this->assertGreaterThan(0, $preset['width_mm'], $key);
            $this->assertGreaterThan(0, $preset['height_mm'], $key);
            $this->assertGreaterThanOrEqual(150, $preset['dpi'], $key.' must print at 150dpi or better.');
            $this->assertNotEmpty($preset['layout']['elements'], $key);
        }
    }

    public function test_a_badge_renders_to_a_pdf_at_the_requested_size(): void
    {
        $preset = $this->presetService->find('A6_PORTRAIT_STANDARD');

        $result = $this->renderService->render($this->credentialId, $preset);

        $this->assertStringStartsWith('%PDF-', $result['pdf']);

        // 105mm and 148mm in PostScript points, which is what the printer is told.
        $this->assertStringContainsString('297.638', $result['pdf']);
        $this->assertStringContainsString('419.528', $result['pdf']);
    }

    public function test_the_snapshot_records_what_was_rendered(): void
    {
        $preset = $this->presetService->find('A6_PORTRAIT_STANDARD');

        $result = $this->renderService->render($this->credentialId, $preset);

        $this->assertSame('Test Person', $result['snapshot']['fields']['person.full_name']);
        $this->assertSame($this->identifier, $result['snapshot']['fields']['credential.identifier']);
        $this->assertSame(105.0, $result['snapshot']['width_mm']);
        $this->assertNotEmpty($result['snapshot']['qr_encoding']);
    }

    public function test_an_arabic_name_survives_rendering(): void
    {
        DB::table('persons')
            ->where('id', DB::table('credentials')->where('id', $this->credentialId)->value('person_id'))
            ->update(['first_name' => 'إبراهيم', 'last_name' => 'الشطح']);

        $result = $this->renderService->render(
            $this->credentialId,
            $this->presetService->find('A6_PORTRAIT_STANDARD')
        );

        $this->assertSame('إبراهيم الشطح', $result['snapshot']['fields']['person.full_name']);

        // DejaVu Sans is the font that actually carries Arabic glyphs; without it dompdf
        // prints empty boxes. This is finding F8.
        $this->assertStringContainsString('DejaVu', $result['pdf']);
    }

    public function test_the_qr_encodes_the_credential_identifier_exactly(): void
    {
        $matrix = Encoder::encode($this->identifier, ErrorCorrectionLevel::H(), 'UTF-8')->getMatrix();

        // A version-4 symbol, not the version-5 the same 40 characters needed in lower case.
        // Upper-case lets the encoder use alphanumeric mode instead of byte mode, so the
        // printed code has fewer, larger modules and reads faster on a phone camera. This is
        // the payoff ARZ-313 predicted, measured rather than assumed.
        $this->assertSame(
            33,
            $matrix->getWidth(),
            'An upper-case 40-character identifier at ECC H should fit a version 4 symbol.'
        );

        $this->assertLessThan(
            37,
            $matrix->getWidth(),
            'A denser symbol than the lower-case format would be a regression, not a change.'
        );

        $png = $this->qrService->png($this->identifier);

        $this->assertSame("\x89PNG", substr($png, 0, 4), 'The print path needs a raster, not an SVG.');
    }

    public function test_the_zone_colour_bar_uses_the_zone_colours(): void
    {
        $this->grantZone($this->makeZone('HALL', '#1B7E99'));
        $this->grantZone($this->makeZone('BACK', '#D94F4F'));

        $result = $this->renderService->render(
            $this->credentialId,
            $this->presetService->find('A6_PORTRAIT_STANDARD')
        );

        $this->assertContains('#1B7E99', $result['snapshot']['zone_colours']);
        $this->assertContains('#D94F4F', $result['snapshot']['zone_colours']);
    }

    public function test_a_badge_with_no_zones_still_renders(): void
    {
        $result = $this->renderService->render(
            $this->credentialId,
            $this->presetService->find('A6_PORTRAIT_STANDARD')
        );

        $this->assertStringStartsWith('%PDF-', $result['pdf']);
        $this->assertSame([], $result['snapshot']['zone_colours']);
    }

    public function test_gathering_data_for_an_unknown_credential_is_refused(): void
    {
        $this->expectExceptionMessage('The credential could not be found.');

        $this->renderService->gatherData(99999999);
    }

    public function test_every_preset_renders(): void
    {
        foreach (array_keys($this->presetService->all()) as $key) {
            $result = $this->renderService->render(
                $this->credentialId,
                $this->presetService->find($key)
            );

            $this->assertStringStartsWith('%PDF-', $result['pdf'], $key.' must render.');
        }
    }

    private function grantZone(int $zoneId): void
    {
        DB::table('access_grants')->insert([
            'short_id' => 'ag_'.Str::lower(Str::random(20)),
            'credential_id' => $this->credentialId,
            'zone_id' => $zoneId,
            'status' => 'ACTIVE',
            'allow_reentry' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeZone(string $code, string $colour): int
    {
        return (int) DB::table('zones')->insertGetId([
            'short_id' => 'zn_'.Str::lower(Str::random(20)),
            'venue_id' => $this->venueId,
            'name' => $code,
            'code' => $code.Str::upper(Str::random(4)),
            'zone_type' => 'GENERAL',
            'colour' => $colour,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function makeCredential(): array
    {
        $personId = (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Test',
            'last_name' => 'Person',
            'company' => 'Test Company',
            'job_title' => 'Tester',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'MEDIA',
            'name' => 'Media',
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

        $credentialId = (int) DB::table('credentials')->insertGetId([
            'short_id' => 'cr_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'person_id' => $personId,
            'accreditation_id' => $accreditationId,
            'credential_type' => 'MEDIA',
            'status' => 'ACTIVE',
            'identifier' => $identifier,
            'identifier_hash' => app(CredentialIdentifierService::class)->hash($identifier),
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$credentialId, $identifier];
    }

    private function makeVenue(): int
    {
        return (int) DB::table('venues')->insertGetId([
            'short_id' => 'vn_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Badge Venue',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Badge Organizer',
            'email' => 'bdg-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Badge Event',
            'organizer_id' => $organizerId,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(6),
            'end_date' => now()->addDays(8),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
