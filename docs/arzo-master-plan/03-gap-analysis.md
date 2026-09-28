# Gap Analysis

**Status:** WRITTEN · **Authority:** Derived from `02-current-state-audit.md` · **Audit date:** 2026-09-28

---

## Method

Gaps are grouped by **root cause**, not by feature name. Two dozen "missing features" collapse into
five causes, and fixing a cause unblocks everything under it. A feature-by-feature list would imply
the work is parallelizable; it is not.

## Cause 1 — No model of SPACE

`MISSING`. No zone, venue-interior, room, booth, seat, or access-point entity. `locations` is a flat
geocoded address.

Blocks: zone access control · seating · exhibitor booths · lead capture by location ·
anti-passback · per-door queue measurement · venue heatmaps · the command-center map.

Fix: `25-zones-and-permissions.md`. Additive; nothing existing changes.

## Cause 2 — No model of TIME-WITHIN-EVENT

`MISSING`. No session, track, speaker, or agenda. `event_occurrences` is RRULE **event repetition**,
not programme.

Blocks: agenda · session check-in · session capacity/waitlist · speaker management ·
per-session analytics · the attendee app (agenda is its spine) · networking/meetings ·
engagement scoring.

Fix: `27-sessions-tracks.md`. Additive.

## Cause 3 — No identity for non-buyers

`PARTIAL`. `attendees` is order-bound; `users` are platform logins. A journalist, contractor,
security guard, speaker, or exhibitor's booth staff has no representation.

`CONFIRMED`: `attendees` has only **3 inbound FKs** — it is a leaf, not a hub, so hanging
credentials off it would not scale.

Blocks: accreditation · credentials · badges · staff assignment · exhibitor staff passes.

Fix: `persons` + `credentials` in `23-accreditation.md`. Extends `attendees` with a nullable
`person_id`; does not replace it.

## Cause 4 — No offline capability, and no realtime transport

`MISSING` on both. Verified: no service worker, no IndexedDB, no outbox, `networkMode: "always"`;
`broadcasting.php` is Laravel's untouched stub with no driver configured.

Blocks: reliable door operation · kiosks · badge-on-demand · the command center · device fleet
management.

**This is also a live bug, not only a gap** — see F1 in the audit: an offline scan is silently
discarded and the dedupe guard then blocks the retry.

Fix: `71-realtime-architecture.md`. New infrastructure.

## Cause 5 — Authorization and tenancy rest on discipline

`PARTIAL`. Three roles with no granularity; the role gate is a **no-op** for the default
`ORGANIZER` level; no policies; no global scopes; child resources scoped by parent only; tenant id
held in **static mutable state** on the `User` model; only 7 of 268 Actions have a Feature test.

Blocks: per-event staff roles · scanner-only identity · accreditation approval delegation ·
exhibitor portal · API scopes · anything multi-party.

Plus it is a **present security risk**: one omitted `isActionAuthorized` call is a cross-tenant read,
with no second line of defence.

Fix: `09-permissions-and-roles.md` plus architecture tests. Replace.

## Secondary gaps

Real work, but none blocks anything structural:

| Gap | State | Doc |
|---|---|---|
| Badge printing | `MISSING` — prints tickets via `window.print()` | `21`, `22` |
| SMS | `MISSING` — no provider | `43` |
| Push | `MISSING` | `44` |
| Public API | `MISSING` — table exists, unused | `48` |
| CRM | `MISSING` | `47` |
| RSVP as a distinct flow | `MISSING` | `12` |
| Email segmentation | `PARTIAL` — 5 fixed audiences | `42` |
| Kiosk | `MISSING` | `19` |
| Queue management | `MISSING` — one throughput metric | `20` |
| Payment providers | `PARTIAL` — Stripe + offline only | `15` |
| Demographics | `MISSING` | `52` |
| Frontend tests | `MISSING` — no runner | `79` |

## Known defects, independent of the roadmap

Fixable now, and they reduce live risk. None depends on the space/time work.

| # | Defect | Severity |
|---|---|---|
| F1 | Offline scans lost; dedupe blocks retry | **High** — live event risk |
| F10 | Two check-in write models; dashboard path fires no webhook | High |
| F11 | Static tenant state; no global scopes; no defence in depth | High |
| F12 | Role gate no-op at default level; `/admin` enforced per-action | High |
| F5 | SSR query client and axios defaults are cross-request singletons | High — possible data bleed |
| F6 | Redundant `process.env` define (claim corrected — low) | Low |
| F13 | `occurrences` queue unconsumed in prod | Medium — armed by a config change |
| F14 | Webhook payloads coupled to API resources | Medium |
| F7 | Dev/prod/e2e queue divergence; Postgres 15 vs 17 | Medium |
| F8 | Printing cannot support badges | Medium |
| F9 | No frontend tests; one check-in E2E spec | Medium |

## Bottom line

ARZO is a **high-quality implementation of roughly one third** of the target platform. The commerce
third is genuinely strong and should not be rewritten.

The remaining two thirds are not blocked by effort — they are blocked by **five missing
foundations**. Build those first and each feature becomes ordinary work. Skip them and every feature
becomes a workaround that later has to be undone.

Separately, the eleven defects above belong on their own track. They are not architecture; they are
things that will bite at the next real event.

## Related

`02-current-state-audit.md` · `113-roadmap.md` · `120-risk-register.md` · `136-master-backlog.md`
