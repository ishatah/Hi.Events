<?php

declare(strict_types=1);

namespace Tests\Unit\DomainObjects\Status;

use HiEvents\DomainObjects\Status\EventStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventStatusTransitionTest extends TestCase
{
    #[DataProvider('allowedTransitions')]
    public function test_an_allowed_transition_is_permitted(EventStatus $from, EventStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('refusedTransitions')]
    public function test_a_refused_transition_is_rejected(EventStatus $from, EventStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public static function allowedTransitions(): array
    {
        return [
            'draft goes on sale' => [EventStatus::DRAFT, EventStatus::LIVE],
            'draft is archived' => [EventStatus::DRAFT, EventStatus::ARCHIVED],
            'live is withdrawn to draft' => [EventStatus::LIVE, EventStatus::DRAFT],
            'live is archived' => [EventStatus::LIVE, EventStatus::ARCHIVED],
            'a no-op is permitted' => [EventStatus::LIVE, EventStatus::LIVE],
        ];
    }

    public static function refusedTransitions(): array
    {
        return [
            'an archived event is never put back on sale' => [EventStatus::ARCHIVED, EventStatus::LIVE],
            'an archived event does not return to draft' => [EventStatus::ARCHIVED, EventStatus::DRAFT],
            'an organizer does not choose manual review' => [EventStatus::DRAFT, EventStatus::PENDING_MANUAL_REVIEW],
            'nor from live' => [EventStatus::LIVE, EventStatus::PENDING_MANUAL_REVIEW],
            'a reviewed event is released by the admin path, not this one' => [EventStatus::PENDING_MANUAL_REVIEW, EventStatus::LIVE],
        ];
    }
}
