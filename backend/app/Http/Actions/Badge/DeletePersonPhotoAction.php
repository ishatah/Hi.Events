<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badge;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Domain\Badge\PersonPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Validation\ValidationException;

class DeletePersonPhotoAction extends BaseAction
{
    public function __construct(
        private readonly PersonPhotoService $personPhotoService,
    ) {}

    public function __invoke(int $eventId, int $personId): LaravelResponse|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::CREDENTIAL_ISSUE);

        try {
            $this->personPhotoService->remove($personId);
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['photo' => $exception->getMessage()]);
        }

        return $this->deletedResponse();
    }
}
