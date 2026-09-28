<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Venue;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Venue\DeleteVenueHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteVenueAction extends BaseAction
{
    public function __construct(
        private readonly DeleteVenueHandler $handler,
    ) {}

    public function __invoke(int $id): Response|JsonResponse
    {
        $accountId = $this->getAuthenticatedAccountId();

        $this->handler->handle($accountId, $id);

        return $this->deletedResponse();
    }
}
