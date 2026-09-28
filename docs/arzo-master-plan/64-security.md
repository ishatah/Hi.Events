# Security Architecture

**Status:** WRITTEN · **Audit date:** 2026-09-28

---

## Current state — mixed, with two live risks

### Genuinely strong · `CONFIRMED`

- **Webhook SSRF defence.** `SecureCallWebhookJob` disables automatic redirects, follows up to 3 hops manually, revalidates the URL on **every hop**, and pins the resolved IP with `CURLOPT_RESOLVE` to defeat DNS rebinding. The best-engineered infrastructure in the codebase — and `UNVERIFIED`/untested.
- **Input sanitization.** `HtmlPurifierService` applied to user HTML before storage.
- **Concurrency safety.** Postgres advisory locks serialize checkout per event.
- **Session invalidation.** `validateUserStatus()` re-reads `account_users.status` on every authorized call and force-logs-out deactivated users.
- **Payment scope.** Stripe handles card data; ARZO never stores it.
- **Impersonation auditing.** Logged, and attached to Sentry scope.
- **Spam screening.** AI-assisted event screening with an admin queue.
- **Test database guard.** Enforced in `CreatesApplication`, unbypassable by forgetting a trait.
- **Privacy defaults.** Sentry `send_default_pii` off, `sql_bindings` off in breadcrumbs.

### Live risks

| # | Risk | Evidence |
|---|---|---|
| **S1** | **Tenancy by discipline.** No global scopes; child resources scoped by parent only; tenant id in static mutable state; 159 hand-written checks with no cross-tenant test. One omission is a cross-tenant read. | F11 |
| **S2** | **Authorization has no declarative layer.** No policies, no gates; the default `ORGANIZER` role gate is a **no-op**; the entity `match` has no default arm (500, not 403); `/admin` enforced per-action; only 7 of 268 Actions tested. | F12 |
| **S3** | **SSR cross-request state.** Module-level query client and axios auth defaults mutated across an `await` — possible data bleed between concurrent users. | F5 |
| **S4** | **Build env in the client bundle.** `define: {"process.env": process.env}` ships the entire build environment. | F6 |
| **S5** | **Scanner has no identity.** URL short ID is the only credential; no attribution. | F3 |
| **S6** | **No token refresh.** JWT carries `account_id` and `role`, so a role change is stale until re-login; expiry is a hard redirect. | audit §4 |

## Threat model

Actors and what they would try. Ordered by how much damage they do.

### T1 — Another tenant
**Wants:** attendee lists, revenue figures.
**Path:** guess an entity id on an endpoint that forgot to authorize.
**Today:** plausible — no second line of defence (S1, S2).
**Controls:** tenant global scope; policies; cross-tenant test suite; architecture test. All four.

### T2 — Gate-crasher
**Wants:** entry without a valid credential.
**Paths:** forged QR; cloned badge; a revoked badge at an offline door; passback to a friend.
**Controls:** opaque random credential identifiers, never sequential or derived from personal data (`23`); anti-passback (`24`); clone detection (`68`); deny-list propagation (`71`). **Residual:** an offline door can admit a recently revoked badge — stated, not hidden (R8).

### T3 — Device thief
**Wants:** the attendee roster on a stolen tablet.
**Today:** n/a — no offline storage exists.
**Future:** severe. A device carries names, photos, possibly ID numbers.
**Controls:** OS-keystore encryption at rest, mandatory; short-lived device keys, revocable per device; remote wipe (`40`). **This is the strongest argument for native scanner apps over a PWA** — a PWA cannot match the at-rest guarantee (`94`).

### T4 — Malicious organizer
**Wants:** use the platform to attack others.
**Paths:** webhook URL pointed at internal infrastructure (SSRF); spam via bulk messaging; scraping.
**Controls:** the existing SSRF defence (test it); messaging tiers and quotas; rate limits (`48`).

### T5 — Insider
**Wants:** issue themselves access, or read data outside their remit.
**Today:** any ORGANIZER can act on every event in the account, and the role gate is a no-op at that level.
**Controls:** per-event roles (`09`); audit trails on credential issue and access override (`67`); separation between who approves accreditation and who issues credentials.

### T6 — Credential-stuffing attacker
**Wants:** account takeover.
**Controls:** rate limiting on auth; MFA (**not present — `MISSING`**); breach-password checks. MFA should precede any external SaaS launch.

## Architecture

| Layer | Target |
|---|---|
| Authentication | JWT for users; API keys (`48`); device keys (`40`); **MFA to be added** |
| Authorization | Policies + per-event roles + scopes (`09`), three enforcement layers |
| Tenant isolation | Global scope + authorization + negative tests |
| Transport | TLS everywhere; HSTS; signed webhooks |
| At rest | DB encryption; encrypted device stores; ID documents and DOB encrypted at column level (`23`) |
| Secrets | Never in the client bundle (S4); not logged; rotated |
| Rate limiting | Per key, per IP, per endpoint class |
| Audit | Credential, access-override, badge and accreditation decisions (`67`) |
| Device trust | Enrolment, scoped keys, revocation, remote wipe |
| Monitoring | Auth failures, denial spikes, cross-tenant attempts, SSRF blocks |

## Priorities

1. **Phase 0:** S1, S2, S3, S4 — all four are live and cheap relative to impact.
2. **Phase 1:** RBAC (`09`) closes S5 and S6 structurally.
3. **Before external SaaS:** MFA, penetration test (`124`).
4. **Phase 4:** device security, encryption at rest, remote wipe.

## Open questions

- **MFA:** TOTP, or email OTP? TOTP is stronger; email is lower friction and already has the mail infrastructure.
- **Penetration test:** internal or external, and before which milestone?
- **Does any client require tamper-evident audit logging?** Government clients may.
- **Column-level encryption key management** for ID documents — where do keys live?

## Related

`09-permissions-and-roles.md` · `08-multi-tenancy.md` · `65-privacy-gdpr.md` ·
`67-audit-logging.md` · `68-fraud-prevention.md` · `124-security-testing-plan.md` ·
`120-risk-register.md`
