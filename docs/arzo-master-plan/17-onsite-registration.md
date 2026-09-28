# On-Site / Walk-In Registration

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Extend · **Priority:** P2
**Depends on:** `71-realtime-architecture.md`, `23-accreditation.md`, `21-badge-management.md`
**Blocks:** `19-kiosk-system.md`

---

## Current state — `PARTIAL`, ~30%

The pieces exist; they are in the wrong place.

`CONFIRMED`:

- `CreateAttendeeAction` creates an attendee manually and accepts `override_capacity`
- `MarkOrderAsPaidAction` plus the `OFFLINE` payment provider support door sales
- `allow_orders_awaiting_offline_payment_to_check_in` lets an unpaid attendee through the gate

**But all of it lives in the organizer backoffice.** There is no at-the-door flow: a staff member
must open the admin UI, create an attendee, create or mark an order, then switch to the scanner.

For a queue of walk-ins that is unusable, and it is why this scores 30% rather than 0%.

## Target flow

One screen, four outcomes, **working offline**:

```
capture name, email, phone, ticket type
  -> create person + attendee            (23, 16)
  -> take payment: cash | defer | card-if-online   (15)
  -> issue credential + materialize grants          (23, 24)
  -> print badge                                    (21, 22)
```

Target: **under 60 seconds** per walk-in, including the badge. Above that a queue forms faster than
it drains, which is the actual failure mode at a busy door.

## Offline behaviour

Walk-in is the hardest offline case in the plan, because it **creates** data rather than validating
it. Per `71`:

| Concern | Rule |
|---|---|
| Person and attendee creation | Captured locally, queued, reconciled on sync |
| Identifiers | Device-generated UUID (`client_generated_id`), so replay is idempotent |
| Credential | Issued locally from a device-held identifier range, marked provisional |
| Badge | Printed locally from cached template |
| **Card payment** | **Never offline** — PCI and chargeback exposure (`15`) |
| Cash | Recorded locally, reconciled at closeout (`108`) |
| Duplicates | Server flags a probable duplicate; **never auto-merges** |

The duplicate case is the one to design carefully: someone who registered online and then walks up
because they cannot find their email will be created twice. Flag and let a human decide.

## Capacity

`override_capacity` already exists and is the right primitive — a door supervisor sometimes must
admit beyond the configured limit. Requirements: it must be **permissioned** (`09`, `attendee.edit`
or a dedicated capability), and **logged with a reason**, the same accountability model as an access
override (`24`).

## Open questions

- **Cash handling and reconciliation** — is that in scope for software, or a manual float process? The software can record; it should not pretend to be a till system.
- **Does walk-in need the full question set**, or a reduced at-the-door subset? Full forms at a desk are slow. Probably a per-event "walk-in required fields" subset.
- **Who can perform a walk-in?** Needs operator identity, which does not exist yet (F3, `92`).
- **Pre-printed versus on-demand badges** — affects whether walk-in needs printing at all (`21`).

## Related

`19-kiosk-system.md` · `15-payments-invoicing-vat.md` · `21-badge-management.md` ·
`23-accreditation.md` · `71-realtime-architecture.md` · `92-staff-platform.md`
