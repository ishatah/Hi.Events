# Test Traceability Matrix

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Derived · **Priority:** P1 · **Phase:** all
**Depends on:** `79-testing-strategy.md`, `138-acceptance-criteria.md`, `01-executive-vision.md`
**Blocks:** `127-production-readiness.md`, `140-final-100-percent-checklist.md`

---

## What this traces

Requirement → implementation → test → evidence. Two kinds of requirement are traced here: the
**invariants** the architecture depends on, and the **eleven success criteria** in `01`. Feature-level
traceability belongs in work packages (`137`), not in this document — a matrix of every acceptance
criterion would be abandoned within a quarter, which is the scaffold's own warning.

`Evidence` means a test that runs **automatically**. Today nothing does — ARZO's code has no CI
(`123`) — so every "Tested" below is "a test exists and passes when run by hand". That caveat applies
to the whole document until ARZ-300.

## Invariants

The properties that, if broken, break the platform. Each should have a test that fails loudly.

| # | Invariant | Implementation | Test | State |
|---|---|---|---|---|
| I1 | No Eloquent above repositories | Architecture | ARZ-004 architecture test | **Tested** |
| I2 | Every non-public Action authorizes | Architecture | ARZ-005 architecture test | **Tested** |
| I3 | Account B cannot read, change or delete account A's entities | `isActionAuthorized` + (future) global scope | ARZ-006, 20 cases | **Tested for legacy entities; not for the 31 new ones** |
| I4 | A credential has exactly one source | `credentials_exactly_one_source` CHECK | `Phase2AccreditationSchemaTest` | **Tested** |
| I5 | A grant has exactly one target | `access_grants_exactly_one_target` CHECK | — | **Untested** |
| I6 | Two published sessions never share a room at overlapping times | GiST exclusion | `Phase1FoundationSchemaTest` | **Tested** |
| I7 | Access decisions are a pure function of their context | `AccessDecisionService` | 35 table-driven cases | **Tested** |
| I7b | A rule applies only to its subject (accreditation type, product, credential…) | `AccessDecisionService`, `GrantMaterializationService` | — the test builder has no subject fields | **Violated** — subjects are ignored (`124` ST1, ARZ-320) |
| I8 | Re-entry and exits are recordable; replay is idempotent | `access_logs` + `client_generated_id` | `AccessScanServiceTest` | **Tested** |
| I9 | Time windows evaluate in venue-local time | — | — | **Violated** — windows evaluate in UTC (`115`) |
| I10 | Every attendee has a person | Backfill | Case-folding test on few rows | **Violated at scale** — skips batches (`122`) |
| I11 | No scan is silently lost | Scanner F1 fix | — (no frontend test of the scan path) | **Untested**; offline queueing absent |
| I12 | Device and server decide identically | Golden vectors (`37`) | — | Not yet applicable |
| I13 | Webhooks retry failed deliveries | `SecureCallWebhookJob` config | — | **Violated** — `dispatchSync` (`49`) |
| I14 | SSRF defences hold across redirects and DNS rebinding | `WebhookUrlValidator`, `SecureCallWebhookJob` | Validator tests only | **Half-tested** — the job is untested |
| I15 | Personal data exports exclude sensitive classes by default | — | — | Not yet applicable; exports include everything today (`51`) |
| I16 | Test sends never reach real recipients | `SendEventEmailMessagesService` | — | **Violated** for two audiences (`42`) |

Five invariants are violated today — I7b, I9, I10, I13, I16 — and each is in the hardening track
(ARZ-320, ARZ-302, ARZ-301, ARZ-304, ARZ-305). I7b is the sharpest lesson: 35 passing tests and
a correctness bug, because the test fixtures could not express the property. A test that reproduces each violation should land **with** its
fix, so the matrix moves from "violated" to "tested" in one step.

## Success criteria (`01`)

| # | Criterion | Built by | Proven by (target) | State |
|---|---|---|---|---|
| 1 | Lead to closeout in one system | `56`, `47`, `108` | E2E: operated event through G1–G4 | Not built |
| 2 | Register online and at the door, incl. walk-ins | `10`, `17` | E2E online (exists); walk-in E2E (Phase 4) | Online: tested; door: not built |
| 3 | Accreditation by type with approval | `23` | E2E application → approval → credential | Schema only |
| 4 | Photo badges on demand; reprint after failure | `21`, `22`, `39` | Render test incl. Arabic; print-job retry against a fake printer; field test | Not built |
| 5 | Zone and time rules at many points, with anti-passback | `24` | Decision vectors (exist); multi-point E2E | **Core tested**; I9 violated |
| 6 | **Keeps working when the network drops; reconciles** | `71` | Partition, replay, skew, 8-hour-offline suite (`79`) | Not built |
| 7 | Session programme with per-session check-in and capacity | `27` | E2E register → waitlist → check-in | Schema only |
| 8 | Exhibitor lead capture and analytics | `33` | E2E offline capture → resolve → export | Not built |
| 9 | Live command center as event-day truth | `53` | Scan → screen < 2 s; staleness display | Not built |
| 10 | Post-event reporting on attendance, sessions, leads, operations | `51`, `108` | Report reconciles to logs (spot recompute) | Commerce only |
| 11 | All of it through an authenticated public API | `48` | Key-scope tests; contract test (exists for JWT) | Not built |

## Test inventory at `e7228c1d`

| Layer | Count | Location |
|---|---|---|
| Backend unit | ~1,260 | `backend/tests/Unit` |
| Backend feature / integration | 32 files at the first audit, plus schema and scan-service tests | `backend/tests/Feature` |
| Actions with a feature test | 7 of 268 at the first audit | — |
| Frontend unit | 24 | `frontend` (Vitest) |
| E2E | 73 specs / 288 tests; **1 check-in spec** | `e2e/tests` |
| Offline, hardware, load | 0 | — |

## Gaps this matrix makes visible, in order

1. **No automatic execution at all** — ARZ-300.
2. **The four violated invariants** — fix with reproducing tests.
3. **Cross-tenant coverage of the 31 new entities** before any of their CRUD ships — the uncommitted CRUD work is exactly the shape of change I3 exists for.
4. **The scan path in the frontend** — I11 has no test; F1 shipped for that reason.
5. **Offline suite** — designed before Phase 4 code, not after (`79`).

## Maintaining it

The invariants table is hand-maintained and small on purpose. Success-criteria rows move when a phase
exits. Per-feature traceability lives in each work package's acceptance criteria (`137`, `138`),
linked from the issue — so no row here needs updating for ordinary feature work.

## Related

`79-testing-strategy.md` · `138-acceptance-criteria.md` · `137-engineering-work-packages.md` ·
`01-executive-vision.md` · `127-production-readiness.md` · `129-quality-gates.md` ·
`123-release-strategy.md` · `140-final-100-percent-checklist.md`
