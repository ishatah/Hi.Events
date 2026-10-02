# Attendance Intelligence

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (derived) · **Priority:** P2 (ARZ-171, ARZ-172) · **Phase:** 2 for event-level, 3 for sessions
**Depends on:** `24-access-control.md`, `27-sessions-tracks.md`, `52-analytics.md`, `18-check-in.md`
**Blocks:** `55-event-intelligence.md`, `108-post-event-closeout.md`

---

## Current state — `MISSING` beyond check-in counts

`CONFIRMED`:

| Fact | Evidence |
|---|---|
| The only attendance data is `attendee_check_ins` — one row per attendee per list, check-out as soft-delete | F2 |
| `attendees.checked_in_at` is a scalar and becomes advisory (`16`) | |
| `check_in_summary` counts check-ins without an attendee-status filter and ignores dates | `51` R5, R6 |
| `attendee_check_ins` has **no `created_at` index** | Live DB |
| `access_logs` is written only by `AccessScanService` (`e7228c1d`) — not by either check-in path; `session_attendance` has no writer | `c34f6a59`, `e7228c1d` |

The landed `access_logs` carries what this document needs: `occurred_at` and `recorded_at`
(`timestamptz`), `direction`, `result`, `zone_id`, `access_point_id`, `credential_id`,
`is_offline_replay`, `source`, and indexes on `(event_id, occurred_at)`, `(zone_id, occurred_at)`,
`(attendee_id, occurred_at)`, `(credential_id, occurred_at DESC)`.

### One inconsistency to fix while the tables are empty

| Table | Direction values |
|---|---|
| `access_logs.direction` | `ENTRY` default; `AccessDirection` enum is `ENTRY` / `EXIT` |
| `session_attendance.direction` | **`IN`** default (`2026_09_29_000006:88`) — `27` specified `IN` / `OUT` |

Two vocabularies for one concept guarantee a query that silently matches nothing. Standardize on
`ENTRY` / `EXIT` — the enum that exists — and change the `session_attendance` default **before** the
first row is written.

## Metric definitions

Precise, because vague attendance definitions are how two reports disagree and neither is wrong.

| Metric | Definition | Requires |
|---|---|---|
| **Arrived** | Credential with ≥ 1 `GRANTED` or `GRANTED_OVERRIDE` `ENTRY` at any access point during the event window | Entry scanning |
| **Show rate** | Arrived ÷ credentials `ACTIVE` at event start | |
| **No-show** | Per policy below | |
| **Arrival curve** | Arrived per minute, relative to doors-open (`56`) | |
| **Peak occupancy** | Maximum of `zone_occupancy_snapshots.occupancy` per zone | Exit scanning, or occupancy is entries only |
| **Re-entries** | `ENTRY` logs per credential per zone, minus one | |
| **Dwell time** | Sum of `ENTRY`→`EXIT` intervals per credential per zone | **Exit scanning** |
| **Session attendance rate** | Distinct attendees with a session `ENTRY` ÷ `REGISTERED` registrations | Session check-in (`27`) |
| **Session drop-off** | Attendees whose `EXIT` precedes the session's end by > 10 minutes | Session exit scanning |
| **Denial rate** | Denied ÷ all scans, per access point, by `AccessResult` | |

### Valid only where exits are scanned

Many venues scan entry only. Where no `EXIT` access point exists for a zone, **dwell time and true
occupancy are not computable**, and the report must say so rather than print a number derived from
entries alone. The configuration decides which metrics are valid:

| Zone configuration | Valid |
|---|---|
| Entry only | Arrivals, show rate, arrival curve, re-entries |
| Entry and exit | All of the above, plus occupancy, dwell and drop-off |

`access_points.direction` defaults to `BIDIRECTIONAL`; at such a point the **device's scan mode**
(`38`) supplies each log's direction. A bidirectional point where the operator never switches mode
records everything as `ENTRY` — a data-quality problem best caught at readiness (`59`).

### No-show policy

Set per event, not assumed:

| Event shape | No-show means |
|---|---|
| Single day | No arrival during the event window |
| Multi-day | Per day: no arrival that day. The event-level figure is attendees who never arrived at all. |
| Session | Registered, no session `ENTRY` |

## Late and replayed data

Offline devices submit late and out of order (`71`). Every metric over a window is **provisional
until all devices active in the window have synced past it** (`52`). `occurred_at` places a scan in
time; `recorded_at` minus `occurred_at` measures lateness, and large or negative gaps are clock skew
— detected from heartbeats (`40`) and reported rather than silently corrected.

## Privacy

Movement across zones is among the most sensitive data the platform will hold (`120` R6).

| View | Who | Rule |
|---|---|---|
| Aggregates — curves, occupancy, rates | Organizers, sponsors | Default |
| Zone-to-zone flow matrices | Organizers | Cells below 5 suppressed |
| **One person's trail** | Holders of `access.logs.view` | Every view audited (`67`); used for incidents and disputes, not curiosity |
| Anything individual | **Sponsors and exhibitors** | **Never** |

Retention (decided in `65`): at event end + 90 days by default — configurable to 12 months for
government clients — access logs have their **identity stripped**, not deleted. Credential and
person links are removed; the time, place, direction and result remain. The aggregates and the
shape of the event survive; the trails do not (`55`, `65`).

## Legacy data during the cutover

Until ARZ-041 completes, attendance lives in `attendee_check_ins`, and during dual-write it lives in
both. Reports read `access_logs` once backfilled (`18` step 4). Before that, event-level arrival
metrics come from `attendee_check_ins.created_at` — which needs the missing index.

## Outputs

| Output | Where |
|---|---|
| Live arrivals, occupancy, denials | Command center (`53`) |
| Attendance and session reports | `51` |
| Post-event bundle | `108` |
| Benchmarks against past events | `55` |
| Staffing lessons — arrival peaks versus rostered staff | `57` |

## Open questions

- **Retention period for identifiable access logs** — `65` sets 90 days by default, 12 months for government clients; both are proposals until legal confirms.
- **Is a session a zone?** A session room behind a controlled zone produces both `access_logs` and `session_attendance` rows (`27`). Metrics must not double-count them.
- **Staff and exhibitor credentials** — excluded from audience metrics by default, since they are not the audience. Needs `credential_type` filtering in every query.
- **Heatmaps** (ARZ-223) — a view over zone occupancy on the floor plan. Cheap once `25`'s coordinate question is answered; not a separate data problem.

## Related

`24-access-control.md` · `27-sessions-tracks.md` · `18-check-in.md` · `51-reporting.md` ·
`52-analytics.md` · `53-live-event-command-center.md` · `55-event-intelligence.md` ·
`59-event-readiness.md` · `65-privacy-gdpr.md` · `67-audit-logging.md`
