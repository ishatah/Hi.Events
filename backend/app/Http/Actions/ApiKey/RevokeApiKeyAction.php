<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\ApiKey;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\ApiKey\RevokeApiKeyHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class RevokeApiKeyAction extends BaseAction
{
    public function __construct(
        private readonly RevokeApiKeyHandler $handler,
    ) {}

    public function __invoke(int $apiKeyId): LaravelResponse|JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->isActionAuthorized($accountId, AccountDomainObject::class, Role::ADMIN);
        $this->requirePermission(Permission::APIKEY_MANAGE);

        try {
            $this->handler->handle($apiKeyId, $accountId, $this->getAuthenticatedUser()->getId());
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
