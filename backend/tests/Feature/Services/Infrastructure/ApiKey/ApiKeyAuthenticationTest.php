<?php

namespace Tests\Feature\Services\Infrastructure\ApiKey;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\ApiKey\ApiKeyIssuanceService;
use HiEvents\Services\Domain\Permission\RoleSeedService;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyAuthenticator;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private ApiKeyIssuanceService $issuance;

    private ApiKeyAuthenticator $authenticator;

    private ApiKeyHasher $hasher;

    private int $accountId;

    private int $userId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        app(RoleSeedService::class)->seedSystemRoles();

        $this->issuance = app(ApiKeyIssuanceService::class);
        $this->authenticator = app(ApiKeyAuthenticator::class);
        $this->hasher = app(ApiKeyHasher::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = (int) $user->id;
        $this->accountId = (int) $user->accounts()->first()->id;
        $this->eventId = $this->makeEvent($this->accountId);
    }

    public function test_an_issued_key_authenticates(): void
    {
        $issued = $this->issue(['attendee.view']);

        $principal = $this->authenticator->resolve($issued['plaintext']);

        $this->assertNotNull($principal);
        $this->assertSame('API_KEY', $principal->type);
        $this->assertSame($this->accountId, $principal->accountId);
        $this->assertTrue($principal->hasScope(Permission::ATTENDEE_VIEW));
        $this->assertFalse($principal->hasScope(Permission::ORDER_REFUND));
    }

    public function test_the_plaintext_secret_is_not_stored(): void
    {
        $issued = $this->issue(['attendee.view']);

        $row = DB::table('api_keys')->where('id', $issued['id'])->first();

        $this->assertStringNotContainsString($issued['plaintext'], (string) $row->key_hash);
        $this->assertSame(
            0,
            DB::table('api_keys')->where('key_hash', $issued['plaintext'])->count(),
            'A stored key must not be recoverable from the database.'
        );
    }

    public function test_a_tampered_secret_is_rejected(): void
    {
        $issued = $this->issue(['attendee.view']);
        $parsed = $this->hasher->parse($issued['plaintext']);

        $this->assertNull(
            $this->authenticator->resolve($parsed['prefix'].'_wrongsecretwrongsecret'),
            'A valid prefix with the wrong secret must not authenticate.'
        );
    }

    public function test_a_revoked_key_stops_working(): void
    {
        $issued = $this->issue(['attendee.view']);

        $this->issuance->revoke($issued['id'], $this->accountId, $this->userId);

        $this->assertNull($this->authenticator->resolve($issued['plaintext']));
    }

    public function test_an_expired_key_stops_working(): void
    {
        $issued = $this->issue(['attendee.view']);

        DB::table('api_keys')->where('id', $issued['id'])->update(['expires_at' => now()->subMinute()]);

        $this->assertNull($this->authenticator->resolve($issued['plaintext']));
    }

    public function test_an_ip_allowlist_is_enforced(): void
    {
        $issued = $this->issue(['attendee.view'], allowedIps: ['10.0.0.1']);

        $this->assertNotNull($this->authenticator->resolve($issued['plaintext'], '10.0.0.1'));
        $this->assertNull($this->authenticator->resolve($issued['plaintext'], '10.0.0.2'));
    }

    public function test_an_allowlisted_key_is_refused_when_the_caller_ip_is_unknown(): void
    {
        $issued = $this->issue(['attendee.view'], allowedIps: ['10.0.0.1']);

        $this->assertNull(
            $this->authenticator->resolve($issued['plaintext'], null),
            'An allowlist that cannot see the caller must deny, or the restriction '
            .'disappears behind a proxy.'
        );
    }

    public function test_malformed_keys_are_rejected_without_throwing(): void
    {
        foreach (['', 'nonsense', 'arzo_', 'arzo_abc_', 'Bearer arzo_a_b', 'xyz_abc_def'] as $candidate) {
            $this->assertNull(
                $this->authenticator->resolve($candidate),
                sprintf('%s must not authenticate.', var_export($candidate, true))
            );
        }
    }

    public function test_an_unknown_scope_is_refused_at_issue_time(): void
    {
        $this->expectExceptionMessage('Unknown scope: not.a.real.scope');

        $this->issue(['not.a.real.scope']);
    }

    public function test_a_key_cannot_carry_a_scope_its_creator_lacks(): void
    {
        $operator = $this->makeAccountMember('CHECKIN_OPERATOR');

        $this->expectExceptionMessageMatches('/cannot grant a scope you do not hold/i');

        $this->issuance->issue(
            accountId: $this->accountId,
            creatorUserId: $operator,
            name: 'Escalation attempt',
            scopes: ['order.refund'],
        );
    }

    public function test_a_creator_can_grant_a_scope_they_hold(): void
    {
        $operator = $this->makeAccountMember('CHECKIN_OPERATOR');

        $issued = $this->issuance->issue(
            accountId: $this->accountId,
            creatorUserId: $operator,
            name: 'Scanner integration',
            scopes: ['attendee.checkin'],
        );

        $principal = $this->authenticator->resolve($issued['plaintext']);

        $this->assertTrue($principal->hasScope(Permission::ATTENDEE_CHECKIN));
    }

    public function test_an_event_scoped_key_does_not_cover_another_event(): void
    {
        $otherEventId = $this->makeEvent($this->accountId);

        $issued = $this->issue(['attendee.view'], eventId: $this->eventId);

        $principal = $this->authenticator->resolve($issued['plaintext']);

        $this->assertTrue($principal->coversEvent($this->eventId));
        $this->assertFalse($principal->coversEvent($otherEventId));
    }

    public function test_an_unscoped_key_covers_every_event_in_its_account(): void
    {
        $issued = $this->issue(['attendee.view']);

        $principal = $this->authenticator->resolve($issued['plaintext']);

        $this->assertTrue($principal->coversEvent($this->eventId));
        $this->assertTrue($principal->coversEvent($this->makeEvent($this->accountId)));
    }

    public function test_a_key_cannot_be_scoped_to_another_accounts_event(): void
    {
        $stranger = User::factory()->withAccount()->create();
        $foreignEventId = $this->makeEvent((int) $stranger->accounts()->first()->id);

        $this->expectExceptionMessage('That event does not belong to this account.');

        $this->issue(['attendee.view'], eventId: $foreignEventId);
    }

    public function test_a_key_with_no_scopes_is_refused(): void
    {
        $this->expectExceptionMessage('A key must carry at least one scope.');

        $this->issue([]);
    }

    public function test_revoking_twice_is_refused(): void
    {
        $issued = $this->issue(['attendee.view']);

        $this->issuance->revoke($issued['id'], $this->accountId, $this->userId);

        $this->expectException(ResourceConflictException::class);
        $this->issuance->revoke($issued['id'], $this->accountId, $this->userId);
    }

    public function test_another_account_cannot_revoke_a_key(): void
    {
        $issued = $this->issue(['attendee.view']);
        $stranger = User::factory()->withAccount()->create();

        $this->expectExceptionMessage('The API key could not be found.');

        $this->issuance->revoke(
            $issued['id'],
            (int) $stranger->accounts()->first()->id,
            (int) $stranger->id
        );
    }

    public function test_using_a_key_records_when_it_was_last_used(): void
    {
        $issued = $this->issue(['attendee.view']);

        $this->assertNull(DB::table('api_keys')->where('id', $issued['id'])->value('last_used_at'));

        $this->authenticator->resolve($issued['plaintext']);

        $this->assertNotNull(
            DB::table('api_keys')->where('id', $issued['id'])->value('last_used_at'),
            'An unused key is the one an operator wants to find and revoke.'
        );
    }

    public function test_a_device_key_authenticates_with_minimal_scopes(): void
    {
        $key = $this->hasher->generate(ApiKeyHasher::DEVICE_KEY_PREFIX);

        DB::table('devices')->insert([
            'short_id' => 'dv_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'event_id' => $this->eventId,
            'name' => 'Gate Scanner',
            'device_type' => 'SCANNER',
            'status' => 'ACTIVE',
            'api_key_prefix' => $key['prefix'],
            'api_key_hash' => $key['hash'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principal = $this->authenticator->resolve($key['plaintext']);

        $this->assertNotNull($principal);
        $this->assertTrue($principal->isDevice());
        $this->assertTrue($principal->hasScope(Permission::DEVICE_SUBMIT_SCAN));
        $this->assertFalse(
            $principal->hasScope(Permission::ORDER_REFUND),
            'A lost tablet must not be able to move money.'
        );
    }

    public function test_a_suspended_device_stops_working(): void
    {
        $key = $this->hasher->generate(ApiKeyHasher::DEVICE_KEY_PREFIX);

        DB::table('devices')->insert([
            'short_id' => 'dv_'.Str::lower(Str::random(20)),
            'account_id' => $this->accountId,
            'name' => 'Lost Tablet',
            'device_type' => 'SCANNER',
            'status' => 'SUSPENDED',
            'api_key_prefix' => $key['prefix'],
            'api_key_hash' => $key['hash'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(
            $this->authenticator->resolve($key['plaintext']),
            'Suspending a device is how a lost tablet is revoked.'
        );
    }

    public function test_a_device_key_is_not_mistaken_for_an_api_key(): void
    {
        $deviceKey = $this->hasher->generate(ApiKeyHasher::DEVICE_KEY_PREFIX);
        $parsed = $this->hasher->parse($deviceKey['plaintext']);

        $this->assertTrue(
            $this->hasher->isDeviceKey($parsed['prefix']),
            'arzod_ also starts with arzo_, so prefix order matters.'
        );

        $apiKey = $this->hasher->generate();
        $this->assertFalse($this->hasher->isDeviceKey($this->hasher->parse($apiKey['plaintext'])['prefix']));
    }

    /**
     * @param  array<int, string>  $scopes
     * @param  array<int, string>|null  $allowedIps
     * @return array{id: int, plaintext: string, prefix: string}
     */
    private function issue(array $scopes, ?int $eventId = null, ?array $allowedIps = null): array
    {
        return $this->issuance->issue(
            accountId: $this->accountId,
            creatorUserId: $this->userId,
            name: 'Test key',
            scopes: $scopes,
            eventId: $eventId,
            allowedIps: $allowedIps,
        );
    }

    private function makeAccountMember(string $role): int
    {
        $user = User::factory()->create();

        DB::table('account_users')->insert([
            'user_id' => $user->id,
            'account_id' => $this->accountId,
            'role' => $role,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $user->id;
    }

    private function makeEvent(int $accountId): int
    {
        $organizerId = (int) DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'API Key Organizer',
            'email' => 'ak-'.Str::lower(Str::random(10)).'@test.local',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('events')->insertGetId([
            'title' => 'API Key Event',
            'organizer_id' => $organizerId,
            'account_id' => $accountId,
            'user_id' => $this->userId,
            'start_date' => now()->addDays(3),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'LIVE',
            'short_id' => 'ev_'.Str::lower(Str::random(20)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
