# Push Notifications

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P2 (ARZ-141) · **Phase:** 3, with the app
**Depends on:** `30-mobile-event-app.md`, `69-notifications-architecture.md`
**Blocks:** `29-agenda-scheduling.md` (change notifications), `60-incident-management.md` (supervisor alerts)

---

## Current state — `MISSING`, 0%

`CONFIRMED`:

| Fact | Evidence |
|---|---|
| No web push, FCM, APNs, OneSignal or Firebase code | Search |
| **No service worker** — the prerequisite for web push | Search for `serviceWorker`, `workbox` |
| `site.webmanifest` declares `display: standalone` with **no `start_url` and no `scope`** | `frontend/public/site.webmanifest` |
| No Laravel `Notification` classes, no notifications table; `User` has the `Notifiable` trait and nothing calls `notify()` | Search |
| `announcements` reach **platform users only** — one banner and one modal at a time | `GetActiveAnnouncementsHandler.php:26-29`; routes under `auth:api` |
| `devices` is for scanners and kiosks, not push tokens | `40` |

There is no channel today by which ARZO can reach an attendee's phone except email.

## Two audiences, different cases

| | Attendees (`30`) | Staff and supervisors (`92`, `97`) |
|---|---|---|
| Value | Room changes, delays, gate changes, session starting | Queue alerts, incidents, device failures, callouts |
| App | PWA, per `30`'s recommendation | Native, per `94` / `97` |
| Device | Their own phone, any OS | Often ARZO-managed |
| Delivery reliability | Best-effort is acceptable | **Must be monitored** — a missed incident alert is an operational failure |

Staff push is the **higher-value, more controllable** case: known devices, native apps, a small
audience that wants the messages. It should not wait for attendee push.

## Web push, and the iOS constraint

`30` recommends a PWA for attendees. Web push on a PWA works as follows:

- **Android and desktop browsers:** supported with a service worker and VAPID keys.
- **iPhone and iPad:** supported from iOS 16.4, **only for web apps added to the Home Screen**,
  and only after the user grants permission from a gesture inside the installed app.

The consequence: attendee push on iPhone reaches only people who installed the PWA and then opted
in — likely a minority at a one-day event. That is the specific trade-off `30` names: if event-day
push proves inadequate on iOS, native wins and the PWA/native split disappears.

**Decision rule:** measure the installed-and-opted-in share at the first pilot. If operational
messages must reach most attendees, push cannot be the only channel regardless of platform — which
is why SMS or WhatsApp (`43`) carries the critical operational messages.

## Categories and preferences

| Category | Default | Opt-out |
|---|---|---|
| `OPERATIONAL` — room moved, delay, gate change, cancellation | On | Allowed |
| `REMINDER` — your session starts in 10 minutes | On | Allowed |
| `NETWORKING` — meeting request, connection (`31`) | On if networking is enabled | Allowed |
| `MARKETING` | **Off, and not offered in v1** | — |

Over-sending is how push permission gets revoked; one promotional push can cost the channel for the
rest of the event.

**Push is not a safety system.** Life-safety instructions travel by venue PA and staff. Push is
best-effort, can be delayed or throttled by the OS, and must never be the only route for a safety
message.

## Payload privacy

Notifications appear on lock screens. Payloads carry **no personal data and no ticket codes** —
"Your 14:00 session has moved. Tap for details." The detail is fetched from the app after unlock.

## Model

```
push_subscriptions
  id, subscriber_type,          -- ATTENDEE | USER | DEVICE (staff and ops devices, 97)
  subscriber_id, event_id NULL,
  platform,                     -- WEB | FCM | APNS
  endpoint text NULL, token text NULL, keys jsonb NULL,   -- web push: p256dh, auth
  categories jsonb,             -- per-category opt-outs
  user_agent NULL, locale,
  created_at, last_success_at NULL, failure_count int default 0,
  revoked_at NULL
  INDEX (subscriber_type, subscriber_id), INDEX (event_id)
```

- Attendee subscriptions bind to the attendee, per event — consistent with magic-link identity
  (`30`). If attendee accounts arrive, they rebind to the person.
- A `404`/`410` from the push service revokes the subscription immediately. Dead endpoints
  accumulate otherwise and slow every fan-out.

## Delivery

- Fan-out is a queued job per notification, chunked, never inline in a request.
- Send, delivery and failure are recorded per recipient through the shared delivery log in `69`, so
  "sent" is never mistaken for "delivered".
- An agenda change (`29`) targets session registrants; a gate change targets credentials with a
  grant at that access point. Targeting comes from the domain model, not from free-text lists.
- Throughput to 10,000 subscribers is `UNVERIFIED` against provider limits; test before the first
  large event.

## Migration

| Step | Change |
|---|---|
| 1 | Service worker (with `30`'s offline work) and a corrected manifest — `start_url`, `scope` |
| 2 | VAPID keys, `push_subscriptions`, subscribe and unsubscribe endpoints |
| 3 | Push channel adapter behind `69` |
| 4 | Native FCM/APNs for the staff app (`97`) |
| 5 | Domain triggers — session changed, gate changed, meeting requested |

## Open questions

- **Web push only, or native for attendees as well?** Decided by the pilot measurement above.
- **Who may send an ad-hoc push?** A permission (`message.send` exists) and a review step for large audiences, mirroring email tiers.
- **Quiet hours** for reminders on multi-day events.
- **Where do VAPID keys live**, and how are they rotated? Rotation invalidates every web subscription.

## Related

`30-mobile-event-app.md` · `43-sms-notifications.md` · `69-notifications-architecture.md` ·
`29-agenda-scheduling.md` · `31-networking.md` · `60-incident-management.md` ·
`92-staff-platform.md` · `97-onsite-operations-app.md`
