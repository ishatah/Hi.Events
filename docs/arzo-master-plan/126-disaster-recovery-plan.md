# Disaster Recovery Runbook

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P0 for D0 (live today); P1 for the rest · **Phase:** D0 now; D1–D5 before the first live event on ARZO's own hosting
**Depends on:** `77-disaster-recovery.md`, `71-realtime-architecture.md`, `76-reliability.md`, `85-deployment.md`
**Blocks:** `107-event-day-runbook.md`, `127-production-readiness.md`, `130-launch-checklist.md`

---

The tested procedures for serious failure: what triggers each, who decides, the steps, how we know
it worked, and how often it is rehearsed. A restore that has never been rehearsed is a hope. Every
RPO and RTO below is **provisional** until the business confirms it and a rehearsal measures it.

## Current state — `MISSING`, ~5%

| Fact | State | Evidence |
|---|---|---|
| **ARZO's code exists in one place** | `CONFIRMED`, live | `git remote -v` → one remote, `origin https://github.com/HiEventsDev/Hi.Events.git` (the public upstream); `git status -sb` → `develop...origin/develop [ahead 14]`. Plus uncommitted work in progress at audit time — 50 new action files (268 → 318) with their requests, resources and handlers. One disk failure loses all of it |
| ARZO production or staging | `MISSING` | `backend/vapor.yml:1-6` (app `HiEvents`, domain `api.hi.events`) and `.github/workflows/deploy.yml:97-101` (env from `s3://hi.events-env-secrets`) are upstream's; ARZO's 14 commits change neither (`git diff origin/develop develop` touches only `supervisord.conf` among deployment files) |
| Backup tooling | `MISSING` | Search `backup\|pg_dump\|pg_basebackup\|PITR\|restore` → no configuration; no backup package in `composer.json` |
| Managed database backups | `UNVERIFIED` | Upstream's Vapor/RDS settings are not in the repository, and are not ARZO's to rely on |
| Self-host data | `CONFIRMED` | `docker/all-in-one/docker-compose.yml:83-88` — Postgres on named volume `pgdata`, Redis on `redisdata`; no backup sidecar |
| Migrations run on every deploy | `CONFIRMED` | `vapor.yml:25-26` `migrate --force`; `docker/all-in-one/scripts/startup.sh:5-12` aborts start on failure; no pre-migration snapshot step |
| Migration atomicity | `CONFIRMED` | Postgres runs each migration in its own transaction (`PostgresGrammar.php:18`, `Migrator.php:448-449`), unless it opts out (`2026_09_12_000002_add_product_price_id_index_to_attendees.php:8`). A batch can still half-apply |
| Rollback paths | `PARTIAL` | All 162 migrations define `down()`; one test exercises it (`AddQuantityAppliesToToProductPricesTest.php:41,69`) |
| Failure detection | `PARTIAL` | `/up` returns a static `ok` (`routes/web.php:20-22`); Sentry errors only; no on-call (`76`, `86`) |
| Event-day fallback | `MISSING` | No device-local store (`71`); a network or backend failure stops check-in |
| Credential identifiers | `CONFIRMED` | Stored in plaintext beside the hash (`CredentialIssuanceService.php:86-93`) so badges can be reprinted — a database read yields every valid badge |

## Decision: DR starts with the repository

The first disaster is not a database. It is this workstation. Everything ARZO has built exists on one
disk, with an upstream remote ARZO cannot push to. D0 is fixed today, before any other item here.

## Decision: RPO and RTO per data class, not one number

| Data | RPO (provisional) | RTO (provisional) | Why |
|---|---|---|---|
| Source code | Last push — push the same day as every commit | 1 hour to a working clone | Cheap to achieve once a second remote exists |
| Orders, payments, invoices | ≤ 5 min | ≤ 4 h outside events | Money. Stripe is an independent record for card payments, so the gap is reconcilable |
| Event configuration: zones, rules, credentials, accreditations | ≤ 15 min | ≤ 4 h; **before doors** during build-up | Re-keying configuration is slow and error-prone |
| Access logs and check-ins during an event | **0 at the door**; ≤ 5 min on the server | Doors: none needed. Server: ≤ 1 h | Devices keep deciding and keep the logs (`71`); the server catches up |
| Uploaded files | Provider versioning | ≤ 4 h | Photos and logos |
| Webhook logs, stats rollups, snapshots | 24 h | Best effort | Derivable or regenerable |

A ≤ 5 min RPO needs continuous WAL archiving with point-in-time recovery — available on mainstream
managed Postgres, but `UNVERIFIED` for whichever host `84` chooses. Owner of the final numbers:
business, because they become client commitments (`101`).

## Decision: on event day the devices carry the event; the server recovers behind them

`71` makes doors independent of the server. DR relies on that deliberately, and so it adds five
requirements to `71`, `40` and `39`:

1. **Devices never evict unacknowledged logs**, and keep acknowledged ones for the whole event.
2. **Restore epoch.** After a restore, the server announces an epoch; devices re-send every log since the cursor the restored database knew. `client_generated_id` idempotency makes the re-send safe. Without this, logs synced between the restore point and the failure are lost — the device thinks they are safe.
3. **Full sync verified at T-24h and T-2h** (`59` blocking checks), so the offline window starts from fresh data.
4. **Print hosts cache badge data for pre-registered people**, so desks keep printing (`39`).
5. **A printed deny-list at supervised points** for high-security events, since revocations stop propagating (R8).

What stops during a backend outage, stated to clients in advance: new online payments, revocation
propagation, zone capacity and cross-door anti-passback checks, and live dashboards. Figures from
the window are **provisional until settled**.

**Until `71` exists** the event-day fallback is paper: a per-gate attendee export printed at T-2h
(`131`), tally sheets, and a back-entry afterwards through the dashboard — which today emits no
webhook (F10).

## Scenarios

Each scenario: trigger → owner → steps → verification. The owner decides; engineering executes.

### D0 — Repository loss · owner: engineering lead · live today (ARZ-300)

Steps: create an ARZO-owned **private** repository under an organization account, not a person's;
push `develop` and tags; keep upstream as a second remote named `upstream`; enable branch protection
(`129`); add a scheduled `git clone --mirror` to storage outside that provider.
Verification: a fresh clone on a clean machine passes the Unit and Feature suites.

### D1 — Database loss or corruption · owner: engineering on-call; business accepts the data-loss window

1. `php artisan down`; stop queue workers and the scheduler so nothing writes to a dying database.
2. Choose the restore point: last known good, before any corruption.
3. Restore **to a new instance** — never in place. Point the application at it.
4. `php artisan migrate:status` — the schema must match the deployed code.
5. Reconcile payments: list Stripe PaymentIntents since the restore point; mark matching orders paid or refund them.
6. Announce the restore epoch to devices (after `71`); watch the re-sync drain.
7. `php artisan up`; tell affected organizers what window was lost.

Verification: row counts and order totals against the last metrics before the incident; Stripe totals
match; `@smoke` E2E against the restored environment.

### D2 — Region or provider outage · owner: engineering lead with business

Decision: **restore into another region from cross-region backup copies, rebuilt from infrastructure
as code — no active-active.** Warm standby doubles hosting cost for a failure the devices already
absorb at the door. Steps as D1 in the other region, plus DNS. RTO ≤ 4 h is only credible once the
rebuild is scripted and rehearsed.

### D3 — Bad migration · owner: the engineer who deployed; engineering lead approves a restore

1. Stop the deploy pipeline. Do not start a second deploy to "fix forward" blind.
2. A failed migration has rolled back its own transaction; earlier migrations in the batch have not.
3. Code rollback plus `migrate:rollback --step=N` **only if** those `down()` methods were tested (`129` migration gate). Otherwise restore the pre-deploy snapshot (D1 steps 3–7).
4. Destructive migrations — drop, rename, type change — are taken only with a pre-deploy snapshot and a written rollback, and **never inside an event freeze window** (`123`).

Verification: `migrate:status` clean; Feature suite green against a copy of the restored database.

### D4 — Credential compromise · owner: security lead; event owner for badges

| Secret | Action | Consequence |
|---|---|---|
| `JWT_SECRET` | Rotate | Every session ends (7-day TTL); acceptable |
| `APP_KEY` | Rotate with the previous key kept for decryption | Nothing is encrypted today; once `persons` encryption lands (`23`), rotation must re-encrypt — how Laravel 13 supports this here is `UNVERIFIED` |
| Stripe keys, webhook signing secret | Rotate in Stripe, then config | Brief payment interruption |
| Outgoing webhook secrets | Delete and recreate — no rotation endpoint exists (`49` W6) | Integrators must update |
| A user account | Deactivate; the next authorized call logs them out (`64`); reset password; review | `order_audit_logs` has no actor column (`67`), so the review is incomplete |
| A device key | Revoke (`40`); keys expire with the event | That device stops syncing |
| **Badge identifiers** (database read) | Revoke and reissue credentials; reprint badges | Mid-event, reprinting everyone is not feasible: revoke the known-leaked, add visual checks at supervised points, accept the residual risk in writing |

### D5 — Event-day backend or network outage · owner: on-site operations lead; engineering restores

1. Detection: devices report sync failures; dashboards go stale. The operations lead declares degraded mode — the system does not decide (`59`).
2. Devices continue; emergency mode shows data age after the threshold (default 30 min, `71`).
3. Supervisors switch high-security points to the printed deny-list; walk-ins take cash or defer payment.
4. Badge desks print from the print host cache.
5. Client communication from a prepared script (`107`).
6. On recovery: devices sync; the reconciliation report surfaces retrospective violations; figures stay provisional until every active device has synced past the window.

## Rehearsal

| Drill | Cadence | Pass |
|---|---|---|
| D0 clone-from-mirror | Quarterly | Suites green on a clean machine |
| D1 restore to a new instance | Quarterly, and before the first event on any new stack | Measured RTO and RPO inside the provisional targets |
| D3 migration rollback | Every release containing a destructive migration, on a staging copy | Rollback leaves `migrate:status` clean |
| D4 secret rotation | Twice a year | All services healthy after rotation |
| D5 offline drill | **Every event**, at T-24h (`59` blocking check) | Reconciliation report clean after reconnect |

**The record.** Each rehearsal is written into the event documentation record (`63`): date, scenario,
environment, elapsed time per step, data loss observed, what failed, follow-ups with owners. A
rehearsal with no record did not happen.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | D0: private ARZO remote, mirror, branch protection | Today |
| 2 | Hosting decision with PITR and cross-region backup copies (`84`, `85`) | Before any ARZO production |
| 3 | Real health check covering database, Redis and queue (`76`) | With hosting |
| 4 | Pre-deploy snapshot step for destructive migrations; rollback test (`129`) | With the deploy pipeline |
| 5 | First D1 rehearsal; numbers confirmed or revised | Before the first live event |
| 6 | Restore epoch and log retention on devices (`71`, `40`) | With ARZ-101 |

## Open questions

- **Where will ARZO run?** Hosting (`84`, `85`) decides the mechanics of D1 and D2. Nothing above can be rehearsed until it exists.
- **Who is on call during events, with what authority?** `76`, `86`, `106`. A runbook with no named owner is a document.
- **Are the provisional RPO/RTO acceptable?** Business, and they become contract terms (`101`).
- **How long do devices keep acknowledged logs?** `71` left this open for privacy; it is now also a DR parameter. Leaning: until the event's reconciliation is signed off.

## Related

`77-disaster-recovery.md` · `71-realtime-architecture.md` · `76-reliability.md` · `85-deployment.md` ·
`84-infrastructure.md` · `123-release-strategy.md` · `107-event-day-runbook.md` · `59-event-readiness.md` ·
`40-device-management.md` · `39-printer-integration.md` · `49-webhooks.md` · `63-event-documentation.md` ·
`129-quality-gates.md` · `131-event-readiness-checklist.md`
