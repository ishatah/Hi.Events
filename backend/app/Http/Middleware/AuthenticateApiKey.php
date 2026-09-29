<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Services\Infrastructure\ApiKey\ApiKeyAuthenticator;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use HiEvents\Services\Infrastructure\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a machine principal from its bearer key.
 *
 * The resolved principal goes into a request-scoped context rather than static state, so a
 * queue worker or a second request cannot inherit it — the mistake that finding F11
 * recorded for the tenant id.
 *
 * Setting the tenant here matters as much as the principal: it is what makes the global
 * scope apply to key-authenticated traffic, so a key cannot read another account's rows
 * even if an endpoint forgets to check.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly ApiKeyAuthenticator $authenticator,
        private readonly ApiPrincipalContext $principalContext,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $this->authenticator->resolve($request->bearerToken(), $request->ip());

        if ($principal === null) {
            return new JsonResponse(
                ['message' => __('Invalid or expired API key.')],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $this->principalContext->set($principal);
        $this->tenantContext->setAccountId($principal->accountId);

        return $next($request);
    }
}
