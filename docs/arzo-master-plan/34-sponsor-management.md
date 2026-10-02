# Sponsors

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem (small) · **Priority:** P2 · **Phase:** 3 (listing) · 5 (fulfilment evidence)
**Depends on:** `32-exhibitor-management.md` (companies), `23-accreditation.md`, `46-promo-codes.md`
**Blocks:** `51-reporting.md` (sponsor fulfilment report)

---

## Current state — `MISSING`

`CONFIRMED`: no sponsor, sponsorship, package or entitlement entity.

| Fact | Evidence |
|---|---|
| No sponsor or partner logo image type | `ImageType.php:14-22` — `GENERIC`, `EVENT_COVER`, `TICKET_LOGO`, `ORGANIZER_LOGO`, `ORGANIZER_COVER` |
| No sponsors section on the event page | `EventHomepage` has no partner/sponsor/logo strip; `HomepageDesigner` uses only `EVENT_COVER` |
| Complimentary passes are expressible today | 100% promo codes (`PromoCodeDiscountTypeEnum` `PERCENTAGE`, `max_allowed_usages`) |
| Only orders can be invoiced | `invoices.order_id` NOT NULL (`32`, fact 3) |
| Demo conference names sponsors only in prose | `ConferenceDemoEvent.php:445` |

The most visible sponsor deliverable — a logo on the event page — cannot be produced at all today.

## Decision changed: sponsors are not exhibitors

The earlier scaffold said "sponsors are exhibitors with entitlements". Two facts make that
the wrong shape:

- **Many sponsors never exhibit.** A title sponsor, a media partner or a hospitality partner has no
  booth, no stand staff and no leads.
- **Many exhibitors never sponsor.** Forcing sponsorship fields onto every exhibitor adds empty
  columns to the common case.

What *is* shared is the company. So: **one `companies` profile (`32`), two independent
participations** — `event_exhibitors` and `sponsorships`. A company that does both has one row of
each, linked through `company_id`, and reports can join them.

## What sponsors are actually buying

Visibility and access, delivered as **entitlements**. Tracking what was promised against what was
delivered is the commercially valuable part, because it is what a sponsor asks for at renewal and
what a dispute turns on.

| Entitlement | Fulfilled by |
|---|---|
| Logo placement (event page, badges, screens, emails) | A public sponsor strip (`87`); a badge `IMAGE` element (`22`) |
| Complimentary guest passes | Single-use promo codes (`46`) |
| Staff / hospitality passes | `SPONSOR` accreditations (`23`) |
| Booth | An `event_exhibitors` row plus booth assignment (`32`, `35`) |
| Speaking slot | A session with the sponsor's speaker (`27`, `28`) |
| Branded session or zone | Session or zone naming, plus attendance evidence (`54`) |
| Email mention, social post | Manual, with evidence attached |

### Guest passes versus staff passes

A useful distinction that decides the mechanism:

- **Guests** are ordinary attendees who happen not to pay. They register through the normal flow
  with a **single-use complimentary promo code**, so they appear in attendance, receive tickets and
  count as audience.
- **Sponsor staff and hospitality** need access an ordinary attendee lacks — a VIP lounge, early
  entry. They get a `SPONSOR` **accreditation** with its own zone grants.

Using accreditations for guests would put audience members through an approval workflow; using
promo codes for staff would give them the wrong access. Both mechanisms already exist or are
already planned.

## Model

```
sponsorship_packages             -- what the organizer sells, per event
  id, short_id, event_id, name, tier, sort_order,
  price numeric NULL, currency NULL,
  entitlements jsonb,            -- template: [{type, quantity, description}]
  max_sponsors NULL int,
  timestamps, deleted_at

sponsorships                     -- one company, one event
  id, short_id, event_id, company_id,
  sponsorship_package_id NULL,
  tier,                          -- denormalized; may be custom per deal
  status,                        -- PROPOSED | CONTRACTED | ACTIVE | FULFILLED | CANCELLED
  contract_value numeric NULL, currency NULL, payment_status,
  display_name NULL, logo_image_id NULL → images, website_url NULL,
  show_on_event_page bool default false, display_order int,
  timestamps, deleted_at
  UNIQUE (event_id, company_id) WHERE deleted_at IS NULL

sponsorship_entitlements         -- instantiated from the package, then negotiated
  id, sponsorship_id, entitlement_type, description,
  quantity int, fulfilled_quantity int default 0,
  status,                        -- PENDING | IN_PROGRESS | FULFILLED | WAIVED
  due_at NULL, fulfilled_at NULL,
  evidence jsonb,                -- image ids, promo code ids, session id, notes
  timestamps
```

`entitlements` on the package is a template; each sponsorship instantiates rows because real deals
are negotiated away from the price list. The package keeps list price; the sponsorship keeps what
was actually agreed.

A new `ImageType` of `SPONSOR_LOGO` is needed so logos get their own processing rules (`73`) rather
than piggybacking on `GENERIC`.

## Fulfilment evidence — where ARZO can be better than a spreadsheet

Because access and attendance are append-only logs (`24`, `27`), several entitlements can carry
**system evidence** rather than a screenshot:

| Promise | Evidence |
|---|---|
| "20 guest passes" | Redemptions of the sponsor's codes, and how many of those guests actually entered |
| "Branded networking lounge" | Unique visitors and dwell time in that zone (`54`) |
| "Sponsored keynote" | Session attendance versus room capacity |
| "Booth in the main hall" | Lead captures (`33`) |

This is differentiator 3 in `112` — evidence-based reporting — applied to the audience that pays
for it. A post-event sponsor report generated from these figures (`51`, `133` A1) is a strong renewal
tool.

**Guardrail:** sponsor reports carry **aggregates only**. Individual attendee movement is never
shown to a sponsor (`54`, `65`).

## Billing

As `32`: v1 records `contract_value` and `payment_status` and leaves invoicing to finance. Sponsor
contracts are negotiated and staged; card checkout is the wrong tool.

## Open questions

- **Sold as products with tax, or contracted offline?** The scaffold asked; the recommendation is offline for v1, with the `GENERAL`-product path from `32` available later.
- **Is sponsor visibility analytics wanted by ARZO's actual sponsors?** The evidence falls out of the architecture almost free; the report is the work.
- **Logo placement on badges** — per-sponsor badge variants multiply templates. A fixed sponsor band on the event's badge is simpler.
- **Tier naming** — fixed (Platinum/Gold/Silver) or free text per event? Free text with a sort order is the flexible default.

## Related

`32-exhibitor-management.md` · `33-exhibitor-lead-capture.md` · `46-promo-codes.md` ·
`23-accreditation.md` · `51-reporting.md` · `54-attendance-intelligence.md` ·
`73-file-management.md` · `87-ui-ux-system.md` · `112-arzo-differentiators.md`
