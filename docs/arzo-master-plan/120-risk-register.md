# Risk Register

**Status:** WRITTEN · **Audit date:** 2026-09-28

---

Scored **likelihood x impact**. Ordered by severity. Risks marked **LIVE** exist today, independent
of the roadmap.

## Critical

### R1 — AGPL licensing blocks the commercial model · LIVE
**L: medium · I: critical**

`CONFIRMED`: the codebase is AGPL-3.0. Network-use copyleft means running modified AGPL software as a
service obliges offering corresponding source to users of that service. Every subsystem in this plan,
built inside this codebase, inherits that obligation.

`UNVERIFIED` whether ARZO has taken legal advice.

**Mitigation:** get advice **before Phase 2**, not after five phases of proprietary work are
entangled with AGPL code. A commercial licence from Hi.Events is available. This is cheap to resolve
now and very expensive later.
**Owner:** business.

### R2 — Cross-tenant data leak · LIVE
**L: medium · I: critical**

`CONFIRMED` (F11): no Eloquent global scopes; child resources scoped by parent only; tenant id in
static mutable state on the `User` model; isolation rests on 159 hand-written checks with **no
cross-tenant test**. One omitted call is a full cross-tenant read, with no defence in depth.

**Mitigation:** Phase 0 — cross-tenant test suite, architecture test for missing authorization,
tenant global scope. All three, not one.
**Owner:** engineering.

### R3 — Event-day data loss · LIVE
**L: high · I: high**

`CONFIRMED` (F1): an offline scan is silently discarded and the dedupe guard then blocks the retry.
At a real event this admits people without recording them, unfixably by staff.

**Mitigation:** Phase 0 fix (small, isolated). Full resolution is `71`.
**Owner:** engineering.

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

## Review

This register is reviewed at each phase boundary, and whenever an audit finding changes a
likelihood. Items marked LIVE are reviewed weekly until closed.

## Related

`02-current-state-audit.md` · `03-gap-analysis.md` · `113-roadmap.md` · `121-technical-debt.md` ·
`64-security.md` · `65-privacy-gdpr.md`
