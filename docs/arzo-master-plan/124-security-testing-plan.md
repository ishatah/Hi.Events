# Security Testing Plan

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P0 (extends ARZ-005, ARZ-006) · **Phase:** 0 onward; external pen test before any external SaaS launch
**Depends on:** `64-security.md`, `79-testing-strategy.md`, `09-permissions-and-roles.md`, `129-quality-gates.md`
**Blocks:** `127-production-readiness.md`, `130-launch-checklist.md`

---

This document turns the threat model in `64` into tests that fail when a control breaks. It
prioritizes by damage, not by ease, and it says which tool proves each claim.

## Current state — `PARTIAL`, ~25%

The Phase 0 nets are real and well built. **None of them runs in CI for ARZO**: the only git remote
is the public upstream (`git remote -v` → `origin https://github.com/HiEventsDev/Hi.Events.git`), and
`develop` is 14 commits ahead of it (`git status -sb`). GitHub Actions has never executed ARZO's
code. I ran `phpunit tests/Unit/Architecture` on the working tree at audit time: 6 tests, OK. They
pass when someone remembers to run them. `129` owns the fix (ARZ-300).

| Test or control | State | Evidence |
|---|---|---|
| Every non-public Action authorizes (ARZ-005) | `CONFIRMED`, presence only | `tests/Unit/Architecture/ActionAuthorizationTest.php:109-132`; regex for `isActionAuthorized`/`minimumAllowedRole`/`getAuthenticated*` at `:177-185`; 59-entry public allowlist `:26-107`; `/admin` must name `Role::SUPERADMIN` `:151-175` |
| No Eloquent above repositories (ARZ-004) | `CONFIRMED`, one blind spot | `LayeringTest.php:24-36` pins 11 files; the check is `use HiEvents\Models\` only (`:107`), so query-builder access in domain services passes (ST7) |
| Cross-tenant suite (ARZ-006) | `CONFIRMED`, **20 cases** | `CrossTenantIsolationTest.php:54-82` — 17 foreign-resource routes (event get/update/settings/stats, products list/get/delete, attendees, orders, questions, promo codes, check-in lists, capacity assignments, webhooks, affiliates, messages, organizer); listing exclusion for events `:110` and organizers `:125`; foreign product create `:140` |
| Cross-tenant for the 31 new ARZO tables | `PARTIAL` | None at `e7228c1d`. `SpaceAndProgrammeApiTest.php:264-280` adds foreign-event checks for the new endpoints — uncommitted, in progress at audit time |
| "Own parent, foreign child" (IDOR shape) | `MISSING` | Every case above uses a foreign **parent**. No test sends `/events/{mine}/products/{theirs}` |
| SSRF validator | `CONFIRMED` | `WebhookUrlValidatorTest.php` — 7 tests, 28 provider cases; `NoInternalUrlRuleTest.php` — 13 tests |
| `SecureCallWebhookJob` (redirect revalidation, DNS pin) | `MISSING` | No test references the job or `CURLOPT_RESOLVE` |
| Schema invariants | `CONFIRMED` | `Phase1FoundationSchemaTest.php` (10: replayed `client_generated_id` rejected `:123`, re-entry rows `:108`); `Phase2AccreditationSchemaTest.php` (10: exactly-one-source `:69-87`, identifier unique per event `:89`) |
| Access decision | `CONFIRMED`, 35 tests | `AccessDecisionServiceTest.php` — 35 methods with shared builders, not a data table; no JSON export exists (search `golden`, `vectors.json` → nothing) |
| Access scan, end to end | `CONFIRMED`, 11 tests | `AccessScanServiceTest.php:61-261` — replay idempotency `:143`, device vs server clock `:261` |
| Stripe inbound signature | `PARTIAL` | `StripeAccountUpdatedWebhookTest.php:152-158` sends only **valid** signatures; the action returns 204 before verification (`StripeIncomingWebhookAction.php:21-39`) |
| Public check-in endpoints | `MISSING` | Seven actions allowlisted as capability URLs (`ActionAuthorizationTest.php:71-77`); no Feature test; the link can undo check-ins (`DeleteAttendeeCheckInPublicAction.php:25-37`); search matches email (`AttendeeRepository.php:130`) |
| Rate limits | `MISSING` | No test asserts a 429 (grep `throttle\|429` in `tests/` → one unrelated Geo test) |
| Invitation overwrite defect | `MISSING` test, defect live | `AcceptInvitationHandler.php:59-71` rewrites the global name and password |
| `persons` sensitive columns | `MISSING` test, plaintext | `2026_09_29_000005_create_persons_table.php:24-29` claims application-layer encryption; `app/Models/Person.php` has no casts |
| Dependency audit, SAST, secret scanning | `MISSING` | Dependabot only (`.github/dependabot.yml:1-11`, composer + frontend npm, weekly); no `composer audit`/`yarn audit` step; no PHPStan/Larastan/Psalm in `composer.json`; no CodeQL; no gitleaks config |
| Vulnerability reporting | `PARTIAL` | `SECURITY.md:9` routes reports to `security@hi.events` — upstream's inbox, not ARZO's |

Feature tests at `e7228c1d`: 36 files, 10 of them under `tests/Feature/Http/Actions`, plus 3 auth
tests. `02`'s "7 of 268 Actions" still describes positive coverage; the cross-tenant suite adds
denial-only coverage for 20 cases.

## Defects found while auditing

| # | Defect | Evidence | Severity |
|---|---|---|---|
| ST1 | **Access rules ignore their subject.** `firstMatchingDenyRule` never reads `subject_type`/`subject_id`, and `GrantMaterializationService` materializes **every** active event ALLOW rule targeting a zone onto **every** credential. A DENY meant for one badge closes the zone to everyone; an ALLOW meant for MEDIA gives every ticket-holder the press zone. The test builder `rule()` has no subject fields, so the 35 tests cannot see it | `AccessDecisionService.php:237-258`; `GrantMaterializationService.php:120-127`; `grep subject app/Services/Domain/Access` → 0; `AccessDecisionServiceTest.php:498-511` | **High**, latent — no committed route reaches it; the uncommitted rule CRUD and scan endpoint would. **Fix now**, before golden vectors freeze the behaviour |
| ST2 | **The decision is evaluated at a client-supplied time.** `occurred_at` from the request becomes the decision's clock, so any caller can pick a moment inside a grant's window | `AccessScanService.php:44,82`; `RecordAccessScanRequest.php` `occurred_at: nullable\|date` (uncommitted) | Medium-High |
| ST3 | Access point not scoped to the event: looked up by id alone; the request validates `exists` across all tenants — a cross-tenant reference and an existence oracle | `AccessScanService.php:61-64`; `RecordAccessScanRequest.php` (uncommitted) | Medium |
| ST4 | Replay lookup not scoped: a known `client_generated_id` returns another event's result and credential id | `AccessScanService.php:47-58` | Low — UUIDv4 is unguessable |
| ST5 | Stripe handler logs the **raw payload** on every failure path, including unverified signatures, and debug-logs the event object; `LOG_LEVEL=debug` in `.env.example:29` | `IncomingWebhookHandler.php:76,82,115-135` | Medium — PII in logs; unsigned requests write attacker content to error logs |
| ST6 | Direction is client-supplied; an ENTRY-only point accepts `direction=EXIT`, which bypasses entry limits, capacity and anti-passback and lowers derived occupancy | `RecordAccessScanRequest.php` (uncommitted); `AccessScanService.php:68` | Medium |
| ST7 | `LayeringTest` cannot see query-builder use in services | `LayeringTest.php:107`; `AccessScanService.php:12,29`; `CredentialIssuanceService.php:12,30` | Low — process |
| ST8 | `ActionAuthorizationTest` proves an authorization call **exists**, not that it covers the entity read. Authorizing the event, then reading a child by id alone, passes | `ActionAuthorizationTest.php:177-180` | Medium — process |

ST1 is independent of the roadmap. ST2, ST3 and ST6 must be fixed before the scan endpoint is committed.
None of ST1–ST8 has a backlog id — unnumbered, add to `136` when scheduled.

## Decision: prove the boundary from outside, generated from the route list

The cross-tenant suite is the most valuable test in the repository and it is hand-maintained. It
will not keep up: 50 new action files appeared, uncommitted, in one working session. **Replace the provider list
with a generated test**: enumerate every non-public route from the router, fill `{event_id}`,
`{organizer_id}` and child placeholders from a two-tenant fixture, call each with the stranger's
token, and assert 401/403/404 plus no row change. A new route is covered the day it exists. The
hand-written cases stay for bodies that need a valid payload to reach authorization.

Objection: some routes need bespoke fixtures. Answer: those fail loudly and get a fixture; silence is
the failure mode being removed.

## The catalogue

Ordered by damage. "Now" means independent of the roadmap.

| # | Test | Asserts | When |
|---|---|---|---|
| A1 | Generated cross-tenant, foreign parent | Every non-public route refuses a stranger | Now |
| A2 | Foreign child under own parent | `/events/{mine}/x/{theirs}` → 404, no change | Now |
| A3 | Foreign references in bodies | `access_point_id`, `zone_id`, `session_id`, `product_ids` from another tenant → 422/404 (ST3 shape) | Now, per new entity |
| A4 | Admin runtime check | Every `/admin/*` route with a non-SUPERADMIN token → 403 | Now |
| A5 | Role matrix | Each role in `09` × each permission class, allow **and** deny, generated from seeded `role_permissions` | With ARZ-011/012 |
| A6 | Channel authorization | A foreign tenant cannot subscribe to `private-event.{id}.*` | With ARZ-104 |
| B1 | `SecureCallWebhookJob` | Redirect to an internal host on hop 2 refused; more than 3 hops refused; resolved address pinned; proxy bypass documented (`49`) | Now |
| B2 | Stripe inbound | Bad signature → no state change and a 4xx once the action verifies before returning; replayed event id handled once | Now |
| B3 | Outgoing webhook signature | Receiver verifies HMAC; secret returned on create only | Now |
| C1 | Public check-in link | Cannot delete a check-in (after the fix); search by email returns nothing | With ARZ-041 |
| C2 | Invitation | Accepting a second account's invite leaves name and password unchanged; email lookup case-insensitive | Now |
| C3 | Self-service and ticket lookup | Throttles hold; tokens expire | Now |
| D1 | Credential forgery | Identifiers are 40 chars over a 36-symbol alphabet (~206 bits) from a CSPRNG; nothing derived from person data; legacy `A-XXXXXXX` ids are not accepted by the new engine | Now |
| D2 | Enumeration resistance | `DENIED_NO_CREDENTIAL` identical in shape whether the identifier exists in another event; denied responses carry no person data | Now |
| D3 | Rule subject scoping | ST1 regression: a subject-scoped DENY/ALLOW affects only its subject | Now |
| D4 | Trusted time | Online scans decided at server time; client `occurred_at` recorded, not trusted (ST2) | Before scan endpoint merges |
| D5 | Direction integrity | Direction must match the access point's configuration (ST6) | Before scan endpoint merges |
| E1 | Duplicate `client_generated_id` | Same id, different payload → first write wins and the conflict is surfaced; same id from another event → rejected (ST4) | With ARZ-101 |
| E2 | Forged `occurred_at` on replay | Future beyond skew tolerance rejected; before device enrolment flagged; skew detected from heartbeats (`71`) | With ARZ-101 |
| E3 | Revoked-credential replay | Offline GRANTED after revocation logged and flagged `retrospective_violation`, never rewritten (R8) | With ARZ-102 |
| E4 | Replay flooding | Sync payload capped and paginated; per-device rate enforced | With ARZ-101 |
| F1 | Stolen device key | An `arzod_` key reaches only `device.submit_scan`; revocation effective on next request; expired-with-event key refused; key never logged | With ARZ-092/100 |
| F2 | Device at-rest encryption | Roster store unreadable without the OS keystore — on real hardware (`128` gate 24) | Phase 4 |
| G1 | Rate limits | Login, password reset, ticket lookup, self-service, public check-in, device submit each return 429 past their limit | Now |
| H1 | PII-in-logs canary | Seed canary PII (name, email, identifier) through Stripe, webhook and exception paths; assert it never reaches the captured log channel (ST5) | Now |
| H2 | Sensitive columns | Raw `persons.id_document_number` value differs from plaintext once casts land (`23`, `65`) | With the encryption fix |
| H3 | Client bundle | No secret-bearing variable carries a `VITE_` prefix (R12) | Now |

Rate-limit **capacity** — the whole venue sharing one 180/min budget behind one NAT address — is a
performance finding and lives in `125`.

## Tooling

**Decision: the PHPUnit suite carries the weight, not a security product.** Most of what can go wrong
here is authorization logic, which only in-suite tests understand.

| Need | Choice | Blocking? | Why |
|---|---|---|---|
| Authorization, tenancy, abuse cases | PHPUnit Feature tests, `DatabaseTransactions` | Block | Knows the domain; runs in seconds |
| Dependency vulnerabilities | `composer audit`; `yarn audit --groups dependencies` (the frontend uses yarn classic) | Block on high/critical in production deps; allowlist entries carry an expiry | Dependabot proposes updates but gates nothing |
| Secrets in commits | gitleaks on the PR diff, with rules for `arzo_` and `arzod_` prefixes (`48`, `40`) | Block | A leaked key in git history outlives rotation |
| SAST | **Larastan**, baseline-gated like the tsc count in `frontend-tests.yml:62-78` | Block on new findings | Laravel-aware; a baseline makes it adoptable on a dirty codebase. Not Psalm as well — one analyser |
| JS/TS SAST | ESLint already gates by count (`frontend-tests.yml:46-60`); CodeQL if the ARZO GitHub plan includes it (`UNVERIFIED`) | Warn | Low marginal value over types plus lint |
| DAST | OWASP ZAP baseline against staging, nightly | Warn | Catches headers and misconfiguration, not logic. Only once a staging environment exists |

## Penetration testing

**Decision: external, before any external SaaS launch, scoped to the tenant boundary.** An internal
reviewer tests what the author imagined; an external tester tests what the author did not.

| Stage | Who | Scope | Trigger |
|---|---|---|---|
| Authorization review | Internal, not the author of ARZ-011 | Policies versus the old checks (R7) | ARZ-011 complete |
| On-site red-team | Internal ops + engineering | Badge forgery, passback, revoked badge at an offline door, stolen scanner | Dress rehearsal before the first event using access control (`130`) |
| External pen test | Third party | Multi-tenancy, public and capability URLs, API and device keys, webhooks | Before the first external SaaS customer (`64`); after MFA lands |
| Re-test | Same third party | Findings closed | Before launch |

**Bug bounty: defer.** A bounty without triage capacity produces noise and resentment. First publish
an ARZO `SECURITY.md` with ARZO's own contact — the current file sends reports to upstream.

## Rollout

| Step | Change | When |
|---|---|---|
| 1 | Fix ST1; D3 regression test | Now |
| 2 | Fix ST2, ST3, ST6 with D4, D5, A3 | Before the scan endpoint is committed |
| 3 | A1 generated cross-tenant test; A2, A4 | Now — cheap and permanent |
| 4 | B1, B2, C2, G1, H1, H3 | Now |
| 5 | `composer audit`, `yarn audit`, gitleaks, Larastan baseline in CI | With the ARZO remote (`129`) |
| 6 | A5 role matrix | With ARZ-011/012 |
| 7 | E1–E4, F1 | With ARZ-092, ARZ-100–102 |
| 8 | External pen test, re-test | Before external SaaS |

## Open questions

- **Who receives security reports for ARZO?** A named inbox and owner are needed before any client uses the platform.
- **Pen-test vendor and budget?** `UNVERIFIED` — business decision; the scope above sizes the quote.
- **Do clients require attestation** (a pen-test letter, ISO 27001, SOC 2)? Government clients may. Business to answer before the first tender.
- **Replace the hand-written cross-tenant list entirely?** Leaning no — keep it as readable documentation of the highest-value cases, with the generated test as the net.

## Related

`64-security.md` · `79-testing-strategy.md` · `09-permissions-and-roles.md` · `08-multi-tenancy.md` ·
`24-access-control.md` · `49-webhooks.md` · `71-realtime-architecture.md` · `40-device-management.md` ·
`48-api-platform.md` · `125-performance-testing-plan.md` · `129-quality-gates.md` · `120-risk-register.md`
