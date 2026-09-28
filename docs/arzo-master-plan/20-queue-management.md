# Queue Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 25, 24  
**Blocks:** 53


---

## Purpose

Measuring and reducing wait at entrances and desks.

## Current state

`MISSING`, 5%. `StatsTab` computes a 5-minute rolling throughput client-side. No queue entity, no wait estimate, no desk model.

## Key decisions

- Queue depth is derived from access-point scan rate and arrival rate, not manually entered.
- Per-access-point measurement is only possible because `access_points` exists (`25`) — this is a concrete payoff of the space model.

## Open questions

- Do we need physical queue sensors, or is scan-rate inference sufficient? Inference is free and probably adequate.
- Is a public-facing wait-time display wanted?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `24` · `25` · `53`
