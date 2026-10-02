# API Platform

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for the public API · **Audit date:** 2026-09-29 (re-verified; first written 2026-09-28)
**Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P1 (ARZ-090, ARZ-091, ARZ-092)
**Blocks:** `40-device-management.md`, `47-crm-integrations.md`, `50-third-party-integrations.md`

---

## Current state — `MISSING`, 5%

### The orphaned token infrastructure

The brief asked specifically about this. Findings, all `CONFIRMED`:

| Question | Answer |
|---|---|
| Does the table exist? | **Yes** — `personal_access_tokens`, created by the 2020 baseline (`schema.sql:11`), with a unique token index and a `tokenable_type/tokenable_id` index. 0 rows. |
| Is Sanctum installed? | **Yes** — `laravel/sanctum ^4.0` (4.3.2) and `config/sanctum.php` |
| Is there a domain object? | Yes — `PersonalAccessTokenDomainObject` + a Generated abstract |
| Does token creation exist? | **No** — zero `createToken()` calls, no `HasApiTokens` trait anywhere |
| Does token authentication exist? | **No** — `config/auth.php` defines only `web` (session) and `api` (JWT) guards |
| Who touches the table? | Only account deletion and anonymization (`AccountHardDeletionService.php:145`, `UserAnonymizer.php:37`) |
| Are there token routes or scopes? | **No** |

**Conclusion:** Laravel's Sanctum scaffolding was inherited and never wired up. It is dead
infrastructure, not a partial implementation.

### What exists instead

`CONFIRMED`: **266** route registrations (get 112, post 82, put 33, delete 31, patch 8), every one
either `auth:api` or `/public/*`. The JWT package is `php-open-source-saver/jwt-auth` 2.9.2 with a
**7-day TTL** (`config/jwt.php:95`).

There is **no machine-to-machine authentication path**. A third party cannot integrate without
impersonating a user session, which is why CRM integration (`47`) is blocked.

At audit time the working tree was adding routes for venues, zones, access points, access rules,
accreditation types, credentials, sessions, speakers, tracks and access scans — uncommitted, and
all under `auth:api`. The API is growing quickly while its only principal is still a user JWT, which
is the argument for landing API and device keys before the scan endpoint is used by hardware.

No versioning: the only prefix is Laravel's default `api`, and `config/scramble.php:68` describes the
API as "unversioned and likely to change".

### Rate limiting that already exists

Not mentioned in the first revision of this document, and worth building on:

| Limiter | Limit | Scope | Evidence |
|---|---|---|---|
| `api` — **global**, every route incl. `/public/*` | 180/min (`app.api_rate_limit_per_minute`) | User id, else IP | `RouteServiceProvider.php:27`; `Kernel.php` api group |
| `self-service-email`, `self-service-edit` | 20/hour | Order short id, else IP | `RouteServiceProvider.php:32,36` |
| Per-route | 5–60/min | Geo lookup, occurrences, organizer contact, waitlist, promo validation, ticket lookup | `api.php:354-677` |

Public order creation and completion, the Stripe webhook and the check-in endpoints have **only**
the global limit. `38` asks for a tighter per-route limit on check-in.

### Pagination and filtering

`CONFIRMED`: `applyFilterFields()` (`BaseRepository.php:475`) with an allowlist and operators
`eq/ne/lt/lte/gt/gte/like/in`; the literal `null` becomes `IS`/`IS NOT`; unknown fields are silently
ignored; `MAX_PAGINATE_LIMIT = 100` (`RepositoryInterface.php:29`).

**Defect:** an unknown operator throws `BadMethodCallException`, which surfaces as a 500 rather than
a 422 (`UNVERIFIED` at runtime). A public API must reject bad filters as client errors.

### Idempotency

`MISSING`. The only idempotency key in the codebase is the **outbound** one sent to Stripe on
refunds (`StripePaymentIntentRefundService.php:34`). No inbound header is honoured.

### What is genuinely good

OpenAPI generation is strong and should be built on, not replaced. `CONFIRMED`: `dedoc/scramble`
with 5 custom extensions, plus `OpenApiGenerationTest.php` — a real **contract test** asserting the
spec version, that `info.version` matches the `VERSION` file, that `/admin`, `/mail-test` and
sitemap paths are **absent** (a leak guard), that every `/public/*` summary is marked, that the JWT
security scheme is exact, and that core schemas and error responses are documented.

### New since the first revision

`devices` landed in `c34f6a59` with its own `api_key_prefix` and `api_key_hash` columns (`40`).
Device keys therefore live on the device row, not in `api_keys`. The two stores must share one
hashing scheme and one prefix convention.

## Target

Three principal types, one authorization model (`09`).

| Principal | Credential | Stored | Typical scopes |
|---|---|---|---|
| User | JWT (existing) | — | Role + per-event permissions |
| **API key** | `Authorization: Bearer arzo_...` | `api_keys` | Explicit list |
| **Device** | `Authorization: Bearer arzod_...` | `devices` (`40`) | Usually `device.submit_scan`, `device.sync` |

### `api_keys`

```
id, short_id, account_id, organizer_id NULL, event_id NULL,
name, key_prefix,               -- shown in UI for identification
key_hash,                       -- hashed; plaintext shown once at creation
scopes jsonb,                   -- array of permission strings
rate_limit_per_minute int NULL,
allowed_ips jsonb NULL,
expires_at NULL, last_used_at NULL,
created_by, revoked_at NULL, revoked_by NULL,
metadata jsonb, timestamps
INDEX (key_prefix), INDEX (account_id, revoked_at)
```

Scoping to an organizer or event matters: an integration for one event should not read the whole
account.

Keys are hashed. The plaintext is displayed once, never retrievable — and the UI must say so
clearly, because users will assume otherwise.

## Design

### Authentication
`Authorization: Bearer <key>` with a distinct prefix per principal type (`arzo_` for API keys,
`arzod_` for devices) so keys are recognizable in logs and in leaked-secret scanning, and so the
guard knows which store to consult. Resolve by prefix, verify the hash, check expiry, revocation, and
IP allowlist. The resolved principal lives in request-scoped context — not static state (F11).

### Scopes
Reuse the permission strings from `09` rather than inventing a parallel vocabulary; the
`permissions` table seeded in Phase 1 is that vocabulary. A key cannot hold a scope its creator lacks
— otherwise key creation becomes privilege escalation.

### Versioning
URL-based: `/api/v1/...`. The current unversioned `/api/...` remains as an alias to v1 so nothing
breaks, and the Scramble description's "unversioned" warning is retired.

### Rate limiting
Extend the existing named-limiter pattern with a per-key limiter keyed on the key id. Return
`X-RateLimit-Limit`, `-Remaining`, `-Reset`, and `429` with `Retry-After`. Distinct default tiers for
read, write, and bulk export.

### Pagination and filtering
Expose `applyFilterFields()` consistently, and convert unknown operators and fields into `422`
responses rather than a 500 and a silent ignore respectively.

### Idempotency
`Idempotency-Key` header on POST, stored with the response for a retention window. Required for
order creation and scan submission — the two places a retry must not double-act. Scan submission
from devices already carries `client_generated_id` (`24`); the header generalizes the same idea.

### Webhook payload decoupling — F14
`CONFIRMED`: `WebhookDispatchService` serializes with the REST `JsonResource` classes — Event,
Attendee, AttendeeCheckIn, EventOccurrence, Product, Order — and the envelope carries no version.
**Any REST refactor is silently a breaking change for every integrator.**

Fix: dedicated versioned payload transformers (`49`). This must land with the API platform, not
after.

### Signatures and retries — corrected
The first revision said "keep the existing exponential backoff". **The backoff does not run.**
`SecureCallWebhookJob` is dispatched with `dispatchSync()`, so its `tries = 3` and
`ExponentialBackoffStrategy` never take effect and the final-failure event never fires (`49`).
Keep the spatie signing; **make the delivery genuinely queued**; and preserve and test the SSRF
defences — manual redirect following with per-hop revalidation and `CURLOPT_RESOLVE` pinning. The
URL validator is tested; the job itself has **no test** (`CONFIRMED`).

### API logs
Request id, principal, endpoint, status, duration, retained on a defined window. Needed for support
("my integration broke") and for abuse investigation.

### Developer portal and sandbox
Scramble already generates the spec; the portal is documentation plus key management plus examples.
A sandbox needs isolated data — `UNVERIFIED` whether that is a separate tenant or a separate
environment. Decide before promising it.

## Migration

| Step | Change |
|---|---|
| 1 | Create `api_keys`; management UI under account settings |
| 2 | Key authentication guard alongside JWT, with a shared prefix-and-hash resolver for API and device keys |
| 3 | Scope enforcement via the `09` permission model |
| 4 | Introduce `/api/v1` prefix, alias unversioned |
| 5 | Per-key rate limiting + headers, on top of the existing global limiter |
| 6 | Idempotency support; `422` for invalid filters |
| 7 | Versioned webhook payload transformers (F14) and genuinely queued delivery (`49`) |
| 8 | Device keys on `devices` (`40`) |
| 9 | Drop `personal_access_tokens` and remove Sanctum. Leaving an unused auth package installed is misleading and widens the dependency surface. |

## Open questions

- **Adopt `personal_access_tokens` or a new table?** Resolved in favour of a new table: its schema is shaped for scopes, organizer/event scoping and IP allowlists, which Sanctum's is not. Step 9 removes the old one.
- **OAuth for third-party apps?** Only if there will be third-party apps acting on users' behalf. API keys cover integrations. Do not build OAuth speculatively.
- **Is the sandbox a separate environment or a flagged tenant?** Affects `84`.
- **Public rate-limit defaults** — need real usage data; start conservative and raise.
- **JWT TTL of 7 days** — long for a token carrying role and account (S6 in `64`). Revisit with RBAC (`09`).

## Related

`09-permissions-and-roles.md` · `40-device-management.md` · `49-webhooks.md` ·
`47-crm-integrations.md` · `50-third-party-integrations.md` · `64-security.md` · `74-performance.md`
