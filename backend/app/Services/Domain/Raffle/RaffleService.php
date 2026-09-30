<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Raffle;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\AccessResult;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Domain\Raffle\DTO\RaffleDrawDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Prize draws over the people who actually attended.
 *
 * The eligible pool comes from the access log rather than a ticket list: being sold a ticket
 * is not the same as turning up, and a raffle drawn from ticket holders would award prizes to
 * people who stayed home.
 *
 * The draw is auditable by construction. It records the pool it drew from, the seed it used,
 * who ran it and when, and the same seed over the same pool reproduces the same winners — so a
 * contested prize can be settled by re-running rather than by argument.
 *
 * @see docs/arzo-master-plan/31-networking.md
 */
class RaffleService
{
    public function __construct(
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function create(
        int $eventId,
        string $name,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        ?string $prizeDescription = null,
        ?int $zoneId = null,
        int $winnerCount = 1,
        bool $excludeStaff = true,
        bool $excludeExhibitors = true,
    ): int {
        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            throw new ResourceConflictException(
                __('The eligibility window must end after it starts.')
            );
        }

        if ($winnerCount < 1) {
            throw new ResourceConflictException(__('A raffle needs at least one winner.'));
        }

        return (int) $this->databaseManager->table('raffles')->insertGetId([
            'short_id' => 'rf_'.Str::lower(Str::random(20)),
            'event_id' => $eventId,
            'name' => $name,
            'prize_description' => $prizeDescription,
            'eligibility_window_start' => $windowStart,
            'eligibility_window_end' => $windowEnd,
            'zone_id' => $zoneId,
            'winner_count' => $winnerCount,
            'exclude_staff' => $excludeStaff,
            'exclude_exhibitors' => $excludeExhibitors,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Who would be in the draw, without drawing.
     *
     * Worth showing before the draw runs: a pool of three when the organizer expected three
     * hundred usually means the window or the zone is wrong, and that is much better found
     * before a winner has been announced.
     *
     * @return array{eligible: int, excluded_staff: int, excluded_exhibitors: int}
     */
    public function poolSummary(int $raffleId): array
    {
        $raffle = $this->findRaffle($raffleId);

        $all = $this->eligiblePersonIds($raffle, applyExclusions: false);
        $eligible = $this->eligiblePersonIds($raffle, applyExclusions: true);

        $excludedStaff = 0;
        $excludedExhibitors = 0;

        if ($raffle->exclude_staff || $raffle->exclude_exhibitors) {
            $removed = $all->diff($eligible);

            $excludedExhibitors = $this->exhibitorStaffPersonIds((int) $raffle->event_id)
                ->intersect($removed)
                ->count();
            $excludedStaff = $removed->count() - $excludedExhibitors;
        }

        return [
            'eligible' => $eligible->count(),
            'excluded_staff' => $excludedStaff,
            'excluded_exhibitors' => $excludedExhibitors,
        ];
    }

    /**
     * Runs the draw.
     *
     * @throws ResourceConflictException
     */
    public function draw(int $raffleId, ?int $drawnByUserId = null, ?string $seed = null): RaffleDrawDTO
    {
        return $this->databaseManager->transaction(function () use ($raffleId, $drawnByUserId, $seed): RaffleDrawDTO {
            $raffle = $this->databaseManager->table('raffles')
                ->where('id', $raffleId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($raffle === null) {
                throw new ResourceConflictException(__('That raffle could not be found.'));
            }

            if ((string) $raffle->status === 'DRAWN') {
                throw new ResourceConflictException(
                    __('That raffle has already been drawn. Create another to draw again.')
                );
            }

            if ((string) $raffle->status === 'CANCELLED') {
                throw new ResourceConflictException(__('That raffle was cancelled.'));
            }

            $pool = $this->eligiblePersonIds($raffle, applyExclusions: true)->values();

            if ($pool->isEmpty()) {
                throw new ResourceConflictException(
                    __('Nobody entered during the eligibility window, so there is nobody to draw.')
                );
            }

            $winnerCount = min((int) $raffle->winner_count, $pool->count());
            $drawSeed = $seed ?? bin2hex(random_bytes(16));

            $winners = $this->pickWinners($pool, $winnerCount, $drawSeed);

            $drawId = (int) $this->databaseManager->table('raffle_draws')->insertGetId([
                'short_id' => 'rd_'.Str::lower(Str::random(20)),
                'raffle_id' => $raffleId,
                'eligible_pool_size' => $pool->count(),
                'random_seed' => $drawSeed,
                'drawn_by_user_id' => $drawnByUserId,
                'drawn_at' => now(),
                'excluded_counts' => json_encode([
                    'exclude_staff' => (bool) $raffle->exclude_staff,
                    'exclude_exhibitors' => (bool) $raffle->exclude_exhibitors,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];
            $position = 1;

            foreach ($winners as $personId) {
                $rows[] = [
                    'short_id' => 'rw_'.Str::lower(Str::random(20)),
                    'raffle_draw_id' => $drawId,
                    'person_id' => $personId,
                    'attendee_id' => null,
                    'position' => $position++,
                    'status' => 'DRAWN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $this->databaseManager->table('raffle_winners')->insert($rows);

            $this->databaseManager->table('raffles')
                ->where('id', $raffleId)
                ->update(['status' => 'DRAWN', 'updated_at' => now()]);

            return new RaffleDrawDTO(
                raffleId: $raffleId,
                drawId: $drawId,
                eligiblePoolSize: $pool->count(),
                randomSeed: $drawSeed,
                winnerPersonIds: $winners->all(),
            );
        });
    }

    /**
     * Re-runs a recorded draw over the same pool.
     *
     * This is what makes the audit trail worth keeping: a challenged result is settled by
     * reproducing it rather than by asserting that the code is fair.
     *
     * @throws ResourceConflictException
     */
    public function verify(int $drawId): array
    {
        $draw = $this->databaseManager->table('raffle_draws')
            ->where('id', $drawId)
            ->first();

        if ($draw === null) {
            throw new ResourceConflictException(__('That draw could not be found.'));
        }

        $raffle = $this->findRaffle((int) $draw->raffle_id);
        $pool = $this->eligiblePersonIds($raffle, applyExclusions: true)->values();

        $recorded = $this->databaseManager->table('raffle_winners')
            ->where('raffle_draw_id', $drawId)
            ->orderBy('position')
            ->pluck('person_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $recomputed = $this->pickWinners(
            $pool,
            count($recorded),
            (string) $draw->random_seed
        )->all();

        return [
            'pool_size_now' => $pool->count(),
            'pool_size_at_draw' => (int) $draw->eligible_pool_size,
            'pool_unchanged' => $pool->count() === (int) $draw->eligible_pool_size,
            'recorded_winners' => $recorded,
            'recomputed_winners' => $recomputed,
            'reproducible' => $recorded === $recomputed,
        ];
    }

    /**
     * @throws ResourceConflictException
     */
    public function open(int $raffleId, int $eventId): void
    {
        $updated = $this->databaseManager->table('raffles')
            ->where('id', $raffleId)
            ->where('event_id', $eventId)
            ->where('status', 'DRAFT')
            ->whereNull('deleted_at')
            ->update(['status' => 'OPEN', 'updated_at' => now()]);

        if ($updated === 0) {
            throw new ResourceConflictException(
                __('That raffle could not be opened. Only a draft raffle can be opened.')
            );
        }
    }

    /**
     * @throws ResourceConflictException
     */
    public function recordClaim(int $winnerId, bool $claimed): void
    {
        $winner = $this->databaseManager->table('raffle_winners')
            ->where('id', $winnerId)
            ->first();

        if ($winner === null) {
            throw new ResourceConflictException(__('That winner could not be found.'));
        }

        $this->databaseManager->table('raffle_winners')
            ->where('id', $winnerId)
            ->update([
                'status' => $claimed ? 'CLAIMED' : 'FORFEITED',
                'claimed_at' => $claimed ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function winners(int $drawId): Collection
    {
        return $this->databaseManager->table('raffle_winners')
            ->leftJoin('persons', 'persons.id', '=', 'raffle_winners.person_id')
            ->where('raffle_winners.raffle_draw_id', $drawId)
            ->orderBy('raffle_winners.position')
            ->select([
                'raffle_winners.id',
                'raffle_winners.short_id',
                'raffle_winners.position',
                'raffle_winners.status',
                'raffle_winners.claimed_at',
                'raffle_winners.person_id',
                'persons.first_name',
                'persons.last_name',
                'persons.company',
            ])
            ->get();
    }

    /**
     * A seeded shuffle rather than shuffle() or random_int(), so the seed in the audit record
     * is enough to reproduce the result. The hash chain gives each candidate a deterministic
     * sort key that depends on both the seed and the person, and sorting by it is the draw.
     *
     * @param  Collection<int, int>  $pool
     * @return Collection<int, int>
     */
    private function pickWinners(Collection $pool, int $count, string $seed): Collection
    {
        return $pool
            ->sortBy(static fn (int $personId): string => hash('sha256', $seed.':'.$personId))
            ->take($count)
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function eligiblePersonIds(object $raffle, bool $applyExclusions): Collection
    {
        $entries = $this->databaseManager->table('access_logs')
            ->where('access_logs.event_id', $raffle->event_id)
            ->where('access_logs.direction', 'ENTRY')
            ->whereIn('access_logs.result', [
                AccessResult::GRANTED->value,
                AccessResult::GRANTED_OVERRIDE->value,
            ])
            ->whereBetween('access_logs.occurred_at', [
                $raffle->eligibility_window_start,
                $raffle->eligibility_window_end,
            ])
            ->whereNotNull('access_logs.person_id')
            ->when(
                $raffle->zone_id !== null,
                static fn ($query) => $query->where('access_logs.zone_id', $raffle->zone_id)
            )
            ->distinct()
            ->pluck('access_logs.person_id')
            ->map(static fn ($id): int => (int) $id);

        if (! $applyExclusions) {
            return $entries;
        }

        $excluded = collect();

        if ($raffle->exclude_exhibitors) {
            $excluded = $excluded->merge($this->exhibitorStaffPersonIds((int) $raffle->event_id));
        }

        if ($raffle->exclude_staff) {
            $excluded = $excluded->merge($this->staffPersonIds((int) $raffle->event_id));
        }

        return $entries->diff($excluded->unique())->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function exhibitorStaffPersonIds(int $eventId): Collection
    {
        return $this->databaseManager->table('exhibitor_staff')
            ->join('event_exhibitors', 'event_exhibitors.id', '=', 'exhibitor_staff.event_exhibitor_id')
            ->where('event_exhibitors.event_id', $eventId)
            ->whereNotNull('exhibitor_staff.person_id')
            ->whereNull('exhibitor_staff.deleted_at')
            ->distinct()
            ->pluck('exhibitor_staff.person_id')
            ->map(static fn ($id): int => (int) $id);
    }

    /**
     * Staff hold a credential issued against an accreditation rather than a ticket. The
     * credentials table enforces exactly one of the two, so the accreditation link is the
     * schema-backed test for somebody who is working rather than attending.
     *
     * @return Collection<int, int>
     */
    private function staffPersonIds(int $eventId): Collection
    {
        return $this->databaseManager->table('credentials')
            ->where('credentials.event_id', $eventId)
            ->whereNotNull('credentials.person_id')
            ->whereNotNull('credentials.accreditation_id')
            ->distinct()
            ->pluck('credentials.person_id')
            ->map(static fn ($id): int => (int) $id);
    }

    /**
     * Confirms a raffle belongs to the event before anything is read or changed through it,
     * because the id arrives in the URL and the event is what authorization was checked on.
     *
     * @throws ResourceConflictException
     */
    public function assertBelongsToEvent(int $raffleId, int $eventId): void
    {
        $exists = $this->databaseManager->table('raffles')
            ->where('id', $raffleId)
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw new ResourceConflictException(__('That raffle could not be found.'));
        }
    }

    /**
     * @return Collection<int, object>
     */
    public function listForEvent(int $eventId): Collection
    {
        return $this->databaseManager->table('raffles')
            ->leftJoin('raffle_draws', 'raffle_draws.raffle_id', '=', 'raffles.id')
            ->where('raffles.event_id', $eventId)
            ->whereNull('raffles.deleted_at')
            ->orderByDesc('raffles.created_at')
            ->select([
                'raffles.id',
                'raffles.short_id',
                'raffles.name',
                'raffles.prize_description',
                'raffles.eligibility_window_start',
                'raffles.eligibility_window_end',
                'raffles.zone_id',
                'raffles.winner_count',
                'raffles.exclude_staff',
                'raffles.exclude_exhibitors',
                'raffles.status',
                'raffles.created_at',
                'raffle_draws.id as draw_id',
                'raffle_draws.eligible_pool_size',
                'raffle_draws.drawn_at',
            ])
            ->get();
    }

    /**
     * @throws ResourceConflictException
     */
    private function findRaffle(int $raffleId): object
    {
        $raffle = $this->databaseManager->table('raffles')
            ->where('id', $raffleId)
            ->whereNull('deleted_at')
            ->first();

        if ($raffle === null) {
            throw new ResourceConflictException(__('That raffle could not be found.'));
        }

        return $raffle;
    }
}
