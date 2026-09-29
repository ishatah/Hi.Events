<?php

declare(strict_types=1);

namespace HiEvents\Http\Middleware;

use Closure;
use HiEvents\Services\Infrastructure\ApiKey\ApiPrincipalContext;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-key rate limiting.
 *
 * Keyed on the key id rather than the IP, because several integrations behind one NAT
 * would otherwise share a budget and one busy partner would throttle the others.
 *
 * @see docs/arzo-master-plan/48-api-platform.md
 */
class ThrottleApiKey
{
    private const DEFAULT_PER_MINUTE = 120;

    public function __construct(
        private readonly ApiPrincipalContext $principalContext,
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $principal = $this->principalContext->get();

        if ($principal === null) {
            return $next($request);
        }

        $limit = $principal->rateLimitPerMinute ?? self::DEFAULT_PER_MINUTE;
        $bucket = sprintf('api-key:%s:%d', $principal->type, $principal->id);

        if ($this->rateLimiter->tooManyAttempts($bucket, $limit)) {
            $retryAfter = $this->rateLimiter->availableIn($bucket);

            return new JsonResponse(
                ['message' => __('Rate limit exceeded.')],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $limit,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->getTimestamp(),
                ]
            );
        }

        $this->rateLimiter->hit($bucket);

        $response = $next($request);

        $response->headers->add([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $this->rateLimiter->remaining($bucket, $limit)),
        ]);

        return $response;
    }
}
