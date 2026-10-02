# Scanner Application

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (native app) + Keep (web scanner, narrowed) · **Priority:** P2 (ARZ-152, ARZ-120, ARZ-122) on P1 prerequisites (ARZ-092, ARZ-100–103) · **Phase:** 4; extraction and the N-defect fixes now
**Depends on:** `71-realtime-architecture.md`, `38-scanner-platform.md`, `40-device-management.md`, `37-hardware-integration.md`, `92-staff-platform.md`
**Blocks:** `96-kiosk-application.md` (shared core), `97-onsite-operations-app.md`, `36-rfid-nfc.md` (phone readers)

---

`30`, `38`, `40` and `71` settled that door scanning runs in a native app: at-rest encryption for a
device carrying the roster, NFC on iPhone, and a process that stays alive all day. This document
decides how that app is built, shared, released and tested — and what happens to the web scanner.

## Current state — `PARTIAL`, ~25%: a good online web scanner, no native app

### The web scanner — `CONFIRMED`

| Fact | Evidence |
|---|---|
| 17 files, **3,771 LOC** (1,974 TS/TSX, 1,797 SCSS); `02`'s 3,752 predates the F1 fix | `frontend/src/components/layouts/CheckIn/` at `e7228c1d` (3,752 at `7dec84ca`) |
| Route `/check-in/:checkInListShortId`; seven public routes authenticated by the list `short_id` alone | `router.tsx:664`; `api.php:655-661` |
| Recognition is **one inline check**, not a module: `barcode.startsWith("A-")` | `CheckIn/index.tsx:410-414`, repeated in the wedge timer at `:455` |
| Keyboard wedge reads `e.key` (S1, Arabic layout) | `index.tsx:429-474` |
| Camera: `qr-scanner`, 1 decode/s, 1,000 ms debounce, torch, camera picker | `InlineCameraScanner.tsx:24,37,87-105` |
| Online only after the F1 fix: offline, the operator is told to scan again; no outbox | `index.tsx:271-275`; `utilites/queryClient.ts:8,11` (`networkMode: "always"`) |
| **No frontend test touches the scanner** — 24 tests in 2 files; 3 E2E tests on the check-in app, none drives a scan | `utilites/*.test.ts`; `e2e/tests/check-in/check-in-app.spec.ts:20-55` |

### What is reusable in a native app — less than the LOC suggests

| Piece | Where | Reuse |
|---|---|---|
| Recognizer | Inline, `index.tsx:410-414` | **Logic** — after extraction to a pure module |
| Wedge buffering | `index.tsx:429-474`, with S1/S2 defects | Rewrite against key-code vectors; native uses vendor intents |
| Camera | `InlineCameraScanner.tsx` — DOM `<video>`, `getUserMedia` | None — native camera module |
| Sounds | `public/sounds/scan-success.wav`, `scan-error.wav` | Assets as-is (`scan-in-progress.wav` is referenced nowhere) |
| Haptics | Pattern table `useHaptics.ts:6-11`; `navigator.vibrate` | Values only |
| Occurrence filter | `hooks/useCheckInOccurrenceFilter.ts` (router + `localStorage`) | Behaviour only; device config replaces it |
| Recent scans (20, in memory) and undo (DELETE) | `index.tsx:35,195-225` | Replaced — the local log persists, and append-only logs have no delete |
| Lingui catalogs, design tokens | `src/locales/*.po`; Mantine theme | Message ids and colours, not components |

**No pure scanner logic exists today.** Code sharing with a native app starts with extraction, not
with an import.

### Toolchain and repository shape — `CONFIRMED`

| Fact | Evidence |
|---|---|
| React `^19.2.0`, TypeScript `^5.8.3`, Vite `^5.4.19`, TanStack Query `5.102.2`, Lingui `4.14.1`, Yarn `1.22.22` | `frontend/package.json:73,106,107,49,27,116` |
| **Not a workspace**: three independent `package.json` (backend, e2e, frontend); no root manifest | Repository root |
| The frontend build context is `frontend/` in every pipeline | `Dockerfile.all-in-one:3-15`; `docker/frontend/docker-compose.yml` (`context: ./../../frontend`); `frontend-tests.yml:7-12,26` |
| No React Native, Expo, Flutter or native project anywhere | Search |

### The first identity-bearing endpoint — in progress

`POST /events/{id}/access-scans` under `auth:api` was **uncommitted, in progress at audit time**
(`api.php:618` in the working tree; `RecordAccessScanAction.php:23-38`). It takes `identifier`,
`access_point_id`, `direction`, `client_generated_id` and `occurred_at`, calls the committed
`AccessScanService`, and returns 200 with the verdict even for denials, so an offline queue never
retries a settled decision. It serves staff with platform logins. Devices need `40`'s key guard.

## Defects — fix before any device code is written

| # | Defect | Evidence | Severity |
|---|---|---|---|
| N1 | **Daily windows and days-of-week are evaluated in UTC.** `AccessContextDTO::venueTimezone` is never read; the decision compares `$now->format('H:i:s')` on a UTC Carbon. A 09:00–17:00 window at a Doha venue (UTC+3) opens at 12:00 local. | `AccessDecisionService.php:205,226`; `AccessScanService.php:83`; `config/app.php:147` | **High** — and golden vectors exported now would encode it |
| N2 | **The access point is not scoped to the event.** It is fetched by id alone, and the request rule checks existence only, so a user of one account can write logs against another tenant's access point and zone. | `AccessScanService.php:61-64`; `RecordAccessScanRequest.php:14-18` (uncommitted) | **High** once the route ships — risk R2 in `120` |
| N3 | **Every scan stores the plaintext credential identifier** in `access_logs.raw_identifier`. A log export or backup is a list of working badges. | `AccessScanService.php:221` | Medium — store nothing when resolved, a hash when not |
| N4 | `scan()` accepts `?int $deviceId` and drops it — there is no column | `AccessScanService.php:38`; live DB | Low — dead parameter until `40` adds the column |
| N5 | Haptics can never be switched off: `scannerHapticsOn` is read, nothing writes it | `useHaptics.ts:18`; search | Low |

N1–N3 are latent: `AccessScanService` has no committed caller. They become live the day the
in-progress route merges, which is why they belong in this week, not in Phase 4.

## Decision: React Native with Expo and a dev client — not Flutter, not Kotlin and Swift

| | **React Native + Expo** | Flutter | Kotlin + Swift |
|---|---|---|---|
| Team fit | The team writes React 19 and TypeScript daily | New language (Dart) | Two new stacks |
| Shares the TS core (recognizers, decision port, sync client) | **Directly** | No — reimplement; vectors still apply | No — reimplement twice |
| Encrypted store | `expo-sqlite` with `useSQLCipher` — documented, not available in Expo Go (docs.expo.dev, SQLite) | Plugin, `UNVERIFIED` | Native, strongest |
| NFC | `react-native-nfc-manager`, with an Expo config plugin (its README and wiki) | Plugin | Native APIs |
| Camera QR throughput | Native module (Vision Camera or `expo-camera`) — `UNVERIFIED` on target hardware | Strong | Strongest |
| Dedicated imagers (Zebra, Honeywell) | A thin native module over the vendor's broadcast intents — to write | To write | Vendor SDK |
| Upgrade churn | **Highest** | Medium | Lowest |

**React Native wins on the argument that decides the others: the decision function and recognizers
must be identical on every device, and a TypeScript core shared by the scanner, the kiosk (`96`), the
lead-capture PWA (`93`) and the web scanner is one implementation instead of three.** Golden
vectors make divergence detectable; a shared core makes it rare.

A **dev client** is required, not optional: SQLCipher, NFC and the camera module are native code
that Expo Go cannot load.

The objection is churn. Answer: at most five native modules (camera, NFC, SQLite, secure storage,
imager intents), the Expo SDK pinned for an event season and upgraded between seasons, and a spike
on the procured hardware before commitment (build order N1).

## Decision: one pure TypeScript core, and a workspace only when the app starts

```
packages/arzo-core            -- no React, no DOM, no React Native, no runtime dependencies
  recognize/                  -- identifier formats (38), wedge key-code → string (S1 fix)
  decide/                     -- port of AccessDecisionService, offline variant
  sync/                       -- outbox, cursor, backoff, deny-list-first delta application
  feedback/                   -- AccessResult → reason category, tone, haptic pattern
spec/golden/                  -- language-neutral vectors, owned by the PHP tests
```

- **Now:** extract the recognizer, wedge decoder and feedback map into `frontend/src/arzo-core/`
  with an ESLint `no-restricted-imports` boundary (no `react`, no `@mantine/*`, no DOM globals). The
  web scanner uses it immediately; the S1 fix lands there.
- **When ARZ-152 starts:** move the directory to `packages/arzo-core`, add a root workspace, and
  change the three build contexts above in the same pull request. Paying the workspace cost before
  a second consumer exists buys nothing.
- **Purity is the design, not tidiness.** Expo's monorepo guide states that duplicate React
  versions in one app cause runtime errors. A core with no React dependency cannot cause that.
- Workspace tool: the frontend's Yarn, unless the spike shows Expo's monorepo support needs another
  package manager — `UNVERIFIED`.

## Golden vectors — the mechanics

```
spec/golden/access-decision/v1/*.json
{ "name": "denies outside daily window in venue time",
  "context": { "credential": {...}, "grants": [...], "rules": [...],
               "access_point_id": 7, "zone_id": 3, "direction": "ENTRY",
               "occurred_at": "2026-11-02T05:30:00Z", "venue_timezone": "Asia/Qatar",
               "last_log_for_zone": null, "entry_count_for_zone": 0,
               "zone_occupancy": null, "zone_capacity": 500 },     -- null occupancy = offline variant
  "expected": { "result": "DENIED_TIME_WINDOW" } }
```

- **PHP owns the vectors.** A PHPUnit test runs every file through `AccessDecisionService` and fails
  on disagreement, so the JSON cannot drift from the server. The 35 existing cases are converted,
  not rewritten. Recognizer and wedge vectors follow the same pattern.
- **Fix N1 first**, and pin `venue_timezone` in every case.
- `decision_spec_version` = SHA-256 of the canonicalized vector set, compiled into both
  implementations, reported in the heartbeat (`40`) and checked at readiness (`59`: "devices on the
  current spec").
- **CI triggers must cross the language boundary.** `frontend-tests.yml:7-12` runs only on
  `frontend/**`; a PHP change that alters a vector would not run the TypeScript suite. Both suites
  trigger on `spec/**` and `app/Services/Domain/Access/**`.

## The app

One app for ARZO-owned phones and handhelds. What it does at a door is **device configuration**
(`38`, `40`), not an operator choice:

| Purpose | Mode | Writes |
|---|---|---|
| `ACCESS` | Gate: scan → local decision → feedback | `access_logs` |
| `SESSION` | Session door (ARZ-082) | `session_attendance` (and `access_logs` where the room is controlled, `27`) |
| `LOOKUP` | Help desk: search by name or order reference — never email | nothing |
| Supervisor role | Ops mode (`97`) — unlocked by the operator's role, not by purpose | overrides, incidents |

The device key (`arzod_`, `40`) authenticates the tablet; the operator signs in by scanning their own
staff credential (`92`). Both are recorded on every scan.

### Offline store — SQLite with SQLCipher

```
-- key: 256 random bits generated on first launch, held in Android Keystore / iOS Keychain
credentials   id, identifier_hash, legacy_hash NULL,       -- sha256 of an A- public_id
              display_name, credential_type, accreditation_type_code, status,
              valid_from, valid_until, photo_path NULL,     -- photos only for supervised points
              operator_role                                -- NONE | OPERATOR | SUPERVISOR (92)
grants        as access_grants: zone/room/session/access_point, starts_at, ends_at,
              days_of_week, time_from, time_to, max_entries, entries_used,
              allow_reentry, min_reentry_seconds, status
rules         the event's active rules; deny_list (applied before roster deltas)
config        event + venue timezone, access points, purpose, staff sign-in point,
              thresholds, decision_spec_version, sync_cursor
outbox        client_generated_id PK, identifier_hash, identifier_type, access_point_id,
              direction, result, occurred_at, operator_credential_id,
              override_by_credential_id NULL, override_reason NULL, synced_at NULL
-- never on the device: email, phone, ID document, date of birth, order, payment, answers
```

**Identifiers never rest in the clear.** The app hashes what it reads and matches hashes; the outbox
carries hashes. A decrypted store yields no working badge for 40-character credentials (`38`).
Legacy `A-` codes (~36 bits) are brute-forceable from their hash — one more reason to finish
ARZ-053 and print credentials.

### Inputs by platform

| Input | Android (fleet) | iPhone |
|---|---|---|
| Camera QR | Native module | Native module |
| Dedicated imager | Vendor intent module; wedge as fallback with the `code`-based decoder | — |
| NFC (HF, `36`) | Continuous reading | Session-based: each read is requested by the app with a system sheet (the library's `requestTechnology` model) — slower per person, `UNVERIFIED` in gate throughput |

**Recommendation: Android handhelds for the gate fleet, iPhone allowed.** Android runs in lock-task
mode under MDM and reads NFC continuously. "Background sync" matters less than it sounds: a gate
device is in the foreground all day, and sync runs in the app's own loop.

## Decision: the web scanner stays, narrowed

It is **the** scanner for self-serve organizers, who will never run MDM, and the fallback when native
devices run short. Its limits are stated, not fixed:

| Limit | Consequence |
|---|---|
| Online-only; no roster at rest, by design | A network drop stops it — it says so, per the F1 fix |
| No NFC on iPhone; camera through `qr-scanner` | QR only |
| Attribution | Device-bound link plus operator sign-in once `40` and `92` exist; the IP address until then |
| Not for high-security access points | Configured per access point |

It adopts `arzo-core` recognizers now (fixing S1, S3), writes `access_logs` after ARZ-041, and gains
no offline mode. An outbox without a local decision is "admit and hope"; the native app is the
offline answer.

## Release and update policy

| Channel | Devices | Distribution |
|---|---|---|
| `dev` | Engineers | Dev client builds |
| `pilot` | Hardware-in-the-loop rig, staging devices | Internal track through MDM |
| `stable` | Event fleet | Android Enterprise managed private app; iOS through Apple Business Manager — terms and lead time `UNVERIFIED` |

- **Pinned per event; never updated from `BUILD_UP` to event end** (`123`). An emergency fix follows
  `123`'s named-approver path and installs at a shift change, never by forced restart.
- Over-the-air JavaScript updates (EAS Update) use the same channels and the same freeze. They are
  convenient, not a way round it.
- The server supports N and N-1 (`123`); the fleet view flags older builds (`40`).
- **A server deploy that changes the decision is a device release.** `123`'s event-aware freeze
  already blocks it during operated events; outside them, `decision_spec_version` mismatch shows in
  the fleet view.

## Telemetry to `40` and `53`

`40`'s heartbeat plus: scans by result since the last beat, local decision p95, camera decode
failures, `unsynced_count` and **age of the oldest unsynced scan**, last successful sync, emergency
mode flag, signed-in operator, purpose, `decision_spec_version`. Crash reports through Sentry,
already used by both tiers, with breadcrumbs scrubbed of names and identifiers.

## Test strategy

| Layer | Approach |
|---|---|
| Core | Vitest over `arzo-core` with golden vectors; fake clock, fake network, fake reader (`37`) |
| Offline | `71`'s scenarios in the core: partition, flap, replay, skew, battery death mid-sync |
| App | Component tests with the fakes; device E2E with a mobile runner (Maestro or Detox — pick in the spike) on an Android emulator in CI |
| Hardware | The rig (`37`): each procured handheld, an iPhone, NTAG21x and NTAG 424 DNA tags, run before every `stable` release — `128` gate 24 |
| Pilot | Shadow run beside the web scanner at a low-stakes event; compare decisions row by row |

## Build order

Aligned with `117`, which puts the sync prototype first.

| Stage | Content | Backlog |
|---|---|---|
| N0 — now | Fix N1–N5 and S1; extract `arzo-core` into `frontend/src/`; convert the 35 cases to vectors | unnumbered; ARZ-060 |
| N1 | Spike on procured hardware: dev client, SQLCipher, decode rate, NFC UID, imager intents | ARZ-152 |
| N2 | Sync protocol prototype with partition and replay tests; TS decision passing all vectors | ARZ-101 |
| N3 | Device guard, pairing, heartbeat; `access_logs.device_id` and operator columns (`92`) | ARZ-092, ARZ-100 |
| N4 | Gate mode: workspace, operator sign-in, local decision, outbox, emergency banner | ARZ-152, ARZ-120, ARZ-103 |
| N5 | Reconciliation report; NFC; session and lookup modes | ARZ-102, ARZ-122, ARZ-082 |
| N6 | Fleet health; ops mode (`97`) | ARZ-123, ARZ-170, ARZ-202 |

ARZ-152 is sized L. That holds only if sync lives in ARZ-101 and ops mode is counted under `97`'s
items.

## Open questions

- **Public app-store listing for self-serve organizers?** It would give them native scanning with pairing codes. A buyer question (`04`); until answered, stores are private and the web scanner serves self-serve.
- **AGPL and app distribution (R1).** Whether a store- or MDM-distributed app built from this repository is compatible with AGPL-3.0 is for legal. Keep the native app's code ARZO-authored rather than porting AGPL UI code, so the answer stays open.
- **Which MDM?** An operational purchase (`40`, `103`); the app needs only lock-task and managed-config support.
- **Photos on the device** — only at supervised points, or everywhere? Photos dominate roster size and breach impact. Leaning: supervised points only.
- **Arabic on staff devices** — language set per device at enrolment; direction changes need an app reload in React Native (`UNVERIFIED` against the pinned SDK). Depends on whether Arabic is required (`82`).

## Related

`38-scanner-platform.md` · `37-hardware-integration.md` · `40-device-management.md` ·
`71-realtime-architecture.md` · `92-staff-platform.md` · `96-kiosk-application.md` ·
`97-onsite-operations-app.md` · `36-rfid-nfc.md` · `18-check-in.md` · `24-access-control.md` ·
`59-event-readiness.md` · `79-testing-strategy.md` · `117-phase-4.md` · `123-release-strategy.md` ·
`128-definition-of-done.md` · `120-risk-register.md`
