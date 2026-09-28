<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Zone;

use HiEvents\DomainObjects\VenueDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Zone\DeleteZoneHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteZoneAction extends BaseAction
{
    public function __construct(
        private readonly DeleteZoneHandler $handler,
    ) {}

    public function __invoke(int $venueId, int $id): Response|JsonResponse
    {
        $this->isActionAuthorized(
            $this->resolveVenueId($venueId),
            VenueDomainObject::class,
        );

        $this->handler->handle($venueId, $id);

        return $this->deletedResponse();
    }

    private function resolveVenueId(int $venueId): int
    {
        return $venueId;
    }
}
