<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Networking;

use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The opt-in attendee directory, connections and blocks.
 *
 * Discoverability is opt-in with no path that defaults it on: a directory listing people who
 * did not ask to be listed is a privacy incident whatever the setting is called.
 *
 * Connections are one row per unordered person pair, so A requesting B and B requesting A are
 * the same connection rather than two halves that can disagree about their status.
 *
 * @see docs/arzo-master-plan/31-networking.md
 */
class NetworkingService
{
    private const MAX_NOTE_LENGTH = 280;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function optIn(
        int $eventId,
        int $personId,
        ?string $headline = null,
        ?array $interests = null,
        ?array $lookingFor = null,
        bool $shareContactOnConnect = false,
    ): int {
        $existing = $this->databaseManager->table('networking_profiles')
            ->where('event_id', $eventId)
            ->where('person_id', $personId)
            ->whereNull('deleted_at')
            ->first();

        $attributes = [
            'is_discoverable' => true,
            'share_contact_on_connect' => $shareContactOnConnect,
            'headline' => $headline,
            'interests' => $interests !== null ? json_encode($interests) : null,
            'looking_for' => $lookingFor !== null ? json_encode($lookingFor) : null,
            'opted_in_at' => now(),
            'opted_out_at' => null,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            $this->databaseManager->table('networking_profiles')
                ->where('id', $existing->id)
                ->update($attributes);

            return (int) $existing->id;
        }

        return (int) $this->databaseManager->table('networking_profiles')->insertGetId(
            $attributes + [
                'short_id' => 'np_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'person_id' => $personId,
                'created_at' => now(),
            ]
        );
    }

    /**
     * Opting out hides the profile but keeps the record, because the connections already made
     * are a shared history rather than one person's data to erase.
     *
     * @throws ResourceConflictException
     */
    public function optOut(int $eventId, int $personId): void
    {
        $updated = $this->databaseManager->table('networking_profiles')
            ->where('event_id', $eventId)
            ->where('person_id', $personId)
            ->whereNull('deleted_at')
            ->update([
                'is_discoverable' => false,
                'opted_out_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new ResourceConflictException(__('That networking profile could not be found.'));
        }
    }

    /**
     * The directory, as one person sees it.
     *
     * Excludes anybody either side has blocked: a block that only works in one direction
     * leaves the blocked person still able to see and approach the blocker.
     *
     * @return Collection<int, object>
     */
    public function directory(int $eventId, int $viewerPersonId, ?string $search = null): Collection
    {
        $blocked = $this->blockedPersonIds($eventId, $viewerPersonId);

        return $this->databaseManager->table('networking_profiles')
            ->join('persons', 'persons.id', '=', 'networking_profiles.person_id')
            ->where('networking_profiles.event_id', $eventId)
            ->where('networking_profiles.is_discoverable', true)
            ->where('networking_profiles.person_id', '!=', $viewerPersonId)
            ->whereNull('networking_profiles.deleted_at')
            ->whereNull('persons.deleted_at')
            ->when(
                $blocked->isNotEmpty(),
                static fn ($query) => $query->whereNotIn('networking_profiles.person_id', $blocked)
            )
            ->when($search !== null && trim($search) !== '', static function ($query) use ($search): void {
                $term = '%'.Str::lower(trim($search)).'%';

                $query->where(static function ($inner) use ($term): void {
                    $inner->whereRaw('lower(persons.first_name) like ?', [$term])
                        ->orWhereRaw('lower(persons.last_name) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(persons.company, \'\')) like ?', [$term])
                        ->orWhereRaw('lower(coalesce(networking_profiles.headline, \'\')) like ?', [$term]);
                });
            })
            ->orderBy('persons.first_name')
            ->select([
                'networking_profiles.short_id',
                'networking_profiles.person_id',
                'networking_profiles.headline',
                'networking_profiles.interests',
                'networking_profiles.looking_for',
                'persons.first_name',
                'persons.last_name',
                'persons.company',
                'persons.job_title',
            ])
            ->get();
    }

    /**
     * @throws ResourceConflictException
     */
    public function requestConnection(
        int $eventId,
        int $requesterPersonId,
        int $recipientPersonId,
        ?string $note = null,
        string $source = 'REQUEST',
    ): int {
        if ($requesterPersonId === $recipientPersonId) {
            throw new ResourceConflictException(__('You cannot connect with yourself.'));
        }

        if ($note !== null && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new ResourceConflictException(
                __('A connection note is limited to :max characters.', ['max' => self::MAX_NOTE_LENGTH])
            );
        }

        return $this->databaseManager->transaction(function () use (
            $eventId,
            $requesterPersonId,
            $recipientPersonId,
            $note,
            $source
        ): int {
            if ($this->isBlockedEitherWay($eventId, $requesterPersonId, $recipientPersonId)) {
                // Deliberately the same message as a missing profile. Telling somebody they
                // have been blocked invites them to work around it.
                throw new ResourceConflictException(
                    __('That person is not accepting connections.')
                );
            }

            $existing = $this->findConnection($eventId, $requesterPersonId, $recipientPersonId);

            if ($existing !== null) {
                // The other person already asked, so this is an acceptance rather than a
                // second request: two people who both want to connect are connected.
                if ((string) $existing->status === 'PENDING'
                    && (int) $existing->recipient_person_id === $requesterPersonId
                ) {
                    $this->databaseManager->table('connections')
                        ->where('id', $existing->id)
                        ->update([
                            'status' => 'ACCEPTED',
                            'responded_at' => now(),
                            'updated_at' => now(),
                        ]);

                    return (int) $existing->id;
                }

                throw new ResourceConflictException(
                    __('A connection with that person already exists.')
                );
            }

            $recipientIsDiscoverable = $this->databaseManager->table('networking_profiles')
                ->where('event_id', $eventId)
                ->where('person_id', $recipientPersonId)
                ->where('is_discoverable', true)
                ->whereNull('deleted_at')
                ->exists();

            // A badge scan is two people standing together who chose to scan, which is its own
            // consent; the directory opt-in is only needed to be approached out of the blue.
            if (! $recipientIsDiscoverable && $source !== 'BADGE_SCAN') {
                throw new ResourceConflictException(__('That person is not accepting connections.'));
            }

            return (int) $this->databaseManager->table('connections')->insertGetId([
                'short_id' => 'cn_'.Str::lower(Str::random(20)),
                'event_id' => $eventId,
                'requester_person_id' => $requesterPersonId,
                'recipient_person_id' => $recipientPersonId,
                'status' => $source === 'BADGE_SCAN' ? 'ACCEPTED' : 'PENDING',
                'source' => $source,
                'note' => $note,
                'responded_at' => $source === 'BADGE_SCAN' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @throws ResourceConflictException
     */
    public function respondToConnection(int $connectionId, int $respondingPersonId, bool $accept): void
    {
        $this->databaseManager->transaction(function () use ($connectionId, $respondingPersonId, $accept): void {
            $connection = $this->databaseManager->table('connections')
                ->where('id', $connectionId)
                ->lockForUpdate()
                ->first();

            if ($connection === null) {
                throw new ResourceConflictException(__('That connection request could not be found.'));
            }

            // Only the person who was asked can answer. Without this the requester could
            // accept on the other person's behalf.
            if ((int) $connection->recipient_person_id !== $respondingPersonId) {
                throw new ResourceConflictException(
                    __('Only the person who received the request can answer it.')
                );
            }

            if ((string) $connection->status !== 'PENDING') {
                throw new ResourceConflictException(__('That request has already been answered.'));
            }

            $this->databaseManager->table('connections')
                ->where('id', $connectionId)
                ->update([
                    'status' => $accept ? 'ACCEPTED' : 'DECLINED',
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Blocking is stronger than declining: it also closes any existing connection and stops
     * the blocked person appearing in, or seeing, the blocker's directory.
     */
    public function block(int $eventId, int $blockerPersonId, int $blockedPersonId): void
    {
        if ($blockerPersonId === $blockedPersonId) {
            return;
        }

        $this->databaseManager->transaction(function () use ($eventId, $blockerPersonId, $blockedPersonId): void {
            $alreadyBlocked = $this->databaseManager->table('networking_blocks')
                ->where('event_id', $eventId)
                ->where('blocker_person_id', $blockerPersonId)
                ->where('blocked_person_id', $blockedPersonId)
                ->exists();

            if (! $alreadyBlocked) {
                $this->databaseManager->table('networking_blocks')->insert([
                    'event_id' => $eventId,
                    'blocker_person_id' => $blockerPersonId,
                    'blocked_person_id' => $blockedPersonId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $connection = $this->findConnection($eventId, $blockerPersonId, $blockedPersonId);

            if ($connection !== null) {
                $this->databaseManager->table('connections')
                    ->where('id', $connection->id)
                    ->update([
                        'status' => 'BLOCKED',
                        'responded_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function unblock(int $eventId, int $blockerPersonId, int $blockedPersonId): void
    {
        $this->databaseManager->table('networking_blocks')
            ->where('event_id', $eventId)
            ->where('blocker_person_id', $blockerPersonId)
            ->where('blocked_person_id', $blockedPersonId)
            ->delete();
    }

    /**
     * One person's connections, with contact details only where the other side agreed to
     * share them: accepting a connection is not the same as handing over an email address.
     *
     * @return Collection<int, object>
     */
    /**
     * One person's connections, each with the other side's details.
     *
     * @return Collection<int, object>
     */
    public function connectionsFor(int $eventId, int $personId): Collection
    {
        return $this->databaseManager->table('connections')
            ->joinSub(
                $this->databaseManager->table('persons')
                    ->leftJoin('networking_profiles', static function ($join) use ($eventId): void {
                        $join->on('networking_profiles.person_id', '=', 'persons.id')
                            ->where('networking_profiles.event_id', '=', $eventId);
                    })
                    ->select([
                        'persons.id',
                        'persons.first_name',
                        'persons.last_name',
                        'persons.company',
                        'persons.job_title',
                        'persons.email',
                        $this->databaseManager->raw(
                            'coalesce(networking_profiles.share_contact_on_connect, false) as shares_contact'
                        ),
                    ]),
                'other',
                function ($join) use ($personId): void {
                    $join->on('other.id', '=', $this->databaseManager->raw(
                        'case when connections.requester_person_id = ?'
                        .' then connections.recipient_person_id'
                        .' else connections.requester_person_id end'
                    ))->addBinding($personId, 'join');
                }
            )
            ->where('connections.event_id', $eventId)
            ->where(static function ($query) use ($personId): void {
                $query->where('connections.requester_person_id', $personId)
                    ->orWhere('connections.recipient_person_id', $personId);
            })
            ->orderByDesc('connections.created_at')
            ->select([
                'connections.id',
                'connections.short_id',
                'connections.status',
                'connections.source',
                'connections.note',
                'connections.requester_person_id',
                'connections.recipient_person_id',
                'connections.responded_at',
                'connections.created_at',
                'other.id as other_person_id',
                'other.first_name',
                'other.last_name',
                'other.company',
                'other.job_title',
                // Accepting a connection is not the same as handing over an email address,
                // so the address appears only where the other side chose to share it.
                $this->databaseManager->raw(
                    "case when connections.status = 'ACCEPTED' and other.shares_contact"
                    .' then other.email else null end as email'
                ),
            ])
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function blockedPersonIds(int $eventId, int $personId): Collection
    {
        return $this->databaseManager->table('networking_blocks')
            ->where('event_id', $eventId)
            ->where(static function ($query) use ($personId): void {
                $query->where('blocker_person_id', $personId)
                    ->orWhere('blocked_person_id', $personId);
            })
            ->get(['blocker_person_id', 'blocked_person_id'])
            ->flatMap(static fn (object $row): array => [
                (int) $row->blocker_person_id,
                (int) $row->blocked_person_id,
            ])
            ->reject(static fn (int $id): bool => $id === $personId)
            ->unique()
            ->values();
    }

    private function isBlockedEitherWay(int $eventId, int $firstPersonId, int $secondPersonId): bool
    {
        return $this->databaseManager->table('networking_blocks')
            ->where('event_id', $eventId)
            ->where(static function ($query) use ($firstPersonId, $secondPersonId): void {
                $query->where(static function ($inner) use ($firstPersonId, $secondPersonId): void {
                    $inner->where('blocker_person_id', $firstPersonId)
                        ->where('blocked_person_id', $secondPersonId);
                })->orWhere(static function ($inner) use ($firstPersonId, $secondPersonId): void {
                    $inner->where('blocker_person_id', $secondPersonId)
                        ->where('blocked_person_id', $firstPersonId);
                });
            })
            ->exists();
    }

    private function findConnection(int $eventId, int $firstPersonId, int $secondPersonId): ?object
    {
        return $this->databaseManager->table('connections')
            ->where('event_id', $eventId)
            ->whereRaw(
                'LEAST(requester_person_id, recipient_person_id) = ?'
                .' AND GREATEST(requester_person_id, recipient_person_id) = ?',
                [min($firstPersonId, $secondPersonId), max($firstPersonId, $secondPersonId)]
            )
            ->first();
    }
}
