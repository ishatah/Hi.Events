<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\ApiKey;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\ApiKey\ApiKeyResource;
use HiEvents\Services\Application\Handlers\ApiKey\GetApiKeysHandler;
use Illuminate\Http\JsonResponse;

class GetApiKeysAction extends BaseAction
{
    public function __construct(
        private readonly GetApiKeysHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->isActionAuthorized($accountId, AccountDomainObject::class, Role::ADMIN);
        $this->requirePermission(Permission::APIKEY_MANAGE);

        return $this->resourceResponse(ApiKeyResource::class, $this->handler->handle($accountId));
    }
}
