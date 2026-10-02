# On-Site Operations App

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New — a role-gated mode of the staff app, not a separate app · **Priority:** P2 (ARZ-170) + P3 (ARZ-202, ARZ-200) · **Phase:** 5, on the Phase 4 staff app
**Depends on:** `53-live-event-command-center.md`, `92-staff-platform.md`, `94-mobile-scanner.md`, `60-incident-management.md`, `44-push-notifications.md`
**Blocks:** nothing directly

---

The supervisor's tool on the floor: a mobile slice of the command center (`53`) plus the ability to
act — override a denial, raise an incident, get a badge reprinted, move staff. It has to work when the
venue network is degraded, because that is when supervisors need it most.

## Current state — `MISSING`, 0%, with the override record half-ready

| Fact | Evidence |
|---|---|
| No supervisor or operations UI of any kind | Search of `frontend/src` |
| No realtime transport: `BROADCAST_DRIVER=log`, `BroadcastServiceProvider` commented out | `.env.example:46` at `e7228c1d`; `config/app.php:242` |
| No incidents, positions, shifts or push subscriptions tables | Live DB, `to_regclass` |
| **`AccessResult::GRANTED_OVERRIDE` exists**; `access_logs` has `override_by` and `override_reason` | `AccessResult.php:17`; live DB |
| `override_by` references `users` — a supervisor signed in by credential cannot be recorded | Live DB FK; `92` SP2 |
| `access.override`, `incident.manage`, `badge.reprint`, `credential.revoke` are seeded, unread | Live DB `permissions` |
| `POST /events/{id}/credentials/{id}/revoke` — uncommitted, in progress at audit time | `api.php:623` (working tree) |

The data model for an override exists; the actor, the transport and every other action do not.

## Decision: a mode of the staff app, not a third app

The scaffold asked whether this is a separate app or a role-gated view of the staff app. **A mode.**

| Argument | Weight |
|---|---|
| Same fleet, enrolment, offline store, operator sign-in and release train as the gate app (`94`) | Decisive — a separate app doubles `123`'s device channels for a few dozen users |
| Supervisors also scan — they cover gates at breaks and at surges | Strong |
| Overrides happen **at the gate device** where the denial occurred, not on a second device | Strong — see below |
| Objection: a defect in ops code could affect gate mode | Real. Gate mode never imports ops code; ops mode ships behind a per-event flag (`123`) |

Supervisors who also hold platform logins use the **web** command center (`53`, ARZ-170) on a laptop
in the ops room. That is the same screen, online. It is not a third surface either.

## How the mode unlocks — two keys

Ops mode requires **both** a device whose type carries the ops scopes (`TABLET`, `40`) **and** an
operator signed in with `operator_role = SUPERVISOR` (`92`). A supervisor badge scanned on a gate
handheld unlocks nothing beyond that handheld's scopes; a steward on a supervisor tablet sees gate
mode only.

**The exception is the override itself.** `40` gives `access.override` only to `TABLET`. That makes a
supervisor fetch their own tablet for every exception at a gate. Instead: `SCANNER` devices also
carry `access.override`, usable **only for one action, while a `SUPERVISOR` credential is scanned for
it** — a supervisor tap. The steward stays signed in; the override row names both.

New device scopes, to add alongside `40`'s: `operations.view`, `incident.report`,
`badge.reprint_request`, `credential.suspend`, `staff.reassign` — unnumbered, add to `136` when
scheduled.

## What the mode shows

`53`'s five questions, filtered to **my area** — the zones of the supervisor's position (`57`), or
chosen:

| Question | Shown |
|---|---|
| Are people getting in? | Per gate: throughput, queue state `FLOWING`/`BUSY`/`CONGESTED` (`20`), denials by reason category |
| Is the equipment working? | Devices: online, last sync, emergency mode; desk printers: status and queue (`39`) |
| Where are people? | Zone occupancy against capacity (`25`) — marked provisional while any device is unsynced (`52`) |
| What is going wrong? | Open incidents by severity; **restricted incidents as existence only** (`60`); alerts assigned to me |
| Who is working? | Rostered vs signed-in per position; no-shows at 15 minutes (`57`) |

**Every figure carries its age.** Online, a Reverb channel (`private-event.{id}.operations`, ARZ-104)
with a compact polled snapshot as fallback. Offline, the last snapshot with "as of HH:MM" and this
device's own scans — it cannot know other gates, and must not pretend to.

## Actions and what each does offline

| Action | Online | Offline | Recorded as |
|---|---|---|---|
| **Override a denial** | Local and immediate | **Identical — local and immediate** | `access_logs` `GRANTED_OVERRIDE`, `override_by_credential_id`, reason category + text |
| **Raise an incident** | Created; alerts fan out | Captured with photo and location from the position; queued; banner: "Not yet sent — use the radio for anything urgent" | `incidents` with `client_generated_id`, `source = STAFF_APP` (`60`) |
| Acknowledge / add to an incident | Timeline entry | Queued; concurrent status changes are both kept — the timeline is append-only | `incident_entries` |
| **Request a reprint** | New badge + job; the desk's print host pulls it (`39`) | Queued; "send the person to the desk if urgent" — the desk prints offline itself (`96`) | `badges`, `badge_print_jobs`, reason |
| **Suspend a credential** (lost badge, misuse) | Status change; deny-list delta prioritized (`71`) | Applied to **this device's** deny-list at once; queued; "effective on this device only until sync" | Credential status + audit (`67`) |
| **Reassign staff** | Assignment moved; the exclusion constraint may refuse — shown | Queued. The move happens anyway: the steward signs in at the new gate and that scan is the record | `shift_assignments` (`57`) |

Two principles carry the table. **Nothing a supervisor needs to keep a door moving waits for the
network** — overrides are local by design, matching `59`'s "the system never blocks doors". And
**nothing that changes shared state pretends to have happened** when it has not: suspensions and
reassignments say plainly where they are effective.

## Offline store — additions to `94`'s

```
ops_snapshot     aggregates only, as_of timestamp          -- no personal data
team             staff display names, positions, signed-in state, from sync
incidents_local  open, non-restricted incidents in my area: id, type, severity, status, location
outbox           + incidents, incident entries, suspension and reprint requests, reassignments
incident_photos  files encrypted with the store key; deleted once the server confirms receipt
```

## Alerts

- Native push (FCM/APNs) to the **device and its signed-in operator**, per `44` step 4, and monitored:
  a missed incident alert is an operational failure (`44`), so delivery is logged per recipient
  (`69`).
- SEV1/SEV2 escalation by SMS when acknowledgement is overdue (`60`, `43`). Push alone is not enough.
- **Push is not a safety system** (`44`). Life-safety runs on the venue PA and radio.
- `44`'s `push_subscriptions.subscriber_type` is `ATTENDEE | USER`; a managed device is neither. It
  needs `DEVICE`, with the operator resolved at send time.

## Release, telemetry, tests

| Concern | Approach |
|---|---|
| Release | Rides the staff app's train and freeze (`94`, `123`); the mode is a per-event flag |
| Telemetry | `94`'s heartbeat plus: ops-mode sessions, alert-to-acknowledge latency, incidents captured offline and their sync delay, overrides per access point |
| Tests | Fakes for the alert stream and snapshot endpoint; offline E2E — override and incident with photo while partitioned, then sync, then both appear in the reconciliation report (ARZ-102) with credential attribution |
| Drill | `59`'s offline drill includes one supervisor override and one offline incident; `107` carries the procedure |

Overrides per supervisor are personal data about workers. The fleet view shows them per access
point; per-person counts are for the event director (`57`, `133` A11).

## Build order

| Stage | Content | Backlog |
|---|---|---|
| O1 | Supervisor tap-to-override on gate devices; credential attribution | With ARZ-152 (`94` N4); unnumbered |
| O2 | Ops mode, read-only: snapshot endpoint, device channel authorization, Reverb | ARZ-170, ARZ-104 |
| O3 | Offline incident capture with photo | ARZ-202 |
| O4 | Reprint request; credential suspension | ARZ-073; unnumbered |
| O5 | Native push, monitored; SMS escalation | ARZ-141, ARZ-140 |
| O6 | Reassignment | ARZ-200 |

**Backlog note:** `136` makes ARZ-141 depend on ARZ-150, the attendee app. Staff push must not wait for
attendee push (`44`); `116` already splits it ("web push first; staff native push with Phase 4
apps"). The dependency in `136` should follow.

## Open questions

- **Log-only mode at a gate?** Under crowd pressure a gate opens whatever the software says. A supervisor action that records "admitted without check" from now until closed keeps the count honest, and proposes a SEV2 incident. Leaning yes; it is `59`'s principle applied at one door.
- **Override reason categories** — a fixed list (VIP, staff error, equipment, other) plus free text. Confirm with operations (`106`).
- **Tablets or phones for supervisors?** A procurement question (`102`); the mode is designed for a phone.
- **Restricted incidents on devices** — can a named medical lead see them in the field? Needs `09`'s per-event roles and `60`'s visibility rules; until then, existence only.

## Related

`53-live-event-command-center.md` · `92-staff-platform.md` · `94-mobile-scanner.md` ·
`60-incident-management.md` · `44-push-notifications.md` · `43-sms-notifications.md` ·
`40-device-management.md` · `57-manpower-and-staffing.md` · `71-realtime-architecture.md` ·
`59-event-readiness.md` · `39-printer-integration.md` · `96-kiosk-application.md` ·
`107-event-day-runbook.md` · `123-release-strategy.md` · `136-master-backlog.md`
