# Event Operating Model

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 — needed before the first operated event; enforcement via ARZ-011, ARZ-012 · **Phase:** any; permission mapping with Phase 1
**Depends on:** `09-permissions-and-roles.md`, `56-event-operations.md`, `105-event-lifecycle.md`
**Blocks:** `107-event-day-runbook.md`, `131-event-readiness-checklist.md`

---

Who does what on an operated event, who decides, and who is woken up. The scaffold's rule is the
design constraint: **roles must map onto the permission model (`09`)** — an operating model the
system cannot express is aspirational. This document names the decision rights, then says plainly
which of them the permission model can express, which it will express after ARZ-011 and ARZ-012,
and which it never will because they are not software decisions.

## Current state — `MISSING`, ~5%

| Fact | State | Evidence |
|---|---|---|
| A documented event operating model | `MISSING`; whether a tacit one exists is `UNVERIFIED` — ask the operations lead | Repository search |
| Permission names | **43 seeded** | `2026_09_29_000007_create_permission_tables.php:20-37`; live DB count |
| Roles, role grants, per-event assignments | **0, 0, 0 rows** — the migration seeds names only | Live DB: `permission_roles`, `permission_role_permissions`, `event_users` |
| Code that reads any of them | **None** — only generated domain objects reference `PermissionRole` or `EventUser` | Search of `app/` |
| Enforcement today | Three-role enum; `ORGANIZER`, the default, checks nothing | `IsAuthorizedService.php:36-47`; `BaseAction.php:146-149` defaults to `Role::ORGANIZER` |
| Access override in software | `MISSING` — `GRANTED_OVERRIDE` exists as an enum case; nothing writes it, `override_by` or `override_reason` | `AccessResult.php:17`; `AccessScanService` has no override parameter |
| Production deploy control | **Every push to `main` deploys production**; no approval, no freeze | `.github/workflows/deploy.yml:3-8,73-75` |
| Paging | `MISSING` — the failed-jobs monitor only writes a log line | `Kernel.php:21-26`; `86` |

At audit time the uncommitted, in-progress credential and access-scan actions authorized with
`isActionAuthorized($eventId, EventDomainObject::class)` at the default role, so **any active member
of the account could revoke a credential**. That is consistent with today's model, and it is exactly
the gap ARZ-011 closes.

## Operating roles and their `09` mapping

| Operating role | Abbr | `09` role | Login | Note |
|---|---|---|---|---|
| Event Director | ED | `EVENT_MANAGER` | Yes | Owns G2 and G3 (`56`) |
| Duty Manager | DM | `EVENT_MANAGER` | Yes | Runs the day; incident commander for SEV2 |
| Accreditation Lead | AL | `ACCREDITATION_OFFICER` | Yes | |
| Security Lead | SL | **none fits** — proposed `GATE_SUPERVISOR` plus `credential.revoke` | Yes | |
| Gate Supervisor | GS | **none fits** — proposed `GATE_SUPERVISOR` | Yes | `access_logs.override_by` references `users` (`2026_09_29_000006:45`), so whoever overrides needs a login |
| Badge Desk Lead | BL | `BADGE_OPERATOR` | Yes | |
| Gate operator, steward | — | **none** — a staff credential and a device key | **No** | Staff are people with credentials, not account users (`57`, `38`) |
| Device Lead | TL | **none fits** — proposed `DEVICE_LEAD` (`device.manage`) | Yes | Owns device readiness (`103`) |
| Command Center Operator | CCO | `VIEWER` + `access.logs.view` | Yes | |
| Engineering on-call | ENG | Platform `SUPERADMIN`; acts in tenant data only through impersonation, which is logged (`67`) | Yes | |
| Engineering Lead | EL | — | — | Second key for emergency deploys |
| Data Protection Lead | DPL | `ADMIN` | Yes | Whether ARZO has a designated privacy owner is `UNVERIFIED` |
| Client representative | Client | `VIEWER` per event, if at all | Optional | Per contract |
| Venue safety officer | Venue | Outside the system | No | Venue procedures govern life safety (`60`) |

`09`'s role list has no role that holds `access.override` at a gate, and none for device fleet
management. Two proposals, recorded in `09` first because it is authoritative:
`GATE_SUPERVISOR` (`attendee.checkin`, `access.override`, `access.logs.view`, `incident.manage`) and
`DEVICE_LEAD` (`device.manage`, `access.logs.view`).

## Decision rights — RACI

R does it, A decides and answers for it, C is consulted before, I is told after.

| Decision | R | A | C | I |
|---|---|---|---|---|
| Approve or reject accreditation — standard types | AL | AL | SL for security-vetted types | ED (daily summary) |
| Approve accreditation — sensitive types (all-access, government media) | AL | **ED** | SL, Client | DM |
| Access override for one person at a gate | GS | GS | SL if VIP or repeated | CCO (logged) |
| Revoke a credential during the event | AL | SL | ED if VIP | GS, CCO, TL (deny-list sync) |
| Hold admissions to a zone — capacity breach | GS | DM | Venue | ED, CCO |
| **G3 go / no-go** | DM runs the review | **ED** | SL, TL, AL, BL, Venue; Client if contracted | All leads |
| Waive a blocking readiness item (`59`) | DM | **ED only** | Owner of the item | — |
| Declare venue-wide degraded mode (paper fallback) | TL | DM | ENG | ED, every GS |
| Incident command — SEV1 | Venue emergency procedures; DM liaises | ED for ARZO's own actions | SL | Client |
| Incident command — SEV2 | DM as incident commander | DM | SL, TL | ED |
| Incident — SEV3, SEV4 | GS or owner | GS | — | CCO |
| **Emergency production deploy, or a device-app update mid-event** | ENG | **ED + EL, both** | DM, TL | CCO |
| Data export — standard attendee or order data to the client | Requester | ED | DPL | — |
| Data export — ID documents, dates of birth, incident detail, individual movement trails | Requester | **DPL** | ED | — |
| Suspend a lost or stolen device | TL | TL | SL | DPL (breach assessment), DM |
| Evacuate or stop the event | Venue and authorities | Venue and authorities | ED | Everyone — ARZO records it |

Three principles behind the table:

- **The system records; a human decides** (`56`, `59`). Every A above is a person, and every
  decision that changes access or data leaves a record with that person's name.
- **One A per decision.** "The team decided" is how a go/no-go becomes nobody's.
- **Life safety is not ARZO's decision.** Evacuation and SEV1 response follow the venue's emergency
  procedures (`60`); ARZO's software records and notifies.

## Can the permission model express it?

| Decision | Seeded permission | Scope needed | Today | After ARZ-011/012 | Remaining gap |
|---|---|---|---|---|---|
| Accreditation approval | `accreditation.approve`, `.reject` | Per event, **per accreditation type** | No — nothing reads permissions; no workflow (ARZ-051) | Per event | Per type — a media officer who approves only `MEDIA` needs an ABAC condition |
| Access override | `access.override` | Per event, ideally per zone | No — no code path writes an override | Per event | Per zone; no `09` role bundles it |
| Credential revocation | `credential.revoke` | Per event | No — any account member (uncommitted action) | Yes | — |
| G3 decision, waivers | **None** | Per event | No | **No** | New permissions `gate.decide`, `readiness.waive` |
| Incident command | `incident.manage` | Per event | No — no incidents table | Yes | Restricted incidents need `incident.view_restricted` (`60`) |
| Hold admissions at an access point | `zone.manage` (configuration-level) | Per access point | No — and `is_active` is ignored by the scan path (`107` B1) | Too broad for a supervisor | A supervisor-level "close access point" action |
| Emergency deploy | Not a platform permission | CI/CD | No — push to `main` deploys | n/a | Protected production environment plus a freeze check |
| Export approval | `report.export`, `attendee.export`, `order.export` | Per event, per data class | Partial — nothing reads permissions | Per event | Four-eyes approval and per-class opt-in (`51` principle 5) — no mechanism |
| Device suspension | `device.manage` | Per event | No — no device endpoints (`40`) | Yes | — |

**Summary.** The 43 seeded names cover most decision rights. Three have **no** permission (G3,
waivers, restricted incident view); two need **sub-event scope** the `event_users` model cannot
express (per accreditation type, per zone); one lives **outside the platform** (deploys). **None is
enforceable until ARZ-011**, because no code reads the tables. Until then, the RACI is enforced by
people, and the platform's records are the audit.

## Who watches the command center

A command center with nobody in front of it is theatre (`53`). A Command Center Operator is in the
seat from T-3h until every device has synced after last exit — one per shift, two when there are
more gates than one person can scan by eye (threshold `UNVERIFIED`; set after the pilot). The CCO
**dispatches, does not fix**: alerts go to the CCO, the CCO radios the owner, the owner acts.

Before `53` exists (ARZ-170) the CCO watches the check-in list stats per list — **counts only**: the
throughput gauge cannot exceed 4 per minute (`51` R7) — Sentry alerts relayed by ENG, and radio
reports. That is thin, and the runbook (`107`) is written for it.

## On-call during events

- **ENG on-call from T-24h until reconciliation at T+1d**, reachable by phone, not only by chat —
  chat dies with the venue network. Acknowledgement targets follow `60`: SEV1 immediately, SEV2
  within 5 minutes.
- **Nothing pages anyone today.** The failed-jobs monitor writes a log line (`Kernel.php:21-26`);
  Sentry's alert routing lives outside the repository (`UNVERIFIED`). Until `86` defines paging, the
  on-call engineer watches Sentry during operating hours.
- **Whether ARZO has more than one engineer able to be on call is `UNVERIFIED`** (`120` R9). One
  person on call for a multi-day event is a risk to record at G2, not discover at 03:00.

## Decision: freeze deploys for operated events; two keys to break the freeze

From T-24h until `breakdown_ends_at` (`56`), no production deploy and no device-app update. Breaking
the freeze requires: a SEV1 or SEV2 caused by a platform defect, no workaround in the runbook, a
minimal change, and **both** the Event Director (business risk) and the Engineering Lead (technical
risk). Device apps are never updated during operating hours; on a multi-day event, overnight only,
after the golden vectors (`37`) pass on the new build.

Today nothing enforces this: every push to `main` deploys production (`deploy.yml:73-75`). The fix
is independent of the roadmap — a protected production environment with required reviewers, and a
check against a published freeze calendar.

The honest limit: this is a **multi-tenant** platform. A freeze for ARZO's event freezes every
tenant, and a SaaS tenant's event may be live at any hour. "Never deploy during an event" (`85`,
`123`) is only satisfiable for ARZO-operated events plus a published calendar; beyond that, safety
comes from feature flags on check-in and access paths (`123`), not from freezes.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| M1 | **Production deploys on every push to `main`**, with no approval step and no freeze | `deploy.yml:3-8,73-75` | Medium — a routine merge can land mid-event. **Fix now** |
| M2 | No permission exists for the G3 decision or for waiving a blocking readiness item | `2026_09_29_000007:20-37` | Low today — nothing enforces permissions; add before ARZ-204 |
| M3 | No `09` role bundles `access.override` or `device.manage` for event staff | `09` role table | Low — resolve with ARZ-011 |

## Open questions

- **Is the tacit model different from this one?** If ARZO already runs events with other titles or splits, map those onto the roles above rather than renaming people.
- **Does the client sit in the G3 meeting?** Government clients may require co-signature (`59`); record it as gate evidence when the contract says so.
- **Who is the Data Protection Lead?** Export approval and breach assessment need a named person, not a function.
- **Can one person hold two roles?** At small events, yes — ED and DM commonly merge. Never ED and EL for an emergency deploy: the two keys must be two people.

## Related

`09-permissions-and-roles.md` · `56-event-operations.md` · `57-manpower-and-staffing.md` ·
`59-event-readiness.md` · `60-incident-management.md` · `53-live-event-command-center.md` ·
`86-monitoring.md` · `123-release-strategy.md` · `85-deployment.md` · `105-event-lifecycle.md` ·
`107-event-day-runbook.md` · `131-event-readiness-checklist.md`
