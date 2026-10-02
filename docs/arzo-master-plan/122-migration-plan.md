# Data Migration Plan

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process + Fix · **Priority:** P0 for the backfill defect, P1 otherwise · **Phase:** every phase
**Depends on:** `07-database-evolution.md` (authoritative for schema order)
**Blocks:** `115-phase-2.md` (credential backfill), `18-check-in.md` (cutover), `123-release-strategy.md`

---

## What this document owns

`07` owns **schema** order. This document owns **data**: every backfill, conversion and cutover, how
each is run, verified and undone. The distinction matters because the two fail differently — a bad
schema migration fails loudly at deploy; a bad backfill succeeds and leaves quietly wrong data.

## Current state

`CONFIRMED`: 160 PHP migrations (149 at the 2026-09-28 baseline, plus 11 ARZO migrations dated
2026-09-29/30). On the dev stack all have run, through batch 9, against a database with **0 attendees,
0 persons and 0 credentials** — so no ARZO backfill has ever run on real data.

| Data migration | Where | State |
|---|---|---|
| Venues from `event_locations` | `2026_09_29_000008` | Ran on empty data. Chunked over `locations`, which it does not modify — correct |
| Default `GENERAL` zone + `RECEPTION_DESK` access point per venue | `000008` | As above — correct |
| `event_venues` from `event_locations` | `000008` | As above — correct |
| **`persons` from attendees** | `000008:163-206` | **Defective** — below |
| Occurrence capacity / cancelled counts | `2026_07_10_000001`, `2026_07_20_000001` | Upstream; empty `down()` |
| Attribution reclassification | `2026_09_05_000001` | Upstream; empty `down()`; calls live application code, so its result depends on the code version that runs it |

### The `persons` backfill skips half the attendees — fix before any deploy with data

```php
DB::table('attendees')
    ->whereNull('attendees.person_id')        // filter on the column...
    ->orderBy('attendees.id')
    ->chunk(500, function ($attendees) {       // ...paged by OFFSET...
        // ...
        DB::table('attendees')->where('id', $attendee->id)
            ->update(['person_id' => $personId]);   // ...that this loop fills in
    });
```

`chunk()` pages with `LIMIT/OFFSET`. After the first batch fills 500 `person_id`s, those rows leave the
filtered set; the second query's `OFFSET 500` then skips the next 500 **unprocessed** rows. Every other
batch is skipped. On a database with 10,000 attendees, roughly 5,000 would be left without a person —
and the migration would report success.

`16` records the backfill as "fully backfilled" and verified by a case-folding test. That verification
was real, and ran on too few rows to cross a batch boundary.

**Fix — two parts, because the migration has already run on dev:**

1. Change the migration to `chunkById(500, ..., 'attendees.id', 'id')`, which pages by key and is
   immune to the filtered set shrinking. Environments that have not yet run it get the correct
   version.
2. Add an idempotent command, `persons:backfill`, running the same logic with `chunkById` over
   `WHERE person_id IS NULL`, ending with an assertion of **zero** null `person_id` on non-deleted
   attendees. Environments that already ran the defective version run the command.

Both belong before the next deploy to any environment holding attendees.

## Principles

`07`'s five principles apply — additive first, no hot-table renames, declared constraints,
idempotent restartable backfills, one concern per migration. Four more, learned from the above:

1. **Schema in migrations; large data in commands.** A migration runs inside the deploy (at container
   start in the all-in-one image; in the deploy step on Vapor). A backfill over a large table there
   holds the deploy hostage to its runtime. Backfills over tables that can be large run as
   resumable, queued artisan commands, invoked explicitly, with progress logging.
2. **Page by key, never by offset**, whenever the loop modifies anything the query filters on.
   `chunkById` or a `WHERE id > :last` cursor. `chunk()` is acceptable only over rows the loop does
   not touch.
3. **Every backfill ends with an assertion**, not a log line. "Zero attendees without a credential"
   is checkable; "backfill complete" is not.
4. **Rehearse on production-shaped data.** Restore an anonymized production snapshot to staging and
   run the backfill there first, timed. The dev database is empty and proves nothing about volume or
   batch boundaries.

## Upcoming data migrations

| # | Migration | Doc | Kind | Risk | Notes |
|---|---|---|---|---|---|
| D1 | `persons` backfill fix + re-run | above | Command | **High until done** | Before any data-bearing deploy |
| D2 | **Credential per non-cancelled attendee** (ARZ-053) | `23`, `115` | Command | Medium — volume | `chunkById`; skip attendees that already have a credential; issuance **without** per-row grant materialization, then a second pass materializing grants in batches — per-credential materialization at < 500 ms × 100k rows is hours |
| D3 | Dual-write start, then `attendee_check_ins` → `access_logs` (`source = IMPORT`) | `18` | Command | Medium | Map each check-in to the venue's default access point; `occurred_at = created_at`; soft-deleted check-ins (check-outs) become `EXIT` rows so history is **recovered**, not dropped |
| D4 | Encrypt `persons.id_document_*`, `date_of_birth` | `65` | Schema + command | Low today — no rows written | Switch to encrypted casts **before** the first accreditation type requires ID; if any rows exist by then, re-encrypt in place |
| D5 | `booths.event_id`, `booth_assignments`; narrow `booths.status` | `35` | Schema | Low — 0 rows | Before any booth service |
| D6 | `rfid_uid`/`nfc_uid` → `credential_media` | `36` | Schema | Low — never written | Drop the columns before first use |
| D7 | `session_attendance.direction` default `IN` → `ENTRY` | `54` | Schema | Low — 0 rows | Before the first row |
| D8 | `device_id` on `access_logs`, `session_attendance`, `badge_print_jobs` | `40` | Schema | Low | Nullable; before devices write |
| D9 | `affiliates.total_sales_gross` float → `numeric(14,2)`; unique `(event_id, code)` | `45` | Schema | **Medium** | Report duplicate codes first — the unique index fails if any exist; decide merges by hand |
| D10 | Encrypt webhook secrets | `49` | Command | Low | Read plaintext, write encrypted; verify a signature before and after |
| D11 | `event_logs` — repair `entity_type` to a string, or retire the table for `audit_events` | `67` | Schema | Low — never written | Retiring is cleaner |
| D12 | Missing FK constraints on high-value legacy columns | `07` §0.1 | Schema | **Medium** — may surface orphans | Run as a report first; `07`'s step 0.1 has no backlog item and has not been done |

D3 is the one genuinely risky sequence in the plan, and `18` owns its steps. The safety property is
unchanged: until reads move to `access_logs`, turning the new path off restores the old behaviour
exactly.

## How each data migration is run

```
1. Written as a command (or migration if schema-only / small), idempotent, key-paged
2. Dry-run mode prints counts it would change
3. Rehearsed on an anonymized production snapshot in staging, timed
4. Run in production outside any client event window (123 freeze)
5. Assertion passes — recorded in the release notes with counts
6. Domain objects regenerated if the schema changed (07)
```

## Reversibility

| Kind | Rollback |
|---|---|
| Additive schema | `down()` drops it — tested in CI by migrating down and up |
| Backfill into new tables | Truncate the new table; the source is untouched |
| Backfill writing to an existing column (`persons` → `attendees.person_id`) | Null the column for rows written by the backfill — which requires knowing which ones: log touched ids to a file, or accept "not reversed" and say so, as `000008`'s `down()` honestly does |
| Conversion of existing data (D9, D10) | Snapshot the affected table first; restore from it |
| Cutover (D3 step "reads move") | A flag, not a migration — flip back |

An empty `down()` is acceptable when stated and reasoned; `000008` does that. An empty `down()` with
no comment — `2026_09_05_000001` — is a silent one-way door.

## Open questions

- **Production data volume.** How many attendees and check-ins exist in the environments that will run D1–D3? Determines whether D2 needs a maintenance window.
- **Do we ever drop `attendee_check_ins`?** Deferred; it is the rollback path until D3 has been stable through several events (`18`).
- **Which environments have already run `000008`?** Any that did, with data, need D1's command, not just the fixed migration.
- **Anonymized snapshots** — is there a process to produce one? If not, it is prerequisite work (`65`, `126`).

## Related

`07-database-evolution.md` · `16-attendee-management.md` · `18-check-in.md` · `23-accreditation.md` ·
`35-booth-management.md` · `36-rfid-nfc.md` · `40-device-management.md` · `45-affiliate-referrals.md` ·
`49-webhooks.md` · `54-attendance-intelligence.md` · `65-privacy-gdpr.md` · `67-audit-logging.md` ·
`115-phase-2.md` · `123-release-strategy.md` · `126-disaster-recovery-plan.md`
