# Speakers

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Extend (table exists) · **Phase:** 3
**Depends on:** `27-sessions-tracks.md`, `23-accreditation.md` · **Blocks:** `29-agenda-scheduling.md`

---

## Current state — tables exist, no behaviour

`CONFIRMED`: Phase 1 created `speakers` (account-scoped, nullable `event_id`, name, email, title,
company, bio, `photo_image_id`, `social` jsonb, `is_published`) and `session_speakers`
(`session_id`, `speaker_id`, `role`, `sort_order`, unique per pair).

No CRUD, no UI, no portal.

## Why speakers are not attendees or users

The modelling decision, restated because it will be questioned:

| | Attendee | User | **Speaker** |
|---|---|---|---|
| Has an order | Yes | No | **Usually not** |
| Has a login | No | Yes | **Usually not** |
| Public profile | No | No | **Yes** — bio, photo, socials |
| Privacy profile | Personal data, private | Internal | **Deliberately published** |
| Lifecycle | Per event | Per account | **Reused across events** |

That last row is why `event_id` is nullable: a keynote speaker ARZO books twice a year should be one
record, not two.

The published bio and photo are the sharpest distinction — attendee data is private by default,
speaker data is public by intent. Conflating them risks publishing the wrong thing.

## What gets built

### CRUD and assignment
Speaker create/edit/publish, plus assignment to sessions with a role (`SPEAKER`, `MODERATOR`,
`PANELLIST`, `HOST`, `TRANSLATOR`) and ordering.

### Accreditation linkage
A speaker should receive a `SPEAKER` accreditation and credential (`23`) so they can actually get in
— typically with backstage or green-room access a general attendee lacks.

**Design decision:** a speaker is a `person` (`23`) with a `speakers` profile attached, rather than a
parallel identity. That way credentials, badges and access logs work unchanged. `speakers` holds the
*public* presentation; `persons` holds the identity.

This needs a nullable `person_id` on `speakers` — a small follow-on migration, not in Phase 1.

### Conflict detection
A speaker in two overlapping sessions is a **hard error** (`27`), unlike an attendee clash which is a
warning. Enforced in the application, since the GiST constraint covers rooms rather than speakers.

## Speaker portal — assess, do not assume

A self-service portal for bio, photo and slide upload is frequently requested and frequently
underused. Speakers are busy and often send materials by email regardless.

**Recommendation:** start with staff-entered profiles and a public agenda page. Build the portal only
if chasing materials by email proves to be the actual bottleneck. If built, it is a surface like the
exhibitor portal (`93`) and needs the same per-resource permissions (`09`).

## Open questions

- **Add `person_id` to `speakers`?** Recommended, for credential issuance. Small migration.
- **Do speakers get automatic accreditation on assignment**, or does a human approve? Automatic is convenient; for a government or high-security event, approval is required.
- **Slide and materials storage** — `73` handles files, but versioning and a "final" flag are extra.
- **Speaker no-shows** — who is notified, and does the agenda update publicly?

## Related

`27-sessions-tracks.md` · `29-agenda-scheduling.md` · `23-accreditation.md` ·
`73-file-management.md` · `93-exhibitor-platform.md`
