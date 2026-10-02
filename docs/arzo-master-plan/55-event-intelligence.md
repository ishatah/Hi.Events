# Cross-Event Intelligence

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (derived) · **Priority:** P3 (ARZ-220) · **Phase:** 5, and data-gated
**Depends on:** `54-attendance-intelligence.md`, `52-analytics.md`, `65-privacy-gdpr.md`
**Blocks:** `133-ai-capabilities.md` A5 (predictive attendance)

---

## Current state — `PARTIAL` for commerce, `MISSING` for everything else

More exists than the scaffold recorded:

| Fact | Evidence |
|---|---|
| **Organizer-level cross-event reports exist** — `revenue_summary`, `events_performance` compare an organizer's events | `OrganizerReportTypes.php:9-13` (`51`) |
| `events_performance` ignores the requested date range | `51` R5 |
| Events carry a **category** — 24 consumer-oriented values (`MUSIC`, `BUSINESS`, `TECH`, `FESTIVAL`, `WORKSHOP`…) — and a type (`SINGLE`, `RECURRING`) | `EventCategory.php`, `EventType.php` |
| `persons` is account-scoped, so one person can be linked across an account's events | `2026_09_29_000005`; backfilled by email |
| Currency conversion exists — OpenExchangeRates behind an interface | `50` |
| Super-admin sees cross-**account** aggregates — with a mislabeled revenue figure across mixed currencies | `51` R9 |

Nothing compares attendance, arrival behaviour or no-show across events, because no attendance data
exists yet (`54`).

## The honest constraint

**Only meaningful after several comparable events have run through the full stack.** Comparing a
gala with a trade show tells an organizer nothing; comparing this year's trade show with last
year's is the whole value. Three events of history support benchmarking. They do not support
prediction — `133` calls predictive attendance on thin data "astrology".

## What to build first: benchmarking within an account

### Comparable sets

An event is compared only against events that share **category**, **format** (single-day,
multi-day, recurring), and a **size band** (by credentials issued: < 250, 250–1,000, 1,000–5,000,
> 5,000). With fewer than three comparables, show the event's own figures and say "not enough
history to compare".

### Normalized curves

Absolute dates mean nothing across events; relative time does.

| Curve | Axis |
|---|---|
| Sales | Days before event start |
| Arrivals | Minutes from doors-open (`56` supplies doors-open) |
| Session attendance | Session slot within the day |

The output is the event plotted against the **range** of comparables — "arrivals peaked 20 minutes
earlier than your usual" — not a single average that hides the spread.

### Benchmarks worth computing

| Benchmark | Operational use |
|---|---|
| Sell-through curve | Is this event behind? Decide marketing spend early. |
| Show rate by category | Over-issue free registrations by the expected no-show |
| Arrival peak and shape | Staff the gates for the peak, not the average (`57`) |
| Scans per staff-hour | Plan gate staffing (`20`, `57`) |
| Badge reprint rate | Data-quality problems in registration forms |
| Session attendance versus registration | Room sizing for next year |

Currency: cross-event revenue comparisons convert at the rate of each event's sale dates, not
today's — otherwise currency movement looks like performance.

## Repeat attendance — needs a privacy position first

`persons` makes "how many of this year's attendees came last year?" answerable. It is also profiling
across events, and `23` flags cross-event identity as needing a privacy position before use.

**Recommendation:** aggregate-only repeat rates by default (a percentage, never a list). Named
repeat-attendee lists — useful for VIP recognition — only with a lawful basis stated in the privacy
notice (`65`).

## Aggregate, then forget

Retention and benchmarking collide: `54` deletes raw access logs after a retention period, and
benchmarks need history. Resolution:

```
event_benchmark_facts      -- written once an event is closed and settled
  event_id, account_id, category, format, size_band,
  metric, bucket,          -- e.g. arrivals at minute +15 from doors
  value numeric, computed_at
```

Computed at closeout (`108`) from settled logs, **containing no personal data**, retained after the
raw logs are deleted. Benchmarking then never needs the personal data it would otherwise force ARZO
to keep.

## Cross-tenant benchmarking — deferred

Comparing against other organizers' events would be far more valuable, and it is a contractual and
privacy question before it is a technical one (`65`, `101`):

- Explicit opt-in per account, in the contract
- Only from `event_benchmark_facts`, never raw data
- Minimum of 5 contributing accounts per comparable set, so no single tenant is identifiable
- Relevant only if ARZO serves external organizers at all — the buyer question in `04`

For ARZO's own events alone, within-account benchmarking is the whole feature.

## Open questions

- **Is `EventCategory` the right axis?** Its 24 values are consumer-marketing categories. ARZO's own taxonomy — conference, exhibition, gala, concert, government ceremony — may need a second, operational classification.
- **Size band thresholds** are guesses; refine once ARZO's event sizes are known (`04`).
- **Who sees benchmarks** — organizers of every tier, or ARZO's operations team only?

## Related

`54-attendance-intelligence.md` · `52-analytics.md` · `51-reporting.md` ·
`56-event-operations.md` · `57-manpower-and-staffing.md` · `65-privacy-gdpr.md` ·
`101-enterprise.md` · `108-post-event-closeout.md` · `133-ai-capabilities.md`
