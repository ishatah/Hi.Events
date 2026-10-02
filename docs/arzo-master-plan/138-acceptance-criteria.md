# Reusable Acceptance Criteria

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 · **Phase:** every phase
**Depends on:** `128-definition-of-done.md`
**Blocks:** `137-engineering-work-packages.md`, `139-test-matrix.md`

---

## Why reusable sets

Most features in this plan are one of about a dozen shapes: a tenant-scoped CRUD, an authorized
endpoint, an offline write, a contestable decision. The failure modes of each shape are known — and
were found repeatedly by the audits behind documents 13–140. A reusable set means a work package
inherits them instead of rediscovering them.

Each set is written as testable statements. A package composes sets by name and adds its own
feature-specific criteria (`137`).

## Executable where it pays

**Decision: sets 1, 2, 3 and 7 become executable test scaffolding; the rest stay checklists.**

| Set | Executable form |
|---|---|
| 1 Tenant-scoped CRUD | A PHPUnit trait parameterized by entity factory and route — extends the Phase 0 cross-tenant suite (ARZ-006) |
| 2 Authorized endpoint | The Phase 0 architecture test (ARZ-005) plus a role-matrix data provider once RBAC lands |
| 3 Offline-capable write | A shared test case asserting replay idempotency, out-of-order arrival and `occurred_at`/`recorded_at` handling |
| 7 Data migration | A base test that seeds across batch boundaries (> 2 × batch size) — the lesson of ARZ-301 |

These four are where the codebase has already shipped or nearly shipped defects. Writing them once is
cheaper than reviewing for them forever.

## The sets

### 1 — Tenant-scoped CRUD

1. Account B receives `404` (not `403`, not `500`) for account A's entity on show, update and delete
2. List endpoints never return another account's rows, including through filters and includes
3. Child entities are unreachable by guessing ids under another account's parent
4. The entity carries `account_id`, or is reachable from it by a join the global scope covers (`08`)
5. Soft-deleted rows are excluded from lists and return `404`

### 2 — Authorized endpoint

1. The action calls `isActionAuthorized` or its policy successor — enforced by the architecture test
2. Each role in `09` is asserted **allowed or denied** — no role untested
3. A per-event role on event A is denied on event B
4. An unauthorized request returns `403` without leaking the entity's existence where tenancy applies
5. An expired `event_users` grant denies

### 3 — Offline-capable write

1. Carries a device-generated `client_generated_id`; replaying it creates **no** second row
2. Out-of-order arrival produces the same end state as in-order
3. Stores `occurred_at` (device) and `recorded_at` (server) separately
4. **No silent loss** under drop, flap, app restart or battery death (`128` gate 21)
5. A conflict is **surfaced** — flagged for review — never silently rewritten (`71`)
6. The operator sees a degraded-mode banner with the age of the data (`128` gate 20)

### 4 — Hardware-dependent action

1. Runs against a fake adapter in CI (`37`)
2. Every normalized error state has a test: paper out, head open, disconnect, power-cycle mid-job
3. A failed job is retryable without duplicating the physical output where the device can confirm
4. Tested against the real device family before release, or `128` gate 24 is waived in writing
5. A non-engineer can recover it on site using the runbook (`107`)

### 5 — Scheduled or queued job

1. Idempotent — running twice is harmless
2. Explicit queue, tries, backoff and timeout (`70`)
3. Failure lands in `failed_jobs` with enough context to retry
4. **Actually retries** — not dispatched synchronously in a way that defeats its own policy (`49` W1)
5. Safe under overlap, or scheduled `withoutOverlapping()`

### 6 — Notification

1. Sent after commit — a rolled-back transaction sends nothing (`BaseMail` `afterCommit()`)
2. Respects suppressions and category preferences (`42`, `69`)
3. "Sent" and "delivered" are distinct states in the delivery log (`69`)
4. Localized to the recipient's locale, with an Arabic variant where `82` requires it
5. Test mode never reaches a real recipient (`42` E1)

### 7 — Data migration or backfill

1. Idempotent, key-paged (`chunkById` or cursor) whenever it modifies what it filters on (`122`)
2. Seeded test crosses at least two batch boundaries
3. Dry-run mode reports the count it would change
4. Ends with an **assertion**, not a log line
5. Rehearsed on production-shaped data, timed
6. Rollback stated — including "not reversible, because…" when that is the honest answer

### 8 — Export containing personal data

1. Server-side generation; no browser-assembled files (`51` R2)
2. Formula-safe for spreadsheets (`FormulaSafeValueBinder`) and correctly escaped for CSV
3. No silent row cap — truncation, if any, is stated in the file (`51` R1)
4. Sensitive classes (ID documents, date of birth, health data) excluded by default; including them requires a permission and writes an audit event (`65`, `67`)
5. Large exports are asynchronous

### 9 — Contestable decision

Accreditation approval, credential revocation, access override, badge void, readiness waiver,
incident closure.

1. Writes an audit event: actor, subject, from → to, reason, time (`67`)
2. A reason is mandatory where the decision disadvantages someone
3. The actor holds the specific permission, not just account membership (`09`)
4. Any AI assistance is recorded and never decides (`133`)

### 10 — Public or unauthenticated endpoint

1. Per-route rate limit appropriate to its abuse profile — not only the global limiter (`48`)
2. Does not act as an enumeration oracle — no behaviour that differs by whether an email or id exists (`38`)
3. Capability tokens are unguessable, scoped, revocable and expiring
4. Marked "(public)" in its OpenAPI summary — asserted by the contract test (`48`)

### 11 — Webhook-emitting change

1. A `DomainEventType` case exists, added to `WebhookForm` and to the payload table in `config/scramble.php`
2. The payload comes from a versioned transformer, not a REST resource (`49`)
3. **Both** code paths that make the change emit the event (the F10 lesson)

### 12 — User-facing surface

1. Loading, empty, error, partial, offline and permission-denied states (`128` gate 5)
2. Keyboard operable, labelled, contrast-checked (`81`)
3. Strings translated for every supported locale; layout checked in RTL once Arabic ships (`82`)
4. Controls a user cannot use are hidden, not merely rejected on submit (`90`)
5. `data-testid` on the interactive elements an E2E spec drives (`CLAUDE.md`)

## Open questions

- **Where do the executable scaffolds live?** `backend/tests/Support/` for PHP traits; a shared TypeScript module for offline-write criteria once the device apps exist (`94`).
- **Should composing sets be mandatory in the package template?** Yes — `137`'s "Acceptance" field names sets by number.

## Related

`128-definition-of-done.md` · `137-engineering-work-packages.md` · `139-test-matrix.md` ·
`79-testing-strategy.md` · `08-multi-tenancy.md` · `09-permissions-and-roles.md` ·
`71-realtime-architecture.md` · `122-migration-plan.md` · `49-webhooks.md` · `51-reporting.md`
