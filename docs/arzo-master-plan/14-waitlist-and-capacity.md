# Waitlist and Capacity

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Keep + **New sibling table** (decision changed — see below)
**Depends on:** `11-ticketing-commerce.md` · **Blocks:** `27-sessions-tracks.md`

---

## Current state — `CONFIRMED`, ~85%

| Capability | State | Evidence |
|---|---|---|
| Waitlist entries | `CONFIRMED` | `waitlist_entries` |
| Offer with expiry | `CONFIRMED` | `offer_token`, `offered_at`, `offer_expires_at` |
| Self-service cancel | `CONFIRMED` | `cancel_token` |
| Position in queue | `CONFIRMED` | `position` |
| Scheduled expiry sweep | `CONFIRMED` | `ProcessExpiredWaitlistOffersJob`, every minute |
| Capacity-triggered processing | `CONFIRMED` | `CapacityChangedEvent` → `ProcessWaitlistOnCapacityAvailableListener` |
| Order linkage on purchase | `CONFIRMED` | `order_id`, `purchased_at` |
| Shared capacity pools | `CONFIRMED` | `capacity_assignments`, `product_capacity_assignments` |
| Localised offer emails | `CONFIRMED` | `locale` column + three mailables |

## Decision changed: session waitlists get their own table

`113-roadmap.md` and an earlier revision of `27` proposed **extending** `waitlist_entries` with a
nullable `session_id`, and flagged it `UNVERIFIED` pending a read of the service layer.

**That read is now done, and the answer is no.** Evidence:

1. `waitlist_entries.product_id` is **NOT NULL** and foreign-keyed to `products`. A session waitlist
   has no product.
2. The table is purchase-shaped throughout: `offer_token`, `purchased_at`, `order_id`. For a session
   these are permanently null — a session offer is accepted by registering, not by buying.
3. `ProcessWaitlistService` carries **22 product references** and derives capacity from product
   quantity (`'You will need to increase the available quantity for the product or date'`).

Extending it would mean making `product_id` nullable, leaving four columns permanently null for one
of two row shapes, and branching the offer service on which shape it is holding. That is the classic
route to a table that serves two masters and confuses both.

**Target instead:** `session_waitlist_entries`, a sibling with the same lifecycle vocabulary
(`PENDING` → `OFFERED` → `ACCEPTED` → `EXPIRED` → `CANCELLED`) pointing at `session_id` and
`attendee_id`, accepted by creating a `session_registrations` row.

What **is** worth sharing is the *policy*, not the table: offer window length, expiry sweep cadence,
and position handling should be one domain service with two repositories behind it.

```
session_waitlist_entries
  id, short_id, session_id, attendee_id,
  status, position int,
  offered_at NULL, offer_expires_at NULL, accepted_at NULL, cancelled_at NULL,
  offer_token unique NULL, cancel_token unique NULL,
  locale, metadata jsonb, timestamps, deleted_at
  UNIQUE (session_id, attendee_id) WHERE deleted_at IS NULL
```

This supersedes backlog item ARZ-081 as written; the item stays, its approach changes.

## Zone capacity is not a waitlist

`25` and `24` derive zone occupancy from `access_logs` rather than storing a counter, precisely
because offline replay makes counters drift. Zone capacity therefore **denies entry** when full; it
does not queue people. Queue management (`20`) handles the human queue outside the door.

Do not reuse this subsystem for zones.

## Open questions

- **Should session waitlist offers be automatic or manual?** Product waitlists are triggered by `CapacityChangedEvent`. Sessions cancel more casually, so automatic offers may fire constantly. Leaning automatic with a per-session toggle.
- **Does an accepted session offer need a deadline distinct from the offer expiry?** Probably not.
- **Multi-session waitlist for one attendee** — allowed by the unique constraint above (one per session, many sessions). Worth confirming it is desirable.

## Related

`11-ticketing-commerce.md` · `27-sessions-tracks.md` · `20-queue-management.md` ·
`24-access-control.md` · `136-master-backlog.md` (ARZ-081)
