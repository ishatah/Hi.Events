<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Venue;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Venue\VenueResource;
use HiEvents\Services\Application\Handlers\Venue\GetVenuesHandler;
use Illuminate\Http\JsonResponse;

class GetVenuesAction extends BaseAction
{
    public function __construct(
        private readonly GetVenuesHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        return $this->resourceResponse(VenueResource::class, $this->handler->handle($accountId));
    }
}
