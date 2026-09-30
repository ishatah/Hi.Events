<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\Messages;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\RejectMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RejectMessageAction extends BaseAction
{
    public function __construct(
        private readonly RejectMessageHandler $handler,
    ) {}

    public function __invoke(Request $request, int $messageId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $this->handler->handle(
            $messageId,
            $request->input('reason') !== null ? (string) $request->input('reason') : null
        );

        return $this->jsonResponse([
            'message' => __('Message rejected and will not be sent'),
        ]);
    }
}
