# Vendor Management

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem (small) · **Priority:** P3 (ARZ-203, shared with `62`) · **Phase:** 5
**Depends on:** `32-exhibitor-management.md`, `23-accreditation.md`, `56-event-operations.md`, `73-file-management.md`, `63-event-documentation.md`
**Blocks:** `62-procurement.md`, `108-post-event-closeout.md` (vendor performance)

---

The suppliers who make an event physically happen — AV, staging, catering, security, cleaning,
stand builders, print, logistics, staffing agencies — recorded per event with their scope, contacts,
documents, on-site staff and how they performed.

## Current state — `MISSING`, 0%

| Fact | Evidence |
|---|---|
| No vendor, supplier, contract or purchase entity | Live DB: 109 base tables, none matching `vendor\|supplier\|purchase\|procure\|contract\|compan`; `grep -ri "supplier\|purchase.?order\|procure"` over `backend/app` returns nothing |
| No `companies` table — `32` designs it for Phase 3 and it has not landed | Live DB; `32` target model |
| `event_operations.client_company_id → companies` is already designed (`56`) | `companies` is becoming the account's counterparty registry: clients, exhibitors, sponsors |
| `CONTRACTOR` is in `23`'s seeded accreditation-type set; `accreditation_types` has no writer on `develop` | `23`; CRUD actions for accreditation types were uncommitted, in progress at audit time |
| No `vendor.*` permission among the 43 seeded permission strings | `2026_09_29_000007_create_permission_tables.php:20-35` |
| **No store for non-image files.** Uploads accept `jpeg,png,jpg,webp` only | `CreateImageRequest.php:25` — contracts, insurance certificates and method statements cannot be held today; `73` designs the `documents` store |

Vendors are managed today in spreadsheets and email, outside the platform. The cost of that shows up
on site: a stand builder's crew arrives at 06:00 with no credentials because nobody entered their
names, and the certificate of insurance is in someone's inbox.

## Decision: a vendor is a participation of a `companies` row, not a new identity tree

`06` lists `Vendor` as a new entity. This document narrows that: **there is no `vendors` table.** A
vendor is a company participating in an event in a supplier role, exactly as `32` models an
exhibitor and `34` a sponsor.

| Option | For | Against |
|---|---|---|
| Separate `vendors` table | Vendor-only fields sit on their own row | The same AV company sponsors one event, supplies the next and exhibits at a third. Three rows, three logos, three VAT numbers, and no way to see the whole relationship. Deduplication becomes a project. |
| **`companies` + `event_vendors`** | One profile per organization per account; the relationship-specific state (scope, contract, staff, rating) lives on the participation | Vendor-specific company facts (trade licence, insurance) need a home — answered below by documents with expiry, not by columns |

The objection worth taking seriously is that vendors need data exhibitors do not: bank details,
payment terms, trade-licence numbers. The answer is that **bank details and payment terms do not
belong here at all** — they belong in the finance system (`62`, `04` non-goal: not an accounting
system). What remains vendor-specific is documents with expiry dates, which are company-level
documents in `73`'s `documents` store, with the validity dates `63` adds.

## Model

```
companies                       -- from 32; no vendor-specific columns added
  + registration_number NULL    -- commercial registration; generic, not vendor-only

event_vendors                   -- one company, one role, one event
  id, short_id, event_id, company_id → companies,
  category,                     -- AV | STAGING | CATERING | SECURITY | CLEANING | STAND_BUILD
                                -- | PRINT | LOGISTICS | STAFFING_AGENCY | FURNITURE | OTHER
  status,                       -- PROPOSED | CONTRACTED | ACTIVE | COMPLETED | CANCELLED
  scope_of_work text NULL,
  contract_reference NULL,      -- the finance/legal system's id, not a copy of the contract
  primary_contact_person_id NULL → persons,
  onsite_lead_person_id NULL → persons,
  staff_pass_quota int NULL,
  rating smallint NULL,         -- 1..5, set at closeout
  performance_notes text NULL,  -- about the company's delivery, never about individuals
  rated_by NULL → users, rated_at NULL,
  metadata jsonb, timestamps, deleted_at
  UNIQUE (event_id, company_id, category) WHERE deleted_at IS NULL
  INDEX (company_id)            -- the company's history across events

vendor_staff
  id, short_id, event_vendor_id → event_vendors, person_id → persons,
  role,                         -- LEAD | STAFF
  accreditation_id NULL → accreditations,
  portal_token_hash NULL, portal_token_expires_at NULL,
  timestamps, deleted_at
  UNIQUE (event_vendor_id, person_id) WHERE deleted_at IS NULL
```

- **A company can hold two categories at one event** (AV and staging from the same supplier), hence
  the three-column uniqueness.
- **`contract_value` is deliberately absent.** Money owed to a vendor is a cost, and costs live in
  `62` against the participation. Putting a value here would create a second, unreconciled figure.
- **`vendor_staff` mirrors `exhibitor_staff` (`32`) rather than sharing a polymorphic table.** A
  single `company_staff (participation_type, participation_id)` table saves ten columns and loses the
  foreign key. Referential integrity wins.

## Decision: vendor staff get `CONTRACTOR` accreditation, nothing else

Same reasoning as `32` for exhibitor staff and `57` for event staff: the two-source
`credentials_exactly_one_source` CHECK is the permanent design.

- Naming a vendor staff member creates a `CONTRACTOR` accreditation, auto-approved within
  `staff_pass_quota`, routed to the organizer beyond it.
- The `CONTRACTOR` type's rules grant back-of-house and the relevant halls during
  `build_up_starts_at → doors_open_at` and `breakdown` windows (`56`), plus show days for vendors who
  operate live (AV, catering, security). A security contractor's guards typically need a narrower
  `SECURITY` type — the vendor category suggests the type; the organizer chooses.
- Crews change at short notice. **Bulk CSV upload of names** through the vendor portal, with
  same-day approval, is the path that will actually be used; single entry is the fallback.
- Venues and government sites often require contractor ID numbers. That writes
  `persons.id_document_number`, which is **plaintext today** (`65` PV2). The encryption fix is a
  prerequisite for collecting vendor staff IDs, not an improvement to it.

## The vendor portal — magic link, as for exhibitors

v1 reuses `32`'s tokenized per-person link, scoped to one `event_vendors` row. The vendor lead can:

- Submit and withdraw staff within quota (creates and withdraws accreditations)
- Upload required documents (below)
- See deadlines and tasks assigned to the participation (`58`)
- See access windows and delivery slots

A vendor never sees attendees, other vendors, costs or budgets. Account membership is ruled out for
the same reason as for exhibitors: today it is account-wide (`32` fact 1).

## Documents with expiry — the compliance core

| Document | Level | Why |
|---|---|---|
| Trade licence / commercial registration | Company | Valid across events until it expires |
| Public liability insurance certificate | Company, with dates | Must cover the event dates; the readiness check compares |
| Method statement, risk assessment | Event participation | Specific to this build |
| Staff list for venue security | Event participation | Personal data — shortest retention (`65`) |
| Rigging, electrical or food-hygiene certificates | Event participation | Category-dependent |

Stored in `73`'s `documents` table, with `expires_at` as `63` extends it. Required documents per category are a
**template of tasks with evidence** (`58`), not bespoke columns. Readiness (`59`) gains one advisory
check, blocking at the organizer's choice: *every `ACTIVE` vendor with on-site staff has an
insurance certificate valid on every event day.*

## Performance

A 1–5 rating and notes per participation, recorded at closeout (`108`), visible on the company's
history across the account's events. That is the whole feature. No weighted scorecards, no
algorithmic supplier ranking (`133`: assist, never decide contestable things). Ratings describe the
company's delivery; notes about named individuals are personal data about workers and do not belong
here (`57` makes the same point about staff).

## Out of scope

| Idea | Why not |
|---|---|
| RFQ, tendering, bid comparison | A procurement product; ARZO's volume does not justify it |
| Vendor marketplace (ARZ-251) | P4, needs a business-model decision first |
| Paying vendors, bank details, payment terms | Finance system (`62`) |
| Vendor-owned logins | v2, after per-resource authorization (ARZ-011/012) |

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | `documents` store (`73`) with `63`'s additions (`COMPANY` owner, validity dates) | Prerequisite; also unblocks `32`'s exhibitor manuals |
| 2 | `companies` (from `32`) with `registration_number` | Lands with ARZ-130, whichever comes first |
| 3 | `event_vendors`, `vendor_staff`; seed `vendor.manage` permission | Low — additive |
| 4 | Vendor portal magic links; bulk staff upload creating `CONTRACTOR` accreditations | Needs ARZ-051 approval workflow |
| 5 | Document templates per category; insurance readiness check | With `58`, `59` |
| 6 | Closeout rating | With `108` |

## Open questions

- **Who contracts the vendor — ARZO or the client?** When the client contracts directly, ARZO still needs the crew credentialed but not the contract. `contract_reference` stays NULL; the rest applies.
- **A preferred-supplier list across events?** Account-scoped `companies` with rating history already is one. Across accounts would cross tenancy (`08`) — no.
- **Contractor ID requirements at Qatari venues** — which venues require ID numbers for contractor passes, and for how long they must be kept, is `UNVERIFIED`. Venue operations and legal must answer before step 4 collects IDs.
- **Insurance minimums** — whether ARZO enforces a minimum cover amount is a commercial policy, not software. The check can compare an entered amount if one is set.

## Related

`32-exhibitor-management.md` · `34-sponsor-management.md` · `23-accreditation.md` ·
`56-event-operations.md` · `57-manpower-and-staffing.md` · `58-task-management.md` ·
`59-event-readiness.md` · `62-procurement.md` · `63-event-documentation.md` ·
`65-privacy-gdpr.md` · `73-file-management.md` · `108-post-event-closeout.md` · `06-domain-model.md`
