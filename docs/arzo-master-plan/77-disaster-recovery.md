# Disaster Recovery

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process + Business commitment · **Priority:** **P0 for D1 — fix now**; P1 for backups and rehearsal (unnumbered — add to `136`) · **Phase:** now; restore rehearsed before the first operated event
**Depends on:** `76-reliability.md`, `84-infrastructure.md`, `65-privacy-gdpr.md`
**Blocks:** `126-disaster-recovery-plan.md`, `107-event-day-runbook.md`, `130-launch-checklist.md`

---

## Current state — `MISSING`, ~5%

The most likely disaster is not a region outage. It is losing the only copy of something, or an
operator deleting the wrong thing. The audit found the first one already true.

| Fact | Evidence |
|---|---|
| **The only git remote is the public upstream repository** — `origin https://github.com/HiEventsDev/Hi.Events.git` | `git remote -v` |
| `develop` is **14 commits ahead** of `origin/develop` (`7dec84ca`): every ARZO commit from `455585b3` (master plan, Phase 0 fixes) to `e7228c1d` (scan service), plus uncommitted work in progress | `git status -sb`; `git log origin/develop..develop` |
| ARZO's commits therefore exist **only on one workstation**. Push access to upstream is reported absent; either way ARZO work does not belong in upstream's public repository | Coordinator; `UNVERIFIED` by this audit — irrelevant to the fix |
| The deploy workflow deploys **upstream Hi.Events' SaaS**: Vapor, `eu-west-1`, env files from `s3://hi.events-env-secrets` | `.github/workflows/deploy.yml:89-119` |
| `vapor.yml` is upstream's: `domain: api.hi.events`, `database: hievents-postgres`, `cache: hievents-redis` | `backend/vapor.yml:1-21` |
| ARZO's production host, database, backups, secrets store and Sentry project | `UNVERIFIED` — **none is evidenced** in the repository |
| No backup configuration of any kind | Search for `backup`, `pg_dump`, `pg_basebackup`, `restore`, `pitr` |
| All-in-one self-host keeps Postgres in a named volume with no backup job | `docker/all-in-one/docker-compose.yml:84-88` |
| Bucket versioning or replication | Configured outside the repository — `UNVERIFIED` |
| RPO, RTO, restore rehearsal | `MISSING` |
| No encrypted casts exist today, so losing `APP_KEY` currently costs sessions and signed URLs only — **this changes** when `49` (webhook secrets) and `23` (identity fields) add encrypted columns | Search; `persons` model has no casts |
| `access_logs.event_id` is `ON DELETE CASCADE`: a hard delete of an event erases its access evidence | `\d access_logs` |
| `access_logs.client_generated_id` is `UNIQUE`, so resubmitting device logs is idempotent | Same |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| D1 | **Single copy of ARZO's codebase and master plan** — a disk failure, theft or a mistaken `git clean` loses Phase 0–2 and the whole master plan | `git remote -v`; ahead 14 | **Critical — fix today** |
| D2 | **No ARZO-owned infrastructure is evidenced**: the only deploy path and host manifest are upstream's. There is nothing to back up yet, and nothing to restore to | `deploy.yml`; `vapor.yml` | High — blocks `84`, `125`, and every target below |
| D3 | No backups, RPO/RTO or rehearsal for any environment | Search | High, once D2 is resolved |
| D4 | Access evidence cascades away with a hard-deleted event | `access_logs` FK | Medium — `67` |

## Decision: fix the single copy before anything else

D1 is not a roadmap item. Today:

1. Create an **ARZO-owned private repository** (an ARZO GitHub organization or equivalent) and push
   every branch and tag. Keep `origin` pointing at upstream for merges; add `arzo` as the push remote.
2. Protect `develop` and `main` there; require CI.
3. A **second, off-site copy**: a scheduled `git bundle` of all refs to ARZO-controlled storage in a
   different provider, verified by `git bundle verify`.
4. Uncommitted work is not in any of this. The engineer working in the tree should push a WIP
   branch to the ARZO remote daily.

The same reasoning applies to the deploy path (D2): ARZO needs its own staging and production,
secrets store and error-tracking project before any target below means anything. That is `84`'s
first decision.

## Recovery objectives

**Provisional**, host-agnostic, to be confirmed against client contracts (`101`):

| Data | RPO | RTO | Why |
|---|---|---|---|
| Orders, payments, invoices | ≤ 5 min | 1 h | Money. Stripe is the reconciliation source for card payments, not a backup |
| Credentials, grants, deny-list | ≤ 5 min | 30 min during an event | While down, revocations cannot propagate |
| Access logs | 0 for device-captured scans until synced; ≤ 5 min server-side | 2 h — doors do not depend on it | Append-only, device-resident until synced |
| Configuration, templates, accreditation data | ≤ 5 min | With the database | |
| Uploaded files | ~0 for overwrites and deletes (versioning); 24 h otherwise | 4 h | Covers and photos can be re-uploaded; ID documents should not exist long (`73`) |
| Code and plan | 0 — every push mirrored | 1 h | D1 |
| Secrets (`APP_KEY`, `JWT_SECRET`, provider keys) | Escrowed | 1 h | Losing `APP_KEY` will lose every encrypted column once `23` and `49` land |

## Backup design

- **Postgres:** managed point-in-time recovery with at least 14 days' retention, plus a daily logical
  dump (`pg_dump` custom format) to a bucket in a **separate account** with object lock, encrypted
  with a key the application does not hold. The separate account is the ransomware and
  operator-error answer: nobody with production credentials can delete the backups.
- **Buckets:** versioning on both; noncurrent versions expire after 30 days. `73`'s purge must delete
  all versions of personal files, or erasure is not erasure.
- **Redis:** not backed up. It must hold nothing that cannot be rebuilt — which is why work that must
  not be lost is a database row first (`70`).
- **Self-host:** the all-in-one ships a documented, supported backup script (dump + upload to any
  S3-compatible store). Self-hosters own running it; ARZO owns it existing.
- **Backups and erasure:** deleted personal data persists in backups until they expire. Whether
  erasure-by-expiry satisfies PDPL is `UNVERIFIED` — `65` and legal to answer.

## Restore rehearsal

An untested backup is a hope. Quarterly, and before the first event of any new kind:

1. Restore point-in-time to a **new** instance at a chosen timestamp.
2. Run a verification script: row counts per table against the source at that time, the latest
   order and access-log timestamps, a checksum of `access_logs` per event.
3. Point a staging stack at it and complete one checkout and one scan.
4. Record elapsed time against the RTO, and what went wrong.

The record is evidence: `59` gains an advisory readiness check, "last successful restore rehearsal
within 90 days".

## Event-day DR — a different scenario

No maintenance window, a crowd at the door, and — once `71` exists — **devices that hold recent
state**. The design makes that deliberate rather than lucky.

| Scenario | Response |
|---|---|
| Backend or region outage during doors | **Island mode**: the ops lead declares it; devices decide locally without waiting for the 30-minute emergency threshold (`71`); local print hosts print from cached badge data (`39`); walk-ins captured locally; no card payments (`71`) |
| Database restored to a point before the outage | Devices resubmit logs since that point; idempotent on `client_generated_id` |
| Venue network total loss | Same as island mode; the venue never needed the server for a decision |
| Revocation needed while the server is down | A paper or radio stop-list to gate supervisors; peer deny-list gossip is Phase 4+ (`71`) |
| Operator error — bulk revoke, wrong rule published | Rule versioning and credential status history (`24`, `67`); point-in-time restore only as a last resort, because it also rewinds every legitimate write |
| Print host fails | Spare host enrolled in advance (`59`) |

Two design consequences for `71` and `40`:

- **Every device-originated scan carries a `client_generated_id`, online or offline.** Today
  `AccessScanService` accepts scans without one; those could not be recovered from devices.
- **Devices keep synced logs until event end + 24 h**, encrypted, and support a "resubmit all"
  command. This answers `71`'s retention question from the recovery side; the breach-exposure side
  argues for shorter, and 24 h after the event is the proposed compromise.

After recovery, every figure for the outage window is **provisional until settled** (`52`).

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | D1: ARZO private remote, protected branches, off-site bundle | None — **today** |
| 2 | D2: ARZO staging and production, secrets store, Sentry project (`84`, `85`) | Business decision first |
| 3 | PITR, logical dumps to a separate account, bucket versioning (D3) | With step 2 |
| 4 | First restore rehearsal, recorded | Before the first operated event |
| 5 | D4: `ON DELETE RESTRICT` on `access_logs.event_id`; events are soft-deleted | Low |
| 6 | Island mode and device resubmission in the scanner (`94`) and the event-day runbook (`107`) | Phase 4 |
| 7 | Event-day DR drill alongside `59`'s offline drill | Before each operated event |

## Open questions

- **Where will ARZO run production?** `84` — every objective above waits on it.
- **What RPO/RTO do clients require?** Government clients may specify them contractually (`101`).
- **Who may declare island mode?** The ops lead, recommended; `106` confirms.
- **Cross-region standby?** Not before offline-first: devices protect the door better than a standby region, at a fraction of the cost.

## Related

`76-reliability.md` · `126-disaster-recovery-plan.md` · `84-infrastructure.md` · `85-deployment.md` ·
`71-realtime-architecture.md` · `40-device-management.md` · `65-privacy-gdpr.md` · `73-file-management.md` ·
`59-event-readiness.md` · `107-event-day-runbook.md` · `101-enterprise.md` · `130-launch-checklist.md`
