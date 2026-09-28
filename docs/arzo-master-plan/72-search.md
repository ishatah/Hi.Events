# Search

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71  


---

## Purpose

Finding attendees, orders, and people quickly.

## Current state

`PARTIAL`: database `ilike` search with server-side pagination. Check-in search is capped at 150 results with no pagination UI, so an operator must narrow the query.

## Key decisions

- On-site attendee lookup is latency-critical and must work offline (`71`) — a server search engine cannot serve the primary check-in path.
- Local prefix and fuzzy search over the device roster is the requirement; central search is secondary.

## Open questions

- Is a search engine (Meilisearch/Typesense) justified for central search, or is Postgres sufficient?
- Fuzzy matching for misspelled names at the door — valuable, and a duplicate-creation risk.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `71`
