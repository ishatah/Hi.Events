# Phase 1 — Foundation

**Status:** WRITTEN · **Audit date:** 2026-09-28 · **Complexity:** L
**Prerequisite:** Phase 0 complete (`113-roadmap.md`)

---

## Objective

Add the two missing dimensions (space, time), a person identity independent of orders, and a
permission model that can express multi-party access. Almost entirely additive.

**Nothing in Phase 1 is visible to an end user.** That is uncomfortable and correct — it is the
foundation every later phase stands on, and building features first means undoing them later.

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-010 `persons` + `attendees.person_id` | `23` | Extend |
| ARZ-011 RBAC/ABAC replacing the 3-role enum | `09` | Replace |
| ARZ-012 Per-event roles | `09` | New |
| ARZ-013 Tenant global scope | `08` | New |
| ARZ-020..023 Space: venues, zones, access points, rooms | `25` | New |
| ARZ-030..032 Time: tracks, speakers, sessions | `27` | New |
| ARZ-040 `access_logs` append-only | `24` | New |
| ARZ-041 Consolidate the two check-in models | `18` | Refactor |

## Sequence

Ordered by dependency, with the riskiest item deliberately late.

```
1. persons + attendees.person_id            (independent)
2. Space: venues -> zones -> access points -> rooms
3. Time: tracks, speakers -> sessions
4. access_logs table
5. Permission tables + seeded roles matching current behaviour
6. Tenant global scope + cross-tenant verification
7. Policies alongside isActionAuthorized, asserting agreement
8. Check-in consolidation with dual-write
9. Per-event roles
10. Remove isActionAuthorized once no callers remain
```

Steps 2 and 3 are parallelizable. Step 8 depends on 4. Step 10 depends on 7 being complete.

## The risky step

**Step 7 — migrating 159 authorization call sites.**

Controls:
- The Phase 0 architecture test already asserts every non-public Action authorizes, so an omission fails CI.
- The Phase 0 cross-tenant suite already proves isolation, so a regression fails CI.
- Run policies and the legacy check **in parallel**, asserting they agree, before removing either.
- Seeded roles must reproduce today's effective permissions **exactly**, so step 5 is behaviourally a no-op.

If any of those three Phase 0 items is missing, do not start step 7.

## Exit criteria

Verifiable, not aspirational:

| # | Criterion | Verified by |
|---|---|---|
| 1 | A venue can be described with nested zones and access points | Create a 3-zone venue with 5 access points via API |
| 2 | Every existing venue has a default zone + reception access point | Migration assertion |
| 3 | An event can have a programme with rooms, tracks, speakers | Create 10 sessions across 2 tracks and 3 rooms |
| 4 | Room double-booking is refused by the database | Insert overlapping sessions; expect constraint violation |
| 5 | A person exists independently of any order | Create a person with no attendee record |
| 6 | Every existing attendee has a `person_id` | Backfill assertion, zero nulls |
| 7 | `access_logs` records entries **and** exits for the same credential | Two scans, same credential, same point — both persist |
| 8 | Both check-in paths write `access_logs` and emit `checkin.created` | Dual-path test + webhook assertion |
| 9 | Per-event roles restrict correctly | A CHECKIN_OPERATOR on event A is denied on event B |
| 10 | Cross-tenant access is refused, with a test proving it | The Phase 0 suite, extended to new entities |
| 11 | Tenant global scope is active on all new tables | Query without explicit scope returns only own-tenant rows |
| 12 | No new TypeScript or Pint errors | CI |

Criterion 7 is the one that proves F2 is resolved — the old schema physically could not satisfy it.

## Out of scope

Deliberately deferred, to keep the phase honest:

- Accreditation workflow (Phase 2) — the tables exist, the workflow does not
- Badges (Phase 2)
- Access **rules** (Phase 2) — `access_logs` exists; the engine that decides comes later
- Session registration and attendance (Phase 3) — `sessions` exists; registration comes later
- Offline anything (Phase 4)

A reasonable objection: why build `sessions` in Phase 1 if registration is Phase 3? Because the table
shape must be settled before anything hangs off it, and because conflict constraints are cheaper to
add at creation than to retrofit.

## Risks

| Risk | Mitigation |
|---|---|
| RBAC migration regresses authorization (R7) | Phase 0 gates; parallel-run assertion |
| `persons` backfill creates duplicates | Exact email match per account only; never fuzzy (`23`) |
| Zone nesting depth unbounded | Validation cap at 5 |
| Check-in dual-write diverges | Reconciliation test comparing both stores |
| Space/time model wrong for real venues | Validate against a real ARZO venue and a real agenda **before** Phase 2 |

That last one matters most and is cheapest to act on: model one actual venue and one actual past
event before declaring the schema settled.

## Related

`113-roadmap.md` · `115-phase-2.md` · `06-domain-model.md` · `07-database-evolution.md` ·
`09-permissions-and-roles.md` · `25-zones-and-permissions.md` · `27-sessions-tracks.md` ·
`137-engineering-work-packages.md`
