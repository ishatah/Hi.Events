<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Session;

use HiEvents\DomainObjects\SessionRegistrationDomainObject;
use HiEvents\DomainObjects\Status\SessionRegistrationStatus;
use HiEvents\DomainObjects\Status\SessionWaitlistStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Session\DTO\SessionRegistrationResultDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Registers an attendee for a session, or places them on its waitlist.
 *
 * Capacity is counted rather than stored. A stored counter drifts the moment anything
 * cancels, expires or is promoted out of order, and every one of those happens here.
 *
 * @see docs/arzo-master-plan/27-sessions-tracks.md
 */
class SessionRegistrationService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function register(int $sessionId, int $attendeeId): SessionRegistrationResultDTO
    {
        return $this->databaseManager->transaction(function () use ($sessionId, $attendeeId) {
            // Locking the session row serialises everyone competing for the last seat. Without
            // it two concurrent registrations both read capacity-1 taken and both succeed.
            $session = $this->databaseManager->table('sessions')
                ->where('id', $sessionId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new ResourceConflictException(__('The session could not be found.'));
            }

            if (! $session->requires_registration) {
                throw new ResourceConflictException(__('This session does not require registration.'));
            }

            $this->guardRegistrationWindow($session);
            $this->guardAttendeeBelongsToEvent($attendeeId, (int) $session->event_id);

            if ($this->activeRegistration($sessionId, $attendeeId) !== null) {
                throw new ResourceConflictException(__('You are already registered for this session.'));
            }

            if ($this->queuedWaitlistEntry($sessionId, $attendeeId) !== null) {
                throw new ResourceConflictException(__('You are already on the waitlist for this session.'));
            }

            if ($this->hasSpace($session)) {
                return new SessionRegistrationResultDTO(
                    registration: $this->createRegistration($sessionId, $attendeeId),
                    waitlisted: false,
                );
            }

            if (! $session->allow_waitlist) {
                throw new ResourceConflictException(__('This session is full.'));
            }

            return new SessionRegistrationResultDTO(
                registration: null,
                waitlisted: true,
                waitlistPosition: $this->joinWaitlist($sessionId, $attendeeId),
            );
        });
    }

    /**
     * Cancelling frees a seat, so the next person queued is offered it in the same
     * transaction. Leaving promotion to a later job means a seat sits empty while somebody
     * is waiting for it.
     *
     * @throws ResourceConflictException
     */
    public function cancel(int $sessionId, int $attendeeId): ?int
    {
        return $this->databaseManager->transaction(function () use ($sessionId, $attendeeId): ?int {
            $this->databaseManager->table('sessions')
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->first();

            $registration = $this->activeRegistration($sessionId, $attendeeId);

            if ($registration === null) {
                $waitlistEntry = $this->queuedWaitlistEntry($sessionId, $attendeeId);

                if ($waitlistEntry === null) {
                    throw new ResourceConflictException(__('You are not registered for this session.'));
                }

                $this->databaseManager->table('session_waitlist_entries')
                    ->where('id', $waitlistEntry->id)
                    ->update([
                        'status' => SessionWaitlistStatus::CANCELLED->value,
                        'cancelled_at' => now(),
                        'updated_at' => now(),
                    ]);

                return null;
            }

            $this->databaseManager->table('session_registrations')
                ->where('id', $registration->id)
                ->update([
                    'status' => SessionRegistrationStatus::CANCELLED->value,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->promoteNextFromWaitlist($sessionId);
        });
    }

    /**
     * @return int|null the attendee promoted, if any
     */
    public function promoteNextFromWaitlist(int $sessionId): ?int
    {
        $session = $this->databaseManager->table('sessions')
            ->where('id', $sessionId)
            ->whereNull('deleted_at')
            ->first();

        if ($session === null || ! $this->hasSpace($session)) {
            return null;
        }

        $next = $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->where('status', SessionWaitlistStatus::WAITING->value)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->first();

        if ($next === null) {
            return null;
        }

        $this->databaseManager->table('session_waitlist_entries')
            ->where('id', $next->id)
            ->update([
                'status' => SessionWaitlistStatus::ACCEPTED->value,
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        $this->createRegistration($sessionId, (int) $next->attendee_id);

        return (int) $next->attendee_id;
    }

    public function registeredCount(int $sessionId): int
    {
        return $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('status', SessionRegistrationStatus::REGISTERED->value)
            ->whereNull('deleted_at')
            ->count();
    }

    public function waitlistCount(int $sessionId): int
    {
        return $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->where('status', SessionWaitlistStatus::WAITING->value)
            ->whereNull('deleted_at')
            ->count();
    }

    private function hasSpace(object $session): bool
    {
        if ($session->capacity === null) {
            return true;
        }

        $taken = $this->registeredCount((int) $session->id) + $this->outstandingOffers((int) $session->id);

        return $taken < (int) $session->capacity;
    }

    private function outstandingOffers(int $sessionId): int
    {
        return $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->where('status', SessionWaitlistStatus::OFFERED->value)
            ->where(function ($query) {
                $query->whereNull('offer_expires_at')
                    ->orWhere('offer_expires_at', '>', now());
            })
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardRegistrationWindow(object $session): void
    {
        if ($session->registration_opens_at !== null && now()->lt($session->registration_opens_at)) {
            throw new ResourceConflictException(__('Registration for this session has not opened yet.'));
        }

        if ($session->registration_closes_at !== null && now()->gt($session->registration_closes_at)) {
            throw new ResourceConflictException(__('Registration for this session has closed.'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function guardAttendeeBelongsToEvent(int $attendeeId, int $eventId): void
    {
        $belongs = $this->databaseManager->table('attendees')
            ->where('id', $attendeeId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw new ResourceConflictException(__('That attendee does not belong to this event.'));
        }
    }

    private function activeRegistration(int $sessionId, int $attendeeId): ?object
    {
        return $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('attendee_id', $attendeeId)
            ->where('status', SessionRegistrationStatus::REGISTERED->value)
            ->whereNull('deleted_at')
            ->first();
    }

    private function queuedWaitlistEntry(int $sessionId, int $attendeeId): ?object
    {
        return $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->where('attendee_id', $attendeeId)
            ->whereIn('status', [
                SessionWaitlistStatus::WAITING->value,
                SessionWaitlistStatus::OFFERED->value,
            ])
            ->whereNull('deleted_at')
            ->first();
    }

    private function createRegistration(int $sessionId, int $attendeeId): SessionRegistrationDomainObject
    {
        // A cancelled row keeps its (session_id, attendee_id) pair, and the partial unique
        // index only covers live rows, so re-registering reuses the cancelled row rather
        // than inserting a duplicate the index would reject.
        $cancelled = $this->databaseManager->table('session_registrations')
            ->where('session_id', $sessionId)
            ->where('attendee_id', $attendeeId)
            ->whereNull('deleted_at')
            ->first();

        if ($cancelled !== null) {
            $this->databaseManager->table('session_registrations')
                ->where('id', $cancelled->id)
                ->update([
                    'status' => SessionRegistrationStatus::REGISTERED->value,
                    'registered_at' => now(),
                    'cancelled_at' => null,
                    'updated_at' => now(),
                ]);

            return SessionRegistrationDomainObject::hydrateFromArray(
                (array) $this->databaseManager->table('session_registrations')->find($cancelled->id)
            );
        }

        try {
            $id = $this->databaseManager->table('session_registrations')->insertGetId([
                'short_id' => 'sr_'.Str::lower(Str::random(20)),
                'session_id' => $sessionId,
                'attendee_id' => $attendeeId,
                'status' => SessionRegistrationStatus::REGISTERED->value,
                'registered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ResourceConflictException(__('You are already registered for this session.'));
        }

        return SessionRegistrationDomainObject::hydrateFromArray(
            (array) $this->databaseManager->table('session_registrations')->find($id)
        );
    }

    private function joinWaitlist(int $sessionId, int $attendeeId): int
    {
        $position = ((int) $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->whereNull('deleted_at')
            ->max('position')) + 1;

        $existing = $this->databaseManager->table('session_waitlist_entries')
            ->where('session_id', $sessionId)
            ->where('attendee_id', $attendeeId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            $this->databaseManager->table('session_waitlist_entries')
                ->where('id', $existing->id)
                ->update([
                    'status' => SessionWaitlistStatus::WAITING->value,
                    'position' => $position,
                    'cancelled_at' => null,
                    'updated_at' => now(),
                ]);

            return $position;
        }

        $this->databaseManager->table('session_waitlist_entries')->insert([
            'short_id' => 'sw_'.Str::lower(Str::random(20)),
            'session_id' => $sessionId,
            'attendee_id' => $attendeeId,
            'status' => SessionWaitlistStatus::WAITING->value,
            'position' => $position,
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $position;
    }
}
