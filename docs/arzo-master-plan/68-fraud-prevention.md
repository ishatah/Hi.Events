# Fraud Prevention

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 24  


---

## Purpose

Detecting abuse of tickets, credentials, and the platform.

## Current state

`PARTIAL`: `event_spam_checks` with AI-assisted event screening and an admin approve/confirm queue — genuinely good. No credential-fraud detection.

## Key decisions

- Credential cloning is the new fraud surface: two GRANTED entries for one credential at distant access points within seconds is a strong signal (`24`).
- Needs a false-positive strategy before it can block rather than flag.

## Open questions

- Ticket resale and transfer policy — is transfer supported, and how is fraud distinguished from legitimate transfer?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `24`
