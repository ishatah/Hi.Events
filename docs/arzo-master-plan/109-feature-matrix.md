# Feature Matrix

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Derived (from `02` and documents 10–108) · **Priority:** — · **Phase:** all
**Depends on:** `02-current-state-audit.md`, `128-definition-of-done.md`
**Blocks:** `140-final-100-percent-checklist.md`

---

## How to read this

One row per capability: status marker, completeness against `128`, the evidence, and the document
that owns it. It answers "do we have X?" in one look. `02` is authoritative for evidence; where they
disagree, `02` wins and this row is stale.

Scores follow `02`: 0 nonexistent · 25 proof of concept · 50 happy path · 75 known gaps · 90
production-capable · 100 every gate met. **Schema-only scores 10** — a table with no behaviour is
foundation, not capability.

## Commerce and registration

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Events, settings, lifecycle | `CONFIRMED` | 85 | No server-side transition rules; publish checks browser-only | `56` |
| Recurring events (occurrences) | `CONFIRMED` | 85 | RRULE + overrides | `11` |
| Products, categories, tiers, early bird | `CONFIRMED` | 90 | `ProductPriceType` incl. `TIERED` | `11`, `13` |
| Checkout, orders | `CONFIRMED` | 90 | Per-event advisory lock | `11` |
| Payments | `PARTIAL` | 60 | Stripe + offline; Razorpay dead; Stripe webhook accepts before verifying | `15`, `50` |
| Invoices, VAT | `CONFIRMED` | 85 | Order-bound only | `15` |
| Promo and access codes | `CONFIRMED` | 90 | No bulk single-use generation | `46` |
| Affiliates | `CONFIRMED` | 75 | Money as `double`; no unique code index | `45` |
| Waitlist (products) | `CONFIRMED` | 85 | | `14` |
| Capacity pools | `CONFIRMED` | 85 | | `14` |
| Custom questions | `CONFIRMED` | 85 | Frontend lacks `PHONE` | `10`, `43` |
| Embeddable widget | `CONFIRMED` | 85 | | `10` |
| RSVP as a distinct flow | `PARTIAL` | 10 | `invitations`, `rsvp_responses` schema only | `12` |
| Order source attribution (UTM) | `MISSING` | 0 | `account_attributions` is platform signup attribution | `41` |

## People and identity

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Attendee management + export | `CONFIRMED` | 80 | Exports silently cap at 10,000 rows | `16`, `51` |
| Person identity (`persons`) | `PARTIAL` | 30 | Schema + model; **backfill skips alternate batches**; ID fields plaintext | `23`, `122` |
| Speakers | `PARTIAL` | 10 | Schema; CRUD in progress, uncommitted | `28` |
| Staff, shifts | `MISSING` | 0 | | `57` |
| Companies, exhibitors | `MISSING` | 0 | | `32` |
| Sponsors | `MISSING` | 0 | | `34` |
| Vendors | `MISSING` | 0 | | `61` |

## Space and programme

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Venues, buildings, floors | `PARTIAL` | 25 | Schema, backfill, models; CRUD uncommitted | `25` |
| Zones, access points | `PARTIAL` | 25 | As above | `25` |
| Rooms | `PARTIAL` | 25 | As above | `25` |
| Booths | `PARTIAL` | 10 | Schema, **wrong-shaped for per-event allocation** | `35` |
| Seating | `PARTIAL` | 10 | Schema; feature deferred | `26` |
| Sessions, tracks | `PARTIAL` | 25 | Schema with GiST room-overlap; CRUD uncommitted | `27` |
| Session registration + waitlist | `PARTIAL` | 10 | Schema incl. sibling waitlist table | `27`, `14` |
| Session attendance | `PARTIAL` | 10 | Schema; direction vocabulary mismatch | `54` |
| Agenda, conflicts, ICS | `PARTIAL` | 15 | Event-level ICS exists; GiST done | `29` |

## Accreditation, credentials, access

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Accreditation types | `PARTIAL` | 15 | Schema; CRUD uncommitted | `23` |
| Applications + approval | `PARTIAL` | 10 | Schema only | `23` |
| Credentials | `PARTIAL` | 40 | Issuance service, random identifiers; no backfill, no format prefix | `23`, `38` |
| Access rules + grants | `PARTIAL` | 45 | Pure decision function (35 tests), materialization, scan service (11 tests); UTC windows defect | `24`, `115` |
| Access logs, re-entry, anti-passback | `PARTIAL` | 40 | Written by scan service; not by either check-in path; no `device_id` | `24`, `18` |
| Zone occupancy | `PARTIAL` | 20 | Derived per scan — **does not scale**; snapshot table unwritten | `74`, `52` |
| Rule simulator | `MISSING` | 0 | | `24` |
| RFID / NFC | `MISSING` | 5 | Two unindexed, non-unique columns | `36` |
| Face recognition | `MISSING` | 0 | Not planned without legal clearance | `133` |

## On-site operations

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Check-in lists | `CONFIRMED` | 75 | | `18` |
| QR / USB scanning | `CONFIRMED` | 60 | Arabic keyboard layout breaks the wedge; link can undo check-ins | `38` |
| Two check-in write models (F10) | `PARTIAL` | — | Unconsolidated; dashboard path emits no webhook | `18` |
| Operator identity | `PARTIAL` | 10 | First identity-bearing scan route in progress, uncommitted | `38` |
| Offline check-in | `MISSING` | 5 | Scans no longer silently lost; not queued either | `71` |
| Walk-in at the door | `PARTIAL` | 30 | Backoffice only | `17` |
| Kiosk | `MISSING` | 0 | | `19`, `96` |
| Queue management | `MISSING` | 5 | Gauge capped at 4/min | `20`, `51` |
| Badge templates, printing | `PARTIAL` | 10 | Schema only; printing is `window.print()` | `21`, `22`, `39` |
| Photo capture | `MISSING` | 0 | | `23`, `73` |
| Devices | `PARTIAL` | 10 | Table + model; no guard or endpoints | `40` |

## Engagement, marketing, messaging

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Transactional email | `CONFIRMED` | 85 | | `69` |
| Organizer bulk messaging | `PARTIAL` | 60 | Test send reaches real customers; Premium tier default; no unsubscribe | `42` |
| Segmentation | `PARTIAL` | 20 | Five fixed audiences | `42` |
| SMS / WhatsApp | `MISSING` | 0 | No phone capture | `43` |
| Push | `MISSING` | 0 | No service worker | `44` |
| Tracking pixels | `CONFIRMED` | 60 | Load without consent by default | `41` |
| SEO, sitemaps, JSON-LD | `CONFIRMED` | 70 | Event pages ignore the noindex flag | `41` |
| Networking, meetings | `MISSING` | 0 | | `31` |
| eRaffle, polls, Q&A | `MISSING` | 0 | | `31` |
| Lead capture | `MISSING` | 0 | | `33` |
| Attendee app | `MISSING` | 0 | Manifest makes the site installable and blank offline | `30`, `95` |

## Platform and integration

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| Public API, keys, scopes | `MISSING` | 5 | JWT only; Sanctum unused | `48` |
| Webhooks | `PARTIAL` | 70 | SSRF defence strong; **retries never run**; payloads coupled to REST | `49` |
| OpenAPI | `CONFIRMED` | 85 | Contract test | `48` |
| Integrations | `PARTIAL` | 50 | Nine direct, two behind interfaces | `50` |
| CRM | `MISSING` | 0 | | `47` |
| Realtime | `MISSING` | 0 | Stub config | `71` |
| Reports | `PARTIAL` | 45 | Nine reports, all commerce; two ignore dates | `51` |
| Analytics rollups | `CONFIRMED` | 70 | No repair job; views lost without orders | `52` |
| Command center | `MISSING` | 0 | | `53` |
| Attendance intelligence | `MISSING` | 0 | | `54` |

## Operations

| Capability | Status | % | Owner |
|---|---|---|---|
| Operations lifecycle, gates | `MISSING` | 0 | `56` |
| Tasks, checklists | `PARTIAL` | 10 | `58` — client-side setup checklist only |
| Readiness, go/no-go | `MISSING` | 0 | `59` |
| Incidents | `MISSING` | 0 | `60` |
| Procurement, dossier | `MISSING` | 0 | `62`, `63` |

## Engineering foundations

| Capability | Status | % | Evidence / note | Owner |
|---|---|---|---|---|
| **Source control and CI for ARZO** | `MISSING` | 0 | Only remote is public upstream; 14 commits local-only; no CI | `123` |
| Authorization model | `PARTIAL` | 25 | Guarded by architecture test; RBAC not started | `09` |
| Tenant isolation | `PARTIAL` | 60 | 20-case suite; no global scope | `08` |
| Audit logging | `PARTIAL` | 30 | `event_logs` never written; `order_audit_logs` no actor | `67` |
| Backend tests | `CONFIRMED` | 75 | ~1,260 unit tests — run by hand only | `79` |
| Frontend tests | `PARTIAL` | 20 | Vitest, 24 tests | `79` |
| E2E | `CONFIRMED` | 70 | 73 specs — on upstream's CI | `79` |
| Load testing | `MISSING` | 0 | | `125` |

Rows for privacy, compliance, observability, reliability, accessibility, localization and
infrastructure are scored in `140`, which carries them with owners.

## Keeping it true

Every merged change that moves a row updates it in the same PR (`00`, `129`). A row moved without
evidence in `02` is wrong. The scaffold's question — generate this from a machine-readable source? —
has a practical answer: once the backlog lives as issues (`137`), a label per capability lets a
script produce this table; until then, hand-maintained with the `129` sync check.

## Related

`02-current-state-audit.md` · `140-final-100-percent-checklist.md` · `128-definition-of-done.md` ·
`136-master-backlog.md` · `111-competitive-gap-closure.md` · `129-quality-gates.md`
