<?php

namespace Tests\Feature\Http\Actions\Admin;

use HiEvents\DomainObjects\Status\UserStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\AuthenticatesApiRequests;
use Tests\TestCase;

class DeactivatedAdminAccessTest extends TestCase
{
    use AuthenticatesApiRequests;
    use DatabaseTransactions;

    private User $user;

    private string $token;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withAccount()->create();
        $this->accountId = (int) $this->user->accounts()->first()->id;

        DB::table('account_users')
            ->where('user_id', $this->user->id)
            ->where('account_id', $this->accountId)
            ->update(['role' => 'SUPERADMIN', 'status' => UserStatus::ACTIVE->name]);

        $this->token = JWTAuth::claims(['account_id' => $this->accountId])->fromUser($this->user);
    }

    public function test_an_active_superadmin_reaches_the_admin_surface(): void
    {
        $this->getJson('/admin/messages', $this->authHeaders($this->token))->assertOk();
    }

    public function test_a_deactivated_superadmin_is_locked_out_of_admin(): void
    {
        // The role travels in a token that lives for days. Deactivating somebody used to
        // leave them with the whole admin surface until it expired, because the role gate
        // read the role and never the membership status.
        $this->deactivate();

        $response = $this->getJson('/admin/messages', $this->authHeaders($this->token));

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            sprintf(
                'PRIVILEGE ESCALATION: a deactivated SUPERADMIN reached /admin and got %d.',
                $response->getStatusCode()
            )
        );
    }

    public function test_a_deactivated_superadmin_cannot_act_on_admin_endpoints(): void
    {
        $this->deactivate();

        $response = $this->getJson('/admin/spam-events', $this->authHeaders($this->token));

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_an_invited_but_unaccepted_member_cannot_act(): void
    {
        DB::table('account_users')
            ->where('user_id', $this->user->id)
            ->where('account_id', $this->accountId)
            ->update(['status' => UserStatus::INVITED->name]);

        $response = $this->getJson('/admin/messages', $this->authHeaders($this->token));

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403],
            'An invitation that was never accepted is not a membership.'
        );
    }

    private function deactivate(): void
    {
        DB::table('account_users')
            ->where('user_id', $this->user->id)
            ->where('account_id', $this->accountId)
            ->update(['status' => UserStatus::INACTIVE->name]);
    }
}
