<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\ApiKey;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ApiKey\CreateApiKeyRequest;
use HiEvents\Services\Application\Handlers\ApiKey\CreateApiKeyHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CreateApiKeyAction extends BaseAction
{
    public function __construct(
        private readonly CreateApiKeyHandler $handler,
    ) {}

    public function __invoke(CreateApiKeyRequest $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->isActionAuthorized($accountId, AccountDomainObject::class, Role::ADMIN);
        $this->requirePermission(Permission::APIKEY_MANAGE);

        try {
            $created = $this->handler->handle(
                accountId: $accountId,
                creatorUserId: $this->getAuthenticatedUser()->getId(),
                name: (string) $request->validated('name'),
                scopes: (array) $request->validated('scopes'),
                organizerId: $request->validated('organizer_id') !== null
                    ? (int) $request->validated('organizer_id')
                    : null,
                eventId: $request->validated('event_id') !== null
                    ? (int) $request->validated('event_id')
                    : null,
                rateLimitPerMinute: $request->validated('rate_limit_per_minute') !== null
                    ? (int) $request->validated('rate_limit_per_minute')
                    : null,
                allowedIps: $request->validated('allowed_ips'),
                expiresAt: $request->validated('expires_at'),
            );
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['scopes' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'id' => $created['id'],
            'key_prefix' => $created['prefix'],
            // Shown once. There is no endpoint that returns it again, by design.
            'key' => $created['plaintext'],
            'message' => __('Store this key now. It cannot be retrieved again.'),
        ]);
    }
}
