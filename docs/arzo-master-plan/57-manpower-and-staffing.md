# Staffing and Manpower

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 23, 09  
**Blocks:** 92, 200


---

## Purpose

Staff profiles, skills, shifts, assignments, performance.

## Current state

`MISSING`, 0%. `CONFIRMED`: no shift, roster, or timesheet entity.

## Key decisions

- Staff are `persons` with assignments and credentials, reusing `23` rather than a separate identity model.
- Shift check-in/out writes to `access_logs` like any other scan — one attendance mechanism, not two.
- **The software is in scope; running a staffing agency is not.** This document plans the system, not the business.

## Open questions

- Payroll integration, or export only?
- Do subcontracted staff need platform accounts, or only credentials?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `200` · `23` · `92`
