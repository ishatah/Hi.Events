<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Badge;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Badge\CapturePersonPhotoRequest;
use HiEvents\Services\Domain\Badge\PersonPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CapturePersonPhotoAction extends BaseAction
{
    public function __construct(
        private readonly PersonPhotoService $personPhotoService,
    ) {}

    public function __invoke(CapturePersonPhotoRequest $request, int $eventId, int $personId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);
        $this->requireEventPermission($eventId, Permission::CREDENTIAL_ISSUE);

        try {
            $imageId = $this->personPhotoService->capture($personId, $request->file('photo'));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['photo' => $exception->getMessage()]);
        }

        return $this->jsonResponse(['image_id' => $imageId]);
    }
}
