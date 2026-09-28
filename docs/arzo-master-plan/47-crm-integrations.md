# CRM Integrations

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 48, 49  
**Blocks:** 50


---

## Purpose

HubSpot, Salesforce, and general CRM sync.

## Current state

`MISSING`, 0%. No integration code.

## Key decisions

- Build on the public API and webhooks (`48`, `49`) rather than bespoke per-CRM code in the core.
- An integration framework (`50`) with per-provider adapters beats N one-off integrations.

## Open questions

- Which CRM does ARZO actually use? Build that one properly first rather than a generic framework nobody exercises.
- Sync direction: push-only, or bidirectional? Bidirectional creates conflict-resolution obligations.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `48` · `49` · `50`
