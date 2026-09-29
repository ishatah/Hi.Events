<?php

namespace Tests\Feature\Http\Actions;

use HiEvents\Models\User;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class AccreditationWorkflowApiTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private string $token;

    private int $accountId;

    private int $userId;

    private int $eventId;

    private int $foreignEventId;

    private int $typeId;

    private int $personId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        [$this->token, $this->accountId, $this->userId] = $this->makeTenant();
        $this->eventId = $this->makeEvent($this->accountId, $this->userId);

        [, $foreignAccountId, $foreignUserId] = $this->makeTenant();
        $this->foreignEventId = $this->makeEvent($foreignAccountId, $foreignUserId);

        $this->typeId = $this->makeType();
        $this->personId = $this->makePerson();
    }

    public function test_the_full_workflow_over_http(): void
    {
        $submitted = $this->postJson("/events/{$this->eventId}/accreditations/applications", [
            'person_id' => $this->personId,
            'accreditation_type_id' => $this->typeId,
        ], $this->authHeaders($this->token));

        $submitted->assertOk()->assertJsonStructure(['id']);
        $accreditationId = $submitted->json('id');

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/approve",
            ['notes' => 'Checked press card'],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('status', 'APPROVED');

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/credential",
            [],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonStructure(['credential_id']);

        $trail = $this->getJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/audit-trail",
            $this->authHeaders($this->token)
        );

        $trail->assertOk();
        $this->assertCount(2, $trail->json('data'));
    }

    public function test_a_rejection_requires_a_reason(): void
    {
        $accreditationId = $this->submit();

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/reject",
            [],
            $this->authHeaders($this->token)
        )->assertStatus(422);

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/reject",
            ['reason' => 'Outlet not accredited for this event.'],
            $this->authHeaders($this->token)
        )->assertOk()->assertJsonPath('status', 'REJECTED');
    }

    public function test_approving_a_decided_application_returns_a_validation_error(): void
    {
        $accreditationId = $this->submit();

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/approve",
            [],
            $this->authHeaders($this->token)
        )->assertOk();

        $this->postJson(
            "/events/{$this->eventId}/accreditations/{$accreditationId}/approve",
            [],
            $this->authHeaders($this->token)
        )->assertStatus(422);
    }

    public function test_the_accreditation_endpoints_refuse_a_foreign_event(): void
    {
        $accreditationId = $this->submit();

        $paths = [
            ['POST', "/events/{$this->foreignEventId}/accreditations/applications"],
            ['POST', "/events/{$this->foreignEventId}/accreditations/{$accreditationId}/approve"],
            ['POST', "/events/{$this->foreignEventId}/accreditations/{$accreditationId}/reject"],
            ['POST', "/events/{$this->foreignEventId}/accreditations/{$accreditationId}/credential"],
            ['GET', "/events/{$this->foreignEventId}/accreditations/{$accreditationId}/audit-trail"],
        ];

        foreach ($paths as [$method, $path]) {
            $response = $this->json($method, $path, [
                'person_id' => $this->personId,
                'accreditation_type_id' => $this->typeId,
                'reason' => 'A reason long enough to pass validation.',
            ], $this->authHeaders($this->token));

            $this->assertContains(
                $response->getStatusCode(),
                [401, 403, 404],
                sprintf('CROSS-TENANT LEAK: %s %s returned %d.', $method, $path, $response->getStatusCode())
            );
        }
    }

    private function submit(): int
    {
        return (int) $this->postJson("/events/{$this->eventId}/accreditations/applications", [
            'person_id' => $this->personId,
            'accreditation_type_id' => $this->typeId,
        ], $this->authHeaders($this->token))->json('id');
    }

    private function makeType(): int
    {
        return (int) DB::table('accreditation_types')->insertGetId([
            'short_id' => 'at_'.Str::lower(Str::random(20)),
            'event_id' => $this->eventId,
            'code' => 'MEDIA',
            'name' => 'Media',
            'requires_approval' => true,
            'requires_photo' => false,
            'requires_id_document' => false,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePerson(): int
    {
        return (int) DB::table('persons')->insertGetId([
            'short_id' => 'pe_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'first_name' => 'Api',
            'last_name' => 'Press',
            'email' => Str::lower(Str::random(10)).'@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function makeTenant(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = (int) $user->accounts()->first()->id;
        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$token, $accountId, (int) $user->id];
    }

    private function makeEvent(int $accountId, int $userId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Accreditation API Organizer',
            'email' => 'aapi-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'Accreditation API Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'start_date' => now()->addDays(5),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
