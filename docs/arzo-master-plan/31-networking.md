# Networking, Meetings and Engagement

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P3 (ARZ-210, ARZ-211, ARZ-212) · **Phase:** 5 (eRaffle may move into late Phase 3)
**Depends on:** `30-mobile-event-app.md`, `27-sessions-tracks.md`, `32-exhibitor-management.md`, `65-privacy-gdpr.md`
**Blocks:** nothing — this is a leaf capability

---

## Current state — `MISSING`

`CONFIRMED` by search of `backend/`, `frontend/src` and migrations for networking, meeting,
appointment, matchmaking, connection, chat, direct message and attendee directory: no product code.
The hits are a spam-detection prompt, a test fixture string, and demo seeders.

What exists that is adjacent:

| Fact | Evidence |
|---|---|
| Attendee → organizer contact form | `POST /public/organizers/{id}/contact`, throttled 5/min, emails the organizer; blocked unless the organizer is `LIVE` (`api.php:617-618`, `SendOrganizerContactMessageHandler.php`) |
| No attendee → attendee channel of any kind | Search above |
| No public attendee directory | Only check-in-list links and a single-attendee lookup expose attendee names |
| Attendees have no login | Identity is a magic link (`ticket_lookup_tokens`, `/my-tickets/:token`) |
| `sessions.session_type` is free `varchar`, default `TALK` | `2026_09_29_000004`; no enum class exists |

**Privacy baseline:** attendee data is private by default today, and that default is the correct
one to preserve. Anything below that makes an attendee discoverable is an opt-in.

## Is this actually wanted?

Asked first, because networking is the feature most often built because competitors list it.

| Event type | Networking value |
|---|---|
| Concerts, galas, festivals | **None.** Nobody books a meeting at a concert. |
| Consumer exhibitions | Low |
| **B2B exhibitions and trade shows** | **High** — specifically attendee ↔ exhibitor meetings ("hosted buyer" programmes) |
| Conferences | Medium — directory and connections, rarely scheduled meetings |

The commercially valuable subset is narrow: **scheduled meetings between buyers and exhibitors.**
Attendee-to-attendee social features are the long tail. Build order below follows that.

## Decision changed: meetings are not sessions

The earlier scaffold said meetings are "a session subtype with a room and participants". Reading
the landed `sessions` schema reverses that. Four reasons, each sufficient alone:

1. **The room constraint forbids it.** `sessions_no_room_overlap` is a GiST exclusion on
   `(room_id, tstzrange)` for published sessions. A meeting lounge hosts twenty concurrent meetings
   in one room — exactly what the constraint rejects. The only way round it is to leave meetings
   unpublished, which overloads `is_published` to mean two different things.
2. **The lifecycle is different.** A session is authored by the organizer and published. A meeting
   is *requested* by one party and *accepted or declined* by another, then possibly rescheduled.
3. **Participants are not registrations.** `session_registrations` is attendee-to-programme, with
   capacity and waitlists. A meeting has two to six named participants who must each consent.
4. **Privacy is inverted.** Sessions exist to be seen on the agenda. A meeting between a buyer and
   an exhibitor is private to its participants.

**Target instead:** a small `meetings` model of its own. Hosted *group* formats — a roundtable, a
speed-networking block — remain sessions with `session_type = MEETING` or `NETWORKING`, because they
*are* programme items.

## Target model

Participants are `persons`, not `attendees`. Exhibitor staff (`32`) are persons without tickets, and
the attendee ↔ exhibitor meeting is the valuable case — keying on `attendee_id` would exclude it.

```
networking_profiles
  id, short_id, event_id, person_id,
  is_discoverable bool default false,          -- opt-in, never default-on
  share_contact_on_connect bool default false,
  headline NULL, interests jsonb, looking_for jsonb,
  opted_in_at NULL, opted_out_at NULL,
  timestamps, deleted_at
  UNIQUE (event_id, person_id) WHERE deleted_at IS NULL

connections
  id, short_id, event_id,
  requester_person_id, recipient_person_id,
  status,        -- PENDING | ACCEPTED | DECLINED | BLOCKED
  source,        -- REQUEST | BADGE_SCAN | MEETING
  note NULL (≤ 280 chars), responded_at NULL, timestamps
  UNIQUE (event_id, LEAST(requester, recipient), GREATEST(requester, recipient))

meetings
  id, short_id, event_id, event_occurrence_id NULL,
  event_exhibitor_id NULL,                     -- set for buyer ↔ exhibitor meetings (32)
  starts_at timestamptz, ends_at timestamptz,
  room_id NULL, location_label NULL,           -- "Table 14, Business Lounge"
  status,        -- REQUESTED | CONFIRMED | DECLINED | CANCELLED | COMPLETED | NO_SHOW
  note NULL, timestamps, deleted_at
  CHECK (ends_at > starts_at)

meeting_participants
  id, meeting_id, person_id,
  role,          -- REQUESTER | INVITEE | HOST
  response,      -- PENDING | ACCEPTED | DECLINED
  responded_at NULL, timestamps
  UNIQUE (meeting_id, person_id)

networking_blocks
  id, event_id, blocker_person_id, blocked_person_id, created_at
  UNIQUE (event_id, blocker_person_id, blocked_person_id)
```

A person double-booked into two confirmed meetings is a **warning** to the requester and a **hard
error** at confirmation — the same asymmetry as attendee versus speaker clashes in `29`. Table
double-booking within a lounge is application-checked against `location_label`, not a GiST
constraint, because lounges are informal.

`timestamptz` throughout, matching the programme tables (`27`, `121`).

## Messaging — deliberately not built

No free-form chat. A meeting or connection request carries a short note; after acceptance, contact
details are shared only if the recipient set `share_contact_on_connect`.

The reasons are obligations, not effort:

- **Moderation.** Any open channel between strangers will carry harassment. It needs reporting,
  review and removal, and someone on the organizer side to do it.
- **Retention.** Messages are personal data with their own deletion obligations (`65`).
- **Identity.** Attendees authenticate by magic link (`30`). A chat product on bearer links is a
  chat product where a forwarded email is an account takeover.

**Blocking is mandatory the moment any contact is possible**, including connection requests. A
blocked person can neither see the blocker in the directory nor send them requests.

## Engagement — polls, Q&A, eRaffle

`136` files these here (ARZ-211, ARZ-212). Assessed separately because their costs differ by an
order of magnitude:

| Feature | Verdict | Why |
|---|---|---|
| **eRaffle** (ARZ-211) | **Build — cheap** | The eligible pool is derivable from `access_logs` (a `GRANTED` entry during the draw window). Evento parity item. |
| Live polls (ARZ-212) | **Integrate** | Needs realtime (`71`) at audience scale and a presenter view; commodity tools exist |
| Session Q&A (ARZ-212) | **Integrate** | As polls, plus moderation of submitted questions |

The eRaffle carries one real requirement: **the draw must be auditable.** Record the eligible pool
size, the random seed, the winner, who ran the draw, and when (`67`). Exclude staff and exhibitor
credentials by default. A contested prize with no record is a reputational incident.

## Matchmaking

Recommendations of whom to meet: **deterministic first** — overlap of `interests` and `looking_for`,
plus exhibitor category. `133` notes that three of its AI candidates are better solved without a
model; this is a fourth. Revisit only with outcome data (meetings that were accepted and attended).

## Build order

| # | Capability | Size | Precondition |
|---|---|---|---|
| 1 | eRaffle from `access_logs` | S | ARZ-041 consolidation, so the pool is complete |
| 2 | Buyer ↔ exhibitor meeting booking | M | `32` exhibitor staff, `30` app identity |
| 3 | Opt-in directory, connections, badge-scan-to-connect | M | `30`, `65` consent wording |
| 4 | Polls and Q&A | — | Integrate, do not build |

## Open questions

- **Attendee identity.** A directory viewer must be authenticated as an attendee of that event. Is a per-event magic-link session enough, or does networking force attendee accounts? This is the `30` accounts question, and networking is what makes it urgent.
- **Badge-scan-to-connect consent.** Scanning someone's badge in the app is a data transfer between attendees. It should require the scanned person's `share_contact_on_connect`, otherwise it only records that a connection was *requested*.
- **Meeting slots.** Free-form times, or organizer-defined slots (e.g. 20 minutes on the half hour)? Slots make lounges manageable and reduce double-booking; they need a small `meeting_slots` table.
- **Who moderates reports**, and within what time? An operating-model question (`106`).
- **Which poll/Q&A tool?** Its data export decides whether engagement feeds `54`.

## Related

`30-mobile-event-app.md` · `27-sessions-tracks.md` · `29-agenda-scheduling.md` ·
`32-exhibitor-management.md` · `33-exhibitor-lead-capture.md` · `44-push-notifications.md` ·
`65-privacy-gdpr.md` · `67-audit-logging.md` · `133-ai-capabilities.md`
