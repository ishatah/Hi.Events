# Platform Admin Surface

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep + Fix + Extend · **Priority:** P1 for A1–A4 (unnumbered — add to `136` when scheduled); extensions ride their domains (ARZ-123 for the fleet view) · **Phase:** fixes now; MFA before external SaaS; extensions with Phases 2–4
**Depends on:** `09-permissions-and-roles.md`, `87-ui-ux-system.md`
**Blocks:** nothing directly

---

ARZO staff administering the platform itself: tenants, users, moderation, jobs, and — once the
on-site subsystems exist — the equipment and credentials that cross event boundaries. The admin
surface is small, well-gated at the action level, and weakly audited. The work here is mostly
closing the gaps between those three facts.

## Current state — `CONFIRMED`, ~70%

### What exists

**40 endpoints** under `/admin` (`api.php:543-601`), every one backed by an action in
`Http/Actions/Admin/`. Earlier documents counted "13" or "14 admin routes"; those were areas and
pages. The frontend has 13 child routes under `/admin` (`router.tsx:140-238`), 12 page directories
in `components/routes/admin/`, and a 12-link sidebar (`layouts/Admin/index.tsx:11-25`).

| Area | Endpoints | Frontend |
|---|---|---|
| Stats, dashboard, UTM attribution, upcoming events | 4 | Dashboard, UTM Analytics |
| Accounts — list, detail, verification, messaging tier (+ tier list) | 5 | Accounts, account detail |
| Organizer VAT setting and fee configuration (assign, update) | 3 | Inside account detail |
| Fee configurations — CRUD | 4 | Configurations |
| Users — list; impersonation start and stop | 3 | Users |
| Events, orders — platform-wide lists | 2 | Events, Orders |
| Failed jobs — list, delete, delete all, retry, retry all | 5 | Failed Jobs |
| Messages pending review — list, approve | 2 | Messages |
| AI-flagged spam events — list, approve, confirm spam | 3 | Flagged Events |
| Announcements to platform users — CRUD | 4 | Announcements |
| Account deletion — list, request, cancel, execute | 4 | Deletion Requests |
| System info | 1 | **None** — no frontend caller |

### Authorization

| Fact | Evidence |
|---|---|
| The route group carries only `auth:api` | `api.php:543` |
| 39 of 40 actions call `minimumAllowedRole(Role::SUPERADMIN)` | per-file search of `Http/Actions/Admin/**` |
| The exception is `StopImpersonationAction`, correctly: its caller *is* the impersonated user, and it acts only when the JWT carries `is_impersonating` | `StopImpersonationAction.php:64-68` |
| A test enforces SUPERADMIN **by directory**: any file under `Http/Actions/Admin/` must contain `Role::SUPERADMIN` | `ActionAuthorizationTest.php:152-176` |
| SUPERADMIN is granted only from the console, to every account membership of the user, logged at `critical`; the app refuses to create or assign it | `AssignSuperAdminCommand.php:57-90`; `CreateUserHandler.php:36-39`; `UpdateUserHandler.php:41-45` |
| The role is read live from `account_users` on each request | `AuthUserService.php:61-76` |
| No MFA anywhere | search `totp`, `two_factor`, `mfa` → 0; `64` |

### Impersonation

| Aspect | State | Evidence |
|---|---|---|
| Guard | SUPERADMIN only; cannot impersonate another SUPERADMIN | `StartImpersonationHandler.php:36-38` |
| Token | A normal JWT with `impersonator_id` and `is_impersonating` claims — **the standard 7-day TTL** | `:40-44`; `config/jwt.php:95` |
| Visible to the operator | Banner on every managed page | `ImpersonationBanner` in `AppLayout` |
| Recorded | **Mutating requests only**, to the application log, with the request payload minus four fields; Sentry tags on exceptions | `LogImpersonationMiddleware.php:20-42`; `Exceptions/Handler.php:51-65` |
| Not recorded | Start, stop, reason, and every read — attendee lists, order lists, exports | same |

`GET /admin/orders` returns buyer names and emails across every tenant, searchable
(`AdminOrderResource.php:20-36`); those reads are not recorded either.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| A1 | **No route-level gate.** Enforcement is per action plus a test keyed on the directory. An admin action placed outside `Http/Actions/Admin/` and routed under `/admin` passes the test and is open to any logged-in user (F12's residue) | `api.php:543`; `ActionAuthorizationTest.php:156` | Medium |
| A2 | **Deactivating a SUPERADMIN does not revoke admin access.** `validateUserStatus()` runs only inside `isActionAuthorized()`; `minimumAllowedRole()` checks the role alone, and no admin action calls `isActionAuthorized()` (0 of 40). An inactive SUPERADMIN keeps `/admin` until the token expires — up to 7 days | `IsAuthorizedService.php:38-49,58,123-128`; `BaseAction.php:214-220` | Medium — the offboarding case |
| A3 | Impersonation audit is log-only: no start/stop record, no reason, no reads; request payloads put personal data in application logs whose retention is `UNVERIFIED` | above | Medium — `67`, `65` |
| A4 | An impersonation token lives 7 days | above | Medium |
| A5 | Dashboard "recent revenue" sums the **lifetime** gross of every event whose stats row changed in the window, **across currencies**; "recent orders total" also sums across currencies | `GetAdminDashboardDataHandler.php:160-172,193-211`; `51` R9 | Low — the figure is meaningless |
| A6 | No MFA for the most powerful role | `64` | High before any external tenant |
| A7 | A message held for review can be approved but not rejected; it waits for the organizer to cancel | `api.php:572-573` | Low |
| A8 | `GET /admin/system-info` has no caller | search `system-info` in `frontend/src` → 0 | Low — wire it to the dashboard or delete it |
| A9 | The frontend guard calls `navigate('/')` during render, before the user query resolves | `layouts/Admin/index.tsx:34-37`; `useIsCurrentUserAdmin.ts:10` | Low — cosmetic; the backend enforces. Runtime `UNVERIFIED` |

A2 narrows `09`'s statement that "deactivation is immediate": true for endpoints that call
`isActionAuthorized()`, not for the admin surface.

**Fix now, independent of the roadmap:** A1 (a SUPERADMIN middleware on the group, keeping the
per-action checks as the second layer, plus a test that every `/admin` route resolves into the
`Admin` namespace), A2 (status check inside `minimumAllowedRole()`), A4 (a one-hour TTL on
impersonation tokens), A5.

## Decision: keep admin in the monolith — separate by gate, not by deployment

The scaffold asked whether platform admin should be a separate deployment. **Not now.**

| | Same deployment (recommended) | Separate deployment |
|---|---|---|
| Blast radius of a public-app compromise | The attacker holds the database credentials either way | **Unchanged** — both apps share the database |
| Blast radius of a stolen admin token | Reduced by MFA, step-up and short TTL, below | Reduced only if the admin host is also network-restricted |
| Cost | Nothing new | Second Vapor application, second frontend build, second release train |

The data a separate deployment would "protect" is in the same Postgres. What a separate deployment
genuinely buys is a network boundary — admin reachable only from ARZO's network or through SSO. The
cheap version of that is a distinct hostname for `/admin` with an edge allowlist; whether the
current CloudFront/Vapor setup supports a per-path rule is `UNVERIFIED` and belongs to `84`.

**Revisit when external SaaS tenants exist** (`100`, and the buyer question in `04`). At that point
an admin action touches another company's customers, and a separate, SSO-only admin host becomes
proportionate.

## Target: step-up admin sessions and recorded impersonation

- `/admin` requires a SUPERADMIN token **and** a recent step-up: re-authentication with a second
  factor, recorded as an `admin_elevated_until` claim, 30 minutes proposed.
- Starting impersonation requires a **reason** and issues a one-hour token.
- Every request under impersonation, reads included, writes an audit event with the impersonator
  as the actor (`67`). Request bodies are not logged; the audit records what was touched, not what
  was typed.

```
impersonation_sessions                 -- one row per session, append-only
  id, impersonator_user_id → users,
  target_user_id → users, account_id → accounts,
  reason text NOT NULL,                -- free text; required, shown to the account owner (below)
  started_at, expires_at,              -- expires_at = started_at + 1 hour
  ended_at NULL,                       -- NULL until stopped or expired
  ip_address, user_agent
```

For external tenants, the account owner should be able to see that ARZO staff impersonated a user
in their account, when, and why. That transparency is cheap once the table exists.

## Extensions for the new subsystems

The rule for what belongs here: **admin inspects and stops things; organizers decide.** Approving an
accreditation, editing an access rule or changing an attendee are event-owner decisions with their
own per-event audit trail; an admin doing them directly bypasses it. The admin acts on those only
through impersonation, which is recorded.

| Capability | Why platform-level | What admin gets | Depends on |
|---|---|---|---|
| **Device fleet** | ARZO-operated events share hardware; on event day ops needs every device across events in one list | Read-only fleet list — status, last seen, app version, key expiry — with **emergency suspend** | `40`, ARZ-100, ARZ-123 |
| **Accreditation oversight** | Stuck queues and personal-data retention are platform risks, not one event's | Pending counts and age per event; identity-document retention status after each event | `23`, `65` |
| **Credential revocation** | A lost or stolen credential is reported to ARZO, not always to the organizer | Revoke by identifier across events, with a reason, **through the same revocation service** the organizer uses — never a direct table update | `23`, `40`, `71` |
| **Messaging review** | Exists for email (`PENDING_REVIEW`) and AI-flagged events | Add reject (A7); extend the same queue to SMS (`43`) and push broadcasts (`44`) | `42`–`44` |
| **Audit search** | "Who did this?" is the question support asks most | Search audit events by actor, target and time | `67` |
| **Webhook health** | Failing receivers are invisible to the organizer until data is missing | Endpoints failing or auto-disabled, across tenants | `49` |
| **Tenant plans and limits** | Only when external SaaS exists | Plan, limits, usage | `99`, `100` |

At audit time a credential revoke endpoint existed only in the working tree, uncommitted and in
progress (`POST /events/{event_id}/credentials/{id}/revoke`); the admin action should call the same
handler with an admin actor, so there is one revocation path to test and to propagate to devices.

## Dashboard (A5)

Revenue from orders completed in the window, **grouped by currency**, never summed across them, with
the window stated on the tile. A platform total in one currency needs exchange rates
(OpenExchangeRates is wired behind an interface with a no-op default) and is a reporting feature
(`51`), not a dashboard tile.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Group middleware + route-to-namespace test (A1); status check in `minimumAllowedRole()` (A2) | **Now** |
| 2 | One-hour impersonation TTL (A4); currency-correct dashboard (A5) | **Now** |
| 3 | `impersonation_sessions`, required reason, audit of reads (A3) | With `67` |
| 4 | MFA and step-up for SUPERADMIN (A6) | Before any external tenant (`64`) |
| 5 | Message reject (A7); decide `system-info` (A8) | Any time |
| 6 | Fleet, accreditation oversight, revocation, audit search | As `40`, `23`, `67` land |

## Open questions

- **Who holds SUPERADMIN?** Recommend a named list of at most three people, reviewed quarterly, with break-glass use for anything outside support. A business decision.
- **Should impersonation be visible to the tenant?** Recommend yes for external tenants: the reason and time, in the account's own audit view.
- **Does admin need attendee personal data outside impersonation?** `GET /admin/orders` shows buyer emails platform-wide. Leaning: keep search by order reference, mask email by default, record every unmasking (`65`).
- **Separate admin host?** Deferred until external tenants; revisit with `100`.

## Related

`09-permissions-and-roles.md` · `64-security.md` · `65-privacy-gdpr.md` · `67-audit-logging.md` ·
`40-device-management.md` · `23-accreditation.md` · `49-webhooks.md` · `51-reporting.md` ·
`84-infrastructure.md` · `100-saas-tenancy.md` · `87-ui-ux-system.md` · `02-current-state-audit.md`
