# Reporting

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + Extend · **Priority:** P2 (ARZ-171, ARZ-172, ARZ-222) · **Phase:** fixes now; catalogue grows with each domain
**Depends on:** `52-analytics.md`
**Blocks:** `54-attendance-intelligence.md`, `108-post-event-closeout.md`

---

## Current state — `PARTIAL`, ~40%: nine reports, all commerce

Earlier documents counted four reports. There are **nine**: four per event and five per organizer.

### Event reports — `ReportTypes.php:9-12`, `GET /events/{id}/reports/{type}` (`api.php:511`)

| Report | Source | Notes |
|---|---|---|
| `product_sales` | Raw `order_items` + `orders` | |
| `daily_sales_report` | `event_daily_statistics`, or the occurrence variant | Views hard-coded to 0 for occurrences |
| `promo_codes_report` | Raw `orders` / `order_items` / `promo_codes` | |
| `occurrence_summary` | `event_occurrence_statistics` + raw `attendee_check_ins` count | No date filter |

Not paginated; cached 20 s (`AbstractReportService.php:39-41`).

### Organizer reports — `OrganizerReportTypes.php:9-13`, `GET /organizers/{id}/reports/{type}` (`api.php:340`)

| Report | Source | Notes |
|---|---|---|
| `revenue_summary` | `event_daily_statistics` | |
| `events_performance` | Raw `orders` + `event_statistics` | **Ignores the date range** |
| `tax_summary` | `orders.taxes_and_fees_rollup` jsonb | |
| `check_in_summary` | Raw `attendees` / `attendee_check_ins` | **Ignores the date range; no attendee-status filter** |
| `platform_fees` | Raw `stripe_payments` | The only paginated report |

Cached 30 s. `ExportOrganizerReportAction` streams CSV synchronously — at most 15,000 rows and 370
days, with formula escaping.

### Exports — `app/Exports`

Attendees, orders, affiliates and question answers (three sheets). All are synchronous
`Excel::download` except answers, which is a queued job the frontend polls every 5 s.
`FormulaSafeValueBinder` is the global Excel binder, so spreadsheet exports are formula-safe.
`PromoCodesExport` is referenced nowhere — dead code.

No scheduled or emailed reports exist; the organizer receives only per-order emails.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| R1 | **Exports silently stop at 10,000 rows** — attendees, orders, affiliates | `AttendeeRepository.php:57` and siblings | **High** — a large event's export is quietly incomplete, and nobody reading the file can tell |
| R2 | **Event-report CSVs are built in the browser** by wrapping strings in quotes — no quote escaping and no formula escaping | `DownloadCsvButton/index.tsx:18-30` | **Medium** — a buyer named `=HYPERLINK(...)` becomes a formula in the organizer's spreadsheet; any quote in a product name breaks the file |
| R3 | Report dates are interpolated into SQL text rather than bound | e.g. `ProductSalesReport.php:32` | Medium — bounded by request validation, `UNVERIFIED` how strictly |
| R4 | Currency filter escaped with `addslashes`, which is not a Postgres escape | `AbstractOrganizerReportService.php:58`; `PlatformFeesReport.php:195` | Low — `size:3` validation limits it |
| R5 | `events_performance` and `check_in_summary` ignore the requested dates | Report classes | Medium — wrong numbers under a correct-looking filter |
| R6 | `check_in_summary` counts check-ins of cancelled attendees | No status filter | Low |
| R7 | **The check-in throughput gauge cannot exceed 4 per minute.** It computes a 5-minute rate from the 20 most recent check-ins the endpoint returns. | `GetCheckInListStatsPublicHandler.php:13`; `StatsTab.tsx:47-62` | **Medium** — at a busy door the gauge reads flat exactly when it matters |
| R8 | Frontend calls `events/{id}/check_in_stats`, which has no backend route; `getCheckedInStats` has no caller | `event.client.ts:62`; `EventStatsFetchService.php:214` | Low — dead code both sides |
| R9 | Admin "recent revenue" sums **lifetime** gross of any recently-updated event, across **mixed currencies** | `GetAdminDashboardDataHandler.php:160-211` | Low — super-admin only, but the figure is meaningless |

R1 and R2 should be fixed before any large ARZO event, independent of the roadmap. R2's fix is to
move event-report CSV generation server-side, where the formula-safe binder already exists.

## Principles

1. **A fixed catalogue, not a report builder.** The scaffold asked; the answer is a catalogue plus
   exports plus the API (`48`). A builder is a product, and ad-hoc questions are better served by
   export or the assistant `133` A8 assesses.
2. **Every report declares its source**: rollup table, raw commerce tables, or append-only logs.
   Figures from counters and figures from logs can disagree (`52`); a report must say which it is.
3. **Server-side generation, always.** No more browser-assembled files (R2).
4. **Large exports are asynchronous.** The queued answers export is the pattern; generalize it and
   remove the silent cap (R1). If a limit remains, the file says so in its first row.
5. **Personal data in exports is opt-in by class.** Today every export includes email, notes,
   billing address and answers. Once `persons` carries ID numbers and dates of birth (`23`), those
   are excluded by default and require an explicit, audited opt-in (`16`, `65`).
6. **Permissions** — `report.view` and `report.export` are already seeded (`09`); export is the
   more sensitive of the two and is granted separately.

## Target catalogue

| Domain | Report | Source | Phase |
|---|---|---|---|
| Commerce | Existing nine, with R1–R6 fixed | Rollups + raw | Now |
| Commerce | **Sales by source** | `order_attributions` (`41`) | Any |
| Attendance | Arrival curve, show rate, peak occupancy, re-entries | `access_logs`, snapshots (`54`) | 2 |
| Access | Denials by reason and access point; overrides with operator | `access_logs` | 2 |
| Accreditation | Applications by type and status; approval turnaround | `accreditations` | 2 |
| Badges | Printed, reprinted, voided, by desk and reason | `badges`, `badge_print_jobs` | 2 |
| Programme | Session registration versus attendance; drop-off | `session_registrations`, `session_attendance` | 3 |
| Exhibitors | Leads per exhibitor, booth traffic by hour | `lead_captures` (`33`) | 3 |
| Sponsors | Entitlement fulfilment with evidence | `sponsorship_entitlements` (`34`) | 3 |
| Devices | Uptime, offline periods, sync lag | `device_status_events` (`40`) | 4 |
| Operations | Incidents by type and severity; staffing fill rate | `60`, `57` | 5 |

## Post-event report

The deliverable a client actually receives: one bundle per event combining the relevant rows above,
with figures computed conventionally and — optionally — narrative written by a model that is
**given** the numbers and never computes them (`133` A1). Owned by `108`.

## Scheduled delivery

Deferred until asked for. When built: a daily or weekly digest per organizer, generated from the
same report services, delivered by email with a link rather than an attachment, so access control
still applies at download.

## Open questions

- **Remove the 10,000-row cap, or raise it and surface it?** Removing it needs async generation for every export; raising it is a stopgap.
- **Report timezone** — reports filter on event-timezone wall-clock dates against `created_at`, stored without time zone. Correct only if every write uses the same convention; `UNVERIFIED` across all writers.
- **Do organizers need reports via the API** for their own BI tools? Cheap once `48` exists.

## Related

`52-analytics.md` · `54-attendance-intelligence.md` · `41-event-marketing.md` ·
`33-exhibitor-lead-capture.md` · `34-sponsor-management.md` · `16-attendee-management.md` ·
`65-privacy-gdpr.md` · `108-post-event-closeout.md` · `133-ai-capabilities.md`
