<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Venue;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Venue\UpsertVenueRequest;
use HiEvents\Resources\Venue\VenueResource;
use HiEvents\Services\Application\Handlers\Venue\UpdateVenueHandler;
use Illuminate\Http\JsonResponse;

class UpdateVenueAction extends BaseAction
{
    public function __construct(
        private readonly UpdateVenueHandler $handler,
    ) {}

    public function __invoke(UpsertVenueRequest $request, int $id): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $record = $this->handler->handle($accountId, $id, $request->validated());

        return $this->resourceResponse(VenueResource::class, $record);
    }
}
