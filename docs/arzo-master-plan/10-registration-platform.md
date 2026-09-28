# Registration Platform

**Status:** WRITTEN · **Audit date:** 2026-09-28
**Classification:** Keep + Extend

---

## Purpose

Registration is how a person becomes known to an event. It is distinct from **ticketing commerce**
(`11`), which is how they pay, and from **accreditation** (`23`), which is how they are approved for a
category of access.

## Current state — `CONFIRMED`, ~85%

Genuinely strong, and the reason this document is short.

| Capability | State | Evidence |
|---|---|---|
| Custom questions | `CONFIRMED` | `questions`, `question_answers`, `product_questions`, 9 types in `QuestionTypeEnum` |
| Per-order vs per-ticket collection | `CONFIRMED` | `AttendeeDetailsCollectionMethod` = `PER_TICKET` \| `PER_ORDER` |
| Copy details across attendees | `CONFIRMED` | `allow_copy_details_to_all_attendees` on event settings |
| Branded registration pages | `CONFIRMED` | `HomepageDesigner`, `homepage_theme_settings` |
| Embeddable registration | `CONFIRMED` | `embed/widget.js` — Shadow DOM, origin-checked postMessage |
| Answer export | `CONFIRMED` | `AnswersExport` with multi-sheet output |
| Self-service edit | `CONFIRMED` | Gated by `allow_attendee_self_edit` |
| A DB view for reporting | `CONFIRMED` | `question_and_answer_views` |

## What changes

Only two things, both small:

**1. `person_id` on attendees.** Landed in Phase 1. Registration now produces a `person` as well as
an `attendee`, so a returning registrant can be recognized and a non-buyer (journalist, contractor)
can exist in the same identity space (`23`).

**2. Reuse the question engine for accreditation.** An accreditation application is a form with
approval attached. Building a second form engine would be duplication; `accreditations.form_data`
holds answers in the same shape.

## What explicitly does not change

The commerce coupling stays as it is. A registration currently flows through the order pipeline even
when free, and that is fine — it gives every registrant an order, an attendee and now a person, with
one code path. The case for a lighter path is RSVP (`12`), and it is a separate flow rather than a
refactor of this one.

## Open questions

- **Conditional question logic** ("show X only if Y = yes") is a common request and does not exist. It is a self-contained addition to the question engine, not a structural change. No customer has asked yet, so it is unscheduled.
- **Question reuse across events.** Questions are event-scoped; an organizer running a recurring series re-creates them. A template library would help and is low priority.
- **Registration windows** live on `event_settings`; session-level registration windows are separate (`27`). Keeping the two distinct is deliberate — see the five-windows table in `00`.

## Related

`11-ticketing-commerce.md` · `12-rsvp-registration.md` · `16-attendee-management.md` ·
`23-accreditation.md` · `27-sessions-tracks.md`
