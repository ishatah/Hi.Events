# Security Architecture

**Status:** WRITTEN · **Audit date:** 2026-09-29 (risks refreshed; first written 2026-09-28) · **Baseline:** `develop` @ `e7228c1d`

---

## Current state — mixed, with two live risks

### Genuinely strong · `CONFIRMED`

- **Webhook SSRF defence.** `SecureCallWebhookJob` disables automatic redirects, follows up to 3 hops manually, revalidates the URL on **every hop**, and pins the resolved IP with `CURLOPT_RESOLVE` to defeat DNS rebinding. The best-engineered infrastructure in the codebase — and `UNVERIFIED`/untested.
- **Input sanitization.** `HtmlPurifierService` applied to user HTML before storage.
- **Concurrency safety.** Postgres advisory locks serialize checkout per event.
- **Session invalidation.** `validateUserStatus()` re-reads `account_users.status` on every authorized call and force-logs-out deactivated users.
- **Payment scope.** Stripe handles card data; ARZO never stores it.
- **Impersonation auditing — partly.** Write requests during impersonation are logged and tagged in Sentry. Start and stop are not recorded, reads are not recorded, the impersonation token lives the normal 7 days, and logged request payloads include personal data (`67`, `65`, `89`; ARZ-312).
- **Spam screening.** AI-assisted event screening with an admin queue.
- **Test database guard.** Enforced in `CreatesApplication`, unbypassable by forgetting a trait.
- **Privacy defaults — partly.** Sentry `send_default_pii` off, `sql_bindings` off in breadcrumbs. **But** the exception handler explicitly attaches the authenticated user's email, full name and IP to every reported exception (`app/Exceptions/Handler.php:54-60`), so organizer PII reaches Sentry regardless of the setting (`78`). Attendee data is not included.

### Live risks

Refreshed 2026-09-29 against `e7228c1d`. S3 and S4 were closed by Phase 0; S7–S14 were found by the
audits behind documents 31–140.

| # | Risk | Evidence | State |
|---|---|---|---|
| **S1** | **Tenancy by discipline.** No global scopes; child resources scoped by parent only; tenant id in static mutable state. | F11 | Guarded by a 20-case cross-tenant suite (ARZ-006) that does not yet cover the new entities; scope itself open (ARZ-013) |
| **S2** | **Authorization has no declarative layer.** No policies; the default `ORGANIZER` gate is a no-op; any account user sees every event. | F12 | Guarded by the architecture test (ARZ-005); RBAC open (ARZ-011) |
| S3 | SSR cross-request state | F5 | **Closed** (ARZ-002) |
| S4 | `process.env` define — downgraded on testing; only `VITE_*` keys reach the bundle | F6 | **Closed** (ARZ-003) |
| **S5** | **Scanner has no identity.** The list link can also **undo** check-ins | F3; `38` | Open — first identity-bearing scan path in progress (`38`) |
| **S6** | **No token refresh; 7-day JWT** carrying `account_id` and role | `48` | Open |
| **S7** | **ARZO's code exists on one workstation and runs in no CI.** The only remote is public upstream; ARZO cannot push to it; none of the Phase 0 security gates runs automatically | `123` | **Open — P0** (ARZ-300) |
| **S8** | **ID document numbers and dates of birth would be stored in plaintext** — the migration comment promises encryption; the model has no casts | `65`, `115` | Open — before any ID collection (ARZ-303) |
| **S9** | **Accepting an invitation to a second account overwrites the user's global password and name** | `AcceptInvitationHandler.php:60-72`; `57` | Open |
| **S10** | **Stripe webhook accepts and queues before verifying the signature**; logs full payloads on failure | `50` I1 | Open |
| **S11** | **Public check-in search matches email** — an enumeration oracle for anyone holding a list link; no per-route throttle | `38` | Open |
| **S12** | **Webhook secrets stored in plaintext**, no rotation; duplicated events reuse them | `49` W6, W7 | Open |
| **S13** | **Tracking pixels load without consent by default** | `41` M1 | Open |
| **S14** | **Access time windows evaluate in UTC**, not venue time — a correctness defect with security consequences once rules are live | `115` | Open — before any rule UI (ARZ-302) |

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
**Controls:** OS-keystore encryption at rest, mandatory; event-scoped device keys, revocable per device; wipe on revocation at next contact (`40`). Remote wipe cannot reach a device that never reconnects, so it is not counted as a control. **This is the strongest argument for native scanner apps over a PWA** — a PWA cannot match the at-rest guarantee (`94`).

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

1. **Now:** S7 — without a repository and CI, none of the other controls is enforced or even backed up.
2. **Hardening track, independent of phases** (`113`): S8 before any ID collection, S14 before any rule UI, then S9–S13 — each small.
3. **Phase 0 residue:** S1, S2 are guarded by tests; closing them structurally is ARZ-011/013.
4. **Phase 1:** RBAC (`09`) closes S5 and S6 structurally.
5. **Before external SaaS:** MFA, penetration test (`124`).
6. **Phase 4:** device security — encryption at rest, event-scoped keys, revocation. Remote wipe is a convenience, not a breach control (`40`).

## Open questions

- **MFA:** TOTP, or email OTP? TOTP is stronger; email is lower friction and already has the mail infrastructure.
- **Penetration test:** internal or external, and before which milestone?
- **Does any client require tamper-evident audit logging?** Government clients may.
- **Column-level encryption key management** for ID documents — where do keys live?

## Related

`09-permissions-and-roles.md` · `08-multi-tenancy.md` · `65-privacy-gdpr.md` ·
`67-audit-logging.md` · `68-fraud-prevention.md` · `124-security-testing-plan.md` ·
`120-risk-register.md` · `123-release-strategy.md` · `38-scanner-platform.md` · `40-device-management.md`
