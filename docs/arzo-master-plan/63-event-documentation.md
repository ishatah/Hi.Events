# Event Documentation — the Event Dossier

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (assembly over existing records) · **Priority:** P3 (ARZ-203 for the vendor and cost sections; the rest unnumbered — add to `136` when scheduled) · **Phase:** 5; `73`'s `documents` store earlier, with ARZ-051
**Depends on:** `56-event-operations.md`, `59-event-readiness.md`, `60-incident-management.md`, `65-privacy-gdpr.md`, `67-audit-logging.md`, `73-file-management.md`
**Blocks:** `108-post-event-closeout.md`

---

The durable, retrievable record of one event: what was planned, what was agreed, what was decided
and on what evidence, what happened, what it cost, and what to do differently. It is what makes a
repeat event cheaper to run than the first, and it is what ARZO produces when a client or an
investigator asks "show me".

## Current state — `MISSING`, ~5%, with fragments

| Fact | Evidence |
|---|---|
| `event_logs` exists and has never been written; `entity_type` is `bigint` | Live DB; `schema.sql:127`; only the two account-deletion paths reference it (`67`) |
| `order_audit_logs` records self-service edits and refund failures, with no actor and **no reader** | `OrderAuditAction.php:9-15`; no repository consumer outside writers (`67`) |
| **No store for non-image files** — uploads accept `jpeg,png,jpg,webp` only | `CreateImageRequest.php:25`; a contract PDF cannot be held. `73` now designs a private `documents` table |
| No plan, gate-decision, readiness, incident, task or lessons entity | Live DB: nothing matching `operation\|task\|incident\|readiness\|gate` |
| Nine commerce reports exist; no post-event bundle | `51` |
| Exports stop silently at 10,000 rows | `51` R1 — an incomplete export must never become the archived record |
| Duplication is the de facto template and copies none of the ARZO tables | `DuplicateEventService` (`56`) |

The scaffold said `PARTIAL` because `event_logs` and `order_audit_logs` exist. Neither is usable as
part of a dossier: one is empty and mistyped, the other has no actor. The honest state is `MISSING`.

## Decision: the dossier is an index over records, plus one frozen pack at closeout

Two ways to build a dossier:

| Option | Problem |
|---|---|
| Copy everything into a dossier at closeout | Duplicates personal data, so every copy needs its own retention and its own erasure path (`65`). The copy drifts from the source. |
| **Reference the records where they live, and freeze one aggregate pack at G4** | The source tables keep their own retention; the frozen pack contains no personal data, so it can be kept long |

**The dossier page is a live view** over records owned by other documents. **The closeout pack is a
snapshot** generated once at G4 (`56`), stored as a document, hashed, and recorded in
`audit_events` (`67`) so its integrity can be checked later.

## What the dossier contains

| Section | Source | Personal data | Retained |
|---|---|---|---|
| Plan — timeline, venue, zones, access rules, programme | `event_operations` (`56`), space (`25`), rules (`24`), sessions (`27`) | Speaker names | Dossier period |
| Contracts and permits | `documents` (`73`) of class `CONTRACT`, `PERMIT`, `INSURANCE`, `CERTIFICATE` | Signatory names | Contract retention (`UNVERIFIED`, legal) |
| Gate decisions G1–G4 | `event_gate_decisions` (`56`) | Decider name | Dossier period |
| Readiness snapshots | `readiness_reviews`, `readiness_items` (`59`) incl. waivers | Waiver names | Dossier period |
| Incidents | `incidents` (`60`): counts by type and severity; SEV1–2 timelines; `RESTRICTED` as counts only | Yes, in timelines | Incident retention per type (`65`) |
| Attendance | `event_benchmark_facts` (`55`), attendance report (`54`), groups under 5 suppressed | No | Long |
| Commercial | Sales reports (`51`), P&L view (`62`) | No | Finance period |
| Vendors | `event_vendors` with ratings (`61`) | No | Long |
| Reconciliation | Offline reconciliation report (`71`): retrospective violations surfaced at settle, with their reviews | Credential ids | Dossier period, pseudonymized with the logs |
| Degraded operation | Paper-fallback sheets as transcribed, override logs (`107`); device sanitization records (`103`) | Operator names | Dossier period |
| Client deliverable | The client report as delivered (`108`) | No | Dossier period |
| Audit extract | `audit_events` for the event (`67`) | Actor names | Audit retention |
| Lessons | `event_lessons` (below) | Should be none | Long |

## Model

### Documents — `73`'s store, with four additions

`73` owns `documents`: private disk only, presigned upload, authorized 60-second downloads, malware
scanning that fails closed, `retain_until` and a purge job. The dossier adds only what contracts and
certificates need:

```
documents                            -- owned by 73; additions for 61, 62, 63
  + document_class values            -- INSURANCE | PERMIT | CERTIFICATE | METHOD_STATEMENT
                                     -- | RISK_ASSESSMENT | DPIA | CLOSEOUT_PACK
  + owner_type values                -- COMPANY (a trade licence outlives one event) | SPONSORSHIP;
                                     -- 73's VENDOR means an event_vendors row (61)
  + valid_from NULL, expires_at NULL -- the document's own validity (an insurance period),
                                     -- distinct from retain_until, which is 65's clock
  + supersedes_document_id NULL → documents
```

- **A superseded contract is not deleted.** The new version points at the old one; the dossier shows
  the chain.
- `expires_at` feeds readiness (`59`): an insurance certificate that lapses before the last event day
  is a failed check (`61`).

### Lessons and snapshots

```
event_lessons
  id, short_id, event_id,
  area,                            -- ACCESS | STAFFING | VENDORS | PROGRAMME | COMMERCIAL
                                   -- | SAFETY | TECHNOLOGY | ATTENDEE_EXPERIENCE | OTHER
  observation text, recommendation text,
  verdict,                         -- KEEP | IMPROVE | STOP
  destination,                     -- TASK_TEMPLATE | READINESS_CHECKLIST | RUNBOOK | BACKLOG | VENDOR_NOTE
  destination_ref NULL,            -- the template item, check key or backlog id it went to
  owner_user_id NULL → users,
  source_incident_id NULL → incidents, source_task_id NULL → event_tasks,
  author_user_id → users, created_at, updated_at

event_dossier_snapshots            -- one per closeout; append-only
  id, event_id, generated_at, generated_by → users,
  document_id → documents,         -- the CLOSEOUT_PACK file (PDF plus CSV annex)
  manifest jsonb,                  -- sections included, row counts, source hashes
  sha256 char(64)
```

- **Every lesson has a destination**, as `108` requires: a lesson with nowhere to go is a complaint.
  `destination` makes "lessons applied" a readiness check for the next edition.
- **`event_lessons` warns on personal names.** Lessons are about processes. "Gate 3 steward was
  slow" is a performance note about a worker (`57`); "Gate 3 needs two scanners at peak" is a lesson.

## The closeout pack

Generated at G4 from settled data — never before, because figures from offline windows are
provisional until every device has synced (`52`, `71`).

| In the pack | Not in the pack |
|---|---|
| Timeline, gates and their evidence summaries, readiness outcomes and waivers | Attendee names, emails, trails |
| Attendance aggregates and curves, groups under 5 suppressed | Individual access logs |
| Incident counts; SEV1–2 timelines with names replaced by roles | `RESTRICTED` incident detail |
| Commercial summary; cost summary by category | Line-level costs, unless the client contract requires |
| Vendor ratings; lessons | Performance notes on named people |
| List of contract documents (titles, dates, hashes) | The contract files themselves (they stay in `documents`) |

Because the pack contains no personal data by construction, it can outlive every personal-data
retention window in `65`. That is the design's whole point.

## Retention — the answer the scaffold asked for

The dossier does not have one retention period. Its parts do.

| Part | Proposed retention | Basis |
|---|---|---|
| Closeout pack, lessons, benchmark facts, vendor ratings | Life of the client relationship + 6 years, default | No personal data. The 6 years is a placeholder for commercial record-keeping; the legal minimum is `UNVERIFIED` — finance and legal must set it |
| Contracts, permits, insurance | Contract term + limitation period | `UNVERIFIED`, legal |
| Gate decisions, readiness snapshots | As the closeout pack | Decision records with staff names — business contact data, kept for accountability |
| Incidents | Per type — `65` sets medical shortest, security longest | Special-category rules in `65` |
| Personal-data documents (staff ID lists, accreditation uploads) | Event end + 30 days, unless a client contract requires longer | `65` |
| Underlying operational records (access logs, orders, attendees) | Their own schedules in `65` | The dossier only references them |

When an underlying record is deleted or pseudonymized, the dossier's live view shows what remains
and says why ("attendance detail aggregated on 2027-01-10 under retention policy"). The frozen pack
is unaffected.

## Why this makes the next event cheaper

1. **Templates carry the lessons.** Lessons routed to a task template or the readiness checklist are
   already in the next edition. When an event is duplicated from a previous edition (`56`), any
   `IMPROVE` or `STOP` lesson whose destination is not yet applied appears on the plan screen, one
   click from becoming a task (`58`).
2. **The budget starts from actuals.** `62`'s costs from the previous edition seed the new budget.
3. **Vendor choice starts from ratings** (`61`), not from memory.
4. **Consumables start from measured rates** — reprint and loss rates (`55`, `62`).

Without the dossier, each of these depends on who remembers.

## Access

| Who | Sees |
|---|---|
| Event director, operations | Everything they are otherwise permitted to see, per section |
| Client | The closeout pack, and any section released by agreement (`101`) |
| Auditor or investigator | A time-boxed, audited grant (`67`) — never a shared login |

Section visibility follows the owning document's permissions. The dossier grants nothing new.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | `73`'s `documents` (its step 6), plus the four additions above | With ARZ-051, or when `32` or `61` first needs uploads |
| 2 | `event_lessons` with destinations | With `108`'s retrospective step |
| 3 | Dossier page as a read-only index over existing records | Grows with `56`, `59`, `60`, `61`, `62` |
| 4 | Unapplied lessons surfaced on duplication | With `56` step 5 |
| 5 | Closeout pack generator, `event_dossier_snapshots`, hash recorded in `audit_events` | With `108`, after `67` |
| 6 | `retain_until` set on dossier documents from `65`'s classes; `73`'s purge job does the rest | With `65` |

## Open questions

- **Commercial record retention** — the 6-year default is a placeholder. Finance and legal own the number.
- **Client-signed closeout** — do government clients sign the pack? If so, the signature is a document attached to the G4 decision, not a new mechanism.
- **Language** — is the closeout pack needed in Arabic (`82`)? It is generated, so both are possible; templates double.
- **Photos and media from the event** — marketing assets are not the dossier. Leaning: out of scope; a link to wherever marketing keeps them.

## Related

`56-event-operations.md` · `58-task-management.md` · `59-event-readiness.md` ·
`60-incident-management.md` · `61-vendor-management.md` · `62-procurement.md` ·
`65-privacy-gdpr.md` · `67-audit-logging.md` · `73-file-management.md` ·
`55-event-intelligence.md` · `51-reporting.md` · `71-realtime-architecture.md` ·
`101-enterprise.md` · `103-hardware-deployment.md` · `107-event-day-runbook.md` ·
`108-post-event-closeout.md`
