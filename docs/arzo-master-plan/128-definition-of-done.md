# Definition of Done — What "100%" Means

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for completion · **Audit date:** 2026-09-29 (waiver rules tightened; first written 2026-09-28) · **Baseline:** `develop` @ `e7228c1d`

---

## Why this is strict

"It works on my machine" and "the UI exists" are the two failure modes this document prevents.
Scored honestly, **no subsystem in ARZO is at 100% today** — including the mature ones — because
observability, documented operational readiness, and edge-case handling are almost never complete.

That is the point. A standard nothing currently meets is a standard that still means something.

## The 19 gates

A feature is done when **every** line is satisfied, or explicitly waived with a recorded reason.

| # | Gate | Satisfied when |
|---|---|---|
| 1 | **Database** | Migration reviewed; indexes match real query patterns; constraints enforce invariants; rollback tested |
| 2 | **Backend** | Action → Handler → Service → Repository respected; no Eloquent above repositories |
| 3 | **Business logic** | Rules live in domain services, not handlers or actions; unit-tested in isolation |
| 4 | **API** | Documented in OpenAPI; versioned; paginated and filterable where it returns collections |
| 5 | **Frontend** | Every state handled: loading, empty, error, partial, offline, permission-denied |
| 6 | **Validation** | Server-side authoritative; client mirrors for UX; boundaries tested |
| 7 | **Authorization** | Every endpoint authorizes; cross-tenant access **tested negatively**; per-event scope where applicable |
| 8 | **Error handling** | Custom exceptions, not generic; converted at the action boundary; no stack traces to users |
| 9 | **Edge cases** | Concurrency, duplicates, partial failure, clock skew, empty and maximum inputs |
| 10 | **Security** | Threat-modelled; input sanitized; secrets never logged; rate-limited where abusable |
| 11 | **Auditability** | State transitions recorded: who, when, from → to, why |
| 12 | **Testing** | Unit + integration + E2E for the real journey; offline and hardware paths where relevant |
| 13 | **Performance** | Measured against a stated target, not assumed |
| 14 | **Accessibility** | Keyboard operable; labelled; contrast checked; screen-reader sane |
| 15 | **Localization** | Strings translatable and translated for supported locales; RTL considered |
| 16 | **Documentation** | Master-plan doc updated; operator docs where behaviour is non-obvious |
| 17 | **Monitoring** | Emits metrics and logs sufficient to diagnose it at 2am |
| 18 | **Deployment** | Ships via the normal pipeline; migration ordering safe; feature-flagged if risky |
| 19 | **Operational readiness** | Runbook entry; failure modes documented; **recovery procedure tested** |

## Additional gates for on-site features

Anything used at a live event — check-in, badges, access control, kiosks — must **also** satisfy:

| # | Gate |
|---|---|
| 20 | Works offline, or degrades in a way the operator is explicitly told about |
| 21 | **No silent data loss under any network condition** |
| 22 | Idempotent under replay — a repeated scan cannot double-count |
| 23 | Recoverable by a non-engineer, on site, under time pressure |
| 24 | Tested against real hardware, not only emulation |

Gate 21 is non-negotiable and is the direct lesson of finding F1, where the current check-in surface
discards a scan when the network drops. Phase 0 exists to close it.

## Scoring scale

Used by `02-current-state-audit.md` and `140-final-100-percent-checklist.md`:

| Score | Meaning |
|---|---|
| 0% | Nonexistent |
| 25% | Proof of concept — happy path, dev only |
| 50% | Partial — works; gaps across gates 6–12 |
| 75% | Mostly complete — gates 1–12 largely met, 13–19 thin |
| 90% | Production-capable — all gates met, minor gaps documented |
| 100% | Every applicable gate satisfied or explicitly waived |

## Waivers

A gate may be waived deliberately, in writing, with the reason and accepted risk recorded in the
feature's work package. **An undocumented gap is a defect; a documented one is a decision.**

**Two gates cannot be waived** (added 2026-09-29, adopting `127`): gate **7** — authorization and
negative cross-tenant tests — and gate **21** — no silent data loss. A waiver of either is a defect
in the review, not a decision. Waivers of other gates expire at the next phase boundary and are
re-decided, so a temporary exception cannot become permanent by being forgotten (`127`).

A gate enforced by CI is only enforced if CI runs. Until ARZO's code has its own repository and
pipeline (ARZ-300, `123`), every "CI" gate here is satisfied by a recorded manual run.

A legitimate example: waiving gate 24 for a kiosk feature during Phase 3 because hardware has not
been procured, recording the residual risk that first hardware contact will surface issues.

## Related

`129-quality-gates.md` (CI enforcement) · `138-acceptance-criteria.md` ·
`139-test-matrix.md` · `140-final-100-percent-checklist.md` · `02-current-state-audit.md`
