# Master Backlog

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for work items · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Progress:** Phase 0 complete (ARZ-001 … ARZ-008) — but its tests run in no CI, because ARZO's code
has no repository of its own (ARZ-300, `123`). Phase 1 additive schema complete (ARZ-010, ARZ-020–023,
ARZ-030–032, ARZ-040); the `persons` backfill in ARZ-010 is defective at scale (ARZ-301). Remaining
Phase 1: ARZ-011 RBAC, ARZ-012 per-event roles, ARZ-013 tenant global scope, ARZ-041 check-in
consolidation — none started.

**Phase 2 started early:** schema for accreditation, credentials, access rules and grants, badges,
devices, RSVP and snapshots is live (109 tables). The access engine core landed in `c34f6a59` and
`e7228c1d`: the pure `AccessDecisionService` (35 tests), `GrantMaterializationService`,
`CredentialIssuanceService` and `AccessScanService` (11 DB tests). Models and repositories for all 31
new tables landed in `602b2b5a`. CRUD for space, programme, accreditation types and credentials was
in progress, uncommitted, at audit time.

**Hardening track added 2026-09-29:** live defects found while writing documents 13–140, as the
ARZ-300 series below. They run alongside the phases (`113`).

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

## Hardening track — ARZ-300 series

Live defects and must-fix-first corrections found while writing documents 13–140 against the code at
`e7228c1d`. They run alongside the phases (`113`). "Before X" means the item gates X, not that it
waits for it. Each item's evidence is in the cited document.

| ID | Priority | Item | Cx | When | Doc | Status |
|---|---|---|---|---|---|---|
| ARZ-300 | **P0** | ARZO-owned private repository; CI running; branch protection; upstream `deploy.yml`/`post-release-push-images.yml`/`cla.yml` disabled; `SECURITY.md` contact changed | S | **Now** | `123`, `83`, `129` | TODO |
| ARZ-301 | **P0** | `persons` backfill skips alternate batches — `chunkById` + idempotent command + zero-null assertion | S | **Now** | `122`, `137` | TODO |
| ARZ-305 | **P0** | Messaging: "send test" reaches real customers; new accounts default to Premium tier; scheduled sends bypass limits; no reject route | S | **Now** | `42` | TODO |
| ARZ-317 | **P0** | Licence and attribution: rebrand hides the required "Powered by" footer (string `"false"` is truthy); no AGPL §13 source offer; licence purchase decision | S + business | **Now** | `98`, `66` | TODO |
| ARZ-302 | **P0** | Access time windows evaluate in UTC, not venue time | S | Before any rule UI and before golden vectors | `115`, `94` | **DONE** — daily and day-of-week windows convert to venue time; absolute windows stay instants |
| ARZ-303 | **P0** | `persons` ID document and date-of-birth fields stored in plaintext | S | Before any ID-collecting accreditation type | `65`, `115` | TODO |
| ARZ-307 | **P0** | Scan-path occupancy: computed every scan, cost grows with the zone's history — make conditional, aggregate in SQL, read snapshots | M | Before any live access scanning | `74`, `75`, `125` | TODO |
| ARZ-313 | **P0** | Credential identifier format: versioned prefix, upper-case, case-insensitive resolution | S | Before the first printed badge | `38` | TODO |
| ARZ-314 | **P0** | Schema corrections while tables are empty: `booths.event_id` + `booth_assignments`; `credential_media`; `session_attendance` direction; `device_id` columns | M | Before the first writer of each table | `35`, `36`, `40`, `54` | TODO |
| ARZ-320 | **P0** | Access rules ignore `subject_type`/`subject_id` — a DENY for one badge closes the zone to all; every ALLOW is copied to every credential | M | Before the rule CRUD merges and before golden vectors | `124` ST1, `115` | **DONE** — both paths match subject; absent subject type ≠ null |
| ARZ-321 | **P0** | Scan endpoint trust: access point not checked against the event or `is_active`; replay lookup crosses tenants; client `occurred_at` and `direction` trusted; `device_id` dropped; concurrent replay → 500 | M | Before the scan route merges | `94`, `107`, `124`, `125` | TODO |
| ARZ-323 | **P0** | Account deletion leaves names and emails in `persons` and never touches credentials, badges, access logs, invitations | M | Before any data-bearing deploy | `108`, `65` | TODO |
| ARZ-304 | P1 | Webhook retries never run (`dispatchSync`); head-of-line blocking; job untested; `DispatchOccurrenceWebhookJob` runs synchronously | S | Soon | `49`, `70` | TODO |
| ARZ-306 | P1 | Stripe webhook accepts before verifying; raw payloads logged on failure | S | Soon | `50`, `124` | TODO |
| ARZ-308 | P1 | Exports cap silently at 10,000 rows; event-report CSV built in the browser without escaping | M | Soon | `51`, `107` | TODO |
| ARZ-309 | P1 | Scanner and public check-in: Arabic keyboard layout breaks the wedge; per-IP limit shared by a venue's scanners; email matched in public search; link can undo check-ins, even on expired lists; camera defects | M | Soon | `38`, `75`, `103` | TODO |
| ARZ-310 | P1 | Consent and SEO: pixels without consent by default; event pages ignore noindex; JSON-LD status; `lang="en"` for every locale; Bunny Fonts as an undisclosed processor | S | Soon | `41`, `81`, `84` | TODO |
| ARZ-311 | P1 | Publish rules server-side; status transition rules; restore statuses on cancelled account deletion | S | Soon | `56` | TODO |
| ARZ-312 | P1 | Accounts: invitation acceptance overwrites global password and name; deactivated SUPERADMIN keeps `/admin` for up to 7 days; impersonation tokens last 7 days, start/stop unlogged, request payloads logged in full | M | Soon | `57`, `89`, `65`, `67` | TODO |
| ARZ-319 | P1 | Audit spine: `audit_events`; retire `event_logs`; actor on order audit | M | Before ARZ-051 | `67` | TODO |
| ARZ-322 | P1 | Self-host defaults let strangers register, auto-verify and take card payments into the installation's Stripe | S | Before any deployment | `100` | TODO |
| ARZ-324 | P1 | Sentry receives organizer email, name and IP on every exception; no browser Sentry SDK | S | Soon | `78`, `86` | TODO |
| ARZ-325 | P1 | Rebrand fit to commit: brand accent fails WCAG AA; SF Pro served as a webfont against its licence; default font no longer loads; cookie banner colours | S + business | Before the rebrand is committed | `88`, `87` | TODO |
| ARZ-326 | P1 | Queue and runtime correctness: Redis `retry_after` shorter than job timeouts; dev/test/E2E run queues synchronously; all-in-one migrates on every start | S | Soon | `70`, `83`, `85` | TODO |
| ARZ-327 | P1 | Ticket integrity: self-service email change leaves the old QR valid; lookup tokens stored in plaintext; public attendee action ignores the event id | S | Soon | `91`, `95` | TODO |
| ARZ-328 | P1 | Admin order search fails on any term (ambiguous `email`) | S | Soon | `72` | TODO |
| ARZ-334 | P1 | **Public check-in list returns every attendee's `public_id` — the ticket QR itself**; anyone holding a list link can mint every ticket | S | Soon | `68` FR1, `38` | TODO |
| ARZ-335 | P1 | Order creation applies promo codes under only the global 180/min limit — hidden VIP and comp products brute-forceable around the 10/min validation throttle | S | Soon | `68` FR2, `46` | TODO |
| ARZ-336 | P1 | Credential lifecycle: `issued_by` never set; a second revocation overwrites the first and leaves grants active; raw identifiers copied into every access log | S | Before revocation ships | `67`, `68` FR3, `94` | TODO |
| ARZ-315 | P2 | Statistics: page views lost without orders; partial batches never flushed; no repair command | S | Later | `52` | TODO |
| ARZ-316 | P2 | Affiliates: money as `double`; no unique `(event_id, code)` | S | Later | `45`, `122` | TODO |
| ARZ-318 | P2 | Webhook secrets plaintext; no rotation; duplicated events reuse them | S | Later | `49` | TODO |
| ARZ-329 | P2 | Money across currencies: 3-decimal currencies record fees ×10; fixed fees charged 1:1 without an exchange-rate key; offline-order fees never collected; default currency for Qatari accounts | M | Before GCC currencies or offline fees | `135`, `99` | TODO |
| ARZ-330 | P2 | Committed contrast failures (warning and tertiary text; dark footer; button text heuristic) | S | Later | `81`, `88` | TODO |
| ARZ-331 | P2 | Localization: Swedish tagged `se`; English month names in Polish/Swedish; server currency formatting in `en-US`; custom email templates have no locale | S | Later | `82` | TODO |
| ARZ-332 | P2 | Auth refresh: client calls GET on a POST route and nothing calls it; public preview trusts the token's role claim | S | Later | `101` | TODO |
| ARZ-333 | P2 | Files: originals keep EXIF and stay public; deleted images stay public; answer exports never deleted; invalid `image_type` returns 500 | S | Later | `73` | TODO |

Twelve of these are P0. Three are present risks today — ARZ-300 (single copy, no CI), ARZ-305
(test sends reach customers), ARZ-317 (attribution suppressed in the working tree). Two strike on the
first deploy to an environment with real data — ARZ-301, ARZ-323. The other seven gate a specific
feature and cost far less before it than after.

## P0 — Foundation and live defects

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-001 | Stabilize | Fix offline scan loss + dedupe-before-await ordering (F1) | S | — | **DONE** |
| ARZ-002 | Stabilize | Request-scope SSR query client and axios auth (F5) | M | — | **DONE** |
| ARZ-003 | Stabilize | Remove `process.env` inlining from client bundle (F6) | S | — | **DONE** |
| ARZ-004 | Stabilize | Architecture test: no Eloquent above repositories | S | — | **DONE** |
| ARZ-005 | Stabilize | Architecture test: every non-public Action authorizes (F12) | M | — | **DONE** |
| ARZ-006 | Stabilize | Cross-tenant 403 test suite (F11) | M | — | **DONE** |
| ARZ-007 | Stabilize | Frontend test runner + CI lint/typecheck (F9) | M | — | **DONE** locally — the workflow has never run for ARZO's code (ARZ-300) |
| ARZ-008 | Stabilize | Align queue names dev/prod/e2e; pin Postgres (F7, F13) | S | — | **PARTLY DONE** — queue names aligned, F13 fixed; dev Postgres still 15; dev/test/e2e run queues synchronously (`83`) |
| ARZ-010 | Foundation | `persons` table + `attendees.person_id` | M | — | **DONE** (schema) — backfill defect → ARZ-301 |
| ARZ-011 | Foundation | RBAC/ABAC model replacing the 3-role enum | L | ARZ-005 | TODO |
| ARZ-012 | Foundation | Per-event roles and assignments | M | ARZ-011 | TODO |
| ARZ-013 | Foundation | Tenant global scope replacing static state (F11) | M | ARZ-006 | TODO |
| ARZ-020 | Space | `venues`, `buildings`, `floors` | M | — | **DONE** |
| ARZ-021 | Space | `zones` with nesting + `access_points` | M | ARZ-020 | **DONE** |
| ARZ-022 | Space | `rooms`; link to zones | S | ARZ-021 | **DONE** |
| ARZ-023 | Space | `event_venues` join + backfill from `event_locations` | S | ARZ-020 | **DONE** |
| ARZ-030 | Time | `tracks`, `speakers` | S | — | **DONE** |
| ARZ-031 | Time | `sessions` + GiST room-overlap exclusion constraint | L | ARZ-022, ARZ-030 | **DONE** |
| ARZ-032 | Time | `session_speakers`, `session_products` | S | ARZ-031 | **DONE** |
| ARZ-040 | Access | `access_logs` append-only table | M | ARZ-021 | **DONE** |
| ARZ-041 | Check-in | Consolidate the two check-in write models (F10) | L | ARZ-040 | TODO |

## P1 — Core platform

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-050 | Accreditation | `accreditation_types` + type rules | M | ARZ-021 | **DONE** (schema) |
| ARZ-051 | Accreditation | `accreditations` application + approval workflow, audited | L | ARZ-050, ARZ-011, ARZ-012, ARZ-319 | TODO |
| ARZ-052 | Accreditation | `credentials` + one-of CHECK constraint; issuance service | M | ARZ-051, ARZ-010 | **DONE** — two sources by design; staff and exhibitor staff come through accreditation (`32`, `57`) |
| ARZ-053 | Accreditation | Backfill credentials for existing attendees — as a command, `chunkById`, grants in a second pass (`122` D2) | S | ARZ-052, ARZ-301 | TODO |
| ARZ-060 | Access | `access_rules` engine + priority evaluation | L | ARZ-040, ARZ-052 | **DONE** — pure decision function, scan service, subject matching, venue-local windows; CRUD exposed over HTTP |
| ARZ-061 | Access | `access_grants` materialization | M | ARZ-060 | **DONE** — subject-gated; grants are a snapshot, so a changed rule needs rematerialisation |
| ARZ-062 | Access | Anti-passback + re-entry rules | M | ARZ-061 | **DONE** in the decision function |
| ARZ-063 | Access | Derived zone occupancy + snapshot cache | M | ARZ-061 | **PARTIAL** — derived per scan, does not scale → ARZ-307; snapshot job not started |
| ARZ-064 | Access | Rule simulator ("would this badge get in?") | M | ARZ-060 | TODO — before any rule UI (`115`) |
| ARZ-070 | Badges | `badge_templates` + **presets first**, canvas deferred (`22`) | L | ARZ-052, ARZ-313 | TODO — schema landed |
| ARZ-071 | Badges | Server-side render, raster-capable, Arabic-tested (replaces F8) | L | ARZ-070 | TODO |
| ARZ-072 | Badges | `badge_print_jobs` queue + failure recovery | M | ARZ-071 | TODO — schema landed |
| ARZ-073 | Badges | Reprint, void, badge history | M | ARZ-072 | TODO |
| ARZ-074 | Badges | Photo capture at the desk | M | ARZ-052 | TODO |
| ARZ-080 | Sessions | Session registration + capacity | M | ARZ-031 | TODO |
| ARZ-081 | Sessions | Session waitlist — **sibling** `session_waitlist_entries` (table created), shared offer policy (`14`) | M | ARZ-080 | TODO — approach changed |
| ARZ-082 | Sessions | `session_attendance` + session check-in, `ENTRY`/`EXIT` vocabulary | M | ARZ-031, ARZ-040, ARZ-314 | TODO |
| ARZ-083 | Sessions | Agenda UI + conflict detection | L | ARZ-031 | TODO |
| ARZ-084 | Sessions | Session + agenda ICS export | S | ARZ-031 | TODO |
| ARZ-090 | API | API keys, scopes, rate limits | L | ARZ-011 | TODO |
| ARZ-091 | API | Versioned webhook payload boundary (F14) | M | ARZ-090, ARZ-304 | TODO |
| ARZ-092 | API | Device-scoped keys (stored on `devices`, shared resolver with API keys) | M | ARZ-090 | TODO |
| ARZ-100 | Offline | Device registry + enrolment, heartbeat, commands | M | ARZ-092, ARZ-314 | TODO — table landed |
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
| ARZ-121 | Hardware | Print host + printer registry + raster adapters (`37`, `39`) | L | ARZ-071 | TODO |
| ARZ-122 | Hardware | RFID/NFC read + encode via `credential_media` (`36`) | XL | ARZ-120, ARZ-052, ARZ-314 | TODO |
| ARZ-123 | Hardware | Device health + fleet dashboard | M | ARZ-100, ARZ-104 | TODO |
| ARZ-130 | Exhibitors | `companies` + `event_exhibitors` + magic-link portal (`32`) | L | ARZ-011 | TODO — approach changed |
| ARZ-131 | Exhibitors | Booth correction + `booth_assignments` (`35`) | M | ARZ-021, ARZ-130, ARZ-314 | TODO — approach changed |
| ARZ-132 | Exhibitors | Staff passes as `EXHIBITOR` accreditations within quota (`32`) | M | ARZ-051, ARZ-130 | TODO — approach changed |
| ARZ-133 | Exhibitors | Lead capture (capture-now-resolve-later) + export, with consent records (`33`, `65`) | L | ARZ-132 | TODO |
| ARZ-134 | Exhibitors | Lead qualification + scoring | M | ARZ-133 | TODO |
| ARZ-140 | Messaging | Phone capture (E.164) first, then SMS/WhatsApp provider behind the `69` channel abstraction (`43`) | M | — | TODO |
| ARZ-141 | Messaging | Push infrastructure — attendee web push with ARZ-150; **staff native push does not wait for the attendee app** (`44`, `97`) | L | — | TODO |
| ARZ-142 | Messaging | Email segmentation / filter builder | L | — | TODO |
| ARZ-150 | Mobile | Attendee app: ticket, agenda, notifications | XL | ARZ-083 | TODO |
| ARZ-151 | Mobile | Venue map | L | ARZ-021, ARZ-150 | TODO |
| ARZ-152 | Mobile | Native scanner app | L | ARZ-101 | TODO |
| ARZ-160 | Seating | Seat maps + assignment — **deferred** until a real event needs it; table seating first (`26`) | L | ARZ-022, ARZ-314 | DEFERRED |
| ARZ-170 | Analytics | Live command center | L | ARZ-104, ARZ-063 | TODO |
| ARZ-171 | Analytics | Attendance + no-show + dwell reporting | M | ARZ-040 | TODO |
| ARZ-172 | Analytics | Session attendance analytics | M | ARZ-082 | TODO |
| ARZ-173 | Analytics | Demographics | M | ARZ-010 | TODO |
| ARZ-180 | CRM | HubSpot / Salesforce integration | L | ARZ-090 | TODO |
| ARZ-190 | Registration | RSVP as a distinct flow | M | — | TODO |
| ARZ-191 | Payments | A Qatar-licensed payment gateway — **raise to P1**: Stripe does not list Qatar as a supported country, so ARZO cannot be merchant of record through it (`98`, `135`) | L | business: gateway choice | TODO |

## P3 — Differentiation

| ID | Epic | Item | Cx | Depends on | Status |
|---|---|---|---|---|---|
| ARZ-200 | Ops | Staff, shifts, assignments — credentials via `STAFF` accreditation (`57`) | L | ARZ-011, ARZ-051 | TODO |
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
| ARZ-250 | Platform | Event digital twin | XL | Emergent, not a project — close as delivered by `53` + ARZ-223 (`132`) |
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
