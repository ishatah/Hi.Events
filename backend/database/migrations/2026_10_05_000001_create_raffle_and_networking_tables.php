<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raffles, networking profiles, connections and meetings.
     *
     * Participants are persons rather than attendees throughout: exhibitor staff are persons
     * without tickets, and the attendee-to-exhibitor meeting is the valuable case, so keying
     * on attendee_id would exclude exactly the meetings somebody paid for.
     *
     * @see docs/arzo-master-plan/31-networking.md
     */
    public function up(): void
    {
        $this->createRaffleTables();
        $this->createNetworkingTables();
        $this->createMeetingTables();
    }

    private function createRaffleTables(): void
    {
        Schema::create('raffles', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('prize_description', 500)->nullable();

            // The pool is everybody who actually entered during this window, read from the
            // access log rather than from a ticket list: being sold a ticket is not the same
            // as turning up, and a raffle drawn from ticket holders would award prizes to
            // people who stayed home.
            $table->timestampTz('eligibility_window_start');
            $table->timestampTz('eligibility_window_end');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            $table->unsignedInteger('winner_count')->default(1);
            $table->boolean('exclude_staff')->default(true);
            $table->boolean('exclude_exhibitors')->default(true);
            $table->string('status', 24)->default('DRAFT');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });

        DB::statement("
            ALTER TABLE raffles
            ADD CONSTRAINT raffles_status_valid
            CHECK (status IN ('DRAFT', 'OPEN', 'DRAWN', 'CANCELLED'))
        ");

        DB::statement('
            ALTER TABLE raffles
            ADD CONSTRAINT raffles_window_ordered
            CHECK (eligibility_window_end > eligibility_window_start)
        ');

        Schema::create('raffle_draws', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('raffle_id')->constrained('raffles')->cascadeOnDelete();

            // A contested prize with no record is a reputational incident, so the draw records
            // everything needed to reproduce it: the pool it drew from, the seed it used, who
            // ran it and when. Anyone can then verify the same seed picks the same winners.
            $table->unsignedInteger('eligible_pool_size');
            $table->string('random_seed', 64);
            $table->unsignedBigInteger('drawn_by_user_id')->nullable();
            $table->timestampTz('drawn_at');
            $table->jsonb('excluded_counts')->nullable();
            $table->timestamps();

            $table->foreign('drawn_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('raffle_winners', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('raffle_draw_id')->constrained('raffle_draws')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 24)->default('DRAWN');
            $table->timestampTz('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['raffle_draw_id', 'position']);
        });

        DB::statement("
            ALTER TABLE raffle_winners
            ADD CONSTRAINT raffle_winners_status_valid
            CHECK (status IN ('DRAWN', 'NOTIFIED', 'CLAIMED', 'FORFEITED'))
        ");
    }

    private function createNetworkingTables(): void
    {
        Schema::create('networking_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();

            // Opt-in, never default-on. A directory that lists people who did not ask to be
            // listed is a privacy incident whatever the setting is called.
            $table->boolean('is_discoverable')->default(false);
            $table->boolean('share_contact_on_connect')->default(false);

            $table->string('headline', 191)->nullable();
            $table->jsonb('interests')->nullable();
            $table->jsonb('looking_for')->nullable();
            $table->timestampTz('opted_in_at')->nullable();
            $table->timestampTz('opted_out_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'is_discoverable']);
        });

        DB::statement('
            CREATE UNIQUE INDEX networking_profiles_event_person_unique
            ON networking_profiles (event_id, person_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('requester_person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('recipient_person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('status', 16)->default('PENDING');
            $table->string('source', 16)->default('REQUEST');
            $table->string('note', 280)->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'recipient_person_id', 'status']);
        });

        // Keyed on the unordered pair, so A requesting B and B requesting A are one
        // connection rather than two halves of one that can disagree about its status.
        DB::statement('
            CREATE UNIQUE INDEX connections_event_pair_unique
            ON connections (
                event_id,
                LEAST(requester_person_id, recipient_person_id),
                GREATEST(requester_person_id, recipient_person_id)
            )
        ');

        DB::statement("
            ALTER TABLE connections
            ADD CONSTRAINT connections_status_valid
            CHECK (status IN ('PENDING', 'ACCEPTED', 'DECLINED', 'BLOCKED'))
        ");

        DB::statement('
            ALTER TABLE connections
            ADD CONSTRAINT connections_not_self
            CHECK (requester_person_id <> recipient_person_id)
        ');

        Schema::create('networking_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('blocker_person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('blocked_person_id')->constrained('persons')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'blocker_person_id', 'blocked_person_id'], 'networking_blocks_unique');
        });
    }

    private function createMeetingTables(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 32)->unique();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->nullable()
                ->constrained('event_occurrences')->nullOnDelete();
            $table->foreignId('event_exhibitor_id')->nullable()
                ->constrained('event_exhibitors')->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();

            // Lounge tables are informal, so this is a label checked in the application
            // rather than a room with an exclusion constraint.
            $table->string('location_label', 191)->nullable();

            $table->string('status', 16)->default('REQUESTED');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'starts_at']);
            $table->index(['event_exhibitor_id', 'status']);
        });

        DB::statement("
            ALTER TABLE meetings
            ADD CONSTRAINT meetings_status_valid
            CHECK (status IN (
                'REQUESTED', 'CONFIRMED', 'DECLINED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'
            ))
        ");

        DB::statement('
            ALTER TABLE meetings
            ADD CONSTRAINT meetings_ends_after_starts
            CHECK (ends_at > starts_at)
        ');

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('role', 16)->default('INVITEE');
            $table->string('response', 16)->default('PENDING');
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'person_id']);
        });

        DB::statement("
            ALTER TABLE meeting_participants
            ADD CONSTRAINT meeting_participants_role_valid
            CHECK (role IN ('REQUESTER', 'INVITEE', 'HOST'))
        ");

        DB::statement("
            ALTER TABLE meeting_participants
            ADD CONSTRAINT meeting_participants_response_valid
            CHECK (response IN ('PENDING', 'ACCEPTED', 'DECLINED'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('networking_blocks');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('networking_profiles');
        Schema::dropIfExists('raffle_winners');
        Schema::dropIfExists('raffle_draws');
        Schema::dropIfExists('raffles');
    }
};
