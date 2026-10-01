<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Backfills so the new space and identity models are usable immediately rather than
     * only for events created from now on.
     *
     * Every venue gets a default GENERAL zone and a RECEPTION_DESK access point, which
     * gives the access-control path somewhere to write from day one and lets current
     * check-in behaviour map onto the new model without a flag day.
     *
     * Batched and idempotent: re-running creates nothing new. These will be interrupted
     * at some point, and a backfill that cannot be resumed is a backfill that gets run
     * by hand at 2am.
     *
     * @see docs/arzo-master-plan/07-database-evolution.md steps 1.2, 1.7, 1.8
     */
    private const BATCH_SIZE = 500;

    public function up(): void
    {
        $this->backfillVenuesFromEventLocations();
        $this->backfillDefaultZonesAndAccessPoints();
        $this->backfillEventVenues();
        $this->backfillPersonsFromAttendees();
    }

    public function down(): void
    {
        // Backfilled rows are indistinguishable from hand-created ones, so this is not
        // reversed. Rolling back the create-table migrations drops them.
    }

    private function backfillVenuesFromEventLocations(): void
    {
        DB::table('locations')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($locations): void {
                foreach ($locations as $location) {
                    $exists = DB::table('venues')
                        ->where('location_id', $location->id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('venues')->insert([
                        'short_id' => 'vn_'.Str::lower(Str::random(20)),
                        'account_id' => $location->account_id,
                        'organizer_id' => $location->organizer_id,
                        'location_id' => $location->id,
                        'name' => $location->name ?: 'Venue',
                        'timezone' => 'UTC',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function backfillDefaultZonesAndAccessPoints(): void
    {
        DB::table('venues')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($venues): void {
                foreach ($venues as $venue) {
                    $zoneId = DB::table('zones')
                        ->where('venue_id', $venue->id)
                        ->where('code', 'GENERAL')
                        ->whereNull('deleted_at')
                        ->value('id');

                    if ($zoneId === null) {
                        $zoneId = DB::table('zones')->insertGetId([
                            'short_id' => 'zn_'.Str::lower(Str::random(20)),
                            'venue_id' => $venue->id,
                            'name' => 'General Admission',
                            'code' => 'GENERAL',
                            'zone_type' => 'GENERAL',
                            'requires_credential' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $hasAccessPoint = DB::table('access_points')
                        ->where('zone_id', $zoneId)
                        ->where('code', 'RECEPTION')
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $hasAccessPoint) {
                        DB::table('access_points')->insert([
                            'short_id' => 'ap_'.Str::lower(Str::random(20)),
                            'zone_id' => $zoneId,
                            'name' => 'Reception Desk',
                            'code' => 'RECEPTION',
                            'direction' => 'BIDIRECTIONAL',
                            'access_point_type' => 'RECEPTION_DESK',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    private function backfillEventVenues(): void
    {
        DB::table('event_locations')
            ->whereNull('deleted_at')
            ->whereNotNull('location_id')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($eventLocations): void {
                foreach ($eventLocations as $eventLocation) {
                    $venueId = DB::table('venues')
                        ->where('location_id', $eventLocation->location_id)
                        ->whereNull('deleted_at')
                        ->value('id');

                    if ($venueId === null) {
                        continue;
                    }

                    $exists = DB::table('event_venues')
                        ->where('event_id', $eventLocation->event_id)
                        ->where('venue_id', $venueId)
                        ->whereNull('event_occurrence_id')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('event_venues')->insert([
                        'event_id' => $eventLocation->event_id,
                        'venue_id' => $venueId,
                        'is_primary' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * One person per distinct (account, lowercased email) pair.
     *
     * Matching is exact on email within an account. Fuzzy identity resolution is its own
     * project and a privacy hazard, so attendees without an email simply get their own
     * person record rather than being guessed at.
     */
    private function backfillPersonsFromAttendees(): void
    {
        DB::table('attendees')
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
            // chunkById, not chunk: the loop sets person_id, which is the column the query
            // filters on, so each processed row leaves the result set. An OFFSET-paginated
            // chunk() then steps past the rows that shifted down and skips roughly every
            // other batch. Keying on the last id seen is immune to the set shrinking.
            ->chunkById(
                count: self::BATCH_SIZE,
                callback: function ($attendees): void {
                    foreach ($attendees as $attendee) {
                        $personId = null;

                        if (! empty($attendee->email)) {
                            $personId = DB::table('persons')
                                ->where('account_id', $attendee->account_id)
                                ->whereRaw('lower(email) = ?', [Str::lower($attendee->email)])
                                ->whereNull('deleted_at')
                                ->value('id');
                        }

                        if ($personId === null) {
                            $personId = DB::table('persons')->insertGetId([
                                'short_id' => 'pn_'.Str::lower(Str::random(20)),
                                'account_id' => $attendee->account_id,
                                'first_name' => $attendee->first_name ?: 'Unknown',
                                'last_name' => $attendee->last_name,
                                'email' => $attendee->email,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('attendees')
                            ->where('id', $attendee->id)
                            ->update(['person_id' => $personId]);
                    }
                },
                // The join makes a bare "id" ambiguous in the keyset comparison, while the
                // selected column arrives unqualified on each row.
                column: 'attendees.id',
                alias: 'id',
            );

        $unlinked = DB::table('attendees')
            ->whereNull('person_id')
            ->whereNull('deleted_at')
            ->count();

        // The backfill is the only thing that writes these links, so anything left is a
        // silent data loss the next migration would build on. Failing here is recoverable;
        // discovering it after credentials have been issued against half the attendees is not.
        if ($unlinked > 0) {
            throw new RuntimeException(
                'persons backfill left '.$unlinked.' attendee(s) without a person_id.'
            );
        }
    }
};
