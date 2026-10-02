# Deployment

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (ARZO) + Process · **Priority:** P0 prerequisite (no target exists — `84`); P1 to build · **Phase:** design now; build with `84`
**Depends on:** `83-devops.md`, `84-infrastructure.md`, `123-release-strategy.md`
**Blocks:** `127-production-readiness.md`, `130-launch-checklist.md`

---

How a change reaches production — web, database and devices — without hurting an event in progress.
`123` owns the release process: branching, versioning, flags and the freeze **policy**. This document
owns the mechanics.

## Current state — `MISSING`, 0% for ARZO; upstream's mechanics as reference

ARZO has nothing to deploy to (`84`) and no pipeline to deploy from (`83`). What it forked:

| Fact | Evidence |
|---|---|
| Backend: `vapor deploy <env>` from CI; `php artisan migrate --force` is a **deploy hook** | `deploy.yml:116`; `vapor.yml:25-26,47-48` |
| Vapor runs deploy hooks against the new deployment **before** activating it, and does not activate if a hook fails | Vapor documentation, `https://docs.vapor.build/projects/deployments` (via search, accessed 2026-09-29) |
| Frontend: a DigitalOcean deployment triggered by API and polled up to 90 × 10 s = 15 minutes; runs only after the backend job | `deploy.yml:124,163-232` |
| Deploys are **not gated on tests** | No `needs:` on any test workflow |
| One deploy per environment at a time, never cancelled mid-flight | `deploy.yml:30-32` |
| All-in-one: `migrate --force` at **every container start**, aborting startup on failure | `docker/all-in-one/scripts/startup.sh:5-12` |
| No rollback step in any workflow; no post-deploy check beyond the platform's own | Workflows; `/up` is static (`routes/web.php:20-22`) |
| Device apps, print host | Do not exist |

## Findings

| # | Finding | Evidence | Severity |
|---|---|---|---|
| DP1 | No ARZO deployment target or pipeline | `83`, `84` | **P0** — decision now |
| DP2 | A failed activation leaves migrations applied: Laravel runs each Postgres migration in its own transaction, but earlier migrations in the batch stay, and the **old code keeps serving on the new schema**. Every migration must therefore be backward-compatible | `vapor.yml:25-26` | A rule, not a bug — see expand/contract |
| DP3 | Migrate-on-boot races with more than one replica: every container runs `migrate --force` at once | `startup.sh:5` | **Medium** the moment ARZO runs two web containers — use `migrate --isolated` or a single release job |
| DP4 | Frontend and backend deploy separately; a failed frontend after a successful backend leaves mixed versions | `deploy.yml:124` | Low — the API must stay compatible with the previous frontend |
| DP5 | Nothing verifies a deploy beyond the platform saying "active" | Static `/up` | Medium (`76`) |

## Decision: build once, promote the same image

From `83`. The image tested in staging is the image that runs in production, by digest.

## Decision: blue/green with a health-gated switch — not canary

The new version starts beside the old; health and smoke checks run against it; traffic switches; the
old version stays warm for fast switch-back.

**Why not canary:** canary needs steady traffic to show a regression statistically. ARZO's traffic is
spiky around its own events and near-idle between them — a 5% canary at 03:00 proves nothing, and one
at 08:55 on event day is exactly what the freeze forbids. **Why not a maintenance window:** it would
be cheaper, but a SaaS customer's on-sale does not wait for ARZO's quiet hour. Blue/green costs a
second set of containers for minutes.

Rollback is switching back — valid only while migrations are expand-only.

## Decision: expand, then contract — a release apart

| Rule | Why |
|---|---|
| New columns nullable or defaulted; new tables free | The old code must not break on the new schema (DP2) |
| No rename or type change in one step — add, dual-write, backfill, switch reads, drop later | A rename is a drop from the old code's point of view |
| Drops only in the release **after** the one that stopped reading the column | So switch-back still works |
| Backfills run as queued jobs, not inside migrations (`122`) | Long transactions lock hot tables |
| Indexes on large tables built `CONCURRENTLY`, in a migration with `$withinTransaction = false` | A blocking index build on `attendees` is an outage |
| No production down-migration during an incident — forward-fix | `down()` is rarely tested against real data (`122`) |

Release notes list each migration and whether it is reversible (`123`).

## Decision: the freeze is enforced by the pipeline, and fails closed

`123` sets the policy: no production deploy while an ARZO-operated event is in `BUILD_UP` or
`LIVE_OPS` (`56`). The mechanism:

```
GET /internal/deploy-freeze          -- deploy token; not a user route
→ { frozen: bool,
    events: [{ event_id, stage, doors_open_at, breakdown_ends_at }],
    override: { approved_by[], reason, expires_at } | null }
```

1. The production job calls it before deploying. **`frozen` and no override → the job stops.**
2. **Endpoint unreachable → the job stops.** Deploying blind is the risk the freeze exists to prevent.
3. An override is created in the admin UI by the named approvers (`106`: event director plus
   engineering lead), carries a reason and an expiry, and is audited (`67`).
4. Until `event_operations` exists, the same rule runs from a shared calendar and a required
   `freeze_acknowledged` input on manual deploys (`123`).

**What the freeze covers:** code, migrations, infrastructure and dependency changes. **What it does
not:** product configuration — access rules, rotas, zone capacities. Those change mid-event by design
and carry their own guard, the rule simulator (ARZ-064).

**Scope: ARZO-operated events and contracts that buy it — not every tenant's event.** On a SaaS
platform some customer always has an event running; a platform-wide freeze would mean never
deploying. Other tenants are protected by blue/green, flags (`123`) and expand/contract.

## Post-deploy verification

Before the switch, against the new version; again after it:

| Check | Pass condition |
|---|---|
| Readiness | Dependency-aware health endpoint green — Postgres, Redis, queue reachable, storage writable (`76`) |
| Smoke | Read-only journeys: public event page, organizer login, a check-in list loads |
| Errors | New-release error rate in Sentry no worse than the previous release over 30 minutes (`86`) |
| Workers | Queue drain rate normal; scheduler heartbeat seen (`86`) |

A failure before the switch aborts; after it, switches back.

## Devices, print hosts and the attendee PWA

Web deploys roll back in seconds. A scanner that updated itself at a gate does not.

| Rule | Mechanism |
|---|---|
| **Channels** | `internal` → `staging` (ARZO's own lab devices) → `production` |
| **Promotion gate** | The build passes the golden vectors (`37`) and the field-test charters (`80`) |
| **Per-event pin** | At the T-24h review the event's app, print-host and protocol versions are fixed (`59`, `123`) |
| **Compatibility** | The server supports app versions N and N-1, and each sync-protocol version any pinned event uses, for that event's whole life |
| **Protocol negotiation** | Each sync call declares its protocol version; an unsupported one gets a specific error the app shows as "update at base", never a corrupted sync |
| **No mid-event updates** | Devices never update themselves between T-24h and breakdown. Updates install when docked at base (`103`) |
| **Emergency exception** | A security fix only, approved as for a deploy override; applied device by device at a quiet access point, **with a spare swapped in first**; the rest keep running N-1 |
| **Distribution** | Through MDM or managed app stores (`40`); which one follows the device platform choice (`94`, `96`) — `UNVERIFIED` |
| **Print hosts** | Same channels; drain the queue before updating |
| **Attendee PWA** | A new service worker activates on the next navigation, never mid-checkout, and never by forcing a reload of a page showing a ticket (`30`) |

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Deployment target decided (`84`) | Now |
| 2 | Image promotion with blue/green switch and post-deploy checks | With the first ARZO environment |
| 3 | `migrate --isolated` or a single release job (DP3) | With step 2 |
| 4 | Expand/contract rules in `CLAUDE.md` and the PR template | Now — costs nothing |
| 5 | Freeze check endpoint and override record | With `56` |
| 6 | Device channels, pinning, protocol negotiation | With ARZ-100 / ARZ-101 |

## Open questions

- **Target platform?** Everything here waits on `84`.
- **App-store review timelines for native apps** — an emergency fix may be days away if it needs store review. MDM-distributed private apps avoid it; `94`, `96`.
- **Do SaaS customers buy a freeze?** Possibly an enterprise term (`101`).
- **Who approves emergency overrides when the event director is unreachable?** A deputy named in advance (`106`).

## Related

`123-release-strategy.md` · `83-devops.md` · `84-infrastructure.md` · `122-migration-plan.md` ·
`56-event-operations.md` · `59-event-readiness.md` · `40-device-management.md` · `37-hardware-integration.md` ·
`76-reliability.md` · `86-monitoring.md` · `94-mobile-scanner.md` · `96-kiosk-application.md` ·
`103-hardware-deployment.md` · `106-event-operating-model.md`
