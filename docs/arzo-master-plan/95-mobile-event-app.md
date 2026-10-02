# Attendee Application Build

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Extend — a PWA inside the existing SSR app · **Priority:** P2 (ARZ-150, ARZ-151, ARZ-141) · **Phase:** 3; the manifest fix now
**Depends on:** `30-mobile-event-app.md`, `27-sessions-tracks.md`, `29-agenda-scheduling.md`, `44-push-notifications.md`, `71-realtime-architecture.md`
**Blocks:** `31-networking.md`, `93-exhibitor-platform.md` (shares the `/m/` service worker)

---

`30` decided **a PWA for attendees**; `116` carried it into Phase 3. This document decides how it is
built: where the code lives, what the service worker does, what is stored on the phone, and how the
installable-but-blank trap is removed.

## Current state — `MISSING`, ~10%: responsive ticket pages and a trap

| Fact | Evidence |
|---|---|
| No service worker, Workbox, IndexedDB, `idb`, `dexie` or `localforage` | Search of `frontend/src`; `vite.config.ts:32-43` has only `react()`, `lingui()`, `copy()` |
| **The trap:** the manifest says `"display": "standalone"` with no `start_url` and no `scope`, and is linked from every page | `public/site.webmanifest:23`; `index.html:17` |
| `networkMode: "always"` for queries and mutations, in the browser and in SSR | `utilites/queryClient.ts:8,11`; `entry.server.tsx:35,38` |
| The client **always hydrates** server markup | `entry.client.tsx:40` (`hydrateRoot`) |
| The ticket email links to `/product/{eventId}/{attendeeShortId}`; the short id is `a_` + 13 random mixed-case characters | `AttendeeTicketMail.php:87-91`; `config/app.php:61`; `IdHelper.php:27-30`; `router.tsx:656` |
| That page reads `GET /public/events/{id}/attendees/{short_id}` | `api.php:632` |
| The QR is the bare `public_id` | `AttendeeTicket/index.tsx:151-152` |
| `/my-tickets/:token` links **expire after 24 hours**; the token is stored in plaintext | `SendTicketLookupEmailHandler.php:21,74-75`; `router.tsx:672` |
| No public programme endpoint; the session routes in the working tree are authenticated CRUD | `api.php` (working tree, uncommitted) |
| Static files are served by `sirv` before the SSR catch-all | `server.js:85,126` |
| 20 locales, no Arabic; the English catalog alone is ~157 KB | `lingui.config.ts`; `src/locales/en.js` |
| A local client build holds 518 assets, 8.1 MB — build date unknown, so indicative only | `frontend/dist/client/assets` |

The last row matters for the design: precaching "the app" means precaching the organizer dashboard.
The attendee app must be a **slice**.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| A1 | **Installable, chrome-less, blank offline.** Any page — including the organizer dashboard — can be added to a home screen and launches as an app that shows nothing without network. | `site.webmanifest:23`; `index.html:17` | Medium — **fix now**: set `display` to `browser` until `/m/` ships its own manifest |
| A2 | Ticket-lookup tokens stored in plaintext | `SendTicketLookupEmailHandler.php:74` | Low — 24-hour expiry bounds it; hash on the next change |

## Decision: inside the SSR app, under `/m/` — not a separate build

| | Inside the SSR app | Separate Vite app |
|---|---|---|
| Ticket rendering, QR, theme, organizer colours, Lingui catalogs | Reused as they are | Copied, or shared through a workspace that does not exist |
| First open from the ticket email | Server-rendered, fast, shareable | Client-rendered |
| Deploy | The existing pipeline | A new one |
| Bundle | Must be sliced — the hard part | Small by construction |
| SSR complications | Offline boot needs a shell (below) | None |

**Inside, under a new `/m/` prefix** — free today (`router.tsx` has no `/m` route). The prefix does
the work a separate build would: the service worker, the manifest scope, the precache list and the
`networkMode` change all attach to `/m/` and **nothing outside it**. The organizer dashboard keeps
its current behaviour, which `30` requires.

```
/m/                                   launcher: tickets held on this device
/m/t/:eventId/:attendeeShortId        ticket — QR, holder, product, gate information
/m/e/:eventId/programme               full programme (public data)
/m/e/:eventId/agenda                  my agenda — registrations and stars
/m/e/:eventId/map                     venue map (ARZ-151)
/m/staff                              staff self-service (92)
/m/leads                              exhibitor lead capture (93)
```

## Decision: identity stays the ticket link; the phone holds a wallet

No attendee accounts in v1 — `30`'s open question, answered for the build. The ticket email gains an
"Open in the app" link to `/m/t/...`, and the existing ticket page gains "Add to phone". Opening
either stores that ticket's short id on the device. A phone can hold several tickets; an order owner
can add every ticket in the order (unnumbered).

The short id is a bearer secret of roughly 77 bits that never expires — adequate for a ticket, and
exactly what the ticket page uses today. Networking (`31`) is what forces accounts; it is Phase 5.

## Service worker — Workbox through `vite-plugin-pwa`

- **`injectManifest`**, not `generateSW`: a hand-written worker is needed for push (`44`) and for a
  precache list filtered to `/m/` chunks. Behaviour inside this custom Express SSR setup is
  `UNVERIFIED` — the plugin runs in the client build (`build:ssr:client`), which is a standard Vite
  build; spike first.
- Emitted as `/m/sw.js`, so its default scope is `/m/` with no special header. Served with
  `Cache-Control: no-cache`, as `server.js:48` already does for `widget.js`.

| Request | Strategy |
|---|---|
| Navigations under `/m/` | Network first, 3 s timeout, then the **offline shell** |
| `/m/` JavaScript and CSS chunks, icons, fonts | Precached, revisioned; a size budget enforced in CI once measured |
| The attendee's locale catalog | Cache first after first use — not all 20 precached |
| Event cover, speaker photos, floor plan | Cache first, with expiry and an entry cap |
| API calls | Network only in the worker — the app stores what it needs in IndexedDB |

**Server-rendered pages are never cached.** They embed `__REHYDRATED_STATE__`, which is personal
data in a cache nobody manages. Offline, the worker serves a static shell, and `entry.client.tsx:40`
must choose `createRoot` instead of `hydrateRoot` when it boots from that shell. Small change;
without it the offline boot is a hydration error.

**Updates apply on next launch.** A new worker waits; it never reloads a ticket that is on screen
at a gate.

## IndexedDB — what the phone holds

```
tickets     attendee_short_id → event_id, qr_value, holder name, product, event name,
            starts_at, ends_at, timezone, venue summary, status, fetched_at
events      event_id → name, timezone, dates, venue, programme_version, fetched_at
programme   (event_id, session_id) → title, times, room, track, speaker names, status
agenda      (event_id, session_id) → source (REGISTERED | STARRED),
            status (CONFIRMED | WAITLISTED | PENDING_SYNC)
outbox      client_generated_id → session registration or cancellation
settings    locale, push state, install prompt dismissed
-- purge an event's records 7 days after it ends; "Remove from this device" on every ticket
```

- **Stars are device-only**; registrations are server-authoritative and capacity-limited (ARZ-080).
  An offline registration is `PENDING_SYNC` until the server confirms or refuses — refusal is shown,
  never hidden (`71`).
- The app's own queries set `networkMode: "offlineFirst"` per query. The global defaults in
  `queryClient.ts` and `entry.server.tsx` do not change.
- Unencrypted at rest, and acceptable: it is the attendee's own ticket and a public programme
  (`30`). Browsers may evict storage (`UNVERIFIED` per platform); the app requests persistent
  storage, and the email link remains the recovery path.

## Offline ticket validity — the `30` question, answered

**Validity is decided at the door, not in the wallet.** The QR carries an identifier, not a verdict
(`38` deferred signed QR). A revoked ticket shown from a stale cache is denied by every scanner that
has synced the deny-list; the only exposure is a scanner that is itself offline past the revocation —
risk R8 in `120`, bounded by `71`'s emergency mode and stated to clients. So the app does not expire tickets for
validity. It refreshes when online, shows "updated HH:MM", shows `CANCELLED` if that was the last
known status, and hides the ticket seven days after the event for housekeeping.

## Manifest and install

| Now (A1) | With `/m/` |
|---|---|
| `display: "browser"` in `site.webmanifest` | `/m/app.webmanifest`: `id`, `start_url: "/m/"`, `scope: "/m/"`, `display: "standalone"`, `lang`, `dir`; linked by the attendee routes. `/m/leads` and `/m/staff` link their own manifests with their own `id` and `start_url`, under the same worker |

- **Android:** capture `beforeinstallprompt`; offer install once the ticket has loaded, never on
  first paint.
- **iPhone:** no prompt API. One dismissible "Share, then Add to Home Screen" hint, in Safari only.
- Web push on iPhone needs iOS 16.4+, a Home Screen install and a permission gesture (`44`). The
  **installed-and-opted-in share at the pilot** is the metric `44` uses to decide whether attendee
  push is enough. It will not be enough for critical messages; those go by SMS or WhatsApp (`43`).

## Scope

**In:** offline ticket and QR; programme; personal agenda; session registration (queued);
notifications (`44`); venue map (ARZ-151); links to the ICS feed (ARZ-084).
**Out** (`30`): transfer and resale, payment, social feeds, gamification beyond eRaffle. **No
organizer tracking pixels on `/m/`** — they load without consent by default today, and a ticket
wallet is not a marketing surface.

## Release, telemetry, tests

| Concern | Approach |
|---|---|
| Release | The SSR app's pipeline and `123`'s event-aware freeze; the worker version is part of the release |
| Telemetry | First-party, aggregated: installs (`appinstalled`), push opt-in rate, offline launches, worker versions in the field |
| Unit | Vitest over the store and outbox with an in-memory IndexedDB |
| E2E | Playwright: load a ticket, `context.setOffline(true)`, reload `/m/`, ticket visible — tagged `@smoke`; `116` exit criterion 11 |
| Real devices | Manual matrix before the pilot: iPhone Home Screen app, Android Chrome installed and in-browser |

## Build order

| Stage | Content | Backlog |
|---|---|---|
| A0 — now | A1 fix: `display: "browser"` | unnumbered, S |
| A1 | `/m/` shell, service worker, offline boot, ticket wallet | ARZ-150 |
| A2 | Public programme endpoint; programme and personal agenda; queued registration | ARZ-150 after ARZ-083, ARZ-080 |
| A3 | Web push with VAPID (`44` steps 1–3) | ARZ-141 |
| A4 | Venue map, image-based | ARZ-151 |
| Later | Networking, eRaffle | ARZ-210, ARZ-211 |

A1 can ship before the programme exists. `30`'s warning — without an agenda the app is a ticket
wallet — argues against **promoting** it early, not against fixing the trap and caching tickets.

## Open questions

- **Apple and Google Wallet passes as the offline ticket?** A barcode pass needs no install and is not subject to browser eviction; it needs signing certificates and issuer accounts (terms and lead time `UNVERIFIED`). Leaning: add one if the pilot's iPhone install share is low.
- **Arabic on `/m/` first?** `82` says attendee-facing surfaces need it before organizer ones. If Arabic is required (`04`), `/m/` is where RTL is built first.
- **Should attendees see which exhibitors scanned them** (`33`)? It would live here.
- **Precache budget** — set once the `/m/` slice is measured, then enforced in CI.

## Related

`30-mobile-event-app.md` · `44-push-notifications.md` · `71-realtime-architecture.md` ·
`27-sessions-tracks.md` · `29-agenda-scheduling.md` · `31-networking.md` · `38-scanner-platform.md` ·
`43-sms-notifications.md` · `82-localization.md` · `91-attendee-platform.md` · `92-staff-platform.md` ·
`93-exhibitor-platform.md` · `116-phase-3.md` · `120-risk-register.md` · `123-release-strategy.md`
