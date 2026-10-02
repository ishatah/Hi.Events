# Advanced Analytics

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (derived), data-gated · **Priority:** P3–P4 (ARZ-220, ARZ-173; lead scoring ARZ-134, ARZ-241 stays in `33`) · **Phase:** 5, and only as the data threshold is met
**Depends on:** `52-analytics.md`, `54-attendance-intelligence.md`, `55-event-intelligence.md`, `41-event-marketing.md`
**Blocks:** nothing directly; gates ARZ-220 (predictive attendance)

---

Cohorts, attribution, prediction and engagement scoring — the analytics above standard reporting
(`51`) and benchmarking (`55`). The governing rule: **each technique has a minimum amount of settled,
comparable data, and the system computes whether it has been met.** Below the threshold the user sees
the descriptive version and the words "not enough history" — never a confident number built on noise.

## Current state — `MISSING`; most inputs do not exist yet

| Input | State | Evidence |
|---|---|---|
| Commerce rollups — sales, orders, refunds by event and day | `CONFIRMED` | `event_statistics`, `event_daily_statistics` (`52`) |
| Page views | `PARTIAL` | Lost for events with no orders; partial batches never flushed (`52` A3–A5) — conversion rate is not computable |
| Order source attribution | `MISSING` | `order_attributions` designed in `41`, not built. The only source-like order columns are `affiliate_id`, `promo_code_id`, `promo_code`, `locale`, `opted_into_marketing_at` (live `information_schema`) |
| Attendance — `access_logs` | `PARTIAL` | Written only by `AccessScanService`; neither check-in path writes it until ARZ-041 |
| Session attendance | `MISSING` data | `session_attendance` has no writer |
| Leads | `MISSING` | Designed in `33` |
| Cross-event identity — `persons` | `CONFIRMED` | Account-scoped, backfilled by email |
| Benchmark store — `event_benchmark_facts` | `MISSING` | Designed in `55`; live query finds no such table |
| Links between editions of one show | `MISSING` | `events` has no series or parent column (`132`) |
| Settled events through the full stack | **Zero** | No event has run with access logs, sessions and closeout |

## Decision: one input, `event_benchmark_facts`, and a readiness gate in front of every technique

Advanced analytics reads **only** settled, personal-data-free facts written at closeout (`55`, `108`),
plus aggregate queries over live tables for the current event. It never reads raw access logs from
past events — those are deleted after retention (`54`), and benchmarks must outlive them.

Two additions to `55`'s design:

```
event_benchmark_facts
  event_id, account_id, category, format, size_band,   -- from `55`
  series_key NULL,       -- links editions of the same show; needs a column on events (`132`)
  metric, bucket, value numeric,
  metric_version int,    -- definition version; facts of different versions are never compared
  computed_at
```

`metric_version` exists because metric definitions will change (`54` is precise about them, and
precision gets revised). A show rate computed under two definitions is two metrics.

### The readiness gate

```
analytics_readiness(account, technique, comparable_set) →
  { available bool, have int, need int, reason text }
-- comparable_set = (category, format, size_band) per `55`, or series_key when set
-- counts only events that are CLOSED and settled (all devices synced past the window, `52`)
```

The UI shows `have` and `need` — "3 of 10 comparable events" — so the threshold is visible, not
mysterious. Thresholds are configuration, starting at the values below and tuned by the backtest rule.

## Techniques and their minimum data

| Technique | Answers | Built on | Shown when | Below threshold |
|---|---|---|---|---|
| **Sales pacing** | Is this event behind its usual sell-through? | Daily sales by days-before-start; comparables' curves | ≥ 3 comparable settled events (`55`'s rule) | The event's own curve |
| **Show-rate range** | How many will actually come? | Show rate per comparable (`54` definition) | ≥ 3 comparables: min, median, max | "Not enough history" |
| **Repeat-attendance cohorts** | How many came back; who returns first? | `persons` matched across editions | ≥ 2 editions linked by `series_key`; every cell ≥ 5 people | Nothing |
| **Acquisition cohorts** | When and through which channel do buyers buy? | Orders + `order_attributions` | From the first event after `41` lands; cells ≥ 5 | Orders by week only |
| **Channel attribution** | Which channel sold? | `order_attributions`, last non-direct touch | Immediately after `41` | Affiliate and promo reports (exist) |
| **Engagement score** (rules) | Who engaged, in aggregate? | Session attendance, dwell, booth scans | Sessions and exit scanning live (`27`, `54`) | Not shown |
| **Attendance forecast** (ARZ-220) | Predicted turnout for this event | Comparables + lead time, price, category | ≥ 10 comparables **and** it beats the naive baseline in backtest | The show-rate range |

The values 3, 5 and 10 are **starting policies, not statistical facts**: 3 is `55`'s rule for a
benchmark range; 5 is `52`'s small-group suppression; 10 is a deliberately high bar for a model,
because the backtest below is the real test.

### The backtest rule for any prediction

A forecast is displayed only if, replayed over ARZO's own history — each past event predicted from
the events before it — it has lower error than the **naive baseline**: the median show rate of the
comparable set. If it does not beat the median, the median is the forecast, and saying so is the
honest product. This is `133`'s guardrail made mechanical: arithmetic first, a model only when it
earns its place, and no language model computing a figure.

**The honest constraint:** ARZO's mix — galas, exhibitions, concerts, government ceremonies — splits a
small number of events into many small comparable sets. A forecast may never become available in some
categories. That is the correct outcome, not a defect.

## Attribution modelling — last-touch only; multi-touch is refused

`41` stores **one** attribution per order — the last non-direct touch. Multi-touch models (linear,
position-based, data-driven) need a log of every visit per buyer across sessions and devices: tracking
infrastructure with consent obligations (`65`, `41` M1), built to answer a question ad platforms
already answer through the pixels organizers install (`41`). Event purchase cycles are short; the gain
over last-touch is small. ARZO reports channels, affiliates and promo codes, states the model in the
UI, and does not build a touch log.

## Engagement scoring — rules, aggregate by default

```
engagement(credential, event) =
    w_session × sessions_attended           -- session_attendance ENTRY, deduplicated
  + w_dwell   × hours_in_content_zones      -- only where exits are scanned (`54`)
  + w_booth   × distinct_booths_scanned     -- lead captures, with consent (`33`)
  + w_app     × app_actions                 -- `30`, when it exists
-- weights are per-event configuration, displayed beside every score
```

- **Rules, not a model.** There is no outcome to learn from — no recorded renewal, repeat purchase or
  lead conversion yet. A learned score without an outcome is a guess with decimals.
- **Aggregate by default:** distributions and segments ("top quartile attended 4+ sessions").
- **Individual scores are profiling** (`65`): organizers only, with a stated lawful basis; **never**
  sponsors or exhibitors (`54`). Staff and exhibitor credentials are excluded.
- Lead scoring is `33`'s and stays there (ARZ-134 rules, ARZ-241 learned).

## Privacy rules that apply to all of it

| Rule | Source |
|---|---|
| Groups smaller than 5 suppressed in every breakdown and cohort cell | `52` |
| Repeat attendance aggregate-only; named lists need a lawful basis | `55` |
| Facts retained after raw logs are deleted contain no personal data | `55` |
| Movement trails never reach sponsors or exhibitors | `54` |

## Where it computes

Postgres queries over `event_benchmark_facts`, which is small by construction — metrics × buckets ×
events. `52`'s triggers decide when a columnar store is warranted; none of these techniques fires them.

## Migration

| Step | Change | Gate |
|---|---|---|
| 1 | `order_attributions` (`41`) | Prerequisite for cohorts and channels |
| 2 | `series_key` on `events`; `event_benchmark_facts` written at closeout with `metric_version` (`55`, `108`) | First closed, settled event |
| 3 | `analytics_readiness` and the "not enough history" UI | With step 2 |
| 4 | Sales pacing and show-rate range | 3 comparables |
| 5 | Acquisition and repeat cohorts | `41`; linked editions |
| 6 | Rules-based engagement score | Session attendance and exit scanning live |
| 7 | Forecast behind the backtest rule (ARZ-220) | 10 comparables and a winning backtest |

Steps 2–3 unnumbered — add to `136` when scheduled.

## Open questions

- **How many events before predictions are credible?** Per comparable set, at least 10 and a winning backtest — which ARZO may not reach in a year in any single category. The range from 3 comparables is the useful product meanwhile.
- **Does ARZO need an operational event taxonomy?** `55` asks whether `EventCategory`'s 24 consumer categories fit ARZO's events. Comparable sets are only as good as that axis.
- **Who defines engagement weights** — ARZO operations per event type, or each organizer? Leaning per event type, overridable.
- **Should organizers see readiness counts** ("7 more events to unlock forecasts")? Yes — it explains an absence better than hiding the feature.

## Related

`52-analytics.md` · `54-attendance-intelligence.md` · `55-event-intelligence.md` · `41-event-marketing.md` ·
`51-reporting.md` · `33-exhibitor-lead-capture.md` · `65-privacy-gdpr.md` · `108-post-event-closeout.md` ·
`132-future-capabilities.md` · `133-ai-capabilities.md`
