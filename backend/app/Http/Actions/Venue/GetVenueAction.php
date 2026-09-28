<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Venue;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Venue\VenueResource;
use HiEvents\Services\Application\Handlers\Venue\GetVenueHandler;
use Illuminate\Http\JsonResponse;

class GetVenueAction extends BaseAction
{
    public function __construct(
        private readonly GetVenueHandler $handler,
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        return $this->resourceResponse(VenueResource::class, $this->handler->handle($accountId, $id));
    }
}
