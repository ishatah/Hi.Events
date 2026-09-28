# Current State Audit

**Status:** WRITTEN · **Authority:** AUTHORITATIVE — the record of what exists · **Audit date:** 2026-09-28
**Baseline:** `develop` @ `7dec84ca` · **Method:** code reading, live schema queries, live index inspection
**Remediation:** Phase 0 complete 2026-09-28 — F1, F5, F6, F7, F9, F12, F13 addressed; F11 now covered by a test. See `113-roadmap.md`.

---

## How to read this

Completeness is scored against `128-definition-of-done.md`. **100% is not "it exists"** — it
requires database, backend, frontend, authorization, validation, error handling, testing, security,
observability, documentation, deployment, edge cases, and operational readiness.

| Score | Meaning |
|---|---|
| 0% | Nonexistent |
| 25% | Proof of concept |
| 50% | Partial — works on the happy path |
| 75% | Mostly complete, known gaps |
| 90% | Production-capable, minor gaps |
| 100% | Production-complete per `128` |

Nothing in this document scores 100%. That is not pessimism — it is what the standard demands, and
no subsystem here has documented operational readiness plus observability plus edge-case handling.

## Platform metrics — `CONFIRMED`

| Metric | Value | Source |
|---|---|---|
| Live DB tables | **72** | `information_schema` query. NB `schema.sql` is the frozen **2020 baseline** (31 tables, pre-`products` rename) loaded by `2020_01_25_113926_initial_db.php` — **not** the current schema |
| Migrations | **149** | `ls backend/database/migrations/*.php` |
| API endpoints | **266** | `routes/api.php` verb count |
| Frontend route paths | **88** | `router.tsx` |
| HTTP actions | **268** | `app/Http/Actions/**` |
| Handlers | **201** | `Services/Application/Handlers/**` |
| Domain services | **193** | `Services/Domain/**` |
| Repositories | **128** | `app/Repository/**` |
| Domain objects | **82** + 48 enums + 22 status enums | `app/DomainObjects/**` |
| Jobs | **27** | `app/Jobs/**` |
| Mailables | **30** | `app/Mail/**` |
| Backend tests | **1,215 passing**, 2,994 assertions | `php artisan test --testsuite=Unit` |
| E2E specs | **73 files / 288 tests / 27 @smoke** | `e2e/tests/**` |
| Frontend components | **378 `.tsx`** | `frontend/src/components/**` |
| Query / mutation hooks | **102 / 135** | `frontend/src/queries`, `/mutations` |
| Frontend unit tests | **0** | No test runner in `frontend/package.json` |

### Empirical domain core — `CONFIRMED` by FK centrality

Inbound foreign keys per table:

| Table | Inbound FKs |
|---|---|
| `events` | **24** |
| `accounts` | 13 |
| `products` | 12 |
| `orders` | 11 |
| `event_occurrences` | 10 |
| `users` | 10 |
| `organizers` | 7 |
| `product_prices` | 4 |
| `attendees` | **3** |

`attendees` having only 3 inbound FKs is significant: it is a **leaf**, not a hub. Accreditation,
credentials, badges, and access logs all need to hang off a person — which is why
`23-accreditation.md` introduces `persons` rather than overloading `attendees`.

---

## Domain audit

### 1. Core platform & admin

| Capability | Exists | % | Evidence | Problems | Action |
|---|---|---|---|---|---|
| Events | `CONFIRMED` | 90 | `EventDomainObject`, `event_settings`, `event_occurrences`; CRUD `api.php:388-396` | — | Keep |
| Attendee database | `CONFIRMED` | 80 | `attendees` table; `api.php:425-432`; `AttendeesExport` | Order-bound; single `checked_in_at` scalar | Extend w/ `person_id` |
| Multi-tenancy | `PARTIAL` | 60 | `accounts`, `account_users`, `organizers` | `UNVERIFIED` isolation rigour — no automated cross-tenant test found | Audit (`08`) |
| Roles / permissions | `PARTIAL` | 25 | `Enums/Role.php` — exactly `SUPERADMIN`, `ADMIN`, `ORGANIZER` | No granularity, no per-event roles, no scanner role, no delegation | Replace (`09`) |
| Webhooks | `CONFIRMED` | 85 | `webhooks`, `webhook_logs`; `api.php:503-508`, `342-347`; `DomainEventType` | — | Keep |
| Public API | `MISSING` | 5 | `personal_access_tokens` table exists (2020 baseline); `PersonalAccessTokenDomainObject` exists | **Orphaned** — no `HasApiTokens`, no `createToken()` usage, no token routes. Dead code. | New (`48`) |
| Admin platform | `CONFIRMED` | 80 | 14 admin routes; impersonation `api.php:561`; failed-jobs UI | — | Keep |
| Audit logging | `PARTIAL` | 50 | `order_audit_logs`, `event_logs` | Not universal | Extend (`67`) |
| Zones | `MISSING` | 0 | Searched: `zone` matches only *time*zone | — | New (`25`) |
| Seating | `MISSING` | 0 | No `seat` entity | — | New (`26`) |
| VIP/media accreditation | `MISSING` | 0 | No application/approval entity for people | — | New (`23`) |
| CRM integration | `MISSING` | 0 | No HubSpot/Salesforce/Zapier code | — | New (`47`) |

### 2. Registration & ticketing — the strong half

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Branded event pages | `CONFIRMED` | 90 | `HomepageDesigner`, `homepage_theme_settings`, public routes | Keep |
| Custom questions | `CONFIRMED` | 90 | `questions`, `question_answers`, 9 types in `QuestionTypeEnum` | Keep |
| Ticket categories/tiers | `CONFIRMED` | 90 | `product_categories`, `product_prices`, `ProductPriceType` incl. `TIERED` | Keep |
| Early bird | `CONFIRMED` | 85 | `TIERED` + sale windows + sequential tier release migration | Keep |
| Promo codes | `CONFIRMED` | 90 | `promo_codes`, `PromoCodeDiscountTypeEnum` | Keep |
| Group registration | `CONFIRMED` | 80 | `AttendeeDetailsCollectionMethod` = `PER_TICKET`\|`PER_ORDER` | Keep |
| Waitlist | `CONFIRMED` | 85 | `waitlist_entries`, offer/expiry mails | Extend for sessions |
| Capacity assignments | `CONFIRMED` | 85 | `capacity_assignments`, `product_capacity_assignments` | Keep |
| Recurring events | `CONFIRMED` | 85 | `event_occurrences` + RRULE, per-occurrence overrides | Keep |
| Payments | `PARTIAL` | 60 | `PaymentProviders` = **only** `STRIPE`, `OFFLINE` | `RazorpayOrderDomainObject` exists but **not in the enum** — dead code | Extend |
| Invoices / VAT | `CONFIRMED` | 85 | `invoices`, `GenerateOrderInvoicePDFService`, VAT settings | Keep |
| Embeddable widget | `CONFIRMED` | 85 | `embed/widget.js` (440 lines, Shadow DOM, `inert`, origin-checked postMessage) | Keep |
| Affiliates | `CONFIRMED` | 80 | `affiliates`, export | Keep |
| RSVP as distinct flow | `MISSING` | 0 | Free tickets exist, but same checkout pipeline | New (`12`) |

### 3. Messaging

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Transactional email | `CONFIRMED` | 90 | 30 mailables; `BaseMail` queued + `afterCommit()` | Keep |
| Bulk email | `CONFIRMED` | 75 | `messages`, `outgoing_messages`, 5 targets in `MessageTypeEnum` | Keep |
| Email templates | `CONFIRMED` | 80 | `email_templates`, `EmailTemplateEngine`, Liquid | Keep |
| Scheduled sends | `CONFIRMED` | 75 | `add_scheduled_at_to_messages_table` migration | Keep |
| Segmentation | `PARTIAL` | 20 | Only 5 fixed audiences | No filter builder | New (`42`) |
| Campaign automation | `MISSING` | 0 | No drips, no open/click tracking | New (`42`) |
| SMS | `MISSING` | 0 | No Twilio/Vonage/provider anywhere | New (`43`) |
| Push | `MISSING` | 0 | No FCM/web-push/service worker | New (`44`) |
| In-app notifications | `PARTIAL` | 40 | `announcements` target **platform users**, not attendees | Extend (`69`) |

### 4. Check-in & badges — where it breaks down

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Check-in lists | `CONFIRMED` | 80 | `check_in_lists`, `product_check_in_lists`, activate/expire windows | Keep → migrate |
| QR scanning | `CONFIRMED` | 70 | `CheckIn/` = 17 files / 3,752 LOC; camera (`qr-scanner`) + USB/HID keypress wedge | Extend |
| Staff scanner route | `PARTIAL` | 50 | `/check-in/:checkInListShortId`, token-in-URL public endpoints | **No operator identity** (below) | Replace auth |
| Offline check-in | `MISSING` | **0** | No SW, no IndexedDB, no outbox, `networkMode:"always"` | **Scans are lost** (below) | New (`71`) |
| Kiosk / self-service | `MISSING` | 0 | Scanner is staff-operated | New (`19`) |
| Walk-in at door | `PARTIAL` | 30 | `CreateAttendeeAction` + `override_capacity` + `OFFLINE` payment — but admin backoffice only | Extend (`17`) |
| Queue management | `MISSING` | 5 | `StatsTab` computes a 5-min throughput metric only | New (`20`) |
| Badge printing | `MISSING` | 0 | Prints **tickets** via `window.print()` + `@media print` | New (`21`,`22`) |
| Photo capture | `MISSING` | 0 | No camera capture outside QR | New |
| Duplicate prevention | `CONFIRMED` | 60 | UNIQUE index + in-memory guard | Over-strict (below) | Replace |

### 5. Access control

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Product-scoped check-in | `CONFIRMED` | 60 | `product_check_in_lists` + list time windows | Keep as compile target |
| Zone rules | `MISSING` | 0 | No zone entity | New (`24`,`25`) |
| Re-entry / anti-passback | `MISSING` | **0** | **Actively prevented** by a UNIQUE index (below) | New table required |
| Access logs | `MISSING` | 10 | `attendee_check_ins` exists but check-out is a **soft-delete**, destroying history | New (`24`) |
| RFID / NFC | `MISSING` | 0 | Zero hits | New (`36`) |
| Face recognition | `MISSING` | 0 | Zero hits | Assess (`133`) |

### 6–7. Exhibitors, programme, attendee app

All `MISSING` at 0%. `CONFIRMED`: no exhibitor, sponsor, booth, session, track, speaker, or agenda
entity. The only string matches are demo seeders (`Console/Commands/Demo/ConferenceDemoEvent.php`).

`CONFIRMED`: no PWA — `frontend/vite.config.ts` plugins are exactly `react()`, `lingui()`,
`copy()`. No service worker, no Workbox, no IndexedDB. `site.webmanifest` exists with
`display: standalone`, making the app **installable but blank without network** — this must not be
mistaken for offline support.

`CONFIRMED`: `event_occurrences` is RRULE event-repeat, **not** sessions. See `27-sessions-tracks.md`.

### 8. Analytics & reporting

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Sales reports | `CONFIRMED` | 75 | `ReportTypes` = `product_sales`, `daily_sales_report`, `promo_codes_report`, `occurrence_summary` | Keep |
| Export CSV/Excel | `CONFIRMED` | 85 | `app/Exports/` — attendees, orders, answers, promos, affiliates | Keep |
| Check-in stats | `CONFIRMED` | 60 | `GetCheckInListStatsPublicAction`, `StatsTab` | Extend |
| Live attendance | `PARTIAL` | 30 | Per-list only; no unified view; **no realtime transport** | New (`53`) |
| Session attendance | `MISSING` | 0 | No sessions | New |
| Demographics | `MISSING` | 0 | No demographic model | New |
| Exhibitor leads | `MISSING` | 0 | — | New (`33`) |

### Platform infrastructure

| Capability | Exists | % | Evidence | Action |
|---|---|---|---|---|
| Queues | `CONFIRMED` | 70 | 27 jobs; prod supervisord runs `queue:work` | Env skew (below) | Fix |
| Realtime | `MISSING` | **0** | `config/broadcasting.php` is Laravel's **unmodified stub**; no `BROADCAST_CONNECTION`; no echo/pusher client | New (`71`) |
| Observability | `PARTIAL` | 50 | Sentry backend + frontend (`instrument.mjs`) | No metrics, no tracing | Extend (`78`) |
| Backend tests | `CONFIRMED` | 75 | 1,215 unit; CI matrix PHP 8.3/8.4/8.5 | Keep |
| E2E tests | `CONFIRMED` | 70 | 73 specs; worker-scoped fixtures; API seeding | Check-in = **1 spec** | Extend |
| Frontend tests | `MISSING` | **0** | No runner in `package.json`; no lint/typecheck workflow found (`UNVERIFIED`) | New (`79`) |
| CI/CD | `CONFIRMED` | 75 | 5 workflows; Vapor + DigitalOcean deploy | Keep |

---

## Critical findings

Ordered by operational severity. The first is a live bug, not a gap.

### F1 — Offline scans are silently lost, and retry is blocked · `CONFIRMED` · **FIXED (ARZ-001)**

Two defects compound in `frontend/src/components/layouts/CheckIn/index.tsx`:

```
L388:  processedBarcodesRef.current.add(attendeePublicId);   // marked processed
L393:  await handleCheckInAction(attendee, "check-in");      // ...before the await
```

```
L261-268:  onError: (error) => {
             recordScan(attendee, attendee.public_id, "error");
             if (!networkStatus.online) { showError(t`You are offline`); return; }
```

On network failure the check-in is **not queued, not retried, not persisted** — the only trace is
an in-memory `RecentScan`, capped at 20 and destroyed on reload. Because the barcode was added to
the dedupe set *before* the await, re-scanning the same ticket within the guard window is rejected
as "just scanned" rather than retried.

**Impact:** at a real event, a wifi blip means attendees are admitted but unrecorded, and staff
cannot fix it by re-scanning. **Severity: high. This warrants a fix ahead of the roadmap** — the
ordering swap plus a retry path is small, independent of the space/time work, and reduces live
risk. See `136-master-backlog.md` P0.

### F2 — The schema forbids re-entry · `CONFIRMED`

```sql
CREATE UNIQUE INDEX attendee_check_ins_unique_attendee_list
  ON attendee_check_ins (attendee_id, check_in_list_id) WHERE deleted_at IS NULL;
```

Plus `attendees.checked_in_at` is a single scalar, and check-out is implemented as a **soft-delete**
of the check-in row (`api.php:661`) — destroying the history an access log exists to preserve.

Re-entry, anti-passback, and multi-point access are **structurally prevented**. Access control
must introduce a new append-only `access_logs` table; it cannot extend this one.

### F3 — Scanner has no operator identity · `CONFIRMED`

`/check-in/:checkInListShortId` authenticates on the **URL short ID alone**. No PIN, no device
pairing, no staff identity, no per-scan attribution. `client.ts`'s `ALLOWED_UNAUTHENTICATED_PATHS`
includes `check-in`, so 401/403 won't redirect.

Anyone with the link can check anyone in, and the log cannot say who did it. Convenient for
handing a link to volunteers; unacceptable for accredited access control.

### F4 — No realtime transport · `CONFIRMED`

`broadcasting.php` is the stock stub; no driver configured; no client library. The command center
(`53`) is blocked on new infrastructure, not new queries.

### F5 — SSR query client is a cross-request singleton · `CONFIRMED` · **FIXED (ARZ-002)**

`frontend/src/utilites/ssrQueryClient.ts` holds a module-scope `let`, set in
`entry.server.tsx:43`, nulled at L75, across an `await`ed render. Under concurrency, request B
overwrites request A's client — a **cross-request cache-bleed risk**, i.e. potential data leakage
between users. Needs verification under load and likely `AsyncLocalStorage`.
Logged in `120-risk-register.md` and `121-technical-debt.md`.

### F6 — `define: {"process.env": process.env}` is unnecessary and misleading · `CONFIRMED` (corrected 2026-09-28)

`frontend/vite.config.ts` carried `define: { "process.env": process.env }`.

**Corrected finding.** An earlier revision of this audit claimed this shipped the entire build
environment into the client bundle. **Empirical testing disproved that.** A canary secret
(`ARZO_CANARY_SECRET`) present at build time did **not** appear in the bundle under either the old or
the new config: Rollup tree-shakes `process.env` keys the code never references.

What testing did establish:

1. Only env keys the code **actually reads** are inlined — and for `VITE_*` keys that happens via
   **Vite's own `import.meta.env` handling** (`src/utilites/config.ts` reads
   `import.meta.env.VITE_*` on 23 lines), not via the `define`. Removing the `define` does **not**
   stop it, which was verified directly.
2. `VITE_API_URL_SERVER` — an internal Docker hostname — is therefore present in the client bundle
   by design. It is overridden at runtime by `window.hievents` (`server.js:170`), so it is a stale
   default rather than a live secret, but it is mild internal-topology disclosure.

**Actual severity: low**, not medium-high. The `define` was removed anyway — it is redundant given
the runtime `window.hievents` mechanism, and a blanket `process.env` define is a footgun that would
leak any key a future code path happened to reference. But it was not the vulnerability originally
described.

**Residual item:** ensure no secret-bearing variable is ever given a `VITE_` prefix, since that
prefix means "safe to ship to the browser". Worth a CI check on the env contract.

### F7 — Dev/prod queue divergence · `CONFIRMED` · **PARTLY FIXED (ARZ-008)** — queue names aligned and CI/e2e Postgres moved to 17; the dev Postgres pin stays at 15 pending a dump/restore

- Dev: worker + scheduler are **un-supervised `docker exec -d`** processes started by an interactive prompt in `start-dev.sh`; a container restart silently kills them.
- Prod: supervisord runs `queue:work --queue=default,webhook-queue` — **omitting the `occurrences` queue** that dev runs.
- E2E stack: **no worker at all**.
- Postgres **17** in all-in-one prod vs **15** in dev/e2e/CI.

Queued work therefore behaves differently in all three environments.

### F8 — Printing cannot support badges · `CONFIRMED`

All printing is `window.print()` behind a 500 ms timeout, with `@media print` CSS in 3 files. No
page-size control beyond one `@page` rule, no print-dialog bypass, no label/ESC-POS path, no
server-side PDF endpoint. `@react-pdf/renderer` is a **production dependency with zero imports**.

Badge-on-demand at a door requires a real print pipeline (`22-badge-design-printing.md`).

### F9 — No frontend test safety net · `CONFIRMED` · **PARTLY FIXED (ARZ-007)** — vitest plus a CI workflow added with 24 tests; component and check-in E2E coverage still thin

Zero frontend unit tests, no runner. Check-in — the most operationally critical surface — has
**one** E2E spec covering the search path only. Nothing exercises QR, the USB wedge, the dedupe
guard, or network failure.


### F10 — The check-in domain is bifurcated · `CONFIRMED`

Two independent write models for "checked in", of unequal quality:

| | Path A — dashboard | Path B — public scanner |
|---|---|---|
| Route | `POST /events/{id}/attendees/{pid}/check_in` | `POST /public/check-in-lists/{uuid}/check-ins` |
| Handler | `CheckInAttendeeHandler` | `CreateAttendeeCheckInPublicHandler` |
| Domain service | **none** — all logic inline in the handler | `CreateAttendeeCheckInService` |
| Writes | `attendees.checked_in_at/_by` columns | `attendee_check_ins` **rows** |
| Transaction | no | yes |
| Domain event | **none → no `checkin.created` webhook** | yes |

Checking someone in via the dashboard and via the scanner produces **different data** and only one
fires webhooks. Any access-control or attendance work must consolidate these first, or it inherits
two sources of truth. `CONFIRMED`: `grep -c DomainEventDispatcher CheckInAttendeeHandler.php` → 0.

### F11 — Tenant id is static mutable state on a model · `CONFIRMED` · **GUARDED (ARZ-006)** — a 20-case cross-tenant suite now proves the boundary; the global scope and request-scoped tenant are still Phase 1

`backend/app/Models/User.php:43` — `protected static ?int $currentAccountId`, set by
`SetAccountContext` middleware from the JWT, read by `User::currentAccount()` / `currentAccountUser()`.

Two consequences: in a queue worker, console command, or any persistent process (Octane/Swoole),
the value survives across units of work — so a job can read a **stale tenant**; and when unset it
throws `RuntimeException`, i.e. a 500 rather than a deny.

Compounding this, **there are no Eloquent global scopes** (`grep addGlobalScope app/Models/` → 0) and
descendant resources are scoped by **parent only**: `GetProductsHandler` filters
`findByEventId($eventId)` with no `account_id`. Isolation rests entirely on the Action having called
`isActionAuthorized` first. **One omitted call at the Action layer is a full cross-tenant read**, and
there is no defence in depth. `UNVERIFIED`: no cross-tenant test exists to prove the 159 call sites
are complete.

### F12 — Authorization is imperative with no declarative layer · `CONFIRMED` · **GUARDED (ARZ-005)** — a CI test now asserts every action authorizes; the declarative policy layer is still Phase 1

`app/Providers/AuthServiceProvider.php` has `$policies = []` and an empty `boot()`; no `app/Policies/`
exists. Authorization is 159 hand-written `isActionAuthorized()` calls plus 50 `minimumAllowedRole()`.

Worse, `IsAuthorizedService::validateUserRole()` has **no branch for `Role::ORGANIZER`** — the default
`$minimumRole`. So the role gate is a **no-op** for most endpoints, which rely solely on account
ownership. And the entity `match` has **no default arm**: an unlisted domain object throws
`UnhandledMatchError` → 500, not 403. Only 6 entity types are expressible.

The `/admin` route group carries only `auth:api` — SUPERADMIN is enforced **per action**, so a new
admin action that forgets `minimumAllowedRole()` is exposed to any authenticated user.

`CONFIRMED`: only **7 of 268 Actions** have a Feature test. The authorization layer is effectively
untested.

### F13 — `occurrences` queue is unconsumed in production · `CONFIRMED` · **FIXED (ARZ-008)**

Four jobs route to it — `GenerateOccurrencesJob`, `BulkCancelOccurrencesJob`,
`RefundOccurrenceOrdersJob`, `SendOccurrenceCancellationEmailJob` — via
`onQueue(config('queue.occurrences_queue_name'))`.

```
config/queue.php:6   'occurrences_queue_name' => env('OCCURRENCES_QUEUE_NAME'),   # no default
prod supervisor      queue:work --queue=default,webhook-queue                    # no occurrences
dev start-dev.sh     queue:work --queue=default,webhook-queue,occurrences
```

With the env unset it is `null` → jobs land on `default` → **works by accident**. Setting
`OCCURRENCES_QUEUE_NAME` in production silently strands all recurring-event work. A latent bug armed
by a config change.

Related: `config/queue.php:4` defaults `webhook_queue_name` to `env('QUEUE_CONNECTION', 'sync')` —
conflating a *queue name* with a *connection name*, so by default webhooks are pushed to a queue
literally named `sync`, which no worker consumes.

### F14 — Webhook payloads are coupled to API resources · `CONFIRMED`

`WebhookDispatchService` serializes using the same `JsonResource` classes the REST API returns
(`OrderResource`, `AttendeeResource`, …). **Any REST response refactor is silently a webhook
breaking change** for every integrator. Needs a versioned payload boundary before the API platform
ships (`48-api-platform.md`).

Worth preserving: `SecureCallWebhookJob` is the best-engineered infrastructure in the codebase —
manual redirect following with per-hop revalidation and `CURLOPT_RESOLVE` DNS pinning to defeat
SSRF/DNS-rebinding. It appears **untested** (`UNVERIFIED`), which is the gap.

### F15 — `BaseRepository` is a stateful builder · `CONFIRMED`

632 lines. `applyConditions()` / `loadRelation()` **reassign `$this->model`**, so a repository is a
mutable query builder, not a stateless gateway. Correctness depends on every method wrapping its body
in `runQuery()`, whose `finally` calls `resetState()`.

`resetModel()` is marked `@deprecated … kept for backwards compatibility with subclass repositories
that build custom queries on $this->model` — direct evidence that subclasses do exactly that.
`UNVERIFIED` and worth a targeted pass: which of the 59 subclasses build custom queries **without**
`runQuery()`. That is the highest-leverage latent-bug area in the backend.

Also note two hydration paths that disagree: `BaseRepository::hydrateDomainObjectFromModel()`
(setter-driven, type-coercing) and `AbstractDomainObject::hydrate*()` (direct property assignment,
bypassing setters). `BaseRepository:548` carries `@todo use hydrate method from AbstractDomainObject`.

---

## What is genuinely strong

Worth stating, because a gap-focused audit can read as dismissive of good work:

- **Layered architecture** — Action → Handler → Service → Repository, consistently applied across 268/201/193 classes
- **Commerce correctness** — tax/VAT, invoices, refunds, audit logs, idempotent payment handling
- **E2E discipline** — 73 specs, worker-scoped fixtures, API-seeded data, assertions kept out of page objects
- **Runtime env injection** — `window.hievents` means one image across environments
- **Widget engineering** — Shadow DOM, `inert` siblings, origin-checked postMessage, payment-return resume
- **Theming** — real WCAG 2.1 contrast math with organizer guardrails (surfaces/text not customizable)
- **i18n** — 20 locales, Lingui throughout
- **Route-level code splitting** — every one of 88 routes lazy-loaded

## Related

`03-gap-analysis.md` · `110-evento-comparison.md` · `120-risk-register.md` ·
`121-technical-debt.md` · `136-master-backlog.md` · `128-definition-of-done.md`
