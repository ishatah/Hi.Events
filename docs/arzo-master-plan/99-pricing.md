# Pricing

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Business commitment + Fix · **Priority:** P2 — no ARZ id · **Phase:** ticketing fixes any time; on-site price model before Phase 2 exit
**Depends on:** `98-commercial-model.md`, `15-payments-invoicing-vat.md`, `102-hardware-procurement.md`
**Blocks:** `100-saas-tenancy.md`

---

How each product line is priced, what the system must count to support it, and which inputs the
business still owes. This document gives **models and inputs, not numbers**: no market price for
Qatar or the GCC has been verified, and inventing one would anchor every later conversation.

## Current state — `PARTIAL`, ~40%: one price mechanism, built for Stripe Connect

| Mechanism | State | Evidence |
|---|---|---|
| Fee = fixed × **paid** tickets + rate × order gross; free items excluded | `CONFIRMED` | `OrderApplicationFeeCalculationService.php:35-41,74-84` |
| Only when SaaS mode is on | `CONFIRMED` | same, `:31-33` |
| Per-organizer configuration (rate, fixed, currency, bypass); super-admin CRUD and assignment | `CONFIRMED` | `organizer_configurations`; `api.php:551-556` (committed) |
| System default from env: 1.5% + 0 | `CONFIRMED` | `config/app.php:23-24` |
| Currency defaults chosen from the Stripe account country — US, GB, AU mapped; everything else EUR | `CONFIRMED` | `AssignCurrencyDefaultOrganizerConfigurationService.php:19-25,61` |
| Pass the fee to the buyer, grossed up so the fee on the new total equals itself; on by default | `CONFIRMED` | `OrderPlatformFeePassThroughService.php:45-67`; `config/app.php:25` |
| VAT added on top of the fee, with Irish defaults (IE, 23%) | `CONFIRMED` | `OrderApplicationFeeCalculationService.php:96-127`; `config/app.php:89-93` |
| Organizer-facing fee preview and a Platform Fees report | `CONFIRMED` | `api.php:486`; `OrganizerReportTypes.php:13` |
| Plans, subscriptions, entitlements, per-event or per-credential prices | `MISSING` | Search for plan, subscription, entitlement, billing |
| Invoicing ARZO's own fees to organizers | `MISSING` | No reader of `order_application_fees` status |

`BootstrapDevDataCommand.php:215-245` seeds "Standard (USD/EUR/GBP/AUD)" configurations at 1.25% plus
a fixed amount. That is **dev data**, not an ARZO price list.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| PR1 | **A fixed fee in another currency is charged 1:1 when no exchange-rate key is set.** The no-op client returns the same number in the target currency, with only a log warning. A USD 0.60 fee on a QAR event becomes QAR 0.60. | `NoOpCurrencyConversionClient.php:18-30`; `AppServiceProvider.php:130-149`; `OPEN_EXCHANGE_RATES_APP_ID` commented out in `.env.example` | **High** the moment a fixed fee is configured |
| PR2 | **Fees on offline-paid orders are recorded and never collected.** Marking an order paid writes an `AWAITING_PAYMENT` fee row; nothing reads that status, invoices it or reports it as owed. | `MarkOrderAsPaidService.php:184-214`; search for `AWAITING_PAYMENT` readers | Medium — where Stripe is unavailable (`98`), this may be most orders |
| PR3 | Offline fees are stored **net** of VAT; Stripe fees are charged **gross** | `MarkOrderAsPaidService.php:210` vs `StripePaymentIntentCreationService.php:85` | Low |
| PR4 | The VAT-on-fee logic assumes an Irish platform; ARZO's tax position on its own fees is unknown | `config/app.php:89-93` | `UNVERIFIED` — tax adviser |

**Fix now:** PR1 — refuse to save a configuration whose fixed fee currency differs from the events it
will price unless a conversion client is configured, or require one configuration per currency (the
`default_for_currency` column already supports that).

## Decision: price each line by its cost structure

Four product lines with different economics. Mixing them — putting hardware into a per-ticket
percentage, say — hides loss-making events inside profitable ones.

| Line | Cost driver | Price unit | Margin logic |
|---|---|---|---|
| **Ticketing** (self-serve) | Payment processing, support, hosting per transaction | Rate + fixed per paid ticket — **existing** | Software margin |
| **On-site software** | Configuration effort per event; credentials issued; event-day support | Per-event base + per-credential band | Software margin, value-priced |
| **Hardware** | Capital, depreciation, logistics, loss, consumables | Rental per device-day; consumables per unit | **Cost-plus** |
| **Services** | Staff time: accreditation desk, gate staff, technicians | Day rates | **Cost-plus** |

### Ticketing — keep the existing unit

Rate plus fixed per paid ticket is the market's familiar shape and the machinery exists. Free tickets
carry no fee. ARZO's own events set `bypass_application_fees`, which exists. **The unsolved part is
collection, not calculation:** with no Stripe in Qatar (`98`), fees must be netted at settlement or
invoiced — which is PR2's ledger.

### On-site software — per credential, not per ticket

| Unit | Verdict | Reason |
|---|---|---|
| Per ticket | **No** | Accreditation is mostly unpriced — media, VIPs, staff, contractors. A percentage of zero is zero, for the heaviest work. |
| Per attendee | No | Ambiguous: ticket holders only, or everyone badged? |
| **Per credential issued, in bands** | **Yes** | Tracks the platform's actual work (issue, print, decide access), is unambiguous, and is countable from `credentials` |
| **Per-event base** | **Yes** | Zones, rules, templates and readiness cost the same for 300 people or 3,000 |
| Per device-day | Only for client-supplied devices | ARZO-supplied devices are priced as hardware |
| Annual subscription | As a wrapper | A volume commitment for repeat clients — annual shows, venues — not the metering unit |

Staff and contractor credentials count, in a cheaper band: they are real issuance and real access
decisions, but the client did not invite them as audience.

### Hardware — rent with operation; do not sell

Selling makes ARZO a hardware reseller with warranty and support obligations — not ARZO's business
(`04`). Renting **without** ARZO operating it ("dry hire") invites 2am support calls on devices ARZO
cannot see. So hardware is rented **as part of an ARZO-operated event**, with spares included
(`103`), until `103`'s procedures make dry hire supportable. Consumables — badge stock, lanyards,
inlays (`36`) — are passed through at cost plus a handling margin. R5 governs.

### Services — cost-plus day rates

Priced like the labour they are. A software margin on staff hides the real cost of each event.

## The model

```
-- ticketing (existing mechanics, collection per `98`)
ticketing_fee(order)   = fixed × paid_tickets + rate × order_gross
                         -- grossed up when passed to the buyer

-- on-site quote, per event
onsite_quote(event)    = base(format)                           -- SINGLE_DAY | MULTI_DAY | ...
                       + band_price(audience_credentials)
                       + band_price(staff_credentials)           -- cheaper band
                       + Σ device_days(type) × rental_rate(type)
                       + Σ consumables × unit_cost × (1 + handling_markup)
                       + Σ staff_days(role) × day_rate(role)
                       + pass_through(sms, print stock)          -- at cost

rental_rate(type)      = landed_cost / expected_event_days_over_life
                       + logistics_per_day + loss_allowance_per_day + margin
```

Every variable on the right is an input below. None is known.

## Inputs the business owes

| Input | Owner | Used for | Status |
|---|---|---|---|
| Ticketing fee levels in Qatar and the GCC | Commercial | Rate and fixed anchors | `UNVERIFIED` |
| Processor fees for the chosen provider | Finance | Floor under the ticketing fee | `UNVERIFIED` — processor not chosen (`98`) |
| Hi.Events licence | Legal | Fixed annual cost | Published 2026-09-29: Standard €499, Platform €2,499, each per year + VAT; confirm in contract (`98`) |
| Hardware landed cost, expected life, loss rate | `102` | `rental_rate` | `UNVERIFIED` |
| Staff cost per role-day | Operations (`57`) | Day rates | `UNVERIFIED` |
| Hosting, email and SMS cost per event | Engineering, once ARZO hosting exists | Floor under on-site base | `UNVERIFIED` — no ARZO deployment |
| Competitor on-site quotes | Sales | Ceiling | `UNVERIFIED` — `110` has no prices |
| Tax treatment of ARZO's fees and rentals | Tax adviser | Whether PR4's logic applies | `UNVERIFIED` |

## What the system must count

Pricing needs metering, not a billing engine. Invoices for on-site work live in ARZO's accounting
system (`04` non-goal: not an accounting system).

| Meter | Source | Exists |
|---|---|---|
| Credentials issued per event, by accreditation type | `credentials` | Table, yes; no report |
| Devices enrolled per event per day | `devices`, `last_seen_at` (`40`) | Table, yes; no report |
| Badges printed and reprinted | `badge_print_jobs` | Table, yes; no writer |
| SMS sent | `43` | No |
| Platform fees owed on offline orders | `order_application_fees` | Rows yes; ledger no (PR2) |

## Migration

| Step | Change | Risk |
|---|---|---|
| 1 | PR1: block cross-currency fixed fees without a conversion client | Low |
| 2 | Per-event metering report (credentials, device-days, prints) for quoting and reconciliation | Low |
| 3 | PR2: fee ledger with a monthly statement per organizer — only if the chosen processor cannot split payments | Medium |
| 4 | Plan and entitlement model (`100`) | Medium |

Unnumbered — add to `136` when scheduled.

## Open questions

- **Do staff and contractor credentials count toward the band?** Leaning yes, in a cheaper band — they are real issuance work.
- **Is hardware ever rented without ARZO operating it?** Leaning no until `103` makes it supportable.
- **Does the ticketing fee apply to clients whose events ARZO operates?** Commercial choice: bundle it into the on-site quote and bypass it per organizer, or charge both. Bundling is simpler to explain.
- **Pricing currency.** QAR for the home market needs per-currency fee configurations (PR1).
- **Is there a free tier for self-serve ticketing?** Only once `98`'s SaaS gates pass; until then there is no self-serve product to tier.

## Related

`98-commercial-model.md` · `100-saas-tenancy.md` · `15-payments-invoicing-vat.md` ·
`102-hardware-procurement.md` · `103-hardware-deployment.md` · `36-rfid-nfc.md` ·
`40-device-management.md` · `57-manpower-and-staffing.md` · `110-evento-comparison.md`
