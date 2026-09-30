<?php

namespace Tests\Feature\Services\Application\Handlers\Auth;

use HiEvents\DomainObjects\Enums\InvitedUserPassword;
use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\Auth\AcceptInvitationHandler;
use HiEvents\Services\Application\Handlers\Auth\DTO\AcceptInvitationDTO;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcceptInvitationHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private AcceptInvitationHandler $handler;

    private EncryptedPayloadService $payloadService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = app(AcceptInvitationHandler::class);
        $this->payloadService = app(EncryptedPayloadService::class);
    }

    public function test_a_brand_new_invitee_sets_their_password_and_profile(): void
    {
        [$userId, $accountId] = $this->inviteNewUser();

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor($userId, $accountId),
            first_name: 'Layla',
            last_name: 'Rahman',
            password: 'ChosenPassword123!',
            timezone: 'Asia/Qatar',
        ));

        $user = DB::table('users')->where('id', $userId)->first();

        $this->assertSame('Layla', $user->first_name);
        $this->assertSame('Asia/Qatar', $user->timezone);
        $this->assertTrue(
            Hash::check('ChosenPassword123!', $user->password),
            'Somebody with no password yet must be able to set one.'
        );
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_the_membership_becomes_active(): void
    {
        [$userId, $accountId] = $this->inviteNewUser();

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor($userId, $accountId),
            first_name: 'Layla',
            password: 'ChosenPassword123!',
            timezone: 'UTC',
        ));

        $this->assertSame(
            UserStatus::ACTIVE->name,
            DB::table('account_users')
                ->where('user_id', $userId)
                ->where('account_id', $accountId)
                ->value('status')
        );
    }

    public function test_an_invitation_to_a_second_account_cannot_reset_an_existing_password(): void
    {
        $existing = User::factory()->withAccount()->create([
            'first_name' => 'Omar',
            'last_name' => 'Khalid',
            'timezone' => 'Europe/London',
            'password' => Hash::make('TheirRealPassword123!'),
        ]);

        // Users are global, so being invited into a second account is an ordinary path — and
        // it used to overwrite the password on the account they already had. Anyone who could
        // invite an email address could force a reset on that person.
        $secondAccountId = $this->makeAccount();
        $this->addInvitedMembership((int) $existing->id, $secondAccountId);

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor((int) $existing->id, $secondAccountId),
            first_name: 'Attacker',
            last_name: 'Chosen',
            password: 'AttackerPassword123!',
            timezone: 'Pacific/Auckland',
        ));

        $user = DB::table('users')->where('id', $existing->id)->first();

        $this->assertTrue(
            Hash::check('TheirRealPassword123!', $user->password),
            'ACCOUNT TAKEOVER: the invitation overwrote the password on an existing account.'
        );
        $this->assertFalse(Hash::check('AttackerPassword123!', $user->password));
    }

    public function test_an_invitation_to_a_second_account_cannot_rename_the_user(): void
    {
        $existing = User::factory()->withAccount()->create([
            'first_name' => 'Omar',
            'last_name' => 'Khalid',
            'timezone' => 'Europe/London',
            'password' => Hash::make('TheirRealPassword123!'),
        ]);

        $secondAccountId = $this->makeAccount();
        $this->addInvitedMembership((int) $existing->id, $secondAccountId);

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor((int) $existing->id, $secondAccountId),
            first_name: 'Attacker',
            last_name: 'Chosen',
            password: 'AttackerPassword123!',
            timezone: 'Pacific/Auckland',
        ));

        $user = DB::table('users')->where('id', $existing->id)->first();

        $this->assertSame('Omar', $user->first_name);
        $this->assertSame('Khalid', $user->last_name);
        $this->assertSame(
            'Europe/London',
            $user->timezone,
            'Their clock belongs to them, not to whoever invited them somewhere.'
        );
    }

    public function test_an_existing_user_still_joins_the_new_account(): void
    {
        $existing = User::factory()->withAccount()->create([
            'password' => Hash::make('TheirRealPassword123!'),
        ]);

        $secondAccountId = $this->makeAccount();
        $this->addInvitedMembership((int) $existing->id, $secondAccountId);

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor((int) $existing->id, $secondAccountId),
            first_name: 'Omar',
            password: 'Ignored123!',
            timezone: 'UTC',
        ));

        $this->assertSame(
            UserStatus::ACTIVE->name,
            DB::table('account_users')
                ->where('user_id', $existing->id)
                ->where('account_id', $secondAccountId)
                ->value('status'),
            'Refusing to touch their credentials must not stop them joining.'
        );
    }

    public function test_accepting_twice_is_refused(): void
    {
        [$userId, $accountId] = $this->inviteNewUser();
        $token = $this->tokenFor($userId, $accountId);

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $token,
            first_name: 'Layla',
            password: 'ChosenPassword123!',
            timezone: 'UTC',
        ));

        $this->expectExceptionMessageMatches('/already been accepted/');

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $token,
            first_name: 'Layla',
            password: 'Different123!',
            timezone: 'UTC',
        ));
    }

    public function test_an_already_verified_email_keeps_its_original_timestamp(): void
    {
        $existing = User::factory()->withAccount()->create([
            'password' => Hash::make('TheirRealPassword123!'),
            'email_verified_at' => now()->subYear(),
        ]);

        $originalVerifiedAt = DB::table('users')->where('id', $existing->id)->value('email_verified_at');

        $secondAccountId = $this->makeAccount();
        $this->addInvitedMembership((int) $existing->id, $secondAccountId);

        $this->handler->handle(new AcceptInvitationDTO(
            invitation_token: $this->tokenFor((int) $existing->id, $secondAccountId),
            first_name: 'Omar',
            password: 'Ignored123!',
            timezone: 'UTC',
        ));

        $this->assertSame(
            $originalVerifiedAt,
            DB::table('users')->where('id', $existing->id)->value('email_verified_at'),
            'When they verified is a fact about them, not about this invitation.'
        );
    }

    // ---------------------------------------------------------------- fixtures

    private function tokenFor(int $userId, int $accountId): string
    {
        return $this->payloadService->encryptPayload([
            'user_id' => $userId,
            'account_id' => $accountId,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function inviteNewUser(): array
    {
        $accountId = $this->makeAccount();

        $userId = (int) DB::table('users')->insertGetId([
            'first_name' => 'Pending',
            'last_name' => 'Invitee',
            'email' => Str::lower(Str::random(12)).'@test.local',
            'password' => InvitedUserPassword::SENTINEL,
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->addInvitedMembership($userId, $accountId);

        return [$userId, $accountId];
    }

    private function addInvitedMembership(int $userId, int $accountId): void
    {
        DB::table('account_users')->insert([
            'user_id' => $userId,
            'account_id' => $accountId,
            'role' => 'ADMIN',
            'status' => UserStatus::INVITED->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccount(): int
    {
        return (int) DB::table('accounts')->insertGetId([
            'name' => 'Invite Account '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@test.local',
            'currency_code' => 'QAR',
            'timezone' => 'UTC',
            'short_id' => 'ac_'.Str::lower(Str::random(16)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
