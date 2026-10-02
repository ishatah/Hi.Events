# Audit Logging

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (one table) + Fix (retire two) · **Priority:** P1 (ARZ-319, which ARZ-051 depends on; A3 in ARZ-314/ARZ-321; A4 in ARZ-312) · **Phase:** 2, before the accreditation workflow writes its first decision
**Depends on:** `09-permissions-and-roles.md`, `40-device-management.md`, `48-api-platform.md`
**Blocks:** `23-accreditation.md` (decision trail), `24-access-control.md` (overrides), `21-badge-management.md` (reprints), `54-attendance-intelligence.md` (trail views), `60-incident-management.md` (restricted views), `63-event-documentation.md`, `65-privacy-gdpr.md` (erasure evidence)

---

Who did what, to what, when, from where, and why — for the actions someone may later contest:
an accreditation refused, a credential revoked, a door overridden, a badge reprinted, an attendee
list exported. Audit is a product feature with its own retention, not a debugging log.

## Current state — `PARTIAL`, ~15%

`02` scores audit logging at 50%. The audit does not support that: the generic table is unused and
mistyped, and the order table has no actor and no reader.

### `event_logs` — dead

```
event_logs   id, user_id NOT NULL → users (no ON DELETE), type varchar,
             entity_id bigint NOT NULL, entity_type bigint NOT NULL,
             ip_address, user_agent, data jsonb, timestamps, deleted_at
             -- no event_id, no account_id, no index beyond the primary key
```

From the 2020 baseline (`schema.sql:127`). **Never written**: no model, no repository; a domain
object exists (`EventLogDomainObject.php:5`) and the only code touching the table is the two
account-deletion paths that delete from it (`ActivityLogAnonymizer.php:55`,
`AccountHardDeletionService.php:150`). `entity_type` is a `bigint` — an entity type is a name.

### `order_audit_logs` — write-only

`event_id`, `order_id`, `attendee_id`, `action`, `old_values`, `new_values`, `changed_fields`,
`ip_address`, `user_agent`. **No actor column.** Seven actions (`OrderAuditAction.php:9-15`), written
by attendee self-service edits and email resends, the occurrence refund job and the manual capacity
override. Organizer edits of orders and attendees in the admin are not recorded. **Nothing reads the
table** — no repository method is called outside the writers — even though the create-attendee
modal tells organizers "the override is recorded in the order audit log"
(`CreateAttendeeModal/index.tsx:277`). Account anonymization deletes it
(`ActivityLogAnonymizer.php:23-24`).

### Impersonation — partly logged, not audited

| Aspect | Evidence |
|---|---|
| Start and stop are **not logged** | `StartImpersonationHandler.php:40-44` issues the token; no logger in the handler or the action |
| The impersonation token has the ordinary **7-day** TTL | `config/jwt.php:95`; no shorter TTL set at start |
| Mutations while impersonating are written to the application log with the request payload | `LogImpersonationMiddleware.php:29-42`; `LOG_CHANNEL=stderr` (`backend/.env.example:27`) — retention depends on the host, `UNVERIFIED` |
| **Reads are not logged** — an impersonating admin can view and export every attendee unrecorded | Same middleware, mutations only |
| Errors during impersonation are tagged in Sentry | `Exceptions/Handler.php:62-65` |

### Other super-admin actions — unrecorded

Account verification, messaging-tier changes, spam approve/confirm, message approval and forced
account-deletion execution write no audit record; their handlers contain no logging at all. Some
leave a trace on the target row (`event_spam_checks.reviewed_by_user_id`), overwritten by the next
decision.

### The new subsystems — state on the row, history lost

| Table / service | What is recorded | Gap |
|---|---|---|
| `CredentialIssuanceService` | `issued_at` | **`issued_by` is never set** (`CredentialIssuanceService.php:88-95`) |
| `CredentialIssuanceService::revoke` | `revoked_at`, `revoked_by`, `revocation_reason` | No status guard; a second revoke **overwrites** the first decision's actor and reason (`:60-71`). Materialized grants are left `ACTIVE`. The uncommitted `RevokeCredentialHandler` passes the user id through and writes nothing else (`RevokeCredentialHandler.php:22-33`) |
| `accreditations` | `reviewed_by`, `reviewed_at`, `rejection_reason` | Last decision only; an appeal (`REJECTED → UNDER_REVIEW → APPROVED`, `23`) erases the rejection |
| `badges` | `printed_by`, `voided_by`, `void_reason`, `print_count` | A counter, not a history of who reprinted when |
| `access_logs` | `operator_user_id`, `override_by`, `override_reason` | **No `device_id` column**; `AccessScanService::scan()` accepts `$deviceId` and drops it (`AccessScanService.php:38` versus the insert at `:211-228`) |
| `access_logs` append-only | By convention | **No database enforcement** — no trigger or rule on any table (`pg_trigger`, `pg_rules` both empty) |
| Exports of personal data | Nothing | `ExportAttendeesAction.php:34-36` |
| Views of individual access trails | Nothing | Uncommitted `GetAccessLogsAction`; `54` requires every trail view audited |

The good precedent is `account_deletion_requests`: a purpose-built record of who requested what,
when it ran, and a manifest of what it did (`AccountDeletionService.php:106-114,213-218`).

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| A1 | `issued_by` never set on credential issue | `CredentialIssuanceService.php:88-95` | Medium. **Fix now** — one field |
| A2 | Revocation overwrites a previous revocation and leaves grants active | `:60-71` | Medium. **Fix now**: guard on status; revoke grants in the same transaction (`24`) |
| A3 | Scans cannot be attributed to a device | `AccessScanService.php:38,211-228`; live DB | Medium — clone detection and device-compromise investigation both need it (`68`). **Fix now** (ARZ-314, ARZ-321), while `access_logs` is empty |
| A4 | Impersonation start/stop unlogged; 7-day token; reads invisible | Above | High for any external tenant (ARZ-312) |
| A5 | `order_audit_logs` has no actor and no reader | Above | Low today; the UI promises a record nobody can see |
| A6 | `event_logs` dead and mistyped | Above | Low — confusing, and cited by three documents as a foundation |
| A7 | Append-only tables unenforced | Above | Medium — one careless `UPDATE` erases evidence |

## Decision: one `audit_events` table for contestable actions

Not event sourcing, not a copy of every write, and not the application log.

| Record | Lives in | Why separate |
|---|---|---|
| Scans | `access_logs` | Volume, and it is the operational evidence (`24`) |
| Incident timeline | `incident_entries` | Already append-only (`60`) |
| Gate decisions | `event_gate_decisions` | Already append-only (`56`) |
| Debugging, errors | Application log, Sentry | Short retention, not a product surface |
| **Who did a contestable thing** | **`audit_events`** | One place to answer "who, when, why" across subsystems |

Where an append-only table already holds the evidence, `audit_events` holds a pointer, not a copy:
an override writes `access.override_granted` with the `access_logs` id.

## What is audited

| Area | Actions |
|---|---|
| Accreditation (`23`) | submitted, approved, rejected (with reason), appealed, withdrawn, zones changed |
| Credential (`23`, `36`) | issued, suspended, reinstated, revoked, reissued; medium encoded, lost, replaced |
| Access (`24`) | override granted; rule created, changed, deleted (before/after); grant edited by hand |
| Badge (`21`) | printed, reprinted (reason), voided (reason) |
| Readiness and gates (`59`, `56`) | check waived (who, what, why) — the gate decision itself stays in its own table |
| Incident (`60`) | view of a `RESTRICTED` incident; sensitivity changed |
| Personal data (`65`) | every export; individual trail viewed; privacy request fulfilled; consent withdrawn; erasure executed |
| Platform administration | impersonation started and stopped; mutations while impersonating; account verified; messaging tier changed; spam approved or confirmed; deletion executed |
| Access to the platform (`09`, `40`, `48`, `49`) | role or permission granted and removed; API key and device key issued and revoked; webhook created, secret rotated |
| Orders | organizer and admin edits, refunds, cancellations, mark-as-paid; everything `order_audit_logs` records today |

Scans, ordinary reads and configuration edits outside this list are not audited. An audit table
that records everything is one nobody reads.

## Model

```
audit_events                         -- append-only; enforced by trigger
  id bigint,
  occurred_at timestamptz NOT NULL,
  account_id bigint NULL,            -- NULL only for platform-level actions with no tenant
  event_id bigint NULL,
  actor_type varchar(24),            -- USER | API_KEY | DEVICE | SYSTEM | SELF_SERVICE
                                     -- | EXHIBITOR_PORTAL | VENDOR_PORTAL
  actor_id bigint NULL,              -- users.id | api_keys.id | devices.id | exhibitor_staff.id ...
  actor_user_id bigint NULL,         -- the human behind a device or portal session, when known
  impersonator_user_id bigint NULL,  -- set whenever the JWT carries is_impersonating
  actor_label varchar(255) NULL,     -- display name at the time; scrubbed on erasure (65)
  action varchar(64),                -- 'credential.revoked', 'export.attendees', 'access_trail.viewed'
  subject_type varchar(48), subject_id bigint NULL,
  before jsonb NULL, after jsonb NULL,   -- changed fields only; personal fields masked (below)
  reason text NULL,
  ip_address inet NULL, user_agent varchar(512) NULL,
  request_id uuid NULL,              -- correlates with application logs and Sentry
  metadata jsonb NULL,
  prev_hash bytea NULL, row_hash bytea NULL,   -- tamper-evidence, opt-in per account
  INDEX (account_id, occurred_at DESC)
  INDEX (event_id, occurred_at DESC)
  INDEX (subject_type, subject_id, occurred_at DESC)
  INDEX (actor_type, actor_id, occurred_at DESC)
  INDEX (action, occurred_at DESC)
```

- **`actor_type` covers devices and API keys from the start.** Devices sign in operators by scanning
  their staff credential (`57`, `38`): a scan-issued override then records `actor_type = DEVICE`,
  `actor_id` the device, `actor_user_id` the operator.
- **`request_id`** — no request-id middleware exists today (search: only deletion-request ids). Add
  one that sets `X-Request-Id`, the log context and a Sentry tag; the audit row carries it.
- **Personal data in `before`/`after` is masked by an allowlist per subject type.** A corrected email
  is recorded as "email changed", not both addresses; an ID number is never recorded. Otherwise the
  audit table becomes the least-protected copy of everything `65` protects.

## Writing it

A domain service, `AuditLogger::record(...)`, called by the handler or domain service that makes the
change, **inside the same database transaction**. A rolled-back change leaves no audit row; a
committed change always has one.

Rejected alternatives:

| Option | Why not |
|---|---|
| Eloquent model observers | Blind to query-builder writes — `AccessScanService` and `CredentialIssuanceService::revoke` both bypass models — and against the rule that Eloquent stays in repositories |
| Queued writes | An audit row lost on a failed job is worse than a slow request |
| Database triggers on every table | Cannot know the actor or the reason |

Reads that are audited (exports, trail views, restricted incidents) are recorded by the action
before the response is sent.

## Enforcement and tamper-evidence

1. **Append-only by trigger** on `audit_events` and `access_logs`: `UPDATE` and `DELETE` raise,
   except (a) the retention job, which sets a transaction-local flag, and (b) for `access_logs`,
   updates that only NULL identity columns — needed for foreign keys with `ON DELETE SET NULL` and
   for `65`'s retention stripping. This guards against application bugs, not against a database
   superuser; it is a seatbelt, not a vault.
2. **Hash chain, opt-in per account**, for clients who ask for tamper-evidence (`64` expects some
   government clients will): `row_hash = SHA-256(prev_hash ‖ canonical JSON of the row)`, with
   writes for that account serialized by a transaction-scoped advisory lock. The cost is contention
   on bursts — thousands of badge prints before doors open — which is why it is opt-in.
3. **Anchoring.** A daily job publishes each chained account's head hash somewhere ARZO's database
   cannot rewrite — an object-lock bucket, or an email to the client's nominated address.
   `php artisan audit:verify {account}` recomputes the chain and reports the first break.

A chain without an external anchor proves only that nobody edited one row carelessly. The anchor is
what makes it evidence.

## Retention

| Record | Proposed retention |
|---|---|
| `audit_events` — default | 2 years |
| Security-relevant: credential, override, impersonation, administration | 3 years |
| Government-client accounts | As contracted; configurable per account, never below the default |
| Application logs (debugging) | 14–30 days, per host (`78`) |

Numbers are proposals; limitation periods are `UNVERIFIED` (`65`). Deletion is by the retention job
only, in whole months; with a hash chain, the job writes a checkpoint row carrying the hash of the
last deleted row, so verification continues from it.

**Erasure versus audit.** On a person's erasure (`65`) their audit rows keep ids and actions; the
`actor_label`, masked values and IP are scrubbed. "Credential 812 was revoked by user 44 for
misconduct" survives; the names do not. Keeping who-did-what is a lawful purpose. Account
anonymization currently **deletes** `order_audit_logs`; after migration it scrubs `audit_events`
instead.

## Reading it

- **A History tab** on every audited subject — credential, accreditation, badge, order, device — built
  from the `(subject_type, subject_id)` index. This is where 90% of reads happen.
- **An account-level audit search** with filters (actor, action, event, date), gated by a new
  `audit.view` permission — absent from the 43 seeded permissions (`2026_09_29_000007:20-35`).
- **CSV export** of a filtered view, itself audited.
- Platform-level actions are visible to `SUPERADMIN` only.

## Retire `event_logs`; fold in `order_audit_logs`

- **`event_logs`: drop.** Nothing writes it, its type is wrong, and it has no tenant column. Remove
  the two deleters and the generated domain object in the same change (`CLAUDE.md`: no dead code).
- **`order_audit_logs`: migrate, then drop.** Backfill each row as an `audit_events` row
  (`actor_type = SELF_SERVICE` or `SYSTEM`, from the action), switch the writers, and drop the table a
  release later. One place to look is the point.

## Impersonation

- Log `impersonation.started` and `impersonation.stopped` with reason — require a reason at start.
- Issue impersonation tokens with a **60-minute** TTL, not 7 days.
- Record `impersonator_user_id` on every audit row written during the session; audit exports and
  trail views made while impersonating like any other.
- Replace the payload logging in `LogImpersonationMiddleware` with field names only (`65` PV4).

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | A1–A3: `issued_by`; guarded revoke that also revokes grants; `access_logs.device_id` recorded (ARZ-314, ARZ-321) | **Now** — `access_logs` and `credentials` are empty |
| 2 | Append-only triggers on `access_logs` (A7) | **Now**, before the first scan is recorded in production |
| 3 | `audit_events`, `AuditLogger`, request-id middleware, `audit.view` (ARZ-319) | Before ARZ-051 — before the first accreditation decision |
| 4 | Wire credential, badge, override, rule, export and trail-view actions | As each lands |
| 5 | Impersonation: logged start/stop, reason, short TTL (A4, ARZ-312) | Before any external tenant |
| 6 | Backfill `order_audit_logs`, switch writers, drop; drop `event_logs` (A5, A6 — ARZ-319's "actor on order audit" is met by the fold-in) | Low |
| 7 | History tabs and account audit search | With the subjects' UIs |
| 8 | Hash chain, anchoring and `audit:verify`, opt-in per account | When a client asks |
| 9 | Retention job | With `65` |

## Open questions

- **Which clients need tamper-evidence?** `64` expects government clients may. Leaning: build steps 1–7 regardless; step 8 on the first contractual request.
- **Retention floor** — 2 and 3 years are proposals; legal and the first government contract set the real numbers.
- **Who reads the platform-level log?** A named person at ARZO should review impersonation and administrative actions monthly; otherwise the log records misuse nobody notices.
- **Client access to its own audit** — do government clients get read access to their account's audit events? Probably yes, through the same `audit.view` permission in their account.

## Related

`23-accreditation.md` · `24-access-control.md` · `21-badge-management.md` · `09-permissions-and-roles.md` ·
`40-device-management.md` · `48-api-platform.md` · `54-attendance-intelligence.md` ·
`60-incident-management.md` · `63-event-documentation.md` · `64-security.md` · `65-privacy-gdpr.md` ·
`68-fraud-prevention.md` · `78-observability.md` · `89-admin-platform.md` · `02-current-state-audit.md` ·
`136-master-backlog.md`
