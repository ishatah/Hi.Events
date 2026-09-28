# Seating

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** New subsystem · **Priority:** P2, and a candidate to defer
**Depends on:** `25-zones-and-permissions.md`

---

## Current state — table exists, no behaviour

`CONFIRMED`: Phase 1 created the `seats` table — `room_id`, `section`, `row`, `number`, `label`,
`seat_type`, `position` jsonb, `is_accessible`, `status`, with a partial unique index on
`(room_id, section, row, number)`.

Nothing reads or writes it. There is no seat map, no assignment, no selection UI.

## The honest question first

**Does ARZO's event mix actually need reserved seating?**

Most of what the platform serves — conferences, exhibitions, general-admission events, corporate
functions — does not. Galas and theatre-style ceremonies do.

Seat-map authoring is a substantial UI project (comparable to the badge canvas in `22`). Building it
speculatively is the single most likely way to spend weeks on something nobody uses. `04` lists it
under "buy or defer" for that reason.

**Recommendation: keep the table, defer the feature until a real event requires it.** The table costs
nothing and means the eventual build is additive.

## If built

### Assignment models
| Model | Description | Complexity |
|---|---|---|
| **Unreserved** | GA within a zone. Today's behaviour. | None |
| **Assigned at issue** | Organizer or system allocates; attendee is told | Low |
| **Attendee-selected** | Live seat picker at checkout | **High** — needs hold/release concurrency |
| **Table seating** | Groups at named tables, common for galas | Medium |

Attendee-selected is where the cost is: seats must be held during checkout and released on
abandonment, which means the same advisory-lock discipline as order creation (`11`) and a hold-expiry
sweep.

For ARZO's likely needs, **table seating** is probably the higher-value case and much cheaper.

### Relationship to access control

Seats live in rooms; rooms are governed by zones (`25`). Access is granted to **zones**, not seats.

`25` assumes zone-level control is sufficient, and that assumption should hold unless a client
requires per-seat enforcement — which would mean a rules engine evaluating thousands of grants per
room rather than one.

### Accessibility

`seats.is_accessible` exists. Accessible seating is frequently a legal requirement, not a
nice-to-have: reserved quantity, companion seating, and a booking path that does not force disclosure
of a disability to a call centre. `81` owns the standard.

## Open questions

- **Does ARZO need this at all in the next year?** Answerable in one sentence by the business, and it determines whether anything here gets built.
- **Buy a seat-map component or build?** Building is weeks; `04` leans buy.
- **Is table seating the real requirement** rather than row/seat? For galas it usually is.
- **Should seat assignment appear on the badge?** Trivial once `22` has a `FIELD` binding, and genuinely useful for ceremonies.

## Related

`25-zones-and-permissions.md` · `11-ticketing-commerce.md` · `22-badge-design-printing.md` ·
`81-accessibility.md` · `04-product-strategy.md`
