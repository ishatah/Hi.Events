# API Platform

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for the public API · **Audit date:** 2026-09-28
**Classification:** New subsystem
**Blocks:** `40-device-management.md`, `47-crm-integrations.md`, `50-third-party-integrations.md`

---

## Current state — `MISSING`, 5%

### The orphaned token infrastructure

The brief asked specifically about this. Findings, all `CONFIRMED`:

| Question | Answer |
|---|---|
| Does the table exist? | **Yes** — `personal_access_tokens`, created by the 2020 baseline (`schema.sql:11`), with a unique token index and a `tokenable_type/tokenable_id` index |
| Does a migration exist? | Yes, via `2020_01_25_113926_initial_db.php` loading `schema.sql` |
| Is there a domain object? | Yes — `PersonalAccessTokenDomainObject` + a Generated abstract |
| Does token creation exist? | **No** — zero `createToken()` calls, no `HasApiTokens` trait anywhere |
| Does token authentication exist? | **No** — no Sanctum guard in use for the API |
| Are there token routes? | **No** |
| Are there scopes? | **No** |
| Is there documentation? | Not for tokens |

**Conclusion:** Laravel's Sanctum scaffolding was inherited and never wired up. The table has
existed unused since 2020. It is dead infrastructure, not a partial implementation — but it does
mean the migration work is already done.

### What exists instead

`CONFIRMED`: 266 endpoints, every one either `auth:api` (a **JWT user session**) or `/public/*`
(unauthenticated storefront, with capability-URL secrets for check-in lists and ticket lookup).

There is **no machine-to-machine authentication path**. A third party cannot integrate without
impersonating a user session, which is why CRM integration (`47`) is blocked.

### What is genuinely good

OpenAPI generation is strong and should be built on, not replaced. `CONFIRMED`:
`dedoc/scramble` with 5 custom extensions, plus `OpenApiGenerationTest.php` — a real **contract
test** asserting the spec version, that `info.version` matches the `VERSION` file, that `/admin` and
sitemap paths are **absent** (a leak guard), that every `/public/*` summary is marked, and that the
JWT security scheme is exact.

## Target

Three principal types, one authorization model (`09`).

| Principal | Credential | Typical scopes |
|---|---|---|
| User | JWT (existing) | Role + per-event permissions |
| **API key** | `Authorization: Bearer arzo_...` | Explicit list |
| **Device** | Device key (`40`) | Usually `device.submit_scan` only |

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
`Authorization: Bearer <key>` with a `arzo_` prefix so keys are recognizable in logs and in leaked
secrets scanning. Resolve by `key_prefix`, verify the hash, check expiry, revocation, and IP
allowlist.

### Scopes
Reuse the permission strings from `09` rather than inventing a parallel vocabulary. A key cannot hold
a scope its creator lacks — otherwise key creation becomes privilege escalation.

### Versioning
URL-based: `/api/v1/...`. The current unversioned `/api/...` remains as an alias to v1 so nothing
breaks. The Scramble description already warns the API is unversioned and subject to change — this
closes that.

### Rate limiting
Per key, with headers (`X-RateLimit-Limit`, `-Remaining`, `-Reset`) and `429` with `Retry-After`.
Distinct default tiers for read, write, and bulk export.

### Pagination and filtering
`CONFIRMED`: the pattern already exists — `applyFilterFields()` with an allowlist and operators
`eq/ne/lt/lte/gt/gte/like/in`, plus `MAX_PAGINATE_LIMIT`. Expose it consistently rather than
designing something new.

### Idempotency
`Idempotency-Key` header on POST, stored with the response for a retention window. Required for
order creation and scan submission — the two places a retry must not double-act.

### Webhook payload decoupling — F14
`CONFIRMED`: webhooks currently serialize with the same `JsonResource` classes the REST API returns.
**Any REST refactor is silently a breaking change for every integrator.**

Fix: dedicated versioned payload transformers, so API responses and webhook payloads can evolve
independently. This must land with the API platform, not after.

### Signatures and retries
Keep the existing spatie signing and exponential backoff. **Preserve and test**
`SecureCallWebhookJob`'s SSRF defences — manual redirect following with per-hop revalidation and
`CURLOPT_RESOLVE` DNS pinning. It is the best-engineered infrastructure in the codebase and appears
untested (`UNVERIFIED`).

### API logs
Request id, key, endpoint, status, duration, retained on a defined window. Needed for support ("my
integration broke") and for abuse investigation.

### Developer portal and sandbox
Scramble already generates the spec; the portal is documentation plus key management plus examples.
A sandbox needs isolated data — `UNVERIFIED` whether that is a separate tenant or a separate
environment. Decide before promising it.

## Migration

| Step | Change |
|---|---|
| 1 | Create `api_keys`; management UI under account settings |
| 2 | Key authentication guard alongside JWT |
| 3 | Scope enforcement via the `09` permission model |
| 4 | Introduce `/api/v1` prefix, alias unversioned |
| 5 | Rate limiting + headers |
| 6 | Idempotency support |
| 7 | Versioned webhook payload transformers (F14) |
| 8 | Device keys (`40`) |
| 9 | Decide the fate of `personal_access_tokens`: adopt it for `api_keys`, or drop it. Leaving it unused is misleading. |

## Open questions

- **Adopt `personal_access_tokens` or a new table?** Adopting means Sanctum conventions and a migration already in place; a new table means a schema shaped for our scopes. Leaning new, and dropping the old.
- **OAuth for third-party apps?** Only if there will be third-party apps acting on users' behalf. API keys cover integrations. Do not build OAuth speculatively.
- **Is the sandbox a separate environment or a flagged tenant?** Affects `84`.
- **Public rate-limit defaults** — need real usage data; start conservative and raise.

## Related

`09-permissions-and-roles.md` · `40-device-management.md` · `49-webhooks.md` ·
`47-crm-integrations.md` · `64-security.md` · `74-performance.md`
