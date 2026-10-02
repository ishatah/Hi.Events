# Privacy and Data Protection

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + New (cross-cutting) · **Priority:** P0 for PV1, PV2 (ARZ-323, ARZ-303); P1 for PV3–PV5 (ARZ-324, ARZ-312, ARZ-310); the programme — consent, retention, subject rights — P1, unnumbered (add to `136` when scheduled) · **Phase:** fixes now; consent and retention before Phase 3; subject-rights tooling before any external SaaS launch
**Depends on:** `23-accreditation.md`, `09-permissions-and-roles.md`
**Blocks:** `33-exhibitor-lead-capture.md`, `42-email-marketing.md`, `54-attendance-intelligence.md`, `60-incident-management.md`, `63-event-documentation.md`, `66-compliance.md`, `133-ai-capabilities.md`

---

What personal data ARZO holds, on what basis, for how long, and how a person exercises their rights
over it. The plan adds the most sensitive data the platform has ever held — ID numbers, dates of
birth, photos, movement across zones, medical incidents — so this document sets the rules each
subsystem must follow before it writes a row.

## Current state — `PARTIAL`, ~30%

Better than typical at the account level; almost nothing at the level of an individual attendee.

### What exists — `CONFIRMED`

| Capability | Evidence |
|---|---|
| Whole-account deletion with a 30-day grace period, a reminder 7 days before, and an hourly executor | `AccountDeletionService.php:30`; `ProcessScheduledAccountDeletionsJob.php:27,67-78`; `Console/Kernel.php:19` |
| Outcome chosen by data: accounts with completed orders are **anonymized** (financial records survive), others **hard-deleted** | `AccountDeletionService.php:81-86,209-211` |
| `AnonymizationStrategy`: `NULLIFY`, `SCRUB_TEXT`, `SCRUB_EMAIL`, `SCRUB_EMAIL_UNIQUE`, `RANDOM_TOKEN` | `AnonymizationStrategy.php:9-13` |
| Eight anonymizers: orders and attendees scrubbed; question answers, outgoing messages, messages, waitlist entries deleted; users, organizers, affiliates, Stripe customers, VAT settings scrubbed; images deleted with their files | `OrderAnonymizer.php:24-51`; `EventContentAnonymizer.php:30-54`; `UserAnonymizer`, `OrganizerAnonymizer`, `PartnerAnonymizer`, `ImageAnonymizer`, `AccountAnonymizer`; `AccountAnonymizationService.php:48-65` |
| A deletion manifest (entity, rows affected) stored on the request | `AccountDeletionService.php:213-218` |
| Cookie banner with analytics / advertising categories and Google Consent Mode defaults of `denied` | `server.js:88-96,173-175`; `CookieConsentBanner` |
| Page-view de-duplication uses the IP only as a 5-minute cache key; it is not stored | `EventPageViewIncrementService.php:22-31` |
| Sentry `send_default_pii` off; SQL bindings off in breadcrumbs | `config/sentry.php:36,65` |
| Self-service correction of attendee name and email, when the event allows it | `SelfServiceValidationTrait.php:15`; `EditAttendeePublicRequest.php:12-14` |

### What is missing or wrong

| Capability | State | Evidence |
|---|---|---|
| Erasure of **one person** (not a whole account) | `MISSING` | No delete route for attendees or orders (`api.php:425-444`); searches for `gdpr`, `erasure`, `subject access`, `personal data` return nothing |
| Subject-access export | `MISSING` | Same search; the only exports are the organizer's bulk exports |
| Consent records | `MISSING` | `orders.opted_into_marketing_at` is a bare timestamp read only by the orders export (`42`) |
| Retention of any kind | `MISSING` | The scheduler runs four jobs, none of them retention (`Console/Kernel.php:17-26`) |
| Encryption of `persons.id_document_number` / `date_of_birth` | `MISSING` | Migration comment promises application-layer encryption (`2026_09_29_000005:24-29`); `Person.php` has no casts; no `encrypted` cast exists anywhere in `app/` |
| Consent before tracking pixels | `PARTIAL` | Banner and Consent Mode apply only when `VITE_COOKIE_CONSENT_ENABLED === 'true'`, default `false` (`frontend/.env.example:18` at baseline); when disabled, organizer pixels are treated as **all granted** (`useCookieConsent.ts:14-16`); the Google Ads tag loads whenever an ID is set (`server.js:176-181`) |
| Special-category flag on custom questions | `MISSING` | Any organizer can ask a health question; a demo event asks about pregnancy (`YogaDemoEvent.php:225`) |
| Audit of who exported personal data | `MISSING` | `ExportAttendeesAction.php:34-36` authorizes and streams; nothing records the export |

IP addresses are stored in six places: `attendee_check_ins.ip_address`, `access_logs.ip_address`,
`order_audit_logs.ip_address`, `event_logs.ip_address`, `password_resets.ip_address`,
`rsvp_responses.responded_from_ip` (live DB). None has a retention rule.

The organizer exports carry more than their names suggest: the attendee export includes email,
notes, **the ticket code** (`Public ID`) and every question answer (`AttendeesExport.php:54-69`); the
orders export includes billing address and marketing opt-in (`OrdersExport.php:46-70`).

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| PV1 | **Account anonymization leaves `persons` untouched** — and the Phase 1 backfill fills `persons` with copies of every attendee's name and email. An anonymized account keeps its attendees' identities in `persons`. Soft-deleting the account does not cascade. | `2026_09_29_000008_backfill_venues_and_persons.php:163-205`; `AccountAnonymizationService.php:49-58` (no persons anonymizer) | **High** — latent on dev (0 persons rows), live from the first production migrate followed by any anonymization. **Fix now** — ARZ-323; the backfill's own batching defect is ARZ-301 |
| PV2 | `persons.id_document_number` and `date_of_birth` are plaintext `text`, contrary to the migration comment | Above | **High** — nothing writes them yet; fix before the first writer. **Fix now** — ARZ-303 |
| PV3 | The exception handler sends every reported error's user **email, full name and IP** to Sentry, overriding `send_default_pii=false` | `Exceptions/Handler.php:55-60` | Medium — platform users, not attendees. **Fix now** (ARZ-324): send the user id only |
| PV4 | Impersonated mutations are logged with the **full request payload** (attendee names, emails, answers) to the application log; logs become Sentry breadcrumbs by default | `LogImpersonationMiddleware.php:33-41`; `config/sentry.php:53`; `LOG_CHANNEL=stderr` (`backend/.env.example:27`) | Medium. **Fix now** (with ARZ-312): log field names, not values |
| PV5 | Consent is off by default and pixels load without it; the Ads tag ignores it entirely | Above | Medium for ARZO-run sites, high for any EU-facing tenant. **Fix now** (ARZ-310): consent on by default, pixels never "all granted" by absence of a banner, Ads tag gated by consent |
| PV6 | No per-person erasure or access export | Above | High before external SaaS; medium for ARZO-run events, where requests would be answered by hand-written SQL |
| PV7 | Anonymization skips `attendee_check_ins.ip_address`, `rsvp_responses`, and every Phase 1–2 table (`accreditations.form_data`/`documents`, `credentials`, `access_logs.raw_identifier`/`ip_address`, `badges.snapshot`) | Anonymizer list above; live DB | Medium — the new tables are empty today. ARZ-323 |
| PV8 | No retention: abandoned orders, waitlist entries, outgoing messages, question answers and IP columns are kept forever (webhook logs are the exception — trimmed inline to 20 per webhook, `49` W8) | `Console/Kernel.php:17-26` | Medium |
| PV9 | Custom questions can collect special-category data with no warning or flag | `QuestionTypeEnum`; demo above | Medium — Article 16 below |
| PV10 | Exports are unaudited and include the ticket code, which is a working bearer credential (`68`) | `AttendeesExport.php:64` | Medium |

PV1 is the one to act on first: it turns an existing, well-built deletion flow into one that
silently leaves the data it was meant to remove.

## The legal frame — PDPL and GDPR, both

### Qatar PDPL — from the primary text

Law No. (13) of 2016 on Protecting Personal Data Privacy, English text from the Ministry of Justice
official portal: <https://www.almeezan.qa/EnglishLaws//132016.pdf> (accessed 2026-09-29). Read in
full for this document.

| Article | Says | Design consequence |
|---|---|---|
| 4 | Process only with consent, unless necessary for a "Lawful Purpose" of the controller | Every data class needs a named basis (table below) |
| 5 | The individual may withdraw consent, object, request erasure, request correction | Per-person erasure and withdrawal are required, not optional (PV6) |
| 6 | The individual may access their data and obtain a copy | Subject-access export |
| 9 | Inform the individual before processing: controller, purposes, disclosures | Versioned privacy notices, and the lead-sharing disclosure in `33` |
| 10 | Keep data no longer than necessary for the purpose | A retention schedule (below) |
| 11 | Review privacy measures **before** new processing; internal systems for complaints, access and erasure requests, and breach reporting | DPIA triggers; `privacy_requests` |
| 13–14 | Protect against loss and disclosure; processors notify controllers; controllers notify the individual and the Competent Department of breaches that may cause serious damage | Breach flow via `60` |
| 15 | The controller may not restrict cross-border data flows unless processing breaches the law or may cause serious damage | See data residency |
| **16** | Data on ethnic origin, **children, health**, physical or psychological condition, religious creeds, marital relations and criminal offences is of a **special nature** and may be processed **only after permission from the Competent Department** | Load-bearing — next section |
| 17 | Websites addressing children need guardian consent and disclosure | Family events |
| 22 | Direct-marketing electronic messages need prior consent, sender identity and an opt-out address | `42`'s prerequisites |
| 23–24 | Penalties up to QR 1,000,000 (Articles 4, 8–12, 14, 15, 22) and up to QR 5,000,000 (Articles 13, 16 para. 3, 17) | |

The law names the "Competent Department" as a unit of the Ministry of Transport and Communications
(Article 1). Which body administers it today, and what implementing decisions and guidelines it has
issued under Articles 7, 8 and 16, are `UNVERIFIED` — not read for this document. ARZO's legal
adviser must supply them.

### GDPR

GDPR reaches a controller outside the EU that offers goods or services to people in the EU
(Article 3(2)). Whether ARZO's events target EU residents is a legal judgement — `UNVERIFIED`. The
plan designs to the stricter of the two regimes wherever they differ; in practice that means GDPR's
explicit deadlines and PDPL's permission requirement for special-category data.

### Who is the controller

| Situation | Controller | ARZO's role |
|---|---|---|
| ARZO operates its own event | ARZO (possibly jointly with the client — `UNVERIFIED`, per contract) | Controller |
| External organizer on the SaaS platform | The organizer | **Processor** — needs a data-processing agreement |
| Exhibitor exports leads | The exhibitor, for its copy (`33`) | — |
| Badge printing, SMS, email providers | ARZO or organizer | Those vendors are processors (`61`) |

The processor case is why subject-rights tooling must work for organizers, not only for ARZO staff.

## Decision: Article 16 gates the sensitive features

Health data, data about children and (possibly) nationality need **permission from the Competent
Department before processing** — a business and legal commitment that software cannot supply.

| Feature | Special-nature data | Consequence |
|---|---|---|
| Medical and safeguarding incidents (`60`) | Health; children | Do not ship `MEDICAL` and `SAFEGUARDING` incident types to production until the permission question is answered. Until then, record "medical assistance given at location X" with no health detail and no name. |
| Registration of minors | Children | Events admitting under-18s collect a guardian contact and the minimum; ask whether a permission is needed |
| Health questions in registration (PV9) | Health | Question-level `sensitivity = SPECIAL` flag, a warning to the organizer, excluded from exports by default |
| Nationality at government events (`23`) | Possibly ethnic origin | Whether nationality counts is `UNVERIFIED`; treat it as special until advised otherwise |

Biometric data is not in Article 16's list as read, but the Minister may add types. Face
recognition (ARZ-260) stays "legal review first".

## Data classes — lawful basis and retention

Retention periods are **proposals**. Where a legal minimum may apply (tax, labour, limitation
periods), it is marked `UNVERIFIED` and legal must set the number before the retention job enforces it.

| Class | Where | Basis | Retention (proposal) | Minimization |
|---|---|---|---|---|
| Buyer and attendee identity | `orders`, `attendees`, `persons` | Contract (Art. 4 Lawful Purpose) | Event end + 24 months, then anonymize with the existing strategies; financial fields kept — tax minimum `UNVERIFIED` | Per-event, not only per-account |
| Question answers | `question_answers` | As disclosed on the form | Event end + 12 months | Organizer sees the retention on the question editor |
| Special-category answers | `question_answers` with `SPECIAL` questions | Explicit consent + Art. 16 permission | Event end + 30 days | Excluded from exports and devices |
| Marketing consent | `consent_records` | Consent (Art. 22) | Until withdrawn; evidence kept withdrawal + 3 years | |
| Badge photo | `images` (private disk) | Lawful Purpose: identity check at accredited events | Event end + 30 days; reused across events only with `CROSS_EVENT_PROFILE` consent | On devices only where photo verification is on; never exported |
| ID document type and number | `persons` | Lawful Purpose tied to a named venue or client security requirement; opt-in per event (`23`) | Event end + 30 days unless the client contract requires longer (`UNVERIFIED`) | Encrypted; masked display (last 4); never exported; **never on devices** — devices carry "ID checked" only |
| Nationality | `persons` | Lawful Purpose, government events | Event end + 90 days | Reports only as aggregates with groups of 5 or more |
| Date of birth | `persons` | Age verification | Event end + 30 days | Store only where age matters; derive `is_minor` at capture |
| Movement — attendee access logs | `access_logs` | Lawful Purpose: security and safety | Identity stripped at event end + 90 days by default, configurable to 12 months for government clients — replaces the 12-month delete proposed in `54` and `108` | Aggregates kept (below); individual trails audited on view (`54`, `67`) |
| Staff sign-in and shifts | `access_logs`, `shift_assignments` | Contract | 24 months — labour record minimum `UNVERIFIED` | Punctuality is personal data about workers (`57`) |
| Lead transfers | `leads`, `lead_captures` | Consent `EXHIBITOR_LEAD_SHARING` | ARZO's copy: event end + 90 days (`33`) | `shared_fields` only |
| Incident — medical, safeguarding | `incidents` `RESTRICTED` | Vital interests (Art. 19(3)) in the moment; Art. 16 permission to hold | Event end + 90 days unless a claim or legal hold | Names only where essential; views audited |
| Incident — security | `incidents` | Lawful Purpose | 3 years — limitation periods `UNVERIFIED` | |
| Device rosters | Device store (`71`) | Contract | Purged at key expiry, event end + grace (`40`) | Identifier hash, name, photo only if needed |
| Platform user accounts | `users` | Contract | Account lifetime; existing deletion flow | |
| IP addresses | Six columns above | Lawful Purpose: security | 90 days, then NULL | |
| Audit events | `audit_events` (`67`) | Lawful Purpose / legal obligation | `67` — 2 years default | Labels scrubbed on erasure; ids kept |
| Outgoing messages, webhook logs | `outgoing_messages`, `webhook_logs` | Contract | 12 months; webhook logs 30 days (`49`) | |
| Abandoned and expired orders | `orders` in `RESERVED`/`ABANDONED` | Pre-contract | 30 days after expiry, then delete | |
| Waitlist entries | `waitlist_entries` | Consent to be contacted | Event end + 30 days | |

## Consent — `consent_records`

`33` and `42` defer the schema here. Consent is purpose-bound, versioned and provable.

```
consent_texts                    -- the wording, immutable once published
  id, account_id NULL,           -- NULL = platform default wording
  purpose, version int, locale,  -- English and Arabic at least
  body text, published_at, created_by
  UNIQUE (COALESCE(account_id, 0), purpose, version, locale)

consent_records                  -- one row per grant; withdrawal is set once, never cleared
  id, short_id, account_id,
  organizer_id NULL,             -- MARKETING consent is to a controller, often the organizer
  event_id NULL,                 -- NULL = not event-scoped
  person_id → persons,
  purpose,                       -- EXHIBITOR_LEAD_SHARING | MARKETING | PHOTOGRAPHY_PUBLICATION
                                 -- | CROSS_EVENT_PROFILE | SPECIAL_CATEGORY | CHILD_GUARDIAN
  granted_at timestamptz,
  withdrawn_at timestamptz NULL,
  source,                        -- REGISTRATION_FORM | SELF_SERVICE | ATTENDEE_APP | KIOSK
                                 -- | ORGANIZER_IMPORT | API | LEGACY_BACKFILL
  source_ref NULL,               -- order_id or attendee_id
  consent_text_id → consent_texts,
  evidence jsonb NULL,           -- truncated IP, user agent; nothing else
  created_at
  UNIQUE (person_id, purpose, COALESCE(organizer_id, 0), COALESCE(event_id, 0))
    WHERE withdrawn_at IS NULL
  INDEX (account_id, purpose), INDEX (event_id, purpose)
```

- **Withdrawal writes `withdrawn_at` once** and an `audit_events` row; re-granting creates a new row.
  History is never lost, and "current consent" is one indexed lookup.
- **`ORGANIZER_IMPORT` is allowed but visible.** Organizers will upload "these people agreed on paper".
  The record says so, and it is the organizer's evidence, not ARZO's.
- **Backfill:** each non-null `orders.opted_into_marketing_at` becomes a `MARKETING` record scoped to
  the event's organizer, `source = LEGACY_BACKFILL`, wording version "legacy". It is weak evidence
  and is labelled as such.
- **Consulted, not merely stored:** lead resolution (`33`), segment filters and ESP sync (`42`),
  photo publication, cross-event profiles (`55`).

## Aggregate, then forget — strip identity, keep the shape

`54` and `55` aggregate before deletion. For logs, a cheaper and more useful pattern: **pseudonymize
in place** rather than delete.

After the event is closed and settled (`108`) plus the class's window, the retention job sets to
NULL `credential_id`, `attendee_id`, `person_id`, `raw_identifier`, `ip_address` and
`operator_user_id` on `access_logs` (and the equivalents on `lead_captures`,
`session_attendance`), keeping `event_id`, `zone_id`, `access_point_id`, `occurred_at`, `direction`
and `result`. Arrival curves and occupancy remain recomputable; nobody can be followed.

- This **amends `24`**: "never updated, never deleted" becomes "never updated, except by retention
  pseudonymization after settlement". The job is the only writer permitted to do it, enforced by the
  trigger in `67` and recorded as one `audit_events` row per event.
- It resolves `06`'s "deletion must cascade through `access_logs`" without deleting evidence:
  per-person erasure applies the same stripping to that person's rows immediately.
- Small cells re-identify. A zone entered by three credentials is a list of three people. Outputs
  keep `52`'s suppression of groups under 5; for zones below that threshold the rows are deleted
  rather than stripped.

## Subject rights

| Right | PDPL | GDPR | Today | Target |
|---|---|---|---|---|
| Access and copy | Art. 6 | Art. 15 | `MISSING` | Per-person export across every table below |
| Correction | Art. 5(4) | Art. 16 | `PARTIAL` — attendee self-edit | Extend to `persons` and accreditation data |
| Erasure | Art. 5(3) | Art. 17 | Account-level only | Per-person anonymizer |
| Withdraw consent, object | Art. 5(1)–(2) | Arts. 7(3), 21 | `MISSING` | Withdrawal endpoint; suppression (`42`) |
| Notice | Art. 9 | Art. 13 | Privacy URL configurable | Versioned notice per event, listing disclosures |

```
privacy_requests                 -- mirrors account_deletion_requests
  id, short_id, account_id, organizer_id NULL,
  request_type,                  -- ACCESS | ERASURE | CORRECTION | OBJECTION | WITHDRAW_CONSENT
  status,                        -- RECEIVED | VERIFIED | IN_PROGRESS | COMPLETED | REJECTED
  requester_email_hash, person_id NULL,
  received_at, verified_at NULL, due_at, completed_at NULL,
  handled_by NULL → users, rejection_reason NULL,
  manifest jsonb NULL,           -- what was found, exported or anonymized
  timestamps
```

- **Verification** reuses the ticket-lookup pattern — an emailed magic link, already throttled at
  10/min (`api.php:668`).
- **Scope of a person** within an account: `person_id`, plus case-insensitive email matches across
  `attendees`, `orders`, `question_answers`, `waitlist_entries`, `outgoing_messages`, `leads`,
  `accreditations`, `credentials`, `badges`, `images`, `access_logs`, `consent_records`.
- **Erasure is the account anonymizer, narrowed to one person.** Reuse `AnonymizationExecutor` and
  the strategies with a person-scoped context. Exceptions, stated in the response: financial records
  are anonymized, not deleted; incidents under legal hold; consent records keep a hashed email as
  evidence; exhibitors' exported copies are theirs (`33`).
- **During a live event**, erasing an active credential would lock someone out of a hall they are in.
  The request is accepted and executed at event end, and the requester is told.
- **Deadlines:** GDPR one month, extendable (Art. 12(3)). PDPL leaves the procedure to a ministerial
  decision (Art. 7) — `UNVERIFIED`. `due_at` defaults to 30 days.
- For SaaS tenants the organizer is the controller: the tooling sits in the organizer's admin, and
  ARZO's platform role assists on request.

## DPIA triggers

Article 11(1) requires a privacy review before new processing; GDPR Article 35 requires a DPIA for
high-risk processing. The plan treats these as the same gate. A DPIA is a `documents` row (`73`) of
class `DPIA` (`63`), linked to the feature or event, and readiness (`59`) checks it exists.

| Trigger | Why |
|---|---|
| Face recognition (ARZ-260) | Biometric; mandatory |
| Collecting ID numbers at scale | Identity-theft impact |
| Individual movement trails kept beyond 90 days | Behavioural tracking |
| Lead sharing to third parties (`33`) | Transfer to separate controllers |
| Minors admitted and registered | Article 16 / 17 |
| Medical or safeguarding incidents recorded (`60`) | Article 16 |
| Cross-event named profiles (`55`) | Profiling |
| UHF or passive RFID reads (`36`) | Tracking without the person's awareness |
| AI over personal data (`133`) | New purpose, new recipient |

## Encryption at rest for `persons` — the PV2 fix

1. **Custom encrypted casts** on `id_document_number` and `date_of_birth`, using a dedicated
   personal-data key rather than `APP_KEY`, so rotating one does not require re-encrypting the other.
2. **Blind index** for lookup at the desk ("find by passport number"):
   `id_document_number_bidx = HMAC-SHA256(normalized number, separate index key)`, indexed per account.
3. **A test that reads the raw column** and asserts it is not the plaintext — the only test that
   would have caught PV2.
4. Excluded from resources by default, from exports, from logs and from device sync.
5. Keys in the hosting secret store, never in `.env` files committed or shared; rotation by
   re-encryption job. Key location is `64`'s open question and must be answered first.

Nothing writes these columns yet, so this is a cast and a new column, not a data migration.

## Telemetry, logs, exports

- **Sentry:** send the user id and nothing else (PV3); keep SQL bindings off.
- **Impersonation logs:** method, route, field names changed — no values (PV4). The audit trail
  proper moves to `audit_events` (`67`).
- **Exports:** every export writes an `audit_events` row (actor, event, export type, row count); the
  ticket-code column becomes opt-in (`68`); ID numbers and DOB are never exported.
- **Cookie consent:** on by default for ARZO deployments; absence of a banner means **no** optional
  pixels, not all of them; the Google Ads tag is gated by the advertising category (PV5). Organizer
  pixels on the checkout page are a PCI question as well (`66`).

## Processor register

`84` lists where personal data already leaves the application. Each processor needs a data-processing
agreement and a line in the privacy notice.

| Processor | Personal data it receives | When |
|---|---|---|
| Stripe | Buyer name, email, payment data | Every paid order |
| Mail provider (SMTP, SES, Mailgun or Postmark) | Recipient email, message content | Every email |
| Sentry | Today: platform user email, name, IP (PV3); request context | When a DSN is set |
| Bunny Fonts | Visitor IP on themed event pages (`84`) | Every themed page view |
| Google Ads, organizer pixels (Meta and others), Fathom | Browsing behaviour, identifiers | When configured — consent-gated after PV5 |
| Anthropic (`laravel/ai`) | Event content for spam checks, including organizer name and description (`EventSpamCheckContentService.php:33-60`) | SaaS mode only |
| Google Places | Addresses typed by organizers | When configured |
| Object storage host | Photos, documents | Always |
| Future: SMS, push, ESP (`42`, `43`, `44`) | Phone, device tokens, segments | When integrated |

## Data residency

Article 15 **forbids** a controller from restricting cross-border flows except where processing
breaches the law or may cause serious damage — the PDPL text itself does not require in-country
hosting. Government clients, sector rules and national information-assurance policies may — `UNVERIFIED`;
legal and each government client's contract must answer.

Where ARZO's data lives today is also `UNVERIFIED`. `backend/vapor.yml` is **upstream Hi.Events'**
production configuration (`domain: api.hi.events`, `vapor.yml:1-6`), and the deploy workflow's
`eu-west-1` (`deploy.yml:95`) belongs to that pipeline. ARZO's hosting region must be decided before
the first government client, because moving a live database between regions is an outage, not a
setting. `84` recommends defaulting to an in-country region; the legal input from this document is
that the PDPL text does not compel it, so the deciding factors are client contracts and sector policy.

## Breach notification

PDPL Article 14: notify the individual and the Competent Department where a breach may cause serious
damage; Article 13: processors notify controllers forthwith. GDPR Article 33: the supervisory
authority within 72 hours where feasible. The mechanism is an incident (`60`) of type `SECURITY` with
a `personal_data_breach` flag that starts the clock, a checklist of who must be told, and the
evidence retained in the incident timeline. The notification contacts are a runbook item (`126`).

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | `PersonAnonymizer` added to account anonymization, with a test that deletes an account and finds none of its names or emails (PV1, ARZ-323) | **Now** — before `2026_09_29_000008` runs against production data |
| 2 | Encrypted casts, blind index, raw-column test (PV2, ARZ-303) | **Now** — before any writer |
| 3 | Sentry user id only; impersonation log without values; consent defaults (PV3–PV5; ARZ-324, ARZ-312, ARZ-310) | **Now** — small |
| 4 | Anonymizers extended to the Phase 1–2 tables and IP columns (PV7, ARZ-323) | With each table's first writer |
| 5 | `consent_texts`, `consent_records`, backfill from `opted_into_marketing_at` | Before `33` and before any cross-event marketing (`42`) |
| 6 | Question `sensitivity` flag; export exclusions (PV9, PV10) | Low |
| 7 | Retention job: per-class windows, strip-then-delete, audited (PV8) | After legal sets the `UNVERIFIED` minima |
| 8 | `privacy_requests`, per-person export and erasure (PV6) | Before external SaaS; before the first large ARZO event |
| 9 | DPIA gate in readiness | With `59` |

## Open questions

- **Article 16 permission** — does ARZO need one, for which data, and how long does it take? Blocks medical incidents and minors' registration. Legal must answer.
- **Joint controllership with clients** — for ARZO-operated events, is the client a joint controller? Determines whose privacy notice attendees see and who answers requests.
- **Retention minima** — tax, labour and limitation periods under Qatari law are `UNVERIFIED`; every proposal in the table that touches them waits on legal.
- **DPO** — does ARZO appoint a data-protection officer? PDPL as read does not require one; GDPR may, depending on scale. Someone must own `privacy_requests` either way.
- **Cross-event identity** (`23`, `55`) — recognizing a returning journalist is useful and is profiling. Leaning: only with `CROSS_EVENT_PROFILE` consent, aggregate repeat rates without it.

## Related

`23-accreditation.md` · `24-access-control.md` · `33-exhibitor-lead-capture.md` ·
`42-email-marketing.md` · `54-attendance-intelligence.md` · `55-event-intelligence.md` ·
`60-incident-management.md` · `63-event-documentation.md` · `64-security.md` · `66-compliance.md` ·
`67-audit-logging.md` · `68-fraud-prevention.md` · `71-realtime-architecture.md` ·
`73-file-management.md` · `84-infrastructure.md` · `108-post-event-closeout.md` ·
`126-disaster-recovery-plan.md` · `133-ai-capabilities.md` · `136-master-backlog.md` ·
`120-risk-register.md` (R6)
