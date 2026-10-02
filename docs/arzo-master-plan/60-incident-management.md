# Incident Management

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P3 (ARZ-202) · **Phase:** 5
**Depends on:** `25-zones-and-permissions.md`, `53-live-event-command-center.md`, `44-push-notifications.md`, `67-audit-logging.md`
**Blocks:** `108-post-event-closeout.md` (incident report)

---

## Current state — `MISSING`

`CONFIRMED`: no incident entity; searches for incident, runbook and escalation return nothing but
the seeded `incident.manage` permission, which nothing reads.

The audit also checked what an incident record could lean on:

| Fact | Evidence | Relevance |
|---|---|---|
| `event_logs` exists and **has never been written** — no model, no repository, 0 rows | `schema.sql:127`; only the anonymizer and hard-delete touch it | Not a usable audit trail today |
| `event_logs.entity_type` is **`bigint`** | Live DB | A type error: an entity type is a name. Unusable as designed. |
| `order_audit_logs` has **no actor column** | Live DB | It records *what* changed, not *who* changed it |
| Organizer edits in the admin UI are not audited at all | `OrderAuditAction` writers are self-service, jobs and one capacity override | |
| State-machine precedents exist: spam review, message review, account deletion | `EventSpamCheckJob`, `ApproveMessageHandler`, `AccountDeletionService` | Patterns to follow, including their gaps (no reject route for messages) |
| No realtime, no push, no in-app notifications for organizers | `53`, `44` | Nothing can alert a supervisor today |

`67` owns the audit trail. This document notes that `67` has less to build on than earlier revisions
implied: the one generic log table is unused and wrongly typed.

## What an incident record must hold

Location matters for response — the scaffold's point, and the reason the space model comes first.

```
incidents
  id, short_id, event_id,
  incident_type,     -- SECURITY | MEDICAL | ACCESS | CROWD | TECHNICAL
                     -- LOST_PROPERTY | SAFEGUARDING | FACILITIES | OTHER
  severity,          -- SEV1 | SEV2 | SEV3 | SEV4
  status,            -- OPEN | ACKNOWLEDGED | IN_PROGRESS | RESOLVED | CLOSED
  title, summary,
  zone_id NULL, access_point_id NULL, room_id NULL, location_note NULL,
  reported_by_user_id NULL, reported_by_person_id NULL,
  owner_user_id NULL,
  occurred_at timestamptz, reported_at timestamptz,
  acknowledged_at NULL, resolved_at NULL, closed_at NULL,
  source,            -- MANUAL | ALERT | STAFF_APP
  sensitivity,       -- STANDARD | RESTRICTED
  client_generated_id uuid UNIQUE NULL,    -- offline capture from the staff app
  timestamps

incident_entries     -- append-only timeline
  id, incident_id, entry_type,   -- NOTE | STATUS_CHANGE | ASSIGNMENT | ESCALATION | ATTACHMENT
  body NULL, from_status NULL, to_status NULL,
  author_user_id NULL, created_at

incident_links       -- what the incident concerns
  id, incident_id,
  subject_type,      -- CREDENTIAL | PERSON | DEVICE | ACCESS_LOG | SESSION | PRINTER
  subject_id, created_at
```

- **The timeline is append-only.** An incident record is evidence; corrections are new entries,
  not edits — the same principle as `access_logs` (`71`: reconcile by surfacing, never rewrite).
- **Links reach into the evidence.** A cloned-badge incident links the credential and the two
  `access_logs` rows that revealed it; a gate-failure incident links the device.
- `occurred_at` and `reported_at` are separate for the same reason as in `access_logs`: things are
  reported late.

## Severity and escalation

| Severity | Example | Acknowledge within | Notify |
|---|---|---|---|
| SEV1 | Crowd crush risk, medical emergency, security threat | **Immediately** — and venue procedures take over | Event director, venue security |
| SEV2 | Gate down with a queue, capacity breach, printer fleet failure | 5 min | Duty manager, area supervisor |
| SEV3 | Single device offline, denied VIP, lost property of value | 15 min | Area supervisor |
| SEV4 | Minor facilities issue | Best effort | Owner |

Escalation is automatic when acknowledgement is overdue: notify the next level by push to the staff
app and by SMS (`43`, `44`). **Life-safety incidents are handled by the venue's emergency procedures
and the PA, not by this software**; SEV1 here means *record and notify*, never *manage*.

## Incidents from alerts — with a human in the loop

`53` raises alerts: denial spike, device offline, capacity breach, queue threshold. An alert can
**propose** an incident; a supervisor **confirms** it.

- **Group, do not multiply.** Two hundred denials at one gate are one alert and at most one incident.
  Alerts are keyed by condition and location, and repeat firings attach to the open alert.
- **Never auto-close** an incident because the triggering metric recovered. Someone confirms it is
  resolved and says why.

`133` A9 concluded automated incident detection is thresholds, not AI. That stands.

## Sensitive incidents

Medical and safeguarding incidents contain **health data and data about minors** — special-category
data under PDPL and GDPR. Qatar's PDPL (Law 13/2016, Article 16) goes further: such data may be
processed **only after permission from the Competent Department** (`65`). Until that question is
answered, `MEDICAL` and `SAFEGUARDING` do not ship to production; the record says "medical assistance
given at location X" with no health detail and no name.

- `sensitivity = RESTRICTED` limits visibility to named roles; the command center shows only that a
  restricted incident exists at a location.
- Free text is where sensitive data leaks. Restricted incidents carry a warning on the summary
  field.
- A shorter, explicit retention period per incident type (`65`).
- Every view of a restricted incident is audited (`67`).

## Capture on the floor

Supervisors raise incidents from the staff app (`97`) with a photo and their location pre-filled
from their assigned position (`57`). Offline capture uses the standard `client_generated_id` pattern
(`71`) — an incident noticed in a hall with no signal must not be lost.

## Venue security and emergency services

**Out of software scope, with a documented interface.** ARZO does not integrate with venue security
systems or emergency services. The interface is procedural: who calls whom (`106`, `107`), and an
incident export in a format the venue's security lead accepts. A software integration can follow if a
venue offers one.

## Post-event

The incident report — counts by type and severity, time to acknowledge and resolve, and the list of
SEV1 and SEV2 incidents with their timelines — is part of the closeout bundle (`108`). Restricted
incidents appear as counts only.

## Open questions

- **Who may see restricted incidents?** A named medical lead and the event director, or a role? Needs `09`'s per-event roles.
- **Retention per type** — medical and safeguarding shortest, security longest? `65`.
- **Does a client receive the incident report?** Counts yes; timelines only by agreement.
- **Lost property** — genuinely an incident type, or a separate small log? It has a different lifecycle (found → claimed). Start as a type; split if it grows.

## Related

`53-live-event-command-center.md` · `25-zones-and-permissions.md` · `43-sms-notifications.md` ·
`44-push-notifications.md` · `57-manpower-and-staffing.md` · `65-privacy-gdpr.md` ·
`67-audit-logging.md` · `97-onsite-operations-app.md` · `106-event-operating-model.md` ·
`107-event-day-runbook.md` · `108-post-event-closeout.md` · `133-ai-capabilities.md`
