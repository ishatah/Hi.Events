# Testing Strategy

**Status:** WRITTEN · **Audit date:** 2026-09-28

---

## Current state — strong backend, zero frontend

`CONFIRMED`:

| Layer | State |
|---|---|
| Backend unit | **204 files**, 1,215 tests, 2,994 assertions. ~164 cover services — where the logic lives. Good. |
| Backend feature | **32 files**. But only **7 of 268 Actions** are covered. |
| Migration tests | 3 — unusual and genuinely good |
| Repository tests | 9, including `BaseRepositoryTest` — important given how much magic lives there |
| OpenAPI contract test | Yes, and strong — asserts spec version, `/admin` absence, public marking, security scheme |
| E2E | **73 specs / 288 tests / 27 @smoke**, worker-scoped fixtures, API-seeded data, assertions kept out of page objects. Well disciplined. |
| **Frontend unit** | **ZERO.** No test runner in `package.json`. |
| **CI frontend gates** | **None.** `lint` and `build-strict:csr` scripts exist; no workflow runs them. |
| Check-in E2E | **1 spec**, covering the search path only |

## The three gaps that matter

### 1. Authorization is untested
With 159 `isActionAuthorized` call sites, no policies, and only 7 Action tests, **nothing proves the
authorization layer works**. This is the highest-leverage gap in the codebase, because F11 and F12 mean
a single omission is a cross-tenant read.

Required:
- **Architecture test:** every non-public Action calls an authorization method. Mechanical, catches omissions forever.
- **Cross-tenant suite:** account A cannot read, update, or delete any of account B's entities. Parameterized over entity types.
- **Role matrix:** each role against each endpoint class, asserting allow and deny.

### 2. Frontend has no net at all
378 components, 59,285 lines, zero tests, no CI gate. The check-in surface — the most operationally
critical code — has one E2E spec that does not touch QR scanning, the USB wedge, the dedupe guard, or
network failure. F1 shipped because nothing would have caught it.

Required: a runner (Vitest), unit tests for `themeUtils`, `calendar`, config resolution and the
check-in state machine, plus lint and typecheck in CI.

### 3. Offline and hardware are untestable today
Neither exists, so neither is tested — but both need test infrastructure designed **before** the
features, or they will be verified by demo rather than by test.

## Strategy by layer

### Unit
Backend: continue the existing service-focused pattern. Frontend: pure functions and state machines
first — highest value per effort. Do not chase component-render coverage.

### Integration
Repositories against real Postgres (pattern exists). Handlers with mocked services. The access
decision function (`24`) needs exhaustive table-driven tests — it is a pure function, which is
precisely why it was specified that way.

### Contract
The OpenAPI test is the model to extend. Add a **webhook payload contract test** so F14 cannot recur
silently — payload shape becomes an asserted interface rather than an accident of resource classes.

### E2E
Extend the existing discipline. Priority additions: accreditation application → approval → credential
→ badge; session registration → check-in; exhibitor lead capture → export; and **check-in beyond the
search path**.

### Offline — new discipline required
This is where systems look fine in dev and fail at the venue. Must cover:

| Scenario | Asserts |
|---|---|
| Network drop mid-scan | Scan is **queued, not lost** (the F1 regression test) |
| Network flap during sync | No duplicates, no loss |
| Clock skew injection | `occurred_at` vs `recorded_at` handled; skew detected |
| Duplicate and out-of-order replay | Idempotent via `client_generated_id` |
| Two devices, capacity-1 zone | Both logged; violation **flagged, not silently corrected** |
| Battery death mid-sync | Unsynced logs survive and replay |
| 8-hour offline day | Reconciliation correct; retrospective violations surfaced |
| Revoked credential offline | Admitted, then flagged — matching the stated residual risk (R8) |

### Hardware
Every adapter needs a **fake** so the application is developable and testable without hardware
present (`37`). Then periodic real-hardware runs — `128` gate 24 — because printer and scanner
behaviour is not faithfully simulable.

### Performance
Per `125`. Load-test the two critical paths: access decision under scan burst, checkout under sales
spike.

### Security
Per `124`. Priority: cross-tenant denial, missing-authorization detection, **SSRF defence** (currently
untested despite being the best-built security code here), credential forgery, offline replay abuse.

## Traceability

Per `139`: requirement → implementation → test → evidence → release. The matrix earns its keep by
making the authorization and offline gaps visible rather than assumed.

## CI gates

Per `129`. Blocking: backend tests, architecture tests, cross-tenant suite, frontend lint/typecheck,
frontend unit, E2E smoke. Non-blocking: full E2E (sharded, nightly), performance budgets.

## Open questions

- **Frontend coverage target?** A number invites gaming. Prefer mandatory tests for specific high-risk modules.
- **Who owns E2E maintenance?** 73 specs already; this will double. Flaky E2E that nobody owns gets disabled.
- **Real-hardware test cadence** — needs hardware first (`102`).
- **Should the e2e stack run a queue worker?** It does not today, so queued-email flows behave differently there than in production.

## Related

`80-qa-strategy.md` · `124-security-testing-plan.md` · `125-performance-testing-plan.md` ·
`128-definition-of-done.md` · `129-quality-gates.md` · `139-test-matrix.md` · `71-realtime-architecture.md`
