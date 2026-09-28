# Evento Comparison

**Status:** WRITTEN · **Authority:** Derived · **Audit date:** 2026-09-28
**Source:** evento.ae/our-services-4, fetched 2026-09-28 · ARZO side from `02-current-state-audit.md`

---

## What Evento is

Not a like-for-like competitor. Evento is an **event services provider** — software plus hardware
plus people. Three of their six service lines cannot be shipped as code at all: manpower supply and
staff scheduling, badge stock (lanyards, holders, holograms, eco materials), and IT
consultancy/resource outsourcing.

ARZO is currently a **ticketing product**. The software columns below are comparable; the rest is a
business-model decision (`04`, `102`).

## Summary

| Evento service line | ARZO today | After Phase 4 |
|---|---|---|
| Online Event Registration | **~90%** | 100% |
| On-Site Registration & Check-In | **~20%** | ~90% |
| Accreditation & Access Control | **~5%** | ~85% |
| Badge Design & Printing | **0%** | ~80% (software) |
| Event Technology Solutions | **~15%** | ~85% |
| Data & Reporting | **~35%** | ~90% |

## 1. Online registration — ARZO leads

| Capability | Evento | ARZO | Evidence |
|---|---|---|---|
| Online registration | yes | `CONFIRMED` | Full flow |
| Multi-category ticketing | yes | `CONFIRMED` | `product_categories`, `product_prices` |
| Secure payments | yes | `PARTIAL` | Stripe + offline only |
| Promo codes | yes | `CONFIRMED` | `promo_codes` |
| Early bird | yes | `CONFIRMED` | `TIERED` + sale windows |
| Group registration | yes | `CONFIRMED` | `PER_ORDER` collection |
| Automated confirmations | yes | `CONFIRMED` | 30 mailables |
| Real-time dashboard | yes | `PARTIAL` | Sales-oriented; no realtime transport |
| Bulk email | yes | `CONFIRMED` | `messages`, 5 audiences |
| **Bulk SMS** | yes | **`MISSING`** | No provider |
| Branded registration sites | yes | `CONFIRMED` | `HomepageDesigner` + themes |
| **RSVP** | yes | **`MISSING`** | Free tickets ≠ RSVP flow |

**ARZO has that Evento does not advertise:** waitlists with offer/expiry, affiliate attribution,
shared capacity pools, recurring events with per-occurrence overrides, VAT/invoicing, an embeddable
Shadow-DOM checkout widget, 20 locales, webhooks.

## 2. On-site — Evento leads decisively

| Capability | Evento | ARZO | Evidence |
|---|---|---|---|
| QR scanning | yes | `CONFIRMED` | Camera + USB/HID wedge |
| **Self-service kiosks** | yes | **`MISSING`** | Staff-operated only |
| **Instant badge printing** | yes | **`MISSING`** | Prints tickets via `window.print()` |
| Walk-in registration | yes | `PARTIAL` | Admin backoffice, not at the gate |
| **Queue management** | yes | **`MISSING`** | One throughput metric |
| Manpower supply | yes | **n/a** | Not software |
| Trained registration staff | yes | **n/a** | Not software |
| Staff scheduling | yes | **`MISSING`** | No shift entity |
| **Offline operation** | implied | **`MISSING`** | And scans are **lost** on failure (F1) |

## 3. Accreditation & access control — near-total gap

| Capability | Evento | ARZO |
|---|---|---|
| Multi-level access control | yes | `PARTIAL` — product-scoped, not zone-scoped |
| **Face recognition** | yes | `MISSING` — and needs legal review (PDPL) |
| **RFID / NFC badges** | yes | `MISSING` |
| **Photo badge printing** | yes | `MISSING` |
| **Zone-based permissions** | yes | `MISSING` — no zone entity |
| Secure database management | yes | `PARTIAL` — see F11 tenancy |
| **VIP & media accreditation** | yes | `MISSING` |
| **Seating** | yes | `MISSING` |
| Anti-passback / re-entry | implied | **`MISSING` — structurally forbidden** by a UNIQUE index (F2) |
| Access logs | implied | `MISSING` — check-out is a soft-delete, destroying history |

## 4. Badge design & printing — 0%

Every item absent: custom layouts, hologram/security stock, colour-coded access levels, lanyards and
holders, eco stock. See F8 — `@react-pdf/renderer` is installed and unused; all printing is
`window.print()`.

Colour-coded access is the one ARZO can do elegantly once zones exist: `zones.colour` drives a badge
colour bar, so a change propagates everywhere (`21`, `25`).

## 5. Event technology — mostly gap

| Capability | Evento | ARZO |
|---|---|---|
| Software/web development | yes | n/a (service line) |
| **Mobile event apps** | yes | `MISSING` — no PWA, no native |
| **QR & RFID tracking** | yes | `PARTIAL` — QR only, no persistent log |
| **Lead capture** | yes | `MISSING` |
| **Automated SMS** | yes | `MISSING` |
| **CRM integration** | yes | `MISSING` |
| **API integration** | yes | `MISSING` — table exists, unused |
| IT consultancy | yes | n/a |
| Email marketing | yes | `PARTIAL` — no segmentation or automation |
| **eRaffle** | yes | `MISSING` |

ARZO has **webhooks with genuine SSRF hardening**, which Evento does not list — a real technical
advantage for integrators.

## 6. Data & reporting

| Capability | Evento | ARZO |
|---|---|---|
| Real-time attendance | yes | `PARTIAL` — per-list, no realtime |
| **Session tracking** | yes | `MISSING` — no sessions |
| Check-in statistics | yes | `CONFIRMED` |
| **Demographics** | yes | `MISSING` |
| **Exhibitor lead retrieval** | yes | `MISSING` |
| Post-event analytics | yes | `PARTIAL` — 4 revenue reports |
| Attendee engagement | yes | `MISSING` |
| Administration panel | yes | `CONFIRMED` — strong |
| Data export | implied | `CONFIRMED` — 5 exporters |

## The structural read

Evento's advantages cluster on exactly the two axes ARZO's data model lacks — **space** (zones,
booths, seating) and **time-within-event** (sessions, agenda) — plus the two physical capabilities
(badges, RFID) and the two service capabilities (staff, consultancy).

That is why `113-roadmap.md` sequences space and time first. Chasing the feature list directly would
produce workarounds; building the dimensions makes each feature ordinary work.

## Where ARZO can pass Evento

Not by matching the list, but where ARZO's position is structurally different:

1. **Offline-first as a designed guarantee**, not an implied one. Evento advertises no offline story. A platform that provably keeps working when the venue network fails is a defensible claim — and is why `71` is treated as mandatory.
2. **Self-serve ticketing depth.** Waitlists, capacity pools, recurring events, affiliates, VAT. Evento's registration is comparatively thin.
3. **Public API and webhooks.** Evento lists "API integration" as a service; ARZO can make it a product.
4. **Talent-management integration.** ARZO represents the talent that delivers its events. No competitor can link casting, booking, and event operations in one system. This is the only genuinely unique opportunity on the list (`112`, `132`).
5. **Transparent evidence-based reporting** rather than dashboards — derived from append-only logs, so figures reconcile.

## Honest caveats

- Software parity does not equal capability parity. Evento shows up with printers, badge stock and trained staff. ARZO would need to buy and hire (`102`, `57`) or partner.
- Evento's public page is marketing copy. Advertised capability is not verified depth, and this comparison should not be read as a claim about their implementation quality.
- ARZO's 90% in registration is measured against `128`, which is stricter than a marketing bullet.

## Related

`02-current-state-audit.md` · `03-gap-analysis.md` · `109-feature-matrix.md` ·
`111-competitive-gap-closure.md` · `112-arzo-differentiators.md` · `113-roadmap.md`
