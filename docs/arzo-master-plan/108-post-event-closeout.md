# Post-Event Closeout

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process + Business commitment (the client report, sanitization evidence) + Fix (C1) · **Priority:** P2 (ARZ-102, ARZ-222); C1 is P0-grade · **Phase:** process now; software in Phases 4–5
**Depends on:** `51-reporting.md`, `55-event-intelligence.md`, `60-incident-management.md`, `63-event-documentation.md`, `65-privacy-gdpr.md`, `103-hardware-deployment.md`, `105-event-lifecycle.md`
**Blocks:** nothing directly — it is the evidence for G4 (`56`)

---

Finishing an operated event properly: settling the data, reviewing what offline operation hid,
producing the reports and the client deliverable, clearing devices, starting the retention clocks,
recording lessons where the next event will read them, and archiving the record. It ends with gate
**G4** (`56`). Closeout is where a repeat event gets cheaper than the first (`105`), and it is the
first thing skipped when everyone is tired — which is why it is a gate and not a courtesy.

## Current state — `PARTIAL`, ~20%

| Capability | State | Evidence |
|---|---|---|
| Commerce reports | `CONFIRMED` — four per event, five per organizer | `ReportTypes.php:9-12`; `OrganizerReportTypes.php:9-13` (`51`) |
| Exports — attendees, orders, affiliates, question answers | `CONFIRMED`, and **silently capped at 10,000 rows** (`51` R1) | `AttendeeRepository.php:57`; `ExportOrdersAction.php:45,58`; `ExportAffiliatesAction.php:28` |
| Attendance, access, badge, device, incident reports | `MISSING` | `51` target catalogue |
| Offline reconciliation and a place to record its findings | `MISSING` — see C3 | ARZ-102 TODO |
| "Settled" marker | `MISSING` | `52` step 4 |
| Incidents, gate decisions, benchmark facts | `MISSING` — no `incidents`, `event_operations`, `event_gate_decisions` or `event_benchmark_facts` table | Live DB, 109 tables |
| Per-event retention or anonymization | `MISSING` — anonymization runs only when a whole account is deleted | `AccountDeletionService.php:81-86,188-232`; hourly job, `Kernel.php:19` |
| Event dossier | `MISSING` | `63` is a scaffold |
| Client post-event report | `MISSING` — ARZ-222 | `51` |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| C1 | **Deleting an account with completed orders leaves its attendees' names and emails in `persons`.** The Phase 1 backfill copied them from `attendees` into `persons`. Accounts with completed orders are anonymized rather than deleted; the anonymizers scrub `attendees` but none mentions `persons`, `credentials`, `badges`, `access_logs` or `invitations`. (The hard-delete outcome is covered: deleting the `accounts` row cascades.) | `2026_09_29_000008:157-195`; `OrderAnonymizer.php:41-46`; `AccountDeletionService.php:81-86`; `AccountHardDeletionService.php:155-157`; search of `Anonymization/` | **High** — a deletion request completes and reports success while personal data survives, wherever the 2026-09-29 migrations have run. **Fix now, independent of the roadmap** |
| C2 | `is_offline_replay` is **inferred**, not declared: set when a `client_generated_id` is present and `occurred_at` is over 2 minutes old | `AccessScanService.php:108` | Low — a slow online retry is flagged and a fast offline replay is not. Reconciliation must use the device's sync batches, not this flag |
| C3 | **No home for retrospective violations.** `71` says an offline admission of a revoked credential is "flagged `retrospective_violation`"; `access_logs` has no such column and no table records findings | `2026_09_29_000006`; `2026_09_30_000001:172-180`; search for "retrospective" finds nothing | Medium — must exist before ARZ-102, or reconciliation has no output |

Also relevant, already recorded: `51` R1 (export cap), R2 (browser-built CSVs), R5 and R6
(`check_in_summary` ignores dates and counts cancelled attendees); `103` H1 (an expired check-in list
can still undo check-ins).

## Decision: settle before you count

Offline devices submit late (`71`). A figure computed before every device active in the window has
synced past it is **provisional** (`52`), and a client report built on provisional figures changes
the next morning. So every count in closeout waits for step 2. A device that never syncs — lost, or
dead with records on it — cannot hold closeout hostage: after 72 hours (planning assumption) the DM
declares the event settled **with named exceptions**, and the report says which window is affected.

## Decision: reconcile by surfacing, never by rewriting

`access_logs` is evidence and is never edited (`71`, `24`). Reconciliation writes **findings**
beside the logs, and a human reviews each one. The shape `71` implies but nobody has written down —
proposed for ARZ-102, owned by `71`:

```
access_reconciliation_findings       -- findings are added, never edited away; logs are untouched
  id, event_id,
  finding_type,          -- REVOKED_ADMITTED | CAPACITY_EXCEEDED | ANTIPASSBACK_VIOLATED
                         -- | DUPLICATE_WALK_IN | CLOCK_SKEW | PRINT_UNCONFIRMED
  access_log_id NULL → access_logs, related_access_log_id NULL,
  credential_id NULL, device_id NULL,
  detected_at, detected_by,          -- SYNC | RECONCILIATION_JOB
  review_status,         -- OPEN | BENIGN | POLICY_BREACH | FRAUD_SUSPECTED
  reviewed_by NULL → users, reviewed_at NULL, review_note NULL,
  incident_id NULL                   -- when a finding becomes an incident (60)
```

Walk-in duplicates are flagged for a human merge, never auto-merged (`17`); an unconfirmed print job
is resolved by the operator's record, not assumed printed (`39`).

## The procedure

Times run from `breakdown_ends_at` and are planning assumptions, to be tuned after the pilot. Owners
are `106`'s roles.

| # | Step | Owner | By | Output — the G4 evidence |
|---|---|---|---|---|
| 1 | Final sync at teardown | TL | Last exit | Every device at zero unsynced, or listed as an exception (`103`) |
| 2 | **Settle** | TL | T+1d | Settled marker; unsettled windows named |
| 3 | Reconciliation review | SL, AL | T+3d | Every finding reviewed; suspected fraud raised as an incident |
| 4 | Cash and walk-in reconciliation, where cash was taken | BL, then finance | T+3d | Count sheet against recorded walk-ins (`17`) |
| 5 | Wind down access: credentials past `valid_until`, device keys, `event_users.expires_at`, check-in lists expired, exhibitor links (`32`) | AL, TL | T+1d | No credential or key usable after the event |
| 6 | Device sanitization | TL | T+2d | One record per device (`103`) |
| 7 | Close incidents; incident report | DM | T+5d | Counts by type and severity, time to acknowledge and resolve, SEV1/SEV2 timelines; restricted incidents as counts only (`60`) |
| 8 | Final reports | DM | T+7d | The report set, in the dossier |
| 9 | Client report | ED | T+10d | Delivered, approved by ED; DPL reviews if it holds any personal data |
| 10 | Benchmark facts | ENG, or the job once built | After step 2 | Rows in `event_benchmark_facts` (`55`) |
| 11 | Retrospective; lessons into templates | DM | T+14d | Every lesson with an owner and a target |
| 12 | Retention timers set | DPL | At G4 | A timer per data class |
| 13 | Dossier assembled | DM | T+21d | Index complete (`63`) |
| 14 | **G4** | DM — the "Operations" owner in `56` | T+30d | `event_gate_decisions` row with the evidence above |

## Final reports — what today's catalogue can be trusted for

| Report or export | Use at closeout | Caveat |
|---|---|---|
| `product_sales`, `daily_sales_report`, `promo_codes_report` | Commerce section | Download server-side exports, not the browser-built CSVs (R2) |
| `occurrence_summary` | Attendance for recurring events | No date filter |
| `revenue_summary`, `tax_summary` | Finance hand-off | — |
| `events_performance`, `check_in_summary` | **Not for this event's figures** | Both ignore dates; `check_in_summary` counts cancelled attendees (R5, R6) |
| Attendee and order exports | Dossier; client, where contracted | Check the row count against the dashboard — **10,000-row cap** (R1). Personal data: export approval per `106` |

Until access logs carry attendance (ARZ-041, ARZ-171), "attended" means an `ACTIVE` attendee with at
least one check-in, and the report says so. After ARZ-171 the attendance, access, badge, device and
staffing reports of `51`'s catalogue join the set.

## The client report

Generated, not hand-made (ARZ-222); until then, assembled from the reports above using the same
rules, so the switch changes the tooling and not the content.

- **Headlines:** credentials issued, arrived, show rate, arrival curve against doors-open, peak
  occupancy **only where exits were scanned** (`54`).
- **Sections as they apply:** sessions, exhibitors and lead counts, badges printed and reprinted,
  incidents by type and severity, operational notes.
- **Rules:** aggregates only; groups under five suppressed (`52`); every figure marked settled or
  provisional and its source declared (`51`); no individual movement trails, ever; nothing
  individual for sponsors or exhibitors (`54`); restricted incidents as counts (`60`).
- **Narrative**, if any, written by a model that is **given** the figures and never computes one
  (`133`).

## Retention and anonymization timers

Anchored to the G4 decision or to event end. **Every period below is a proposal** — `65` owns the
schedule, and the legal minimums and maximums under PDPL and GDPR are `UNVERIFIED` until someone
qualified answers.

| Data class | Where | Proposal | At expiry |
|---|---|---|---|
| ID document number, date of birth, nationality | `persons` (`23`) | Shortest — G4 + 30 days, unless the client contract requires longer | Null the columns |
| Photos | `persons.photo_image_id`; `badges.snapshot` | Event end + a short period | Delete the images; scrub snapshots |
| Raw access logs | `access_logs` | 12 months (`54`) | Write benchmark facts first, then delete |
| Restricted incidents | `incidents` | Shorter, per type (`60`) | Delete or reduce to counts |
| Staff personal data | Shifts, staff profiles (`57`) | Shorter than attendee data (`57` open question) | Delete |
| Leads | `lead_captures` (`33`) | Per exhibitor agreement | Delete |
| Device local data | Devices | Wiped at closeout (`103`) | — |
| Orders, invoices | Commerce | Governed by tax law — `UNVERIFIED` | Keep |
| Benchmark facts | `event_benchmark_facts` | Retained — they hold no personal data (`55`) | — |

The mechanism does not exist: the only deletion path is whole-account deletion (C1 shows even that
is incomplete). A per-event retention job — unnumbered, add to `136` when scheduled — is what makes
the proposals real.

## Lessons into templates

A blameless retrospective within two weeks, while people remember. Every lesson leaves as exactly
one of: a change to a task template item (`58`), to the readiness checklist (`131`), to the runbook
(`107`), a backlog item (unnumbered — add to `136`), or a vendor note (`61`). A lesson with no
destination is a complaint, not a lesson; the template owner applies them (`58` open question).

## The dossier

```
event dossier (63) — index, one per operated event
  contract reference and CRM deal id (105)
  G2 plan sign-off; readiness reviews with their frozen snapshots (59); gate decisions G1–G4
  incident report and restricted-incident counts (60)
  reconciliation findings with their reviews
  device sanitization records (103)
  final reports, client report as delivered
  paper-fallback sheets, transcribed, and override logs (107)
  lessons and where each one went
```

Access-controlled; retention per `65`. Personal data inside it ages out on the timers above; the
index and the aggregates remain.

## G4 — blocking items

Proposed `check_key`s for the registry (`58`), none of which exists yet: `devices.all_synced_past_event_end`,
`reconciliation.findings_reviewed`, `incidents.all_closed`, `devices.all_sanitized`,
`credentials.none_active_after_event`, `benchmark_facts.written`. Manual items with evidence: client
report delivered, lessons recorded, dossier complete, cash reconciled where taken.

A cancelled event runs the steps that apply — revocation, sanitization of anything staged, dossier —
and passes G4 like any other (`105`).

## Migration

| Step | Change | When |
|---|---|---|
| 1 | **Fix C1**: anonymizers cover `persons`, `credentials`, `badges`, `access_logs`, `invitations`; a test that deletes an account and asserts none of its names or emails remain | **Now** |
| 2 | Closeout checklist on paper; dossier as a folder | Now |
| 3 | `access_reconciliation_findings` and the reconciliation report | ARZ-102 |
| 4 | Settled marker from device cursors | `52` step 4 |
| 5 | Client report generator | ARZ-222 |
| 6 | `event_benchmark_facts` written at closeout | With `55` |
| 7 | Per-event retention job | Unnumbered — add to `136` |
| 8 | G4 check keys | With ARZ-204 |

## Open questions

- **Who receives the incident report?** Counts, yes; timelines only by agreement (`60`).
- **How long may settlement wait for a lost device?** 72 hours is a guess; the right answer is "until the DM decides, on the record".
- **Does the client get raw exports at all?** Only where the contract says so, and then through `106`'s export approval — never by default.
- **Is thirty days to G4 realistic?** It is a target that stops closeout drifting; the first three events will say whether it is too tight.

## Related

`51-reporting.md` · `52-analytics.md` · `54-attendance-intelligence.md` · `55-event-intelligence.md` ·
`56-event-operations.md` · `58-task-management.md` · `60-incident-management.md` ·
`63-event-documentation.md` · `65-privacy-gdpr.md` · `71-realtime-architecture.md` ·
`103-hardware-deployment.md` · `105-event-lifecycle.md` · `106-event-operating-model.md` ·
`107-event-day-runbook.md` · `131-event-readiness-checklist.md` · `133-ai-capabilities.md`
