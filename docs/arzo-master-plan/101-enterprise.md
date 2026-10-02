# Enterprise Requirements

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Build on demand · **Priority:** P3 (ARZ-230, ARZ-231) · **Phase:** after ARZ-011/012; triggered by a named prospect
**Depends on:** `09-permissions-and-roles.md`, `100-saas-tenancy.md`, `64-security.md`, `67-audit-logging.md`
**Blocks:** nothing directly

---

What a large client's procurement, IT and security teams will ask for — SSO, provisioning, audit,
residency, an SLA — and when to build each. The stance is **build on demand**: every item is
expensive, most are small only once their foundation exists, and none has a named buyer today. The
exception is MFA, which is baseline hygiene, not an enterprise feature.

## Current state — `MISSING` for every enterprise control, ~10%

| Capability | State | Evidence |
|---|---|---|
| SSO — SAML, OIDC | `MISSING` | No saml, oidc, openid or socialite in `composer.json`, `config/` or `app/` |
| SCIM provisioning | `MISSING` | Same search |
| MFA | `MISSING` | Search for totp, two_factor, webauthn, passkey in backend and frontend: nothing |
| Session token | `CONFIRMED` | JWT, **7-day TTL** (`config/jwt.php:95`); blacklist on, so logout invalidates (`:214`) |
| Claims | `CONFIRMED` | `account_id` and `role` added at login (`LoginService.php:107-116`); `SetAccountContext.php:14` reads `account_id` |
| Role gates | `CONFIRMED` | Read `account_users.role` **per request**, not the token (`BaseAction.php:214-219` → `IsAuthorizedService.php:36-46`). The token's `role` is used only for the super-admin preview of non-public events (`BasePublicEventAction.php:25`). |
| Membership status | `CONFIRMED` | Re-read on every authorized call; non-ACTIVE users are logged out (`08`) |
| Token refresh | `PARTIAL` | The server has `POST /auth/refresh` (`api.php:297`); the client's `refreshAccessTokenFn` issues a **GET** and has **no caller** (`auth.client.ts:12-13`). Sessions hard-expire after seven days. |
| Audit trail | `PARTIAL` | `order_audit_logs` has no actor column; `event_logs` is never written; **no audit read or export endpoint** (`api.php` has no audit route) |
| Impersonation audit | `CONFIRMED` | `LogImpersonationMiddleware.php:24-36` |
| Health check | Static | `/up` returns `{"status":"ok"}` without checking the database, Redis or queues (`routes/web.php:20-22`) |
| Data residency | `UNVERIFIED` | **No ARZO deployment is evidenced.** `vapor.yml` and `deploy.yml` describe upstream Hi.Events (eu-west-1, `api.hi.events`), not ARZO (`98`) |
| SLA, support model, on-call | `MISSING` | `76` is a scaffold; no commitment documented |

### Correction to `09` and `64`

`09` and `64` S6 say a role change does not take effect until the token is refreshed. For **role
gates** that is not so: `minimumAllowedRole` and `isActionAuthorized` read the membership row on every
request. What is genuinely stale is the `account_id` claim (switching accounts needs a new token) and
the `role` claim's one consumer. And a refresh endpoint exists; the client never calls it.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| EN1 | Client refresh helper uses the wrong verb and is dead code | `auth.client.ts:12-13`; `api.php:297` | Low — remove, or wire it when sessions shorten |
| EN2 | A demoted super-admin keeps the token's `role` claim for up to seven days, and the public event endpoint trusts it to show non-public events | `BasePublicEventAction.php:25`; `config/jwt.php:95` | Low |
| EN3 | Audit rows carry no actor; nothing can export an audit trail | `order_audit_logs` (live `\d`); `67` | Medium — the first enterprise questionnaire asks |
| EN4 | The health check reports healthy with a dead database | `routes/web.php:20-22`; `76` | Medium — an SLA cannot be measured on it |

## Decision: build on demand, in dependency order

The trigger for each item is **a named prospect with a signed or near-signed contract that requires
it**. Speculative enterprise work is the classic way a small team spends a quarter on features nobody
uses. What *is* done ahead of demand is the foundation each item stands on — because that foundation
is needed anyway.

| Requirement | Foundation first | Trigger | Size |
|---|---|---|---|
| **MFA (TOTP)** | — | Before any external SaaS (`98` gate 5) — **not** on demand | M |
| Session controls: short access token + refresh | EN1 | With SSO, or any client security review | S |
| **SSO — OIDC first, SAML second** (ARZ-230) | RBAC and per-event roles (ARZ-011, ARZ-012) | Named prospect | L |
| **SCIM** (ARZ-231) | SSO | Prospect with many users and joiner/leaver churn | M |
| Audit trail and export | Actor columns, one audit log (`67`) | First government or enterprise contract | M |
| Data residency | ARZO infrastructure (`84`) | Client or regulator requirement | Depends on hosting |
| Dedicated environment | Infrastructure as code; the all-in-one image proves feasibility | A contract priced to cover it | L, recurring |
| SLA | Real health checks, monitoring, on-call (`76`, `86`) | First contract with a penalty clause | Process |
| Security questionnaire, pen-test report | `124` | First procurement | Business |
| DPA and sub-processor list | `65` | First procurement | Legal |

### Why SSO waits for RBAC

An identity provider asserts **who** someone is and which **groups** they belong to. The value of SSO
is mapping those groups onto what people may do. With three roles — and the default one checking
nothing (`09`) — every mapping grants either the whole account or nothing. SSO before RBAC ships a
login button, not access control.

### Why OIDC before SAML

OIDC is JSON and HTTPS, handled by maintained libraries; SAML is XML signatures, where the
implementation bugs are security bugs. Most modern identity providers speak both. Build SAML when a
client's IdP cannot do OIDC — `UNVERIFIED` which IdPs ARZO's likely clients run.

## SSO model

```
identity_providers
  id, account_id → accounts,
  protocol,                -- OIDC | SAML
  issuer, client_id NULL, client_secret_encrypted NULL,
  saml_metadata_xml NULL,
  verified_domains jsonb,  -- proven by DNS TXT before enforcement
  enforce_sso bool,        -- password login refused for this account
  jit_provisioning bool, default_role_id → roles,
  timestamps
identity_provider_role_mappings
  identity_provider_id, external_group, role_id → roles, event_id NULL → events
user_identities
  id, user_id → users, identity_provider_id, subject, last_login_at
  UNIQUE (identity_provider_id, subject)
```

- **SSO mints the same JWT.** No second session system; the refresh flow (EN1) is what lets an IdP
  logout end an ARZO session within minutes rather than seven days.
- **Enforcement is per account, at token issuance.** Users are global and can belong to several
  accounts; `LoginService` already chooses the account before minting the token, which is the natural
  point to refuse a password login for an SSO-enforced account.
- **Domain proof before enforcement** — otherwise one tenant could claim `gmail.com`.
- SSO users have no password, which makes the invitation password-overwrite defect
  (`AcceptInvitationHandler.php:60-72`) worse, not moot. Fix it first.

## Audit export

Enterprise and government clients ask two questions: *who did this?* and *can you prove the log was
not edited?* The first needs actor columns everywhere (`67`). The second needs append-only storage and
a hash chain or signed daily digest. Export is CSV plus an API endpoint, filtered by date, actor and
entity, carried through the same authorization as everything else (`09`).

## Data residency

Residency is a property of **every** store, not of the database alone:

| Store | Today |
|---|---|
| Database, cache, file storage, backups | Wherever ARZO deploys — undecided |
| Error tracking (Sentry) | Vendor region, `UNVERIFIED` |
| Email provider | smtp default; ses, mailgun, postmark available |
| AI provider (Anthropic, spam check, SaaS mode only) | Vendor region, `UNVERIFIED` |
| Payment processor | Undecided (`98`) |

Whether Qatar's data-protection law restricts transfers of attendee data abroad, and whether
government clients require in-country hosting, are `UNVERIFIED` — legal. Decide the hosting region
with that answer, not before it.

## SLA structure — tied to `76`

An SLA ARZO cannot measure is a liability, not a promise. The structure:

| Component | Commitment type | Measured by |
|---|---|---|
| Platform availability — ticketing, admin | Monthly percentage, excluding announced maintenance | External probes against real health checks (EN4) |
| **Event-day door continuity** | "Doors keep operating with the network down" — a capability commitment, not a percentage | The offline drill evidenced at readiness (`59`, `71`) |
| Support response | Time to first response by severity; **on-site or on-call cover during the contracted event window** | Ticket timestamps; rota (`57`) |
| Data recovery | RPO and RTO | `77`, `126` restore tests |
| Credits | Per breached component | Contract |

Numbers are deliberately absent: there is no measured baseline, no monitoring (`86`) and no on-call
rota. The event-day row is where ARZO can promise more than a ticketing vendor, because offline-first
makes the promise true by design rather than by uptime.

## Dedicated environments — only when priced

The all-in-one image shows a single-tenant deployment is technically easy. Operationally it
multiplies upgrades, monitoring and incident response per client. **No**, unless the contract pays
for the recurring operation and ARZO has infrastructure as code to stamp and upgrade environments
identically. A government client requiring in-country hosting is the likeliest reason to say yes.

## Open questions

- **Is there a named enterprise prospect?** If not, nothing here is scheduled except MFA and EN1–EN4.
- **Which identity providers do likely clients run?** Decides whether SAML is ever needed.
- **Does any client require tamper-evident audit logs?** Government clients may (`64`); it changes `67`'s storage design.
- **Is in-country hosting required for Qatar government events?** `UNVERIFIED` — legal; it may force a dedicated environment regardless of price.
- **Does ARZO staff on-call for event days exist as a function?** An SLA without it is aspirational (`76`).

## Related

`09-permissions-and-roles.md` · `08-multi-tenancy.md` · `64-security.md` · `67-audit-logging.md` ·
`65-privacy-gdpr.md` · `76-reliability.md` · `77-disaster-recovery.md` · `84-infrastructure.md` ·
`86-monitoring.md` · `98-commercial-model.md` · `100-saas-tenancy.md` · `124-security-testing-plan.md`
