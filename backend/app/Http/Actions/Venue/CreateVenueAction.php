<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Venue;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Venue\UpsertVenueRequest;
use HiEvents\Resources\Venue\VenueResource;
use HiEvents\Services\Application\Handlers\Venue\CreateVenueHandler;
use Illuminate\Http\JsonResponse;

class CreateVenueAction extends BaseAction
{
    public function __construct(
        private readonly CreateVenueHandler $handler,
    ) {}

    public function __invoke(UpsertVenueRequest $request): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $record = $this->handler->handle($accountId, $request->validated());

        return $this->resourceResponse(VenueResource::class, $record, statusCode: 201);
    }
}
