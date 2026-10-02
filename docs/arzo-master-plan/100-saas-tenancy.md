# SaaS Packaging and Tenancy

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Extend + Fix · **Priority:** P2 — no ARZ id; depends on ARZ-013 · **Phase:** operator configuration now; entitlements any time; external tenants only after `98`'s gates
**Depends on:** `08-multi-tenancy.md`, `98-commercial-model.md`, `99-pricing.md`
**Blocks:** `101-enterprise.md`

---

How capabilities are packaged for tenants, how a package is enforced, and how a tenant arrives. The
codebase has a deployment-wide SaaS switch and a handful of per-account knobs. It has no notion of a
plan, so today every account on an installation can use every capability.

## Current state — `PARTIAL`, ~35%

### What SaaS mode changes — `CONFIRMED`

| Behaviour when `APP_SAAS_MODE_ENABLED=true` | Evidence |
|---|---|
| New accounts and users start unverified until the email is confirmed | `CreateAccountHandler.php:57,67,78`; `VerifyUserEmailService.php:45` |
| Stripe Connect onboarding is allowed — and only then | `CreateStripeConnectAccountHandler.php:47-51` |
| Payments become direct charges on the organizer's connected account, with an application fee | `StripePaymentIntentCreationService.php:85,122-142` |
| Refunds require the connected account | `StripePaymentIntentRefundService.php:53-57` |
| Sending messages requires super-admin **manual verification** | `SendMessageHandler.php:53-60` |
| Editing email templates requires a verified account | `BaseEmailTemplateAction.php:23-39` |
| Google Tag Manager pixels are refused and stripped from public output | `PartialUpdateOrganizerSettingsRequest.php:25-33`; `OrganizerSettingsPublicResource.php:22-27` |
| AI event-spam screening can run | `EventSpamCheckService.php:30-35` |
| Frontend shows payouts, fee settings and SaaS publish/checklist steps | `OrganizerLayout/index.tsx:71`; `PublishEventModal/index.tsx:63`; `SetupChecklist.tsx:85` |

### Per-tenant knobs — what exists

| Knob | Scope | State | Evidence |
|---|---|---|---|
| Fee configuration | Organizer | `CONFIRMED` | `organizers.organizer_configuration_id`; admin assign `api.php:551-552` |
| Messaging tier | Account | `CONFIRMED` | `accounts.account_messaging_tier_id`; admin `api.php:587-588` |
| Manual verification | Account | `CONFIRMED` | `accounts.is_manually_verified`; admin `api.php:591` |
| Registration kill-switch | Deployment | `CONFIRMED` | `config/app.php:26` |
| Platform branding — name, colours, logos, favicon, support email, ToS and privacy URLs | Deployment (env) | `CONFIRMED` | `config.ts:6-30`; `frontend/.env.example` (rebrand uncommitted at audit time) |
| Email logo, link and footer | Deployment (env) | `CONFIRMED` | `config/app.php:72-74`; `message.blade.php:5-9,28-39` |
| Homepage themes | Organizer, event | `CONFIRMED` | `organizer_settings.homepage_theme_settings`, `event_settings.homepage_theme_settings` |
| Embeddable checkout on the client's own site | Event | `CONFIRMED` | `embed/widget.js` (`02`) |
| Custom domains | — | `MISSING` | Search for custom_domain, cname |
| Plans, feature flags, entitlements | — | `MISSING` | Search for plan, entitlement, feature flag; no Pennant in `composer.json` |

### Isolation — the prerequisite

`08`: the boundary is **tested** (`CrossTenantIsolationTest`, 20 cases) but not **defended** — no
global scope, tenant id in static state. ARZ-013 (tenant global scope, request-scoped context) is TODO.
And no ARZO deployment exists to host tenants (`98`).

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| SP1 | **Every new account gets the Premium messaging tier** (50 messages/day × 50,000 recipients) because `APP_IS_HI_EVENTS` is unset | `CreateAccountHandler.php:227-230` | High for any self-serve signup — `42` E2 |
| SP2 | **With SaaS mode off and registration on — both defaults — any stranger can register, is auto-verified, and can publish an event whose card payments land in the installation's own Stripe account.** New events default to Stripe. | `config/app.php:22,26`; `CreateAccountHandler.php:67`; `CreateEventService.php:246`; `StripePaymentIntentCreationService.php:122-125` | **High** on any public deployment with defaults |
| SP3 | `APP_IS_HI_EVENTS` is overloaded: it sets the default tier, the EU-VAT default and migration backfills. Setting it to fix SP1 changes tax behaviour. | `config/app.php:90,265`; `CreateAccountHandler.php:229`; `2025_12_30_095749_add_messaging_tier_to_accounts_table.php:23` | Medium — a trap for whoever fixes SP1 |
| SP4 | Signup accepts an encrypted invite token carrying a fee configuration, but nothing produces such tokens; and it sets the legacy account-level configuration, which reaches organizers only via `legacy_account_configuration_id` | `CreateAccountHandler.php:176-207`; `CreateOrganizerHandler.php:60-88` | Low — half of a sales-led onboarding path |

**Fix now, independent of the roadmap:** SP2 — ARZO's operator deployment sets
`APP_DISABLE_REGISTRATION=true`. SP1 via a dedicated default-tier setting (Untrusted), **not** by
setting `APP_IS_HI_EVENTS` (SP3).

## Decision: operator configuration first

Until `98`'s gates pass, ARZO runs **one operator deployment**: SaaS mode off (ARZO's merchant
account, ARZO as merchant of record), registration off, accounts created by ARZO staff, messaging
tier set deliberately. This is the configuration the code already supports safely. SaaS mode is
switched on only for an external-tenant deployment, and a processor decision precedes it.

## Decision: entitlements per account, checked where things are configured — never at the door

A package is a **contractual fact** — who bought what, from when, with what limits. That belongs in
a table with dates and an author, not in a rollout flag. Feature-flag libraries such as Pennant solve
gradual rollout; conflating the two makes a support engineer's toggle indistinguishable from a sale.

```
-- plans are defined in code (a PHP enum + config), versioned with the release
account_entitlements
  id, account_id → accounts,
  capability,            -- TICKETING | PROGRAMME | ACCREDITATION | ACCESS | BADGING |
                         -- EXHIBITORS | DEVICES | ATTENDEE_APP | API | SSO | ADVANCED_ANALYTICS
  limit_value int NULL,  -- e.g. credentials per event; NULL = unlimited
  source,                -- PLAN | OVERRIDE | TRIAL
  plan_code NULL,
  starts_at timestamptz, ends_at timestamptz NULL,
  granted_by → users, reason NULL, contract_ref NULL,
  timestamps
  INDEX (account_id, capability)
```

Capability keys are the capability modules of `05` plus the commercial add-ons. Assigning a plan
**materializes** rows, the same pattern as access grants (`24`); overrides are rows too, so every
exception has an author and a reason.

### Enforcement — three layers, one rule

| Layer | Mechanism |
|---|---|
| API | `EntitlementService::require(accountId, capability)` at the Action, beside `isActionAuthorized`; a lapsed or missing entitlement returns 403 with a specific code |
| Limits | Checked at write: issuing a credential beyond `limit_value` is refused with a clear message |
| UI | Navigation and settings hide what the account lacks; the API layer is authoritative |
| CI | An architecture test, like ARZ-005's, asserts every Action in a gated module calls the check |

**The rule:** entitlements are enforced when an organizer **configures and issues** — never on the
scan path, the sync path or a read of existing data. A contract that lapses mid-event must not stop a
door (`59`: the system never blocks doors from opening). On expiry an account becomes read-only for
that capability, and its data stays exportable.

## Packages

| Package | Contents | Channel |
|---|---|---|
| **Ticketing** | Commerce, registration, messaging within tier, standard reports, webhooks | Self-serve — only after `98`'s gates |
| **Programme** | Sessions, speakers, agenda, attendee app | Self-serve add-on |
| **On-site** | Space, accreditation, access, badging, devices, command center | **Sales-led only** |
| **Exhibitor** | Exhibitor portal, lead capture, per-exhibitor licences | Sales-led, attached to On-site or Programme |
| **Enterprise** | SSO, audit export, SLA, dedicated environment | Sales-led, built on demand (`101`) |
| Operations | Staffing, tasks, readiness, incidents | **Not packaged** — ARZO's own tooling (`98`) |

**On-site is sales-led** because hardware, a venue survey, rule configuration and a readiness review
(`59`) decide whether it works. A self-serve signup that reaches a gate without them fails on the day,
publicly, with ARZO's name on the badge.

## Onboarding, sales-led

1. A super-admin creates the account and first organizer for the client — a new admin action; today
   accounts arrive only through public registration (SP4).
2. Assigns the plan (entitlements), the fee configuration (`99`), the messaging tier and manual
   verification — the last three already have admin endpoints.
3. Invites the client's users. Invitations exist, but accepting one into a second account overwrites
   the user's global password and name (`AcceptInvitationHandler.php:60-72`) — fix before clients
   with users in several accounts arrive.
4. On-site packages open an `event_operations` record (`56`) at contract.

## Custom domains — defer

| Option | Cost | Verdict |
|---|---|---|
| Organizer pages under ARZO's domain + widget on the client's own site | Exists | **Now** — covers most "on our website" requests |
| Per-organizer subdomain of an ARZO domain | Wildcard DNS and TLS; host-based organizer resolution in SSR | When a client asks |
| Client-owned domain via CNAME | Per-domain certificate issuance and renewal; depends on hosting that does not exist yet | Enterprise, on demand |

The licence's "domain" scope (`98`) must be checked against any multi-domain option before it ships.

## Tenant isolation prerequisites for external tenants

| # | Prerequisite | Source |
|---|---|---|
| 1 | Request-scoped tenant context; tenant id in every job payload | `08` layer 1, ARZ-013 |
| 2 | Tenant global scope on account-owned models, with parent-join scopes for children | `08` layer 2, ARZ-013 |
| 3 | Cross-tenant tests extended to every new module: accreditation, credentials, badges, sessions, exhibitors, devices | `08` layer 4 |
| 4 | Per-event roles, so a client's staff see only their events | `09`, ARZ-011, ARZ-012 |
| 5 | MFA; audited super-admin access | `64`, `101` |

## Migration

| Step | Change | Risk |
|---|---|---|
| 1 | Operator configuration: registration off; dedicated default-tier setting (SP1, SP2, SP3) | Low |
| 2 | `account_entitlements`, plan enum, `EntitlementService`, CI architecture test | Medium |
| 3 | Admin: create client account and organizer; assign plan | Low |
| 4 | Metering reports (`99`) | Low |
| 5 | Custom subdomains | Deferred |

Unnumbered — add to `136` when scheduled.

## Open questions

- **Should organizers become a tenancy boundary?** An agency hosting several clients in one account wants it. `08` says build on a customer asking; the Platform licence's "client accounts" suggests accounts are the intended boundary.
- **Trials?** `source = TRIAL` supports them; whether ARZO offers any is `99`'s question.
- **Entitlement lapse during a live event** — read-only for configuration, scanning unaffected. Confirm this is contractually acceptable.
- **Who may grant overrides?** Super-admin only, recorded with a reason.

## Related

`08-multi-tenancy.md` · `09-permissions-and-roles.md` · `98-commercial-model.md` · `99-pricing.md` ·
`101-enterprise.md` · `05-product-architecture.md` · `42-email-marketing.md` · `59-event-readiness.md` ·
`56-event-operations.md` · `64-security.md`
