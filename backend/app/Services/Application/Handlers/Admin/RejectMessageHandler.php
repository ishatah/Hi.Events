<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

/**
 * Refuses a message held for review.
 *
 * The counterpart to approval, which existed alone: a message flagged for review could be
 * approved but never refused, so anything a reviewer judged to be spam sat in PENDING_REVIEW
 * indefinitely with no way to close it out. The spam-events flow already has both sides.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-305
 */
class RejectMessageHandler
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ValidationException
     */
    public function handle(int $messageId, ?string $reason = null): MessageDomainObject
    {
        return $this->databaseManager->transaction(function () use ($messageId, $reason) {
            $message = $this->messageRepository->findFirst($messageId);

            if ($message === null) {
                throw new ResourceNotFoundException(__('Message not found'));
            }

            if ($message->getStatus() !== MessageStatus::PENDING_REVIEW->name) {
                throw ValidationException::withMessages([
                    'status' => [__('Message must be in pending review status to be rejected')],
                ]);
            }

            return $this->messageRepository->updateFromArray($messageId, [
                'status' => MessageStatus::CANCELLED->name,
                // Kept on the message rather than only in an audit log, because the organizer
                // asking why their message never went out is the person who needs it.
                'rejection_reason' => $reason,
            ]);
        });
    }
}
