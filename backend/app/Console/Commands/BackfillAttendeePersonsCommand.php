<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Links attendees to a person record, and repairs installations where the original backfill
 * skipped rows.
 *
 * The migration that first did this paginated with chunk() while setting the column it
 * filtered on, so processed rows left the result set and the offset stepped past the ones that
 * shifted down — roughly every other batch was skipped. Anywhere that migration has already
 * run, attendees are silently missing their link, and the subsystems built on persons
 * (accreditation, credentials, access logs) have nothing to attach to for those people.
 *
 * Safe to run repeatedly: it only ever considers attendees with no link, and matches an
 * existing person before creating one.
 *
 * @see docs/arzo-master-plan/136-master-backlog.md ARZ-301
 */
class BackfillAttendeePersonsCommand extends Command
{
    protected $signature = 'backfill:attendee-persons
        {--chunk=500 : Rows per batch}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Link attendees to person records, repairing any the original backfill skipped';

    private const UNKNOWN_FIRST_NAME = 'Unknown';

    public function handle(DatabaseManager $databaseManager): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $outstanding = $databaseManager->table('attendees')
            ->whereNull('person_id')
            ->whereNull('deleted_at')
            ->count();

        if ($outstanding === 0) {
            $this->info('Every attendee already has a person record.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d attendee(s) have no person record.', $outstanding));

        if ($dryRun) {
            $this->line('Dry run: nothing written.');

            return self::SUCCESS;
        }

        $linked = 0;
        $created = 0;

        $databaseManager->table('attendees')
            ->join('events', 'events.id', '=', 'attendees.event_id')
            ->whereNull('attendees.person_id')
            ->whereNull('attendees.deleted_at')
            ->select([
                'attendees.id',
                'attendees.first_name',
                'attendees.last_name',
                'attendees.email',
                'events.account_id',
            ])
            ->chunkById(
                count: $chunkSize,
                callback: function ($attendees) use ($databaseManager, &$linked, &$created): void {
                    foreach ($attendees as $attendee) {
                        $personId = $this->findPerson($databaseManager, $attendee);

                        if ($personId === null) {
                            $personId = $this->createPerson($databaseManager, $attendee);
                            $created++;
                        }

                        $databaseManager->table('attendees')
                            ->where('id', $attendee->id)
                            ->update(['person_id' => $personId]);

                        $linked++;
                    }
                },
                column: 'attendees.id',
                alias: 'id',
            );

        $remaining = $databaseManager->table('attendees')
            ->whereNull('person_id')
            ->whereNull('deleted_at')
            ->count();

        $this->info(sprintf('Linked %d attendee(s), creating %d person record(s).', $linked, $created));

        if ($remaining > 0) {
            $this->error(sprintf('%d attendee(s) are still unlinked.', $remaining));

            return self::FAILURE;
        }

        $this->info('No attendee is left without a person record.');

        return self::SUCCESS;
    }

    /**
     * Exact match on email within the account. Fuzzy identity resolution is its own project
     * and a privacy hazard, so an attendee with no email gets their own record rather than
     * being guessed at.
     */
    private function findPerson(DatabaseManager $databaseManager, object $attendee): ?int
    {
        if (empty($attendee->email)) {
            return null;
        }

        $personId = $databaseManager->table('persons')
            ->where('account_id', $attendee->account_id)
            ->whereRaw('lower(email) = ?', [Str::lower((string) $attendee->email)])
            ->whereNull('deleted_at')
            ->value('id');

        return $personId !== null ? (int) $personId : null;
    }

    private function createPerson(DatabaseManager $databaseManager, object $attendee): int
    {
        return (int) $databaseManager->table('persons')->insertGetId([
            'short_id' => 'pn_'.Str::lower(Str::random(20)),
            'account_id' => $attendee->account_id,
            'first_name' => $attendee->first_name ?: self::UNKNOWN_FIRST_NAME,
            'last_name' => $attendee->last_name,
            'email' => $attendee->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
