# Risk Register

**Status:** WRITTEN · **Audit date:** 2026-09-29 (R1–R3 updated, R21–R32 added; first written 2026-09-28) · **Baseline:** `develop` @ `e7228c1d`

---

Scored **likelihood x impact**. Ordered by severity. Risks marked **LIVE** exist today, independent
of the roadmap.

## Critical

### R1 — Licence terms and the commercial model · LIVE
**L: high · I: critical** — *raised 2026-09-29 on evidence*

`CONFIRMED`: the codebase is AGPL-3.0 **with an additional term under §7(b)** — the "Powered by
Hi.Events" attribution must be retained on every page and email, linking to hi.events, unless a
commercial licence is bought (`LICENCE:1-8`). Network-use copyleft also obliges offering corresponding
source to users of the service.

Two findings turn this from a question into a defect (`98`, `66`):

- **The uncommitted rebrand suppresses the required web footer.** It sets
  `VITE_I_HAVE_PURCHASED_A_LICENCE=false`; the check returns the raw string, and `"false"` is truthy,
  so the footer disappears (`helpers.ts:160-162`, `PoweredByFooter/index.tsx:24`). The email footer
  keeps a compliant rephrasing.
- **Nothing in the UI offers ARZO's modified source** to users.

The commercial licence has a published price (`98`, checked 2026-09-29) — which makes this cheap to
resolve.

**Mitigation:** decide now between buying the appropriate commercial licence and complying with
attribution plus source offer; fix the string-parsing bug either way (ARZ-317). Take legal advice on
the licence terms before signing. **Owner:** business.

### R2 — Cross-tenant data leak · LIVE
**L: medium · I: critical**

`CONFIRMED` (F11): no Eloquent global scopes; child resources scoped by parent only; tenant id in
static mutable state on the `User` model; isolation rests on 159 hand-written checks with **no
cross-tenant test**. One omitted call is a full cross-tenant read, with no defence in depth.

**Mitigation:** Phase 0 — cross-tenant test suite, architecture test for missing authorization,
tenant global scope. All three, not one.
**Owner:** engineering.

**Update 2026-09-29:** the suite (20 cases) and the architecture test exist — and run in no CI (R21).
The suite covers none of the 31 new entities, and the in-progress scan path can write logs against
another account's access point (ARZ-321). The global scope (ARZ-013) is not started.

### R3 — Event-day data loss · LIVE
**L: high · I: high**

`CONFIRMED` (F1): an offline scan is silently discarded and the dedupe guard then blocks the retry.
At a real event this admits people without recording them, unfixably by staff.

**Mitigation:** Phase 0 fix (small, isolated). Full resolution is `71`.
**Owner:** engineering.

**Update 2026-09-29:** F1 is fixed (ARZ-001) — the operator is now told to rescan. Nothing is queued,
so the risk has moved from *silent* loss to *disclosed* non-recording; it closes only with `71`.

### R21 — ARZO's code exists in one place and is gated by nothing · LIVE
**L: high · I: critical**

`CONFIRMED`: the only git remote is the public upstream repository, which ARZO cannot push to;
14 commits — the plan, Phase 0 fixes, Phase 1–2 schema, the access engine — plus a large uncommitted
working tree exist on one workstation (`123`). No CI has ever run ARZO's code; no change is reviewed
before landing.

**Mitigation:** ARZ-300 — private repository, CI, branch protection. An hour's work. **Owner:** engineering.

### R22 — The payment processor may be unavailable to ARZO · LIVE
**L: high · I: critical**

Stripe's global availability page does not list Qatar (checked 2026-09-29 by `98` and `135`), and
self-serve cross-border payouts reach only a handful of regions. If confirmed, ARZO cannot be merchant
of record through its only integrated card processor, and SaaS fees — collected only as Stripe
application fees — cannot work for Qatar organizers.

**Mitigation:** confirm with Stripe; choose a Qatar-licensed gateway (ARZ-191, raised to P1); offline
and invoice payment for B2B in the meantime. **Owner:** business + engineering.

### R23 — Personal data escapes deletion · LIVE on first data-bearing deploy
**L: high · I: high**

`CONFIRMED`: the backfill copies attendee names and emails into `persons`, and no anonymizer touches
`persons`, `credentials`, `badges`, `access_logs` or `invitations` (`108`). ID document fields would
be plaintext (`65`). A deletion request would leave personal data behind.

**Mitigation:** ARZ-323, ARZ-303 before any environment holds real data. **Owner:** engineering.

## High

### R4 — Offline complexity is underestimated
**L: high · I: high**

Offline-first changes every write path it touches, requires the access decision function to be
provably identical on server and device, and needs network-partition, clock-skew and replay testing
that does not exist today.

**Mitigation:** Phase 4 sized XL deliberately. Prototype the sync protocol early, before committing
to kiosk and scanner dates. Do not let a demo of the happy path be mistaken for the work.

### R5 — Hardware procurement not committed
**L: medium · I: high**

Phases 2 and 4 are not deliverable on software alone. Printer and RFID lead times can exceed the
software timeline.

**Mitigation:** `102` names what is needed. Decide buy/rent/partner before Phase 2 exit. Vendor
choice also drives `37`–`39` adapter work, so it gates engineering too.
**Owner:** business.

### R6 — Privacy exposure from new data classes
**L: medium · I: high**

The plan adds photos, ID document numbers, nationality, date of birth, movement history across
zones, and lead transfers to exhibitors. Qatar PDPL and GDPR both apply. Devices carry full rosters.

**Mitigation:** `65` — lawful basis and retention per data class; encryption at rest on devices;
consent at lead-capture time; aggregate movement data by default. DPIA for anything biometric.
**Owner:** business + engineering.

### R7 — RBAC migration regresses authorization
**L: medium · I: high**

Replacing the role model touches 159 call sites, and the current model is effectively untested
(7 of 268 Actions).

**Mitigation:** sequence strictly after the Phase 0 test suite; run policies and the old check in
parallel and assert agreement before removing either.

### R8 — Revoked credential still opens an offline door
**L: medium · I: high**

A disconnected device cannot know about a revocation until it syncs. This is inherent, not a bug.

**Mitigation:** short sync intervals, prioritized deny-list deltas, printed deny-list at supervised
points for high-security events. **State the residual risk to clients explicitly** rather than
implying real-time revocation.

## Medium

### R9 — Scope breadth outruns capacity
**L: high · I: medium**

141 documents and six phases. `UNVERIFIED`: team size is unknown to this plan.

**Mitigation:** the roadmap is dependency-ordered so partial delivery still yields a coherent
product. Phase 1 alone is valuable. Phases 2 and 3 are parallel if capacity exists.

### R10 — Webhook breaking changes for integrators
**L: medium · I: medium**

`CONFIRMED` (F14): payloads use the same resources as REST responses.

**Mitigation:** versioned payload transformers land **with** the API platform (`48`), not after.

### R11 — SSR cross-request state leak · LIVE
**L: low · I: high**

`CONFIRMED` (F5): module-level SSR query client and axios auth defaults mutated across an `await`.
Under concurrency, one request can serve another's data.

**Mitigation:** Phase 0 — `AsyncLocalStorage`. Verify under concurrent load, since the impact is
severe and the likelihood is load-dependent.

### R12 — `VITE_` prefix means "shipped to the browser" · LIVE
**L: low · I: low** — *downgraded 2026-09-28 after testing*

Originally filed as "whole build env inlined". **Testing disproved that** (see F6, corrected): a
canary secret did not appear in the bundle, because Rollup drops unreferenced keys. Only keys the code
reads are inlined, and for `VITE_*` that is Vite's own `import.meta.env` behaviour, not the `define`.

Real residual risk: any secret mistakenly given a `VITE_` prefix **will** ship to the browser.

**Mitigation:** the redundant `define` was removed (ARZ-003, done). Add a CI check asserting no
secret-bearing name carries a `VITE_` prefix.

### R13 — `occurrences` queue silently unconsumed
**L: low · I: medium**

`CONFIRMED` (F13): works only because the env var is unset. Setting it in production strands all
recurring-event work.

**Mitigation:** Phase 0 config alignment.

### R14 — Vendor lock-in through hardware SDKs
**L: medium · I: medium**

**Mitigation:** `37` defines interfaces by ARZO's needs, not by a vendor's SDK shape, with fakes for
testing. The discipline is to write the interface before reading the SDK.

### R15 — Documentation drift
**L: high · I: medium**

141 documents decay the moment code lands without them moving.

**Mitigation:** `129` makes the sync a merge gate. `00` lists exactly which documents a feature must
touch. Accept that scaffolds will lag; the authoritative set must not.

## Low

| # | Risk | Mitigation |
|---|---|---|
| R16 | Postgres version skew (15 dev vs 17 prod) | Phase 0 pin |
| R17 | Realtime transport unproven on Laravel 13 | Spike before committing; SSE fallback |
| R18 | `BaseRepository` latent state leakage | Targeted audit of subclass custom queries |
| R19 | Arabic/RTL larger than estimated | Treat as its own project (`135`) |
| R20 | Kiosk accessibility obligations | `81` review before public deployment |

## Added 2026-09-29 — High and Medium

| # | Risk | L · I | Evidence | Mitigation |
|---|---|---|---|---|
| R24 | **Tests that cannot express the property** — 35 passing decision tests, yet rules ignore their subject; golden vectors would copy the bug to every device | M · H | `124` ST1 | ARZ-320; review fixtures for expressiveness, not only count (`129`) |
| R25 | **Schema outruns authorization** — Phase 2 built before RBAC; the review UI will be tempting to ship on account-wide access | M · H | `115` | ARZ-011/012 as a hard gate for ARZ-051 (`119`) |
| R26 | **Migrations proven only on empty data** — the `persons` backfill skips half its rows at scale | H · H | `122` | Key-paged backfills, rehearsal on production-shaped data (ARZ-301) |
| R27 | **The scan path does not scale** — occupancy recomputed per scan over the zone's history | H · H | `74`, `75` | ARZ-307; `125` scenario 1 |
| R28 | **Venue scanners share one per-IP rate limit** — roughly 45–180 scans/min per venue against a 600/min target | M · H | `75` K2, `125` L2 | Device keys with per-device limits (ARZ-092); interim per-route limit keyed differently (ARZ-309) |
| R29 | **ARZO has no production environment** — the Vapor/DigitalOcean pipeline is upstream's; hosting, backups, monitoring undecided | H · H | `84`, `126` | Decide hosting (`84`) — in-country regions exist on Azure and Google Cloud |
| R30 | **Upstream divergence** — the rebrand edits ~130 upstream files; merges grow costlier and upstream security fixes may be missed | H · M | `121` D2 | Sync cadence; brand values in configuration (`123`) |
| R31 | **Third-party licence exposure in assets** — SF Pro served as a webfont, its own notice says not cleared for web | M · M | `88` D2 | Do not commit the fonts; licensed alternative incl. an Arabic face (ARZ-325) |
| R32 | **Organizer PII to a processor by default** — Sentry receives email, name, IP on every exception; Bunny Fonts receives visitor IPs | H · M | `78`, `84` | ARZ-324, ARZ-310; processor register (`65`) |

## Review

This register is reviewed at each phase boundary, and whenever an audit finding changes a
likelihood. Items marked LIVE are reviewed weekly until closed.

## Related

`02-current-state-audit.md` · `03-gap-analysis.md` · `113-roadmap.md` · `121-technical-debt.md` ·
`64-security.md` · `65-privacy-gdpr.md` · `66-compliance.md` · `98-commercial-model.md` ·
`123-release-strategy.md` · `135-global-expansion.md` · `136-master-backlog.md`
