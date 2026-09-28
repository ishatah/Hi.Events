# Privacy and Data Protection

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 66, 33, 133


---

## Purpose

Personal data handling, consent, retention, and subject rights.

## Current state

`PARTIAL` and better than typical: `CONFIRMED` — `account_deletion_requests` with an `AnonymizationStrategy`, cookie consent with Google Consent Mode defaults, `send_default_pii` off in Sentry, `sql_bindings` off in breadcrumbs.

## Key decisions

- The plan **adds substantial new personal data**: photos, ID document numbers, nationality, date of birth, movement history across zones, lead capture transfers to exhibitors. Each needs a lawful basis, retention period, and minimization decision — not an afterthought.
- Qatar's PDPL applies alongside GDPR for EU attendees. Both, not either.
- Movement data (`access_logs`) is behavioural tracking. Aggregate by default; individual traces need justification.
- Lead capture transfers attendee data to a third party and requires consent at scan time (`33`).
- Devices carry full rosters and must be encrypted at rest (`71`).

## Open questions

- Is a DPIA required for biometric or movement tracking? Very likely for face recognition (ARZ-260).
- Retention schedule per data class — currently undefined.
- Data residency: does Qatar data need to stay in-region? Affects `84`.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `133` · `33` · `66`
