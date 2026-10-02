# Beyond Qatar

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Business commitment + Extend · **Priority:** **P1 for the home-market payment gap** (ARZ-191, today P2 — recommend raising); P3 for expansion · **Phase:** payment decision now; expansion after Phase 3
**Depends on:** `82-localization.md`, `15-payments-invoicing-vat.md`, `65-privacy-gdpr.md`, `84-infrastructure.md`
**Blocks:** `98-commercial-model.md`, `100-saas-tenancy.md`

---

What it takes to sell and run events outside Qatar. The audit's main finding is that **Qatar itself
is not yet covered**: the platform's only online payment processor does not serve Qatar-based
businesses, and it has no Arabic. Expansion is the second problem.

## Current state — `PARTIAL`, ~35%: good multi-currency bones, no home-market payments

| Fact | Evidence |
|---|---|
| 142 currencies accepted, including QAR, SAR, AED, KWD, BHD, OMR | `backend/data/currencies.php:9,21,74,101,109,114`; validated in `UpsertOrganizerRequest.php:12,21` |
| Money in `brick/money` ^0.10.1, which knows KWD, BHD and OMR have **three** decimals | `composer.json:13`; checked in the dev container: `KWD digits=3 minor(1.5)=1500` |
| Timezone on accounts, organizers, events, venues, sessions and users; validated with Laravel's `timezone` rule | `information_schema`; `UpsertOrganizerRequest.php:20` |
| 19 selectable locales, **no Arabic**, no RTL | `82` |
| Payment providers: `STRIPE` and `OFFLINE` only; Stripe Connect per organizer | `15`; `organizer_stripe_platforms` |
| New-account currency guessed from the browser: plain `ar` → AED, and `en-QA` is unmapped, falling back to USD | `utilites/currency.ts:205,274-314`; `Register/index.tsx:32` |

## The critical finding — Stripe does not serve Qatar-based businesses

`CONFIRMED` against Stripe's own pages, accessed 2026-09-29:

| Source | What it shows |
|---|---|
| `https://stripe.com/global` | The list of countries where businesses can open a Stripe account includes the **United Arab Emirates** and **does not include Qatar**, Saudi Arabia, Bahrain, Kuwait or Oman |
| `https://docs.stripe.com/connect/cross-border-payouts` | Self-serve cross-border payouts work only between platforms and connected accounts in the US, UK, EEA, Canada and Switzerland: "Stripe doesn't support self-serve cross-border payouts to countries outside the listed regions. Contact sales to discuss alternatives, or use Global Payouts." |

Precisely what follows:

1. **ARZO, as a Qatar-domiciled company, cannot open a Stripe account** to be merchant of record for
   its own events. `15`'s open question — merchant of record or platform — assumed Stripe was
   available to ARZO. It is not, in Qatar.
2. **Qatar-based organizers cannot be onboarded through the existing Stripe Connect integration**,
   because their country is not a Stripe country and self-serve payouts cannot reach them. The SaaS
   model as built — organizers paid through Connect, ARZO taking application fees — does not work for
   the home market.
3. What still works today: **`OFFLINE` payments** — bank transfer or invoice, marked as paid. That
   fits B2B, government and accreditation fees; it does not fit public ticket sales.
4. Whether Stripe's sales team offers an arrangement for Qatar is `UNVERIFIED`; the documentation
   points only to "contact sales" or Global Payouts, which is a payout product, not acquiring.
5. Third-party guides describe routing around this with a foreign entity. That is a legal and tax
   structure, not an engineering choice, and this plan does not recommend it on engineering grounds.

### Options

| Option | How | Trade-off |
|---|---|---|
| **A. A Qatar-licensed gateway** as a new provider (ARZ-191) | Acquiring in QAR for a Qatar entity; cards, and local debit where the gateway supports it (`UNVERIFIED` per gateway) | One integration per provider. The provider abstraction exists (`PaymentProviders` plus handlers), but refunds, webhooks and payouts are per provider, and marketplace-style split payments comparable to Connect are `UNVERIFIED` for local gateways |
| **B. An entity in a Stripe country** (the UAE is listed) as merchant of record | ARZO's events sold by that entity | Corporate, tax and licensing consequences — `UNVERIFIED`; the business and its advisers decide (`66`) |
| **C. Offline / invoice** | Exists today | B2B only; manual reconciliation |
| D. A third-party ticketing reseller as merchant of record | Outside the platform | Gives up the checkout — defer |

**Recommendation:** C immediately for B2B and accreditation fees; **A for public ticket sales at
ARZO's own Qatar events**, which makes ARZ-191 a P1 home-market requirement rather than P2 parity; B
only if the SaaS business targets markets where Stripe operates. Candidate gateways are for
procurement to shortlist — third-party sources name local options, none of which this audit
evaluated (`UNVERIFIED`).

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| G1 | No online payment path for Qatar-based merchants | Above | **Critical — business decision now** |
| G2 | Three-decimal currencies: the recorded **application fee is ten times too large** for KWD, BHD and OMR orders — `toMinorUnit()` yields thousandths, and the fee service divides by 100 — on both the Stripe and offline paths. Discount allocation and payout reconciliation also hardcode ×100 | `OrderApplicationFeeService.php:24-28`; `MoneyValue.php:35-38`; `PaymentIntentSucceededHandler.php:302-308`; `MarkOrderAsPaidService.php:205-212`; `OrderDiscountAllocationService.php:19`; `StripePayoutService.php:111,115`; `StripePaymentPlatformFeeExtractionService.php:207-220` | Medium — latent until a KWD, BHD or OMR event charges fees; fix before any GCC expansion by deriving the multiplier from `brick/money` |
| G3 | New Qatari accounts default to AED (Arabic browser) or USD (English browser) | `currency.ts:205`; `Register/index.tsx:32` | Low — fix now: map region `QA` to QAR regardless of language |
| G4 | QAR, SAR and OMR share one symbol in price inputs | `82` L6 | Low |

QAR itself is a two-decimal currency, so G2 does not affect Qatar.

## Decision: Arabic is a home-market requirement, not expansion

`112` already concluded this. Arabic belongs to `82`'s programme and to Qatar's readiness, not to this
document's market list. Every GCC market below inherits it for free once Qatar is done.

## What each new market requires

| Gate | Question per market | Owner |
|---|---|---|
| Payments | Is there a supported acquirer for the merchant's country, and does it support split payouts? | `15`, procurement |
| Currency | Decimals (KWD, BHD, OMR are three — G2); symbol; rounding rules | Engineering |
| Tax and invoicing | VAT regime and e-invoicing obligations (Saudi Arabia runs a mandated e-invoicing regime — specifics `UNVERIFIED`); the current invoice is a dompdf PDF | Tax adviser, `15`, `66` |
| Residency | Does the market's data-protection law restrict transfers to where ARZO hosts? | Counsel, `65`, `84` |
| Language | Arabic done (`82`); any additional language through the existing Lingui flow | `82` |
| Messaging | Sender ID registration and delivery quality per country (`43`); WhatsApp template approval | `43` |
| Local operations | For ARZO-operated events: staffing, hardware logistics, venue partners | `57`, `102`, `106` |
| Accessibility | The local accessibility obligations, if any | `66`, `81` |

Timezones need nothing: every event carries its own, and the Gulf states observe no daylight saving
time.

## Decision: Qatar parity before any second market

Sequence:

1. **Qatar parity** — a local payment path (G1), Arabic for attendees (`82` A1–A2), a hosting and
   residency answer (`84`).
2. **The market ARZO's own clients already take it to.** Which that is — `UNVERIFIED`. The UAE is the
   only GCC state on Stripe's list, which makes it cheapest to add for SaaS; Saudi Arabia is a large
   market with heavier invoicing and residency obligations — market size and obligations both
   `UNVERIFIED`, and for the business to weigh.
3. **Outside the GCC** only on demand. The 19 existing locales and 142 currencies already serve
   Hi.Events' international market; ARZO adds nothing there by default.

**Objection:** "SaaS revenue is in bigger markets." Possibly — but the buyer question (`04`) is
unanswered, and a platform that cannot take a card payment at home is not ready to sell abroad.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Business decision on G1 options (A, B, C) with counsel and tax advice | **Now** |
| 2 | G3 region-to-currency default; G4 symbol | Now — small |
| 3 | ARZ-191: first local gateway behind the provider abstraction; delete the dead Razorpay domain object (`15`) | After step 1 |
| 4 | G2: currency-aware minor units everywhere, with tests for 0-, 2- and 3-decimal currencies | Before any KWD, BHD or OMR event |
| 5 | Per-market gate review (table above) for the second market | When the business names it |

## Open questions

- **Which option for Qatar payments?** A, B or both — a business and legal decision, not engineering.
- **Does the chosen local gateway support marketplace payouts?** If not, SaaS organizers in Qatar are paid by ARZO manually, which makes ARZO the merchant of record by default (`15`, `66`).
- **Which second market, if any?** Driven by where ARZO's clients run events.
- **Is QAR a Stripe presentment currency?** Relevant only under option B; Stripe's currency list did not render when checked — `UNVERIFIED`.

## Related

`82-localization.md` · `15-payments-invoicing-vat.md` · `65-privacy-gdpr.md` · `66-compliance.md` ·
`84-infrastructure.md` · `43-sms-notifications.md` · `04-product-strategy.md` · `98-commercial-model.md` ·
`100-saas-tenancy.md` · `112-arzo-differentiators.md` · `136-master-backlog.md`
