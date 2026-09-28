# Email Marketing

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 41  


---

## Purpose

Segmentation, campaigns, automation, analytics.

## Current state

`PARTIAL`, 20% for segmentation. `CONFIRMED`: templates with Liquid, scheduled sends, 5 **fixed** audiences in `MessageTypeEnum`. No filter builder, no drip sequences, no open/click tracking. Messaging is quota-gated by `account_messaging_tiers` as anti-abuse.

## Key decisions

- Build a real segment/filter builder over attendee and (later) session/attendance attributes.
- Open/click tracking has privacy implications — opt-in and disclosed (`65`).

## Open questions

- Build campaign automation, or integrate an ESP and stay the source of truth for audiences? Integration is likely better value.
- How does messaging quota interact with ARZO running its own events?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `41`
