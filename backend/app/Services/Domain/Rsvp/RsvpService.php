<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Rsvp;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\InvitationStatus;
use HiEvents\DomainObjects\Status\RsvpResponseStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Rsvp\DTO\RsvpResultDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Invitation-based RSVP, distinct from ticketing.
 *
 * A guest list is not a shop. There is no cart, no price and no order — an invitation names
 * a person, and they accept or decline for a party up to a size the host set. Modelling it
 * as a zero-priced ticket would drag checkout, fees and tax through a flow that has none of
 * those things.
 *
 * @see docs/arzo-master-plan/91-attendee-platform.md
 */
class RsvpService
{
    private const TOKEN_LENGTH = 48;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * Creates an invitation and returns the token to send.
     *
     * The token is returned once and stored only as a hash: whoever holds it can answer for
     * the invitee, so it is a bearer credential and a database copy should not hand somebody
     * a working guest list.
     *
     * @return array{invitation_id: int, token: string}
     *
     * @throws ResourceConflictException
     */
    public function invite(
        int $eventId,
        string $firstName,
        string $email,
        ?string $lastName = null,
        ?string $phone = null,
        int $maxPartySize = 1,
        ?Carbon $expiresAt = null,
        ?int $personId = null,
    ): array {
        if ($maxPartySize < 1) {
            throw new ResourceConflictException(__('An invitation must allow at least one guest.'));
        }

        $existing = $this->databaseManager->table('invitations')
            ->where('event_id', $eventId)
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            throw new ResourceConflictException(
                __('That email has already been invited to this event.')
            );
        }

        $token = Str::random(self::TOKEN_LENGTH);

        $invitationId = (int) $this->databaseManager->table('invitations')->insertGetId([
            'short_id' => 'iv_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'person_id' => $personId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'token_hash' => hash('sha256', $token),
            'max_party_size' => $maxPartySize,
            'status' => InvitationStatus::PENDING->value,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['invitation_id' => $invitationId, 'token' => $token];
    }

    public function markSent(int $invitationId): void
    {
        $this->databaseManager->table('invitations')
            ->where('id', $invitationId)
            ->update([
                'status' => InvitationStatus::SENT->value,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Records a response against an invitation token.
     *
     * @throws ResourceConflictException
     */
    public function respond(
        string $token,
        string $response,
        int $partySize = 1,
        ?array $formData = null,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): RsvpResultDTO {
        $answer = RsvpResponseStatus::tryFrom($response);

        if ($answer === null) {
            throw new ResourceConflictException(__('That is not a valid response.'));
        }

        return $this->databaseManager->transaction(function () use (
            $token,
            $answer,
            $partySize,
            $formData,
            $notes,
            $ipAddress
        ): RsvpResultDTO {
            $invitation = $this->databaseManager->table('invitations')
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new ResourceConflictException(__('That invitation could not be found.'));
            }

            if ($invitation->expires_at !== null && now()->gt(Carbon::parse((string) $invitation->expires_at))) {
                throw new ResourceConflictException(__('That invitation has expired.'));
            }

            // Declining brings nobody, whatever the form said. Accepting for more than the
            // host allowed is refused rather than silently trimmed, because a guest who
            // thinks four are coming and finds two on the list is a problem at the door.
            $effectiveParty = $answer->bringsGuests() ? $partySize : 0;

            if ($answer->bringsGuests()) {
                if ($partySize < 1) {
                    throw new ResourceConflictException(__('Attending requires at least one guest.'));
                }

                if ($partySize > (int) $invitation->max_party_size) {
                    throw new ResourceConflictException(
                        __('This invitation allows at most :max guest(s).', [
                            'max' => (int) $invitation->max_party_size,
                        ])
                    );
                }
            }

            $previous = $this->databaseManager->table('rsvp_responses')
                ->where('invitation_id', $invitation->id)
                ->first();

            // A guest may change their mind. The response is updated rather than appended,
            // and the invitation status follows it, so the guest list always reads as the
            // latest answer rather than a history somebody has to interpret.
            if ($previous !== null) {
                $this->databaseManager->table('rsvp_responses')
                    ->where('id', $previous->id)
                    ->update([
                        'response' => $answer->value,
                        'party_size' => $effectiveParty,
                        'responded_at' => now(),
                        'responded_from_ip' => $ipAddress,
                        'form_data' => $formData !== null ? json_encode($formData) : null,
                        'notes' => $notes,
                        'updated_at' => now(),
                    ]);

                $responseId = (int) $previous->id;
            } else {
                try {
                    $responseId = (int) $this->databaseManager->table('rsvp_responses')->insertGetId([
                        'short_id' => 'rr_'.Str::lower(Str::random(20)),
                        'invitation_id' => $invitation->id,
                        'event_id' => $invitation->event_id,
                        'response' => $answer->value,
                        'party_size' => $effectiveParty,
                        'responded_at' => now(),
                        'responded_from_ip' => $ipAddress,
                        'form_data' => $formData !== null ? json_encode($formData) : null,
                        'notes' => $notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $responseId = (int) $this->databaseManager->table('rsvp_responses')
                        ->where('invitation_id', $invitation->id)
                        ->value('id');
                }
            }

            $this->databaseManager->table('invitations')
                ->where('id', $invitation->id)
                ->update([
                    'status' => InvitationStatus::fromResponse($answer)->value,
                    'updated_at' => now(),
                ]);

            return new RsvpResultDTO(
                invitationId: (int) $invitation->id,
                responseId: $responseId,
                response: $answer->value,
                partySize: $effectiveParty,
                changedFrom: $previous !== null ? (string) $previous->response : null,
            );
        });
    }

    /**
     * The guest list, each invitation carrying its latest answer.
     *
     * @return Collection<int, object>
     */
    public function guestList(int $eventId, ?string $status = null): Collection
    {
        return $this->databaseManager->table('invitations')
            ->leftJoin('rsvp_responses', 'rsvp_responses.invitation_id', '=', 'invitations.id')
            ->where('invitations.event_id', $eventId)
            ->whereNull('invitations.deleted_at')
            ->when($status !== null, static fn ($query) => $query->where('invitations.status', $status))
            ->orderBy('invitations.first_name')
            ->select([
                'invitations.id',
                'invitations.short_id',
                'invitations.first_name',
                'invitations.last_name',
                'invitations.email',
                'invitations.phone',
                'invitations.max_party_size',
                'invitations.status',
                'invitations.sent_at',
                'invitations.expires_at',
                'invitations.created_at',
                'rsvp_responses.party_size',
                'rsvp_responses.responded_at',
            ])
            ->get();
    }

    /**
     * What the host needs: how many are coming, not how many replied.
     *
     * @return array<string, mixed>
     */
    public function summary(int $eventId): array
    {
        $rows = $this->databaseManager->table('invitations')
            ->leftJoin('rsvp_responses', 'rsvp_responses.invitation_id', '=', 'invitations.id')
            ->where('invitations.event_id', $eventId)
            ->whereNull('invitations.deleted_at')
            ->selectRaw('invitations.status, count(*) as total, coalesce(sum(rsvp_responses.party_size), 0) as guests')
            ->groupBy('invitations.status')
            ->get();

        $summary = [
            'invited' => 0,
            'pending' => 0,
            'attending' => 0,
            'not_attending' => 0,
            'tentative' => 0,
            'expected_guests' => 0,
            'tentative_guests' => 0,
        ];

        foreach ($rows as $row) {
            $summary['invited'] += (int) $row->total;

            $status = (string) $row->status;

            if ($status === InvitationStatus::ATTENDING->value) {
                $summary['attending'] += (int) $row->total;
                $summary['expected_guests'] += (int) $row->guests;
            } elseif ($status === InvitationStatus::NOT_ATTENDING->value) {
                $summary['not_attending'] += (int) $row->total;
            } elseif ($status === InvitationStatus::TENTATIVE->value) {
                $summary['tentative'] += (int) $row->total;
                // Tentative guests are counted separately rather than folded into the
                // expected figure: planning catering against maybes overstates the room.
                $summary['tentative_guests'] += (int) $row->guests;
            } else {
                $summary['pending'] += (int) $row->total;
            }
        }

        return $summary;
    }

    /**
     * @throws ResourceConflictException
     */
    public function revoke(int $invitationId, int $eventId): void
    {
        $updated = $this->databaseManager->table('invitations')
            ->where('id', $invitationId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->update([
                'status' => InvitationStatus::REVOKED->value,
                // The hash goes with it, so the link stops working immediately rather than
                // when somebody remembers to expire it.
                'token_hash' => null,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ResourceConflictException(__('That invitation could not be found.'));
        }
    }
}
