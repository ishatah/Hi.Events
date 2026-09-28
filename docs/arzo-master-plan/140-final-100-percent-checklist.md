# Final 100% Checklist

**Status:** WRITTEN · **Authority:** Derived from `02` and `128` · **Audit date:** 2026-09-28

---

## What this answers

> "Is ARZO actually a complete event technology platform?"

**Today: no.** Roughly one third complete, scored against the 19 gates in `128-definition-of-done.md`.

Scores are current state, `CONFIRMED` unless marked. Nothing scores 100%, because `128` requires
observability, operational readiness and edge-case handling that no subsystem has documented.

Legend: Status = `CONFIRMED` / `PARTIAL` / `MISSING` / `UNVERIFIED`. Owner is a role, not a person.

---

## Product & commerce

| Requirement | % | Status | Evidence | Remaining gap | Owner | Depends |
|---|---|---|---|---|---|---|
| Event creation & lifecycle | 90 | CONFIRMED | `events`, `event_settings` | Observability, runbook | Eng | — |
| Recurring events | 85 | CONFIRMED | `event_occurrences` + RRULE | Occurrence-awareness audit | Eng | — |
| Products & categories | 90 | CONFIRMED | `products`, `product_categories` | — | Eng | — |
| Tiered / early-bird pricing | 85 | CONFIRMED | `ProductPriceType::TIERED` | — | Eng | — |
| Promo codes | 90 | CONFIRMED | `promo_codes` | — | Eng | — |
| Affiliates | 80 | CONFIRMED | `affiliates` | — | Eng | — |
| Orders & checkout | 90 | CONFIRMED | Advisory-locked | Feature test for the lock | Eng | — |
| Payments | 60 | PARTIAL | Stripe + offline only | More providers; delete Razorpay dead code | Eng | — |
| Invoices & VAT | 85 | CONFIRMED | `invoices`, VAT settings | — | Eng | — |
| Refunds | 80 | CONFIRMED | `order_refunds` | — | Eng | — |
| Waitlist | 85 | CONFIRMED | `waitlist_entries` | Session support | Eng | 27 |
| Capacity pools | 85 | CONFIRMED | `capacity_assignments` | — | Eng | — |
| **RSVP flow** | 0 | MISSING | — | Whole feature | Eng | — |

## Registration & attendees

| Requirement | % | Status | Remaining gap | Owner | Depends |
|---|---|---|---|---|---|
| Branded registration pages | 90 | CONFIRMED | — | Eng | — |
| Custom questions (9 types) | 90 | CONFIRMED | Conditional logic | Eng | — |
| Group registration | 80 | CONFIRMED | — | Eng | — |
| Embeddable widget | 85 | CONFIRMED | — | Eng | — |
| Attendee CRUD & export | 80 | CONFIRMED | — | Eng | — |
| Self-service edit | 75 | CONFIRMED | — | Eng | — |
| **Person identity (non-buyers)** | 0 | MISSING | `persons` table | Eng | — |
| **Demographics** | 0 | MISSING | Consented collection + reporting | Eng | 10 |

## Marketing & messaging

| Requirement | % | Status | Remaining gap | Owner |
|---|---|---|---|---|
| Transactional email | 90 | CONFIRMED | — | Eng |
| Bulk email | 75 | CONFIRMED | — | Eng |
| Email templates (Liquid) | 80 | CONFIRMED | — | Eng |
| Scheduled sends | 75 | CONFIRMED | — | Eng |
| **Segmentation** | 20 | PARTIAL | Filter builder; only 5 fixed audiences | Eng |
| **Campaign automation** | 0 | MISSING | Drips, open/click | Eng |
| **SMS** | 0 | MISSING | Provider integration | Eng |
| **Push** | 0 | MISSING | Infrastructure + app | Eng |
| **Attendee in-app notifications** | 0 | MISSING | `announcements` target platform users only | Eng |

## On-site operations

| Requirement | % | Status | Remaining gap | Owner | Depends |
|---|---|---|---|---|---|
| QR scanning | 70 | CONFIRMED | Offline; retry on failure (F1) | Eng | 71 |
| USB/HID scanning | 70 | CONFIRMED | Hardcoded `A-` prefix assumption | Eng | 38 |
| Check-in lists | 80 | CONFIRMED | Migrate to access rules | Eng | 24 |
| **Operator identity** | 0 | MISSING | URL short ID is the only credential (F3) | Eng | 09, 40 |
| **Offline check-in** | 0 | MISSING | **Scans are lost today (F1)** | Eng | 71 |
| **Self-service kiosk** | 0 | MISSING | Whole application | Eng | 19, 71 |
| **Walk-in at the door** | 30 | PARTIAL | Backoffice only | Eng | 17 |
| **Queue management** | 5 | MISSING | One throughput metric | Eng | 20 |
| **Badge printing** | 0 | MISSING | Prints tickets via `window.print()` (F8) | Eng | 21, 22 |
| **Photo capture** | 0 | MISSING | — | Eng | 23 |
| **Badge stock / lanyards** | 0 | MISSING | **Procurement, not code** | Business | 102 |
| **Trained on-site staff** | 0 | MISSING | **Hiring, not code** | Business | 57 |

## Accreditation & access control

| Requirement | % | Status | Remaining gap | Owner | Depends |
|---|---|---|---|---|---|
| **Accreditation types** | 0 | MISSING | Whole subsystem | Eng | 23 |
| **Application & approval** | 0 | MISSING | Whole subsystem | Eng | 23, 09 |
| **Credentials** | 0 | MISSING | Whole subsystem | Eng | 23 |
| **Zones** | 0 | MISSING | No space model | Eng | 25 |
| **Access points** | 0 | MISSING | — | Eng | 25 |
| **Access rules engine** | 0 | MISSING | — | Eng | 24 |
| **Access logs** | 10 | MISSING | Check-out is a soft-delete, destroying history | Eng | 24 |
| **Re-entry / anti-passback** | 0 | MISSING | **Structurally forbidden by a UNIQUE index (F2)** | Eng | 24 |
| **Time-window rules** | 40 | PARTIAL | List activate/expire only | Eng | 24 |
| **RFID / NFC** | 0 | MISSING | — | Eng+Business | 36, 102 |
| **Face recognition** | 0 | MISSING | **Legal review required first** | Business | 133 |
| **Seating** | 0 | MISSING | — | Eng | 26 |

## Programme

| Requirement | % | Status | Remaining gap | Owner |
|---|---|---|---|---|
| **Sessions** | 0 | MISSING | `event_occurrences` is not sessions | Eng |
| **Tracks** | 0 | MISSING | — | Eng |
| **Speakers** | 0 | MISSING | — | Eng |
| **Agenda** | 0 | MISSING | — | Eng |
| **Session registration** | 0 | MISSING | — | Eng |
| **Session attendance** | 0 | MISSING | — | Eng |
| Calendar export | 40 | PARTIAL | Event-level only; session ICS is an extension | Eng |

## Exhibitors & sponsors

All **0% / MISSING**: exhibitor entity, portal, booths, staff passes, lead capture, qualification,
export, sponsor packages, exhibitor analytics. Depends on `32`–`35`, `09`, `25`.

## Mobile

| Requirement | % | Status | Note |
|---|---|---|---|
| **Attendee app** | 0 | MISSING | No PWA — manifest exists but **blank offline**, a trap |
| **Scanner app (native)** | 0 | MISSING | Web route exists, online-only |
| **Staff app** | 0 | MISSING | — |
| **Kiosk app** | 0 | MISSING | — |
| **Ops app** | 0 | MISSING | — |
| **Networking / eRaffle / polls** | 0 | MISSING | Depends on the app |

## Platform, API, integrations

| Requirement | % | Status | Remaining gap | Owner |
|---|---|---|---|---|
| Webhooks | 85 | CONFIRMED | Decouple payloads from API resources (F14); test SSRF defence | Eng |
| **Public API** | 5 | MISSING | `personal_access_tokens` exists, **unused since 2020** | Eng |
| **API keys / scopes** | 0 | MISSING | — | Eng |
| **CRM integration** | 0 | MISSING | Blocked on the API | Eng |
| **Device management** | 0 | MISSING | — | Eng |
| **Realtime** | 0 | MISSING | Stub config, wrong env key, dead channel file | Eng |
| OpenAPI docs | 85 | CONFIRMED | Strong contract test | Eng |

## Analytics & reporting

| Requirement | % | Status | Remaining gap |
|---|---|---|---|
| Sales reports | 75 | CONFIRMED | 4 report types, all revenue |
| Data export | 85 | CONFIRMED | 5 exporters |
| Check-in statistics | 60 | CONFIRMED | Per-list only |
| **Live attendance** | 30 | PARTIAL | No realtime, no unified view |
| **Session attendance** | 0 | MISSING | No sessions |
| **Exhibitor leads** | 0 | MISSING | — |
| **Command center** | 0 | MISSING | Blocked on realtime |
| **Post-event reporting** | 40 | PARTIAL | Revenue only |

## Operations (ARZO-as-operator)

All **0% / MISSING**: staffing, shifts, tasks, checklists, readiness gates, incidents, vendors,
procurement, event dossier, runbook. Depends on `56`–`63`, `09`.

## Security, privacy, compliance

| Requirement | % | Status | Remaining gap | Owner |
|---|---|---|---|---|
| Authentication (JWT) | 75 | CONFIRMED | No refresh; no MFA | Eng |
| **MFA** | 0 | MISSING | Before external SaaS | Eng |
| **Authorization model** | 25 | PARTIAL | Role gate **no-op at default level** (F12) | Eng |
| **Tenant isolation** | 60 | PARTIAL | No global scopes, no negative test (F11) | Eng |
| Input sanitization | 80 | CONFIRMED | — | Eng |
| SSRF defence | 80 | CONFIRMED | **Untested** | Eng |
| Audit logging | 50 | PARTIAL | Not universal | Eng |
| GDPR deletion | 75 | CONFIRMED | `AnonymizationStrategy` | Eng |
| **Privacy for new data classes** | 0 | MISSING | Photos, ID docs, movement, leads | Business+Eng |
| **PDPL compliance review** | 0 | UNVERIFIED | Not evidenced | Business |
| **AGPL licensing position** | 0 | UNVERIFIED | **Resolve before Phase 2 (R1)** | Business |

## Quality & infrastructure

| Requirement | % | Status | Remaining gap |
|---|---|---|---|
| Backend unit tests | 75 | CONFIRMED | 1,215 tests |
| **Action/authz tests** | 5 | PARTIAL | **7 of 268 Actions** |
| E2E tests | 70 | CONFIRMED | 73 specs; check-in has 1 |
| **Frontend tests** | 0 | MISSING | No runner |
| **CI frontend gates** | 0 | MISSING | lint/typecheck not wired |
| **Offline tests** | 0 | MISSING | Nothing to test yet |
| **Hardware tests** | 0 | MISSING | No fakes, no hardware |
| **Load tests** | 0 | MISSING | No evidence |
| Observability (errors) | 70 | CONFIRMED | Sentry, both sides |
| **Observability (metrics)** | 10 | MISSING | Tracing off; no metrics |
| **Health checks** | 20 | PARTIAL | Static 200; **healthy with a dead DB** |
| **DR tested** | 0 | UNVERIFIED | Untested restore is not a backup |
| CI/CD | 75 | CONFIRMED | 5 workflows |
| Localization | 80 | CONFIRMED | 20 locales, **no Arabic** |
| **Accessibility** | 30 | UNVERIFIED | Good contrast math; no audit |

---

## Weighted verdict

| Area | Completeness |
|---|---|
| Commerce & registration | **~85%** |
| Messaging | **~45%** |
| On-site operations | **~15%** |
| Accreditation & access | **~5%** |
| Programme | **~0%** |
| Exhibitors | **0%** |
| Mobile | **0%** |
| Platform/API | **~30%** |
| Analytics | **~35%** |
| Operations | **0%** |
| Security & quality | **~45%** |

**Overall: roughly 30–35% of the target platform.** Strong where it is strong; the rest is genuinely
absent rather than thin.

## How to use this

Update on every merged feature (`129`). If a row moves without evidence in `02`, the row is wrong —
the audit is authoritative, this document is derived.

## Related

`02-current-state-audit.md` · `128-definition-of-done.md` · `109-feature-matrix.md` ·
`113-roadmap.md` · `136-master-backlog.md`
