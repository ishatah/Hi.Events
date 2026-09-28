# Attendee Mobile App — Scope

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** New subsystem · **Phase:** 3
**Depends on:** `27-sessions-tracks.md`, `29-agenda-scheduling.md`, `44-push-notifications.md`
**Blocks:** `31-networking.md`, `95-mobile-event-app.md`

---

## Current state — `MISSING`, and one trap

`CONFIRMED`: there is no PWA and no native app.

- `frontend/vite.config.ts` plugins are exactly `react()`, `lingui()`, `copy()` — **no VitePWA, no Workbox**
- No service worker anywhere; no IndexedDB, `idb`, `dexie` or `localforage`
- `networkMode: "always"` on queries **and** mutations actively opposes offline behaviour

**The trap:** `frontend/public/site.webmanifest` exists with `"display": "standalone"` and icons, and
is linked from `index.html`. The app is therefore **installable to a home screen and launches
chrome-less — with zero offline capability**. Installed and opened without network, it is a blank
page.

That is worse than not being installable, because it looks like an app and fails like a web page. Any
app work must fix this rather than inherit it.

The attendee-facing surface today is responsive web: `/my-tickets/:token` and
`/product/:eventId/:attendeeShortId`.

## What the app is for

Ordered by value, which is not the order they are usually built:

1. **Ticket and QR, available offline.** Attendees arrive when the venue network is worst. This alone justifies the app.
2. **Agenda** — the whole event programme plus a personal schedule. Blocked on `27` and `29`, which is why the app cannot precede Phase 3.
3. **Notifications** — room changes, delays, gate changes. The operational value, and needs `44`.
4. **Venue map** — needs the space model (`25`), which now exists.
5. Networking, polls, eRaffle (`31`) — later, and genuinely optional.

**Without the agenda the app is a ticket wallet.** That is the core scoping argument: build it after
the programme exists, or build something that does not justify its maintenance cost.

## PWA or native — the decision

| | PWA | Native |
|---|---|---|
| Codebase | Shares the React app | Separate |
| Offline tickets | Service worker + IndexedDB — adequate | Better |
| Push | Web push; **iOS support is weaker** | Full |
| Install friction | None | App store |
| Release cycle | Instant | Review delays |
| Encrypted at rest | **No equivalent** | OS keystore |

For **attendees**, a PWA is probably sufficient: the data is the attendee's own ticket and agenda, so
the at-rest encryption gap matters far less than it does for a scanner holding the full roster
(`94`, threat T3).

**Recommendation: PWA for attendees, native for scanner and kiosk.** That splits on the actual
requirement — data sensitivity and hardware access — rather than on tooling preference.

The counter-argument to weigh: two delivery models is two sets of build and release plumbing. If iOS
push proves inadequate for event-day operations, native wins and the split disappears.

## Offline requirements

Per `71`, but narrower than the scanner:

| Capability | Offline |
|---|---|
| Ticket + QR display | **Required** |
| Personal agenda | **Required** |
| Full programme | Cached, best-effort |
| Venue map | Cached |
| Session registration | Queued, reconciled |
| Notifications | Online only, by nature |

Removing `networkMode: "always"` for app routes is a prerequisite, and it must be scoped rather than
global — the organizer UI relies on the current behaviour.

## Out of scope

Stated to prevent scope creep, since attendee apps attract feature requests:

- Ticket transfer and resale — a commerce and fraud question (`68`), not an app feature
- Payment inside the app
- Social feeds
- Gamification beyond eRaffle

## Open questions

- **PWA or native?** Above. Recommendation given; the decision is real and belongs in `95`.
- **Do attendees get accounts, or stay on magic links?** Magic link is lower friction and limits what can be built — no cross-event history, no saved preferences. A genuine product trade-off.
- **Does the app share the SSR frontend or become a separate build?** Sharing reuses components and drags the whole bundle along.
- **Offline ticket validity** — how long may a cached ticket remain scannable if the device never syncs? A revoked ticket could otherwise be presented indefinitely.

## Related

`27-sessions-tracks.md` · `29-agenda-scheduling.md` · `31-networking.md` ·
`44-push-notifications.md` · `71-realtime-architecture.md` · `91-attendee-platform.md` ·
`94-mobile-scanner.md` · `95-mobile-event-app.md`
