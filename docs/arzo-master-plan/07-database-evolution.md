# Database Evolution

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for schema change order · **Audit date:** 2026-09-28

---

## Starting point — `CONFIRMED`

| Fact | Value |
|---|---|
| Live tables | **72** (`information_schema` query) |
| Migrations | **149** |
| `schema.sql` | The frozen **2020 baseline** — 31 tables, pre-`products` rename, loaded by `2020_01_25_113926_initial_db.php`. **Not** the current schema. |
| Declared FK constraints | **sparse** — roughly 50-60 across ~72 tables |

That last row matters more than it looks. Most `*_id` columns are **unconstrained integers**;
referential integrity lives in application code. Any data-integrity workstream should start there,
and new tables should declare their FKs properly rather than following the existing pattern.

## Principles

1. **Additive first.** Create, backfill, cut over, clean up — in that order, reversible until cutover.
2. **No renames of hot tables.** `events`, `orders`, `products`, `attendees` keep their names.
3. **Declare constraints on new tables.** Do not inherit the sparse-FK habit.
4. **Backfills are idempotent and restartable.** They will be interrupted.
5. **One concern per migration**, so a single revert is meaningful.

## Sequence

### Phase 0 — corrective

| # | Change | Risk |
|---|---|---|
| 0.1 | Add missing FK constraints on high-value columns (validate first, then enforce) | Medium — may reveal existing orphans |
| 0.2 | Index review against real query patterns from the slow log | Low |

Step 0.1 may fail on existing bad data. That is information, not a blocker — run it as a report
first.

### Phase 1 — foundation

| # | Change | Doc | Risk |
|---|---|---|---|
| 1.1 | `persons`; add nullable `attendees.person_id` | `23` | Low |
| 1.2 | Backfill `persons` from distinct attendee (email, name) per account | `23` | Low |
| 1.3 | Permission tables: `permissions`, `role_permissions`, `event_users` | `09` | Medium |
| 1.4 | `venues`, `buildings`, `floors` | `25` | Low |
| 1.5 | `zones` (self-referencing), `access_points` | `25` | Low |
| 1.6 | `rooms` | `25` | Low |
| 1.7 | `event_venues`; backfill from `event_locations` | `25` | Low |
| 1.8 | Default `GENERAL` zone + `RECEPTION_DESK` access point per venue | `25` | Low |
| 1.9 | `tracks`, `speakers` | `27` | Low |
| 1.10 | `sessions` + GiST exclusion on `(room_id, tstzrange(starts_at, ends_at))` | `27` | Low |
| 1.11 | `session_speakers`, `session_products` | `27` | Low |
| 1.12 | `access_logs` (append-only) | `24` | Low |

Step 1.8 is what makes the new access path usable immediately: every existing event gains a zone and
an access point, so dual-write has a destination from day one.

### Phase 2 — accreditation, badges, access

| # | Change | Doc | Risk |
|---|---|---|---|
| 2.1 | `accreditation_types`, `accreditation_type_rules`; seed defaults per event | `23` | Low |
| 2.2 | `accreditations` | `23` | Low |
| 2.3 | `credentials` + one-of CHECK constraint | `23` | Medium |
| 2.4 | Backfill: one ACTIVE credential per non-cancelled attendee | `23` | **Medium** — large, must batch |
| 2.5 | `access_rules`, `access_grants` | `24` | Low |
| 2.6 | `badge_templates`, `badges`, `badge_print_jobs` | `21` | Low |
| 2.7 | Dual-write: check-in also writes `access_logs` | `18` | Medium |
| 2.8 | Backfill `attendee_check_ins` → `access_logs` (`source = IMPORT`) | `18` | Medium |
| 2.9 | Move reads to `access_logs`; keep dual-write | `18` | Medium |

Steps 2.7–2.9 are the only genuinely risky sequence in the plan. The safety property: until 2.9
completes, disabling the new path restores exactly the old behaviour.

### Phase 3 — programme and exhibitors

| # | Change | Doc |
|---|---|---|
| 3.1 | `session_registrations`, `session_attendance` | `27` |
| 3.2 | Extend `waitlist_entries` with nullable `session_id` | `14` |
| 3.3 | `exhibitors`, `exhibitor_staff`, `booths` | `32`, `35` |
| 3.4 | `leads`, `lead_qualifications` | `33` |
| 3.5 | `api_keys`, `api_key_scopes` | `48` |

Step 3.2 is `UNVERIFIED` — read the waitlist offer/expiry service first to confirm it is not too
event-coupled to extend.

### Phase 4 — devices and offline

| # | Change | Doc |
|---|---|---|
| 4.1 | `devices`, `device_sync_state` | `40` |
| 4.2 | `zone_occupancy_snapshots` (materialized cache) | `24` |
| 4.3 | Partition `access_logs` by event or month once volume warrants | `74` |

### Phase 5 — operations

| # | Change | Doc |
|---|---|---|
| 5.1 | `staff_assignments`, `shifts` | `57` |
| 5.2 | `tasks`, `checklists`, `readiness_items` | `58`, `59` |
| 5.3 | `incidents` | `60` |
| 5.4 | `vendors`, `procurement_items`, `assets` | `61`, `62` |

## Table count trajectory

| Stage | Approx. tables |
|---|---|
| Today | 72 |
| After Phase 1 | ~85 |
| After Phase 2 | ~93 |
| After Phase 3 | ~101 |
| After Phase 4 | ~104 |
| After Phase 5 | ~112 |

Roughly +55%. Large, but additive — which is why the risk profile is far better than the count
suggests.

## Domain object regeneration

`CONFIRMED`: `php artisan generate-domain-objects` introspects the **live database** via Doctrine
DBAL, rewrites `DomainObjects/Generated/*Abstract.php` every run, and creates the concrete subclass
only if absent.

Two consequences to respect:

1. It requires a fully migrated database and is **not reproducible from migrations alone**. Run it
   against a correct database or it will silently drop abstracts.
2. A column rename silently changes a domain object's public API. Treat renames as breaking changes.

Run it after every migration, per CLAUDE.md.

## Related

`06-domain-model.md` · `122-migration-plan.md` · `08-multi-tenancy.md` · `74-performance.md`
