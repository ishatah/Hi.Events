# Observability

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 53, 86


---

## Purpose

Knowing what the system is doing.

## Current state

`PARTIAL`, 50%. `CONFIRMED`: Sentry on backend and frontend with impersonation context — genuinely good error tracking. But **tracing is off by default** (`traces_sample_rate` null), and there are **no metrics, no OpenTelemetry, no Horizon, no Telescope**. The only operational metric is a scheduled `failed_jobs` count logged as a warning.

## Key decisions

- Errors are covered; **operations are not**. Event-day needs metrics — scan rate, decision latency, sync lag, print queue depth, device health.
- Enable Sentry tracing at a sampled rate before Phase 4; offline sync bugs are distributed and undebuggable without traces.

## Open questions

- Metrics backend choice — Prometheus, hosted, or Sentry metrics?
- Who watches dashboards during an event? Observability without an operator is decoration.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `53` · `86`
