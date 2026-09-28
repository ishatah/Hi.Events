# Attendee Management

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Keep + Extend · **Depends on:** `23-accreditation.md`

---

## Current state — `CONFIRMED`, ~80%

| Capability | State | Evidence |
|---|---|---|
| CRUD + pagination/filtering | `CONFIRMED` | `api.php`, `applyFilterFields()` allowlist |
| Manual creation with capacity override | `CONFIRMED` | `CreateAttendeeAction` + `override_capacity` |
| Edit, partial edit | `CONFIRMED` | `EditAttendeeHandler`, `PartialEditAttendeeHandler` |
| Cancel | `CONFIRMED` | Status transitions, fires domain events |
| CSV/Excel export | `CONFIRMED` | `AttendeesExport`, `AnswersExport` with multi-sheet output |
| Self-service edit | `CONFIRMED` | Gated by `allow_attendee_self_edit`, throttled public endpoints |
| Magic-link ticket access | `CONFIRMED` | `ticket_lookup_tokens`, `/my-tickets/:token` |
| Resend ticket / confirmation | `CONFIRMED` | Public and authenticated paths |
| Per-occurrence attendees | `CONFIRMED` | `attendees.event_occurrence_id` |
| **`person_id`** | `CONFIRMED` | Landed Phase 1 — nullable FK, fully backfilled |

## What changed in Phase 1

`attendees` gained a nullable `person_id`, backfilled for every existing row by matching lowercased
email within an account. Verified: two attendees whose emails differed only in case collapsed to one
person.

**`attendees` is unchanged otherwise.** It has only 3 inbound FKs — it is a leaf, not a hub — so
credentials, badges and access logs hang off `persons` and `credentials` instead. That was the whole
reason for introducing `persons` rather than widening this table.

## What changes next

### `checked_in_at` becomes advisory

Once `access_logs` is authoritative (`18`, `24`), `attendees.checked_in_at` / `checked_in_by` /
`checked_out_by` stop being the source of truth.

**Recommendation: keep the columns**, documented as a derived convenience. Dozens of queries and
reports read them, and removing them is churn for no user benefit. The risk is someone later
mistaking them for authoritative — mitigated by a comment at the definition and a note in `121`.

### Attendee → credential

Every non-cancelled attendee gets an `ACTIVE` credential at Phase 2 (`23` step 5). Ticket buyers get
one **without** an accreditation application — the ticket is the entitlement. That path must stay
frictionless; only non-buyers go through approval.

### Export and privacy

Exports currently include everything the exporter selects. Once `persons` carries ID document
numbers and dates of birth (`23`), exports must **exclude those by default** and require an explicit
opt-in with an audit entry. `65` owns the rule; this is where it bites.

## Open questions

- **Should attendee edit history be surfaced to organizers?** `order_audit_logs` is the existing pattern and attendees have no equivalent. Useful for disputes.
- **Identity merge UI.** The Phase 1 backfill matches on exact email only. Duplicates will accumulate (same person, two emails). A merge tool is a real need eventually and a privacy hazard done carelessly — never automatic (`71` conflict rules).
- **Bulk attendee operations** (bulk cancel, bulk message, bulk reassign) — partially exists via messages; no bulk edit.

## Related

`10-registration-platform.md` · `18-check-in.md` · `23-accreditation.md` · `24-access-control.md` ·
`65-privacy-gdpr.md` · `121-technical-debt.md`
