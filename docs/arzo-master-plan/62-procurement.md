# Procurement and Event Costs

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (narrow) · **Priority:** P3 (ARZ-203, shared with `61`) · **Phase:** 5
**Depends on:** `61-vendor-management.md`, `56-event-operations.md`, `63-event-documentation.md`
**Blocks:** `102-hardware-procurement.md` (consumable forecasting), `108-post-event-closeout.md` (event P&L)

---

What an event costs, captured against the event and the vendor that supplied it, so an operated
event has a margin figure and the next edition has a budget to start from.

## Current state — `MISSING`, 0% on the cost side; the revenue side is strong

| Fact | Evidence |
|---|---|
| Revenue is modelled precisely: orders, refunds, taxes and fees, application fees, payouts | `15`; `orders.total_gross numeric(14,2)`, `stripe_payments`, `order_application_fees` |
| Contracted B2B income is designed but not built — `event_exhibitors.contract_value`, sponsorship values | `32`, `34` |
| **No cost, budget, purchase or expense entity of any kind** | Live DB: no table matching `cost\|budget\|expense\|purchase\|procure`; nothing in `backend/app` |
| No asset or equipment register; `devices` covers enrolled electronics only | `40`; live DB |
| Currency conversion exists behind an interface; the default implementation is a no-op | OpenExchangeRates + NoOp (`50`) |
| Money is handled with `brick/money`; stored as `numeric(14,2)` with a per-event currency | `15`; live DB |

So the platform can say what an event **earned** to the cent, and nothing about what it **cost**.

## Decision: cost capture, not an accounting system

The scaffold asked: full P&L or cost capture only? **Cost capture, with a management P&L view.**

`04` rules out an accounting system, and the reasoning is concrete here. A general ledger,
accounts payable, purchase-order approval chains and supplier payments already exist in ARZO's
finance system (which one is `UNVERIFIED`). Rebuilding them creates two sources of truth for the
same money, and the platform's version will always be the one that is wrong.

What the platform knows that finance does not is **context**: which event, which vendor
participation, which operational quantity (badges printed, credentials issued, staff-hours rostered).
That is what it should capture. The ledger stays where it is.

| In scope | Out of scope |
|---|---|
| Budget lines per event | General ledger, chart of accounts |
| Costs recorded against an event and optionally a vendor participation | Purchase-order approval workflow |
| A status that mirrors finance (`COMMITTED`, `INVOICED`, `PAID`), entered or imported | Paying vendors; bank details |
| A management P&L: revenue known to the platform minus costs captured | Statutory accounts, tax returns |
| Consumable forecasts derived from event data | Warehouse inventory |
| CSV export to finance | Two-way accounting integration (deferred) |

The P&L screen carries a permanent label: **management view — not the accounts.**

## Model

```
event_budget_lines
  id, short_id, event_id,
  category,          -- VENUE | AV | STAGING | CATERING | STAFFING | SECURITY | PRINT_CONSUMABLES
                     -- | HARDWARE | TRAVEL | MARKETING | LOGISTICS | CONTINGENCY | OTHER
  description, budget_amount numeric(14,2),     -- in the event currency
  notes NULL, timestamps, deleted_at

event_costs
  id, short_id, event_id,
  budget_line_id NULL → event_budget_lines,
  event_vendor_id NULL → event_vendors,         -- 61
  description,
  quantity numeric(12,2) NULL, unit NULL, unit_price numeric(14,4) NULL,
  amount numeric(14,2), currency,               -- as invoiced
  amount_in_event_currency numeric(14,2),       -- converted at a recorded rate
  fx_rate numeric(18,8) NULL, fx_rate_date date NULL,
  tax_amount numeric(14,2) NULL,                -- recorded, never computed here
  status,            -- ESTIMATED | COMMITTED | INVOICED | PAID | CANCELLED
  external_reference NULL,                      -- PO or invoice number in the finance system
  document_id NULL → documents,                 -- 73: the quote or invoice
  recorded_by → users, timestamps, deleted_at
  INDEX (event_id, status), INDEX (event_vendor_id)
```

- **The exchange rate is recorded, not looked up live.** The default rates provider is a no-op, and
  a P&L that changes when the rate changes is not a record. Finance's rate is entered with the cost.
- **`tax_amount` is recorded as invoiced.** Qatar has no VAT in force per secondary sources
  (`66`); the column exists for foreign suppliers and for the day that changes.
- **Statuses mirror finance, they do not drive it.** Moving a cost to `PAID` here pays nobody.

## The P&L view

| Line | Source | Confidence |
|---|---|---|
| Ticket revenue, net of refunds | Orders and refunds (`15`) | Exact |
| Platform and payment fees | `order_application_fees`, Stripe fees | Exact where Stripe data is present |
| Contracted exhibitor and sponsor income | `event_exhibitors`, `sponsorships` (`32`, `34`) | As recorded, not as collected |
| Costs committed / invoiced / paid | `event_costs` | As entered |
| Budget variance | `event_budget_lines` versus costs by category | As entered |

Committed and actual are shown separately. A margin computed from `ESTIMATED` costs is labelled as
such. The whole view is gated by a new `finance.view` permission — budgets and margins are
commercially sensitive, and neither exhibitor nor vendor portals ever see them.

## Consumables — where the platform genuinely helps

`102` defines the quantities — badge stock `C · (1 + r) + w` per printer, ribbons by yield, lanyards
with a loss allowance, RFID media, wristbands by day policy — and plans the reprint rate `r` at 10%
until a measured rate exists. The platform's job is to **supply the inputs instead of someone
guessing them**:

| Input | Source |
|---|---|
| `C`, badges to print | `event_operations.expected_attendance` (`56`) plus accreditation quotas at first; credentials actually issued as the event approaches |
| `r`, reprint rate | The account's own history — `55` lists badge reprint rate as a benchmark; `102`'s 10% until three comparable events exist, labelled as a default |
| Pre-print versus on-demand split | Pre-print ratio from readiness (`59`) |
| Unit costs | The last `event_costs` row for the same item |

The forecast is computed, not stored; a purchase becomes an `event_costs` row with its quantity.
This is arithmetic, and `133` is explicit that arithmetic is not an AI problem.

## Hardware: buy, rent, or neither

`102` owns the buy-or-rent decision and the per-event hardware cost template. This document records
where the result lands: rental is an `event_costs` row per event; a purchased fleet appears as the
per-event amortized charge `102` defines, entered as an `event_costs` row in category `HARDWARE`. An **asset register** — serial numbers, locations, condition
— is not procurement; enrolled electronics already live in `devices` (`40`), and physical kit
tracking belongs to `103` if it is needed at all. `06` lists `Asset / Equipment` against this
document; that is superseded here.

## Integration with finance

v1 is **CSV export** of costs per event, in a column layout agreed with finance, plus a
`external_reference` field so rows can be matched back. A two-way integration waits until the
accounting system is named and someone asks for it; building a connector to an unknown system is
the definition of speculative work.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | `event_budget_lines`, `event_costs`; `finance.view` / `finance.manage` permissions | Low — after `61` step 3 |
| 2 | Cost entry UI on the event, filtered by vendor participation | Low |
| 3 | P&L view with committed / actual split | Low — read-only over existing data |
| 4 | Consumable forecast | After `39` defines printer consumable figures |
| 5 | CSV export to finance | Low |

## Open questions

- **Which accounting system does ARZO use?** Determines the export layout and whether step 5 ever becomes an integration. Finance must answer.
- **Who enters costs — event operations or finance?** Leaning operations for `ESTIMATED`/`COMMITTED`, with finance updating status from its own records by import.
- **Does the client see the P&L?** For cost-plus contracts, perhaps a cost summary; margins never. A contract question.
- **Budget approval** — is a budget "approved" at G2 (`56`)? If so, the G2 gate decision records the approved total as evidence; no separate approval workflow is built.

## Related

`61-vendor-management.md` · `56-event-operations.md` · `63-event-documentation.md` ·
`15-payments-invoicing-vat.md` · `32-exhibitor-management.md` · `34-sponsor-management.md` ·
`39-printer-integration.md` · `55-event-intelligence.md` · `102-hardware-procurement.md` ·
`103-hardware-deployment.md` · `108-post-event-closeout.md` · `04-product-strategy.md`
