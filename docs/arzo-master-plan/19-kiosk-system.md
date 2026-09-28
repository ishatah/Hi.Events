# Kiosk System

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** New subsystem · **Phase:** 4
**Depends on:** `71-realtime-architecture.md`, `40-device-management.md`, `22-badge-design-printing.md`
**Blocks:** `96-kiosk-application.md`

---

## Current state — `MISSING`

`CONFIRMED`: the check-in surface is staff-operated throughout. There is no kiosk mode, no attract
screen, no lockdown, and no unattended flow.

## Why a kiosk is not a responsive scanner

Tempting to treat as a layout variant of `/check-in/:shortId`. It is not, and the differences are
behavioural rather than cosmetic:

| | Staff scanner | Kiosk |
|---|---|---|
| Operator present | Yes | **No** |
| Recovers from error | A human retries | Must self-recover |
| Trust model | Trained staff | Any member of the public |
| Session | Continuous | Must reset between users |
| Wrong input | Staff corrects | Must be impossible to get stuck |
| Idle | Irrelevant | Returns to attract screen |
| Accessibility | Desk-mediated | **Legally significant** — `81` |
| Printer jam | Staff notices | Nobody notices for an hour |

The last row is the one that decides the architecture: a kiosk must **detect its own failure and take
itself out of service**, because otherwise it silently accumulates a queue of people it cannot serve.

## Scope

**In:** self check-in by QR or name lookup; badge print on demand; walk-in registration (`17`) where
payment is cash-free or deferred; attract screen; idle reset; self-health reporting.

**Out:** card payment (`15`); anything requiring judgement (accreditation approval, capacity
override); lost-badge reissue, which needs identity verification a machine should not do.

## Design

### Lockdown
Kiosk mode means no browser chrome, no navigation away, no keyboard access to the OS, and no way to
reach the organizer UI. Enforced by the platform's kiosk mode (Android COSU, iPadOS Guided Access,
Windows Assigned Access), not by CSS.

### Session lifecycle
```
ATTRACT -> IDENTIFY -> CONFIRM -> PRINT -> THANK YOU -> ATTRACT
```
Every state has a timeout returning to `ATTRACT`, and **`ATTRACT` clears all personal data from the
screen and memory**. A kiosk showing the previous user's name is a privacy incident.

### Offline
Non-negotiable (`71`): local roster, local decision, local print, queued writes. A kiosk that stops
working when the venue wifi dips is worse than no kiosk, because people queue at it before
discovering it is dead.

### Self-health
The kiosk reports to the command center (`53`, `40`): online, last sync, printer status, stock level,
consecutive failures. After N consecutive failures it shows an out-of-service screen rather than
continuing to take people's time.

### Identity
Device identity only (`40`) — a scoped device key, never a user session. A kiosk in a public foyer
must not hold credentials that could do anything beyond its job.

## Accessibility

`81` owns the standard; kiosks are where it is legally sharpest, because they are unattended public
terminals. Reach height, screen angle, contrast in daylight, touch-target size, audio alternative,
and generous timeouts are requirements rather than polish.

## Hardware

**Not software** (`102`): tablet or all-in-one unit, enclosure, badge printer, mount or stand, power,
and network. Procurement drives the platform decision below.

## Open questions

- **Platform?** Android kiosk mode is cheapest; iPad is the most reliable hardware; Windows has the best printer driver story. Printer support probably decides it — `39`.
- **One kiosk per printer, or shared network printer?** Shared is fewer printers and a worse failure mode.
- **Does the kiosk do walk-in registration, or only check-in?** Registration is far more valuable and much harder — forms, payment, duplicate risk. Recommend check-in first, registration second.
- **Who refills badge stock and clears jams?** An operating-model question (`106`), and the answer determines whether kiosks are viable at all for ARZO.

## Related

`17-onsite-registration.md` · `20-queue-management.md` · `22-badge-design-printing.md` ·
`40-device-management.md` · `71-realtime-architecture.md` · `81-accessibility.md` ·
`96-kiosk-application.md` · `102-hardware-procurement.md`
