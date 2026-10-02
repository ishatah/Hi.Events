# Affiliates and Referrals

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep + small fixes · **Priority:** P3 · **Phase:** any
**Depends on:** `11-ticketing-commerce.md`
**Blocks:** `41-event-marketing.md` (order attribution reuses the capture mechanics)

---

## Current state — `CONFIRMED`, ~80%

### Schema

```
affiliates  id, event_id, account_id, name, code varchar(50), email NULL,
            total_sales int, total_sales_gross double precision,
            status,      -- ACTIVE | INACTIVE (AffiliateStatus.php:13-14)
            timestamps
            INDEX (code)
```

The earlier scaffold said the table tracks "unique visitors". **It does not** — there is no visitor
column and no click logging.

### Capture and attribution

| Step | Mechanism | Evidence |
|---|---|---|
| Capture | `?aff=CODE` on the storefront | `SelectProducts/index.tsx:137` |
| Persistence | localStorage `affiliate_code_{eventId}`, `{code, timestamp}`, 30-day expiry | `:65,134-142` |
| Policy | **Last touch wins** — a new `?aff=` overwrites the stored code | `:133-157` |
| Submission | Hidden `affiliate_code` on order creation | `:172,1091` |
| Match | Upper-cased code, same event, `ACTIVE` | `CreateOrderHandler.php:113-124` |
| Link | `orders.affiliate_id` | `OrderManagementService.php:55` |

### Counters

- **Incremented** at the three order-completion sites: free orders (`CompleteOrderHandler.php:360`),
  offline marked-as-paid (`MarkOrderAsPaidService.php:115`), and Stripe success
  (`PaymentIntentSucceededHandler.php:141`).
- **Decremented** on cancellation only, gated on `isOrderCompleted()` plus the
  `statistics_decremented_at` idempotency marker (`EventStatisticsCancellationService.php:620-630`,
  gate at `:70`). This is the counter-symmetry rule in `CLAUDE.md`, correctly applied.
- **Refunds do not decrement** affiliate figures.

### The documented footgun — confirmed

```
BaseRepository.php:289   incrementEach(array $columns, array $additionalUpdates = [], ?array $where = null)
BaseRepository.php:300   decrementEach(array $where, array $columns, array $extra = [])
```

`where` is the **last** argument of one and the **first** of the other. Anyone writing a new
decrement by analogy with an increment passes the column map as the `where` clause. Not a defect
today; worth a named-argument convention at every call site.

### Also present
Export (`AffiliatesExport.php`), event-level management routes, and duplication with events
(`DuplicateEventService` copies affiliates).

## Defects

| # | Defect | Evidence | Fix |
|---|---|---|---|
| A1 | **Money stored as `double precision`** — `total_sales_gross` | Live schema | `numeric(14,2)`, matching how orders store money. Floats accumulate rounding error in a column that is only ever summed. |
| A2 | **No unique `(event_id, code)` index** — uniqueness is application-only | Migration indexes `code` alone; `CreateAffiliateHandler.php:31` | Partial unique index. Two concurrent creates can currently produce a duplicate code, and attribution then picks one arbitrarily. |
| A3 | Refunds leave `total_sales_gross` overstated | No refund path touches affiliates | **Track, do not decrement:** add `total_refunded`, mirroring `event_statistics.total_refunded`, so gross stays auditable and net is derivable |

A3 follows the same reasoning `event_statistics` already uses: refunds are recorded as their own
figure rather than rewriting gross.

## What to add — only when there is a real affiliate programme

| Capability | Condition |
|---|---|
| **Affiliate self-service stats** — a tokenized, read-only link showing their own sales | As soon as any affiliate asks "how am I doing?" — cheap, the `ticket_lookup_tokens` pattern |
| Commission rules and payout reports | Only if ARZO actually pays commission. A commission engine for a programme that pays by spreadsheet is dead code. |
| Click logging and conversion rate | Only if affiliates are paid per click or judged on conversion — needs a `affiliate_clicks` table and bot filtering |
| Self-referral and fraud checks | With commission, not before — there is nothing to defraud without payout |

## Uses beyond classic affiliates

The mechanism is generic "attribute an order to a named third party", which fits two planned needs
at no cost:

- **Exhibitors inviting their customers** (`32`) — one affiliate per exhibitor gives them a
  trackable invitation link and gives sponsors evidence of the audience they brought (`34`).
- **Partner and media promotions** — the Festival demo already models a transport partner this way.

## Relationship to order attribution

`41` adds channel attribution (UTM, referrer, click ids) to every order. The two coexist: an order
can come from a paid Instagram campaign **and** carry a promoter's affiliate code. Keep them in
separate columns — merging them into one "source" loses whichever was not chosen.

## Open questions

- **Does ARZO pay affiliates or promoters?** Decides whether anything beyond A1–A3 is built.
- **Last touch or first touch?** Last touch is implemented; it should at least be stated to affiliates.
- **Is 30 days right?** For events on sale for months, early promoters lose attribution to late ones.

## Related

`41-event-marketing.md` · `46-promo-codes.md` · `11-ticketing-commerce.md` ·
`32-exhibitor-management.md` · `34-sponsor-management.md` · `51-reporting.md`
