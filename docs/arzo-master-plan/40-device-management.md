# Device Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 48, 37  
**Blocks:** 19, 53, 71, 94, 96


---

## Purpose

Registering, configuring, monitoring, and retiring devices.

## Current state

`MISSING`. No device entity.

## Key decisions

- Devices authenticate with their own scoped keys, never a user's JWT — a shared user token on twenty tablets cannot be revoked per device.
- Enrolment via operator-generated pairing code exchanged for a long-lived key.
- Health telemetry (last seen, battery, sync cursor, app version) is what makes event-day operations possible (`53`).

## Open questions

- Do we need remote wipe for lost devices holding attendee rosters? Given the PII exposure, probably yes.
- MDM integration, or self-managed?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `37` · `48` · `53` · `71` · `94` · `96`
