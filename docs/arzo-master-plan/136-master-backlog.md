# Master Backlog

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for work items · **Audit date:** 2026-09-28

---

## How to read this

**Priority is not implementation order.** A P0 item may be blocked by a P1 foundation. Order comes
from `113-roadmap.md`; priority expresses business importance.

| Priority | Meaning |
|---|---|
| **P0** | Critical foundation, or a live defect. Blocks other work or carries present risk. |
| **P1** | Core platform. Required for ARZO to run an event end to end. |
| **P2** | Competitive parity — what Evento advertises. |
| **P3** | Differentiation — beyond parity. |
| **P4** | Future / advanced. |

Complexity: **S** (days) · **M** (1–2 weeks) · **L** (3–6 weeks) · **XL** (a quarter or more), for
one competent engineer or pair. Dates are deliberately absent.

---

## P0 — Foundation and live defects

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-001 | Stabilize | Fix offline scan loss + dedupe-before-await ordering (F1) | S | — | TODO |
| ARZ-002 | Stabilize | Request-scope SSR query client and axios auth (F5) | M | — | TODO |
| ARZ-003 | Stabilize | Remove `process.env` inlining from client bundle (F6) | S | — | TODO |
| ARZ-004 | Stabilize | Architecture test: no Eloquent above repositories | S | — | TODO |
| ARZ-005 | Stabilize | Architecture test: every non-public Action authorizes (F12) | M | — | TODO |
| ARZ-006 | Stabilize | Cross-tenant 403 test suite (F11) | M | — | TODO |
| ARZ-007 | Stabilize | Frontend test runner + CI lint/typecheck (F9) | M | — | TODO |
| ARZ-008 | Stabilize | Align queue names dev/prod/e2e; pin Postgres (F7, F13) | S | — | TODO |
| ARZ-010 | Foundation | `persons` table + `attendees.person_id` | M | — | TODO |
| ARZ-011 | Foundation | RBAC/ABAC model replacing the 3-role enum | L | ARZ-005 | TODO |
| ARZ-012 | Foundation | Per-event roles and assignments | M | ARZ-011 | TODO |
| ARZ-013 | Foundation | Tenant global scope replacing static state (F11) | M | ARZ-006 | TODO |
| ARZ-020 | Space | `venues`, `buildings`, `floors` | M | — | TODO |
| ARZ-021 | Space | `zones` with nesting + `access_points` | M | ARZ-020 | TODO |
| ARZ-022 | Space | `rooms`; link to zones | S | ARZ-021 | TODO |
| ARZ-023 | Space | `event_venues` join + backfill from `event_locations` | S | ARZ-020 | TODO |
| ARZ-030 | Time | `tracks`, `speakers` | S | — | TODO |
| ARZ-031 | Time | `sessions` + GiST room-overlap exclusion constraint | L | ARZ-022, ARZ-030 | TODO |
| ARZ-032 | Time | `session_speakers`, `session_products` | S | ARZ-031 | TODO |
| ARZ-040 | Access | `access_logs` append-only table | M | ARZ-021 | TODO |
| ARZ-041 | Check-in | Consolidate the two check-in write models (F10) | L | ARZ-040 | TODO |

## P1 — Core platform

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-050 | Accreditation | `accreditation_types` + type rules | M | ARZ-021 | TODO |
| ARZ-051 | Accreditation | `accreditations` application + approval workflow | L | ARZ-050, ARZ-011 | TODO |
| ARZ-052 | Accreditation | `credentials` + one-of CHECK constraint | M | ARZ-051, ARZ-010 | TODO |
| ARZ-053 | Accreditation | Backfill credentials for existing attendees | S | ARZ-052 | TODO |
| ARZ-060 | Access | `access_rules` engine + priority evaluation | L | ARZ-040, ARZ-052 | TODO |
| ARZ-061 | Access | `access_grants` materialization | M | ARZ-060 | TODO |
| ARZ-062 | Access | Anti-passback + re-entry rules | M | ARZ-061 | TODO |
| ARZ-063 | Access | Derived zone occupancy + snapshot cache | M | ARZ-061 | TODO |
| ARZ-064 | Access | Rule simulator ("would this badge get in?") | M | ARZ-060 | TODO |
| ARZ-070 | Badges | `badge_templates` + drag-drop designer | L | ARZ-052 | TODO |
| ARZ-071 | Badges | Server-side PDF render pipeline (replaces F8) | L | ARZ-070 | TODO |
| ARZ-072 | Badges | `badge_print_jobs` queue + failure recovery | M | ARZ-071 | TODO |
| ARZ-073 | Badges | Reprint, void, badge history | M | ARZ-072 | TODO |
| ARZ-074 | Badges | Photo capture at the desk | M | ARZ-052 | TODO |
| ARZ-080 | Sessions | Session registration + capacity | M | ARZ-031 | TODO |
| ARZ-081 | Sessions | Session waitlist — extend `waitlist_entries` | M | ARZ-080 | TODO |
| ARZ-082 | Sessions | `session_attendance` + session check-in | M | ARZ-031, ARZ-040 | TODO |
| ARZ-083 | Sessions | Agenda UI + conflict detection | L | ARZ-031 | TODO |
| ARZ-084 | Sessions | Session + agenda ICS export | S | ARZ-031 | TODO |
| ARZ-090 | API | API keys, scopes, rate limits | L | ARZ-011 | TODO |
| ARZ-091 | API | Versioned webhook payload boundary (F14) | M | ARZ-090 | TODO |
| ARZ-092 | API | Device-scoped keys | M | ARZ-090 | TODO |
| ARZ-100 | Offline | Device registry + enrolment | M | ARZ-092 | TODO |
| ARZ-101 | Offline | Local store + sync protocol | XL | ARZ-100, ARZ-061 | TODO |
| ARZ-102 | Offline | Conflict resolution + reconciliation reporting | L | ARZ-101 | TODO |
| ARZ-103 | Offline | Emergency/degraded mode UX | M | ARZ-101 | TODO |
| ARZ-104 | Realtime | Reverb transport + channel authorization | L | ARZ-011 | TODO |

## P2 — Competitive parity

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-110 | On-site | Kiosk app: self check-in | L | ARZ-101 | TODO |
| ARZ-111 | On-site | Walk-in registration at the door | M | ARZ-110 | TODO |
| ARZ-112 | On-site | Queue management + wait estimates | M | ARZ-063 | TODO |
| ARZ-120 | Hardware | Scanner abstraction (camera, USB, BT, dedicated) | L | ARZ-100 | TODO |
| ARZ-121 | Hardware | Printer abstraction (thermal, badge, network) | L | ARZ-071 | TODO |
| ARZ-122 | Hardware | RFID/NFC read + encode | XL | ARZ-120, ARZ-052 | TODO |
| ARZ-123 | Hardware | Device health + fleet dashboard | M | ARZ-100, ARZ-104 | TODO |
| ARZ-130 | Exhibitors | Exhibitor entity + portal | L | ARZ-011 | TODO |
| ARZ-131 | Exhibitors | Booth assignment | M | ARZ-021, ARZ-130 | TODO |
| ARZ-132 | Exhibitors | Staff passes | M | ARZ-052, ARZ-130 | TODO |
| ARZ-133 | Exhibitors | Lead capture + export | L | ARZ-132 | TODO |
| ARZ-134 | Exhibitors | Lead qualification + scoring | M | ARZ-133 | TODO |
| ARZ-140 | Messaging | SMS provider integration | M | — | TODO |
| ARZ-141 | Messaging | Push notification infrastructure | L | ARZ-150 | TODO |
| ARZ-142 | Messaging | Email segmentation / filter builder | L | — | TODO |
| ARZ-150 | Mobile | Attendee app: ticket, agenda, notifications | XL | ARZ-083 | TODO |
| ARZ-151 | Mobile | Venue map | L | ARZ-021, ARZ-150 | TODO |
| ARZ-152 | Mobile | Native scanner app | L | ARZ-101 | TODO |
| ARZ-160 | Seating | Seat maps + assignment | L | ARZ-022 | TODO |
| ARZ-170 | Analytics | Live command center | L | ARZ-104, ARZ-063 | TODO |
| ARZ-171 | Analytics | Attendance + no-show + dwell reporting | M | ARZ-040 | TODO |
| ARZ-172 | Analytics | Session attendance analytics | M | ARZ-082 | TODO |
| ARZ-173 | Analytics | Demographics | M | ARZ-010 | TODO |
| ARZ-180 | CRM | HubSpot / Salesforce integration | L | ARZ-090 | TODO |
| ARZ-190 | Registration | RSVP as a distinct flow | M | — | TODO |
| ARZ-191 | Payments | Additional payment providers | L | — | TODO |

## P3 — Differentiation

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-200 | Ops | Staff, shifts, assignments | L | ARZ-011 | TODO |
| ARZ-201 | Ops | Tasks + checklists | M | ARZ-200 | TODO |
| ARZ-202 | Ops | Incident management | M | ARZ-104 | TODO |
| ARZ-203 | Ops | Vendors + procurement | M | — | TODO |
| ARZ-204 | Ops | Event readiness gates + go/no-go | M | ARZ-201 | TODO |
| ARZ-210 | Networking | Attendee networking + meetings | L | ARZ-150 | TODO |
| ARZ-211 | Engagement | eRaffle | S | ARZ-150 | TODO |
| ARZ-212 | Engagement | Live polls + Q&A | M | ARZ-104, ARZ-150 | TODO |
| ARZ-220 | Intelligence | Predictive attendance | L | ARZ-171 | TODO |
| ARZ-221 | Intelligence | Predictive queues | M | ARZ-112 | TODO |
| ARZ-222 | Intelligence | Automated post-event reports | M | ARZ-171 | TODO |
| ARZ-223 | Intelligence | Venue heatmaps | M | ARZ-151, ARZ-040 | TODO |
| ARZ-230 | Enterprise | SSO — SAML/OIDC | L | ARZ-011 | TODO |
| ARZ-231 | Enterprise | SCIM provisioning | M | ARZ-230 | TODO |

## P4 — Future

| ID | Epic | Item | Cx | Notes |
|---|---|---|---|---|
| ARZ-240 | AI | Event assistant | L | Assess first — `133` |
| ARZ-241 | AI | Intelligent lead scoring | M | Needs lead volume to train on |
| ARZ-242 | AI | Schedule optimization | L | Needs conflict data |
| ARZ-243 | AI | Staffing recommendations | M | Needs historical shift data |
| ARZ-250 | Platform | Event digital twin | XL | Emergent from `06` — `29` |
| ARZ-251 | Platform | Vendor marketplace | XL | Business model decision first |
| ARZ-260 | Access | Face recognition | XL | **Legal review first** — Qatar PDPL biometrics |

---

## Item template

Every item, when picked up, is expanded into a work package per `137-engineering-work-packages.md`:

```
ID · Epic · Priority · Complexity
Objective            — one sentence
Current state        — with CONFIRMED/MISSING evidence
Target state
Dependencies         — item IDs
Database changes
Backend changes
Frontend changes
API changes
Infrastructure changes
Security requirements
Testing requirements
Migration requirements
Rollout strategy
Rollback strategy
Acceptance criteria  — per 138
Definition of done   — per 128, with any waivers recorded
```

## Related

`113-roadmap.md` (order) · `137-engineering-work-packages.md` · `138-acceptance-criteria.md` ·
`119-dependency-map.md` · `128-definition-of-done.md`
