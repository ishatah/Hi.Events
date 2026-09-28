# Queue Management

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** New subsystem (small) · **Phase:** 4
**Depends on:** `25-zones-and-permissions.md`, `24-access-control.md`
**Blocks:** `53-live-event-command-center.md`

---

## Current state — `MISSING`, ~5%

`CONFIRMED`: `StatsTab.tsx` computes a rolling **5-minute throughput** client-side from
`stats.recent_check_ins`, re-ticking every 15 seconds. That is one gauge on one check-in list.

There is no queue entity, no wait estimate, no per-door measurement, and no historical arrival data.

## What became possible in Phase 1

Per-door measurement needs somewhere to attribute a scan. `access_points` now exists, and
`access_logs` records `access_point_id` with `occurred_at`. That is the entire data requirement — a
concrete payoff of building the space model first.

## Design: derive, do not enter

No manual queue entry, no dedicated sensors initially. Everything is derived from the scan stream:

| Metric | Derivation |
|---|---|
| Throughput per access point | `GRANTED` entry scans per minute over a rolling window |
| Arrival rate | Scan attempts, including denials — denied people are still queueing |
| Queue depth estimate | Arrival rate minus throughput, integrated over the window |
| Wait estimate | Queue depth divided by throughput |
| Service time | Median gap between consecutive scans at one point |

**These are estimates and must be labelled as such.** A wait figure shown as fact will be wrong and
will be quoted back. Show a range, or a qualitative state (`FLOWING` / `BUSY` / `CONGESTED`).

### Why include denials in arrival rate

A gate denying many scans is *slower*, not faster — each denial consumes staff time. Counting only
successes would show a congested door as quiet, which is exactly backwards.

### Trend before ML

`133` A4 assesses predictive queues and concludes a linear extrapolation on arrival rate is probably
80% as good as a model, explainable, and debuggable at 2am. Start there.

## Storage

Snapshots rather than recomputing an aggregate per request:

```
access_point_throughput_snapshots
  id, access_point_id, window_start, window_end,
  scans_granted int, scans_denied int,
  median_service_seconds numeric NULL,
  estimated_queue_depth int NULL,
  created_at
  INDEX (access_point_id, window_start)
```

Written by a scheduled job every 30–60 seconds. This also gives the **historical arrival curve**,
which is what makes staffing planning (`57`) and post-event analysis (`54`) possible.

## Reducing queues, not just measuring them

Measurement is the cheap half. What the command center (`53`) should enable:

- Alert **before** congestion, from the trend rather than the current depth
- Show which access points are idle while another is congested — redeploy staff
- Reveal whether denials are clustered on one rule, which means a misconfiguration rather than a crowd

## Open questions

- **Physical sensors?** People-counters or LIDAR give true depth. Scan-rate inference is free and probably adequate; revisit only if estimates prove badly wrong in practice.
- **Public-facing wait display?** Sets expectations and invites complaints when wrong.
- **Snapshot window length** — too short is noisy, too long lags. 60s is the starting guess; `UNVERIFIED` against real arrival data.
- **Does a queue need a name and staff assignment**, or is the access point sufficient? Access point is sufficient until someone needs to roster per queue (`57`).

## Related

`24-access-control.md` · `25-zones-and-permissions.md` · `53-live-event-command-center.md` ·
`54-attendance-intelligence.md` · `57-manpower-and-staffing.md` · `133-ai-capabilities.md`
