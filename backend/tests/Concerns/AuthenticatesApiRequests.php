<?php

namespace Tests\Concerns;

use HiEvents\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait AuthenticatesApiRequests
{
    protected function tokenFor(User $user, int $accountId): string
    {
        return JWTAuth::claims(['account_id' => $accountId])->fromUser($user);
    }

    /**
     * The JWT guard resolves its token once and the `tymon.jwt` singleton holds it for the
     * lifetime of the container. Within a single test method the container is not rebuilt,
     * so a second request made with a different token is still authenticated as the first
     * user unless both the guard and that singleton are discarded. Forgetting only the
     * guard is not enough and fails open: a cross-tenant assertion then passes against the
     * wrong tenant.
     */
    protected function authHeaders(string $token): array
    {
        $this->app['auth']->forgetGuards();

        foreach (['tymon.jwt', 'tymon.jwt.auth'] as $binding) {
            $this->app->forgetInstance($binding);
        }

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
