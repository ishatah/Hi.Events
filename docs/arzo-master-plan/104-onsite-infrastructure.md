# On-Site Infrastructure

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71, 102  
**Blocks:** 107


---

## Purpose

Network, power, and contingency at the venue.

## Current state

**Out of engineering scope**, but it determines whether the software works.

## Key decisions

- Assume the venue network will fail. That assumption is the entire justification for `71`.
- Wired connections for fixed positions where possible; cellular failover; UPS for print stations.
- A network survey before the event is cheaper than discovering a dead spot at a gate on the day.

## Open questions

- Who provides connectivity — venue, ARZO, or a contractor?
- Is a local server on-site worth it for very large events, to reduce dependence on the internet entirely?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `102` · `107` · `71`
