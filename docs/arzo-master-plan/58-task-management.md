# Tasks and Checklists

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (narrow) · **Priority:** P3 (ARZ-201) · **Phase:** 5
**Depends on:** `56-event-operations.md`
**Blocks:** `59-event-readiness.md`

---

## Current state — `MISSING`, with one useful precedent

`CONFIRMED`: no task, checklist or to-do entity.

The precedent is the event **setup checklist** (`SetupChecklist.tsx:88-167`): seven items, each
**computed from data** — "tickets" is done when products exist, "payouts" when Stripe Connect setup
is complete. Nobody ticks them. That is the most valuable kind of checklist item, and the design
below generalizes it. Its weaknesses are the ones to avoid: it runs only in the browser, and its
dismissal lives in localStorage.

## Build or integrate?

The scaffold's question, and the answer splits cleanly:

| Kind of task | Example | Where |
|---|---|---|
| General project work | "Brief the caterer", "Book hotel for speakers" | **The team's existing tool** — duplicating it guarantees neither gets used |
| **Event-anchored, verifiable from platform data** | "All accreditations reviewed", "Every gate has an enrolled device", "Badge templates approved" | **ARZO** — no external tool can see the data |
| Event-anchored, manual with evidence | "Fire certificate received", "Rigging inspection passed" | ARZO — it belongs in the event record (`63`) and feeds go/no-go (`59`) |

**Build only the second and third rows.** Whether the team already uses a general tool is
`UNVERIFIED`; the recommendation holds either way.

## The distinctive feature: automatic checks

A task can carry a `check_key` naming a server-side predicate. The platform evaluates it and the
task completes itself when the data says so — the setup-checklist idea, moved server-side and made
extensible.

```php
interface ReadinessCheck
{
    public function key(): string;
    public function evaluate(int $eventId): CheckResult;   // PASS | WARN | FAIL, detail, evidence
}
```

A registry of checks, shared with readiness (`59`). The first seven are the existing setup-checklist
items, ported server-side. Later checks come with each domain:

| Check | Domain |
|---|---|
| `accreditations.none_pending` | `23` |
| `credentials.issued_for_approved` | `23` |
| `access_points.all_have_active_device` | `40` |
| `printers.ready_with_supplies` | `39` |
| `access_rules.simulation_passes` | `24` (ARZ-064) |
| `sessions.all_have_rooms` | `27` |
| `shifts.headcount_filled` | `57` |
| `booths.assigned_are_built` | `35` |

Checks are **read-only** and cheap; they re-run on demand and on a schedule in the days before an
event.

## Model

```
task_templates
  id, short_id, account_id, name,
  event_category NULL, applies_to jsonb,   -- event types or formats this template fits
  timestamps, deleted_at

task_template_items
  id, task_template_id, title, description NULL,
  anchor,              -- CONTRACTED | BUILD_UP | DOORS_OPEN | EVENT_END | BREAKDOWN_END (56)
  offset_minutes int,  -- negative = before the anchor
  owner_role NULL, check_key NULL,
  requires_evidence bool default false,
  is_blocking bool default false,          -- feeds go / no-go (59)
  sort_order

event_tasks
  id, short_id, event_id, task_template_item_id NULL,
  title, description NULL, due_at timestamptz NULL,
  assignee_user_id NULL → users,
  status,              -- TODO | IN_PROGRESS | DONE | BLOCKED | WAIVED
  check_key NULL, last_check_result jsonb NULL, last_checked_at NULL,
  evidence jsonb NULL,                     -- file ids (73), notes
  subject_type NULL, subject_id NULL,      -- ZONE | ACCESS_POINT | SESSION | DEVICE | EVENT_EXHIBITOR
  completed_at NULL, completed_by NULL, waived_reason NULL,
  timestamps, deleted_at
  INDEX (event_id, status, due_at)
```

- **Due dates are relative.** A template says "T-7 days before doors"; instantiating it against an
  event's timeline (`56`) produces absolute times. If the event moves, due dates move with it.
- **Templates accumulate knowledge.** The scaffold's point: the checklist for the third gala should
  contain everything learned at the first two, instead of living in someone's head.
- **`WAIVED` needs a reason.** A skipped blocking item without an explanation is exactly what a
  post-incident review needs to find.
- **Subjects** let a task point at the thing it concerns — "Gate 3 has no device" links to Gate 3.

Exhibitor obligations (`32`) — stand plans, insurance certificates — are `event_tasks` whose subject
is the `event_exhibitors` row, visible in the exhibitor portal.

## Scope limits

- No dependencies between tasks, no Gantt, no time tracking. That is project-management software.
- Assignees are **users**, not staff persons — tasks belong to the planning team, not to stewards.
- Notifications: a daily digest of overdue items, not per-task alerts.

## Open questions

- **Does the team use a PM tool today?** If so, export event tasks as ICS or CSV into it rather than asking people to watch two places.
- **Who maintains templates?** Without an owner they rot after the first event.
- **Should the setup checklist's dismissal move server-side** once ported? Yes — per user per event, so it follows the user across devices.

## Related

`56-event-operations.md` · `59-event-readiness.md` · `63-event-documentation.md` ·
`32-exhibitor-management.md` · `73-file-management.md` · `131-event-readiness-checklist.md`
