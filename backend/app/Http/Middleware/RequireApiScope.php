<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the authenticated key to carry a named scope.
 *
 * A key that reaches an endpoint it has no scope for gets 403 rather than 401: the key is
 * valid, it simply may not do this, and conflating the two sends integrators hunting for a
 * credential problem that does not exist.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class RequireApiScope
{
    public function __construct(
        private readonly ApiPrincipalContext $principalContext,
    ) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $principal = $this->principalContext->get();

        if ($principal === null) {
            return new JsonResponse(
                ['message' => __('Invalid or expired API key.')],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $permission = Permission::tryFrom($scope);

        // An unrecognised scope name in a route definition is a programming error, and
        // denying is the only safe reading of it: the alternative is an endpoint that
        // silently requires nothing.
        if ($permission === null || ! $principal->hasScope($permission)) {
            return new JsonResponse(
                ['message' => __('This API key does not carry the required scope.')],
                Response::HTTP_FORBIDDEN
            );
        }

        $eventId = $request->route('event_id');

        if ($eventId !== null && ! $principal->coversEvent((int) $eventId)) {
            return new JsonResponse(
                ['message' => __('This API key is not scoped to that event.')],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
