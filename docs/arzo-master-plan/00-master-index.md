# ARZO Master Plan — Index

**Status:** Living document
**Last full code audit:** 2026-09-29 (documents 13–140 each written against the code; `02` itself still audits `7dec84ca` and is due a refresh)
**Audited commit baseline:** `develop` @ `e7228c1d`
**Current platform maturity:** Ticketing & registration platform — production-capable in commerce, absent in on-site operations. Phase 1–2 schema in place (109 tables live on dev); access-engine core landed. **Foundations missing:** ARZO's code has no repository of its own, no CI and no hosting (`123`, `84`).
**Current roadmap phase:** Phase 1 (RBAC, tenant scope, check-in consolidation not started) and Phase 2 (started early) in parallel, plus the **hardening track** — 37 items, 12 at P0 (`113`, `136`)
**Documents:** 141 total — all WRITTEN (executable depth)

---

## What this is

The blueprint for evolving ARZO from a self-serve ticketing platform into an end-to-end event
technology and operations platform.

ARZO today is a Hi.Events fork (AGPL-3.0 with an attribution term under §7(b) — see `66`, `98`;
the ARZO rebrand exists in the working tree, uncommitted). It models
`Order → Product → Attendee` and does that well. It has **no model of space** (zones, booths,
seats) and **no model of time within an event** (sessions, tracks, agenda). Those two absences
are the root cause of most capability gaps, and most of this plan follows from them.

## Evidence standard

Every claim in these documents carries one of these markers. This is enforced, not decorative —
a plan that overstates what exists produces a roadmap that under-budgets.

| Marker | Meaning |
|---|---|
| `CONFIRMED` | Verified by reading code, schema, or a live query. File path or query cited. |
| `PARTIAL` | Exists but incomplete, or exists in a shape unsuited to the target use |
| `PROTOTYPE` | Works in a narrow path; not production-hardened |
| `PRODUCTION READY` | Confirmed complete against the §128 definition of done |
| `MISSING` | Verified absent — searched and not found |
| `UNVERIFIED` | Not yet checked. **Never** treat as either present or absent. |

`UNVERIFIED` is a real state and appears in these documents. It is not a failure; an unaudited
claim marked honestly is more useful than a guess.

## Reading order

New readers, in order:

1. `01-executive-vision.md` — what ARZO is becoming and why
2. `02-current-state-audit.md` — **authoritative** record of what exists today
3. `03-gap-analysis.md` — the delta, grouped by root cause
4. `05-product-architecture.md` — the target shape
5. `06-domain-model.md` — **authoritative** entity model
6. `113-roadmap.md` — dependency-ordered sequence
7. `136-master-backlog.md` — executable work items

Executives who want the short version: `01`, then §"Bottom line" of `03`, then `113`.

## Authority

Documents are either **authoritative** (the source of truth for their subject; change them first)
or **derived** (restatements or expansions; must be updated when their source changes).

| Authoritative | Governs |
|---|---|
| `02-current-state-audit.md` | What exists today, with evidence |
| `06-domain-model.md` | Entities, relationships, lifecycle |
| `07-database-evolution.md` | Schema change sequence |
| `09-permissions-and-roles.md` | The authorization model |
| `113-roadmap.md` | Phase order and dependencies |
| `128-definition-of-done.md` | What "complete" means |
| `136-master-backlog.md` | Work items and priority |

Derived: `03`, `109`, `110`, `111`, `140`, and all phase documents (`114`–`118`).
If a derived document contradicts its source, the source wins and the derived document is stale.

## Dependency spine

The order below is derived from the code audit, not from difficulty. Each layer is unbuildable
until the one above it exists.

```mermaid
graph TD
    A["Domain model + tenancy<br/>06, 07, 08"] --> B["Permissions / RBAC<br/>09"]
    B --> C["SPACE model<br/>Venue→Zone→AccessPoint<br/>25, 26"]
    B --> D["TIME model<br/>Session→Track→Agenda<br/>27, 28, 29"]
    C --> E["Accreditation engine<br/>23"]
    D --> E
    E --> F["Badge system<br/>21, 22"]
    E --> G["Access control engine<br/>24"]
    C --> G
    F --> H["Hardware abstraction<br/>36-40"]
    G --> H
    H --> I["Offline-first sync<br/>11 / 71"]
    I --> J["Kiosk + scanner apps<br/>19, 94, 96"]
    D --> K["Exhibitors + leads<br/>32-35"]
    D --> L["Attendee app<br/>30, 95"]
    G --> M["Command center<br/>53"]
    J --> M
    M --> N["Attendance intelligence<br/>54, 55"]
    K --> N
    L --> N
```

**The two hard constraints this graph encodes:**

1. **Access control cannot precede the space model.** A rules engine needs somewhere to point.
2. **Offline-first cannot precede hardware abstraction.** Sync semantics depend on what devices do.

Attempting either out of order produces rework, which is why the roadmap is not ordered by
difficulty.

## Document register

Status values: `WRITTEN` (complete to executable depth) · `SCAFFOLD` (purpose, dependencies and
open questions captured; expand before executing) · `PENDING`.

### Strategy & audit

| Doc | Purpose | Status |
|---|---|---|
| `00-master-index.md` | This file | WRITTEN |
| `01-executive-vision.md` | Target state, business rationale, non-goals | WRITTEN |
| `02-current-state-audit.md` | Capability-by-capability audit with evidence | WRITTEN |
| `03-gap-analysis.md` | Gaps grouped by root cause | WRITTEN |
| `04-product-strategy.md` | Positioning, buyer, build-vs-partner | WRITTEN |
| `05-product-architecture.md` | Target system architecture | WRITTEN |

### Foundation

| Doc | Purpose | Status |
|---|---|---|
| `06-domain-model.md` | Complete entity model | WRITTEN |
| `07-database-evolution.md` | Migration sequence, 72 → target | WRITTEN |
| `08-multi-tenancy.md` | Tenant isolation model | WRITTEN |
| `09-permissions-and-roles.md` | RBAC/ABAC replacing the 3-role enum | WRITTEN |

### Commerce & registration (largely exists)

| Doc | Purpose | Status |
|---|---|---|
| `10-registration-platform.md` | Registration flows | WRITTEN |
| `11-ticketing-commerce.md` | Products, orders, checkout | WRITTEN |
| `12-rsvp-registration.md` | RSVP as distinct from ticketing | WRITTEN |
| `13-pricing-and-promotions.md` | Tiers, early bird, promos | WRITTEN |
| `14-waitlist-and-capacity.md` | Waitlists, capacity pools | WRITTEN |
| `15-payments-invoicing-vat.md` | Payments, invoices, VAT | WRITTEN |
| `16-attendee-management.md` | Attendee lifecycle | WRITTEN |

### On-site operations (the main gap)

| Doc | Purpose | Status |
|---|---|---|
| `17-onsite-registration.md` | Walk-in / at-door registration | WRITTEN |
| `18-check-in.md` | Check-in engine evolution | WRITTEN |
| `19-kiosk-system.md` | Self-service kiosk | WRITTEN |
| `20-queue-management.md` | Queue modelling | WRITTEN |
| `21-badge-management.md` | Badge lifecycle, print jobs | WRITTEN |
| `22-badge-design-printing.md` | Designer + print pipeline | WRITTEN |
| `23-accreditation.md` | Accreditation engine | WRITTEN |
| `24-access-control.md` | Rules engine | WRITTEN |
| `25-zones-and-permissions.md` | Space model | WRITTEN |
| `26-seating-management.md` | Seat maps, assignment | WRITTEN |

### Programme

| Doc | Purpose | Status |
|---|---|---|
| `27-sessions-tracks.md` | Session/track model | WRITTEN |
| `28-speakers-management.md` | Speaker profiles, slots | WRITTEN |
| `29-agenda-scheduling.md` | Agenda, conflicts, calendar | WRITTEN |
| `30-mobile-event-app.md` | Attendee app scope | WRITTEN |
| `31-networking.md` | Networking, meetings | WRITTEN |

### Exhibitors

| Doc | Purpose | Status |
|---|---|---|
| `32-exhibitor-management.md` | Exhibitor entity, portal | WRITTEN |
| `33-exhibitor-lead-capture.md` | Lead capture + scoring | WRITTEN |
| `34-sponsor-management.md` | Sponsor packages | WRITTEN |
| `35-booth-management.md` | Booths as space | WRITTEN |

### Hardware

| Doc | Purpose | Status |
|---|---|---|
| `36-rfid-nfc.md` | RFID/NFC credentials | WRITTEN |
| `37-hardware-integration.md` | Vendor-swappable abstraction | WRITTEN |
| `38-scanner-platform.md` | Scanner abstraction | WRITTEN |
| `39-printer-integration.md` | Printer abstraction | WRITTEN |
| `40-device-management.md` | Registration, health, fleet | WRITTEN |

### Marketing, API, integrations

| Doc | Purpose | Status |
|---|---|---|
| `41-event-marketing.md` | Campaign tooling | WRITTEN |
| `42-email-marketing.md` | Segmentation, automation | WRITTEN |
| `43-sms-notifications.md` | SMS provider integration | WRITTEN |
| `44-push-notifications.md` | Push infrastructure | WRITTEN |
| `45-affiliate-referrals.md` | Existing affiliate system | WRITTEN |
| `46-promo-codes.md` | Existing promo system | WRITTEN |
| `47-crm-integrations.md` | HubSpot/Salesforce | WRITTEN |
| `48-api-platform.md` | Public API, keys, scopes | WRITTEN |
| `49-webhooks.md` | Existing webhook system | WRITTEN |
| `50-third-party-integrations.md` | Integration framework | WRITTEN |

### Intelligence

| Doc | Purpose | Status |
|---|---|---|
| `51-reporting.md` | Report catalogue | WRITTEN |
| `52-analytics.md` | Analytics architecture | WRITTEN |
| `53-live-event-command-center.md` | Event-day operations view | WRITTEN |
| `54-attendance-intelligence.md` | Attendance analytics | WRITTEN |
| `55-event-intelligence.md` | Cross-event intelligence | WRITTEN |

### Event operations (ARZO-as-operator)

| Doc | Purpose | Status |
|---|---|---|
| `56-event-operations.md` | Lifecycle workflow | WRITTEN |
| `57-manpower-and-staffing.md` | Staff, shifts, assignments | WRITTEN |
| `58-task-management.md` | Tasks, checklists | WRITTEN |
| `59-event-readiness.md` | Readiness gates | WRITTEN |
| `60-incident-management.md` | Incident capture | WRITTEN |
| `61-vendor-management.md` | Vendors | WRITTEN |
| `62-procurement.md` | Procurement | WRITTEN |
| `63-event-documentation.md` | Event record | WRITTEN |

### Security & compliance

| Doc | Purpose | Status |
|---|---|---|
| `64-security.md` | Security architecture, threat model | WRITTEN |
| `65-privacy-gdpr.md` | Privacy, existing GDPR flows | WRITTEN |
| `66-compliance.md` | PCI, local requirements | WRITTEN |
| `67-audit-logging.md` | Audit trail | WRITTEN |
| `68-fraud-prevention.md` | Existing spam checks, fraud | WRITTEN |

### Platform infrastructure

| Doc | Purpose | Status |
|---|---|---|
| `69-notifications-architecture.md` | Unified notification bus | WRITTEN |
| `70-background-jobs.md` | Queue architecture | WRITTEN |
| `71-realtime-architecture.md` | Realtime transport + offline sync | WRITTEN |
| `72-search.md` | Search strategy | WRITTEN |
| `73-file-management.md` | Media pipeline | WRITTEN |

### Non-functional

| Doc | Purpose | Status |
|---|---|---|
| `74-performance.md` | Targets with reasoning | WRITTEN |
| `75-scalability.md` | Scaling model | WRITTEN |
| `76-reliability.md` | Reliability targets | WRITTEN |
| `77-disaster-recovery.md` | DR design | WRITTEN |
| `78-observability.md` | Metrics, traces, logs | WRITTEN |

### Quality

| Doc | Purpose | Status |
|---|---|---|
| `79-testing-strategy.md` | Test strategy incl. hardware/offline | WRITTEN |
| `80-qa-strategy.md` | QA process | WRITTEN |
| `81-accessibility.md` | A11y standard | WRITTEN |
| `82-localization.md` | i18n, RTL/Arabic | WRITTEN |

### DevOps

| Doc | Purpose | Status |
|---|---|---|
| `83-devops.md` | Pipeline | WRITTEN |
| `84-infrastructure.md` | Target infrastructure | WRITTEN |
| `85-deployment.md` | Deployment model | WRITTEN |
| `86-monitoring.md` | Monitoring + alerting | WRITTEN |

### Surfaces

| Doc | Purpose | Status |
|---|---|---|
| `87-ui-ux-system.md` | UX principles per surface | WRITTEN |
| `88-design-system.md` | ARZO design system | WRITTEN |
| `89-admin-platform.md` | Platform admin | WRITTEN |
| `90-organizer-platform.md` | Organizer surface | WRITTEN |
| `91-attendee-platform.md` | Attendee surface | WRITTEN |
| `92-staff-platform.md` | Staff surface | WRITTEN |
| `93-exhibitor-platform.md` | Exhibitor surface | WRITTEN |

### Applications

| Doc | Purpose | Status |
|---|---|---|
| `94-mobile-scanner.md` | Scanner app | WRITTEN |
| `95-mobile-event-app.md` | Attendee app build | WRITTEN |
| `96-kiosk-application.md` | Kiosk app | WRITTEN |
| `97-onsite-operations-app.md` | Ops app | WRITTEN |

### Commercial

| Doc | Purpose | Status |
|---|---|---|
| `98-commercial-model.md` | SaaS vs services | WRITTEN |
| `99-pricing.md` | Pricing | WRITTEN |
| `100-saas-tenancy.md` | SaaS packaging | WRITTEN |
| `101-enterprise.md` | SSO, SAML, SCIM | WRITTEN |

### Hardware & on-site

| Doc | Purpose | Status |
|---|---|---|
| `102-hardware-procurement.md` | What to buy, cost envelope | WRITTEN |
| `103-hardware-deployment.md` | Staging, imaging, spares | WRITTEN |
| `104-onsite-infrastructure.md` | Network, power, contingency | WRITTEN |

### Operating model

| Doc | Purpose | Status |
|---|---|---|
| `105-event-lifecycle.md` | Lead → closeout | WRITTEN |
| `106-event-operating-model.md` | Roles, RACI | WRITTEN |
| `107-event-day-runbook.md` | Event-day procedure | WRITTEN |
| `108-post-event-closeout.md` | Closeout | WRITTEN |

### Competitive

| Doc | Purpose | Status |
|---|---|---|
| `109-feature-matrix.md` | Full feature matrix | WRITTEN |
| `110-evento-comparison.md` | Evento comparison | WRITTEN |
| `111-competitive-gap-closure.md` | Parity plan | WRITTEN |
| `112-arzo-differentiators.md` | Beyond parity | WRITTEN |

### Roadmap

| Doc | Purpose | Status |
|---|---|---|
| `113-roadmap.md` | Dependency-ordered roadmap | WRITTEN |
| `114-phase-1.md` | Foundation | WRITTEN |
| `115-phase-2.md` | Accreditation, badges, access | WRITTEN |
| `116-phase-3.md` | Programme, exhibitors | WRITTEN |
| `117-phase-4.md` | Hardware, offline, kiosk | WRITTEN |
| `118-phase-5.md` | Intelligence, AI | WRITTEN |

### Delivery

| Doc | Purpose | Status |
|---|---|---|
| `119-dependency-map.md` | Full dependency graph | WRITTEN |
| `120-risk-register.md` | Risks | WRITTEN |
| `121-technical-debt.md` | Known debt | WRITTEN |
| `122-migration-plan.md` | Data migration | WRITTEN |
| `123-release-strategy.md` | Release process | WRITTEN |

### Verification

| Doc | Purpose | Status |
|---|---|---|
| `124-security-testing-plan.md` | Security testing | WRITTEN |
| `125-performance-testing-plan.md` | Load/perf testing | WRITTEN |
| `126-disaster-recovery-plan.md` | DR runbook | WRITTEN |
| `127-production-readiness.md` | Readiness review | WRITTEN |
| `128-definition-of-done.md` | 100% definition | WRITTEN |
| `129-quality-gates.md` | Gates | WRITTEN |
| `130-launch-checklist.md` | Launch | WRITTEN |
| `131-event-readiness-checklist.md` | Per-event readiness | WRITTEN |

### Future

| Doc | Purpose | Status |
|---|---|---|
| `132-future-capabilities.md` | Longer-term | WRITTEN |
| `133-ai-capabilities.md` | AI, assessed for feasibility | WRITTEN |
| `134-advanced-analytics.md` | Advanced analytics | WRITTEN |
| `135-global-expansion.md` | Beyond Qatar | WRITTEN |

### Execution

| Doc | Purpose | Status |
|---|---|---|
| `136-master-backlog.md` | Full backlog | WRITTEN |
| `137-engineering-work-packages.md` | Work packages | WRITTEN |
| `138-acceptance-criteria.md` | Reusable criteria | WRITTEN |
| `139-test-matrix.md` | Traceability matrix | WRITTEN |
| `140-final-100-percent-checklist.md` | The 100% checklist | WRITTEN |

## Keeping this synchronized

The plan decays the moment code lands without the documents moving — within a day of being written,
this index lagged by twelve documents and the backlog by three commits. The rule, as narrowed by
`129` (gate G15):

**In the same PR as the change** — enforced by a CI path check with a visible label override:

1. `02-current-state-audit.md` — status + evidence, **or**
2. `136-master-backlog.md` — item status

**At each phase boundary**, as a phase-exit item — derived documents reconciled together:

3. `109-feature-matrix.md` — matrix rows
4. `139-test-matrix.md` — traceability
5. `140-final-100-percent-checklist.md` — checklist lines
6. This index's register and header

Forcing the derived documents on every PR produces churn edits nobody reads; reconciling them at the
boundary keeps them true when it matters. The check runs only once ARZO has a repository and CI
(ARZ-300) — until then this is discipline, not a gate.

## Terminology

Used precisely throughout. The first four are routinely conflated, and conflating them is how
access-control bugs get shipped.

| Term | Meaning |
|---|---|
| **Event** | The thing being run. Owns products, settings, programme. |
| **Occurrence** | A repeat of the whole event (RRULE). **Not** a session. |
| **Session** | A programme item *within* an occurrence — talk, workshop, meeting. Has room, time, capacity, speakers. |
| **Activity** | Non-programme happening (catering, transport). No speakers. |
| **Access window** | When a credential may pass an access point. |
| **Registration window** | When registration is open. |
| **Sale window** | When a price is purchasable. Drives early-bird. |
| **Zone** | A controlled space. Access is granted to zones, not rooms. |
| **Access point** | A physical door/gate where a scan happens. Belongs to a zone. |
| **Accreditation** | A *type* of person (VIP, Media, Speaker) with an approval workflow. |
| **Credential** | The issued right to access. Realized as a badge. |
| **Badge** | The physical artifact carrying a credential. |
