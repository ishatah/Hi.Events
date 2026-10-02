# Hardware Abstraction Layer

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P2 (ARZ-120, ARZ-121) · **Phase:** 4
**Depends on:** `71-realtime-architecture.md`, `24-access-control.md`
**Blocks:** `36-rfid-nfc.md`, `38-scanner-platform.md`, `39-printer-integration.md`, `40-device-management.md`

---

## Current state — `MISSING`, 0%

`CONFIRMED`: the entire hardware surface today is three browser APIs.

| Capability | Mechanism | Evidence |
|---|---|---|
| Camera QR | `getUserMedia` via `qr-scanner@1.4.2` | `InlineCameraScanner.tsx:2,32` |
| USB/Bluetooth barcode scanner | Keyboard-wedge `keypress` listener | `CheckIn/index.tsx:429-474` |
| Haptics | `navigator.vibrate` | `hooks/useHaptics.ts:23` |
| Printing | `window.print()` × 3 | `22`, F8 |

No WebUSB, Web Serial, WebHID, Web Bluetooth or Web NFC; no native app; no print host; no device
authentication. The `devices` table exists as of `c34f6a59`, with only a model and repository
behind it (`40`).

What **does** exist, and matters here: `AccessDecisionService` landed in `c34f6a59` as a pure
function of an `AccessContextDTO` — no database, no clock, no container — with 35 table-driven
tests. That is the piece a device must reproduce exactly.

## The decision that shapes everything: where adapters run

There are three places hardware code can live, and each device class belongs in a different one.

| Runtime | Can reach | Cannot | Fits |
|---|---|---|---|
| **Browser (PWA)** | Camera, keyboard wedge, Web NFC on Android Chrome | Printers without a dialog; iOS NFC; USB on Safari | Attendee app; lead capture (`33`) |
| **Native app** (Android/iOS) | Camera, NFC, Bluetooth, OS keystore | Most desktop printer drivers | Door scanners (`94`), kiosks (`96`) |
| **Local print host** — a small service on a mini-PC or laptop per desk or site | USB printers, vendor SDKs and drivers, PC/SC readers, raw TCP 9100 on the LAN | Anything the operator carries | Badge printing (`39`), UID encoding (`36`) |

**Recommendation: scanners in the client app, printers behind a print host.** Printer drivers and
SDKs are desktop-native, the browser cannot bypass the print dialog (`22`), and a print host keeps
working offline with the printer on USB.

The print host is a **new deployable** — hardware to image, stage and manage (`103`). It also answers
`71`'s open question about LAN deny-list gossip: the host is always on, always on the venue LAN, and
could relay revocations between devices without internet. Not in scope for v1, but it is the natural
home if ever wanted.

## Interfaces — defined by ARZO's needs, not by an SDK

`R14` in `120` is vendor lock-in through SDK-shaped interfaces. The discipline: **write the
interface before reading the SDK.**

```
CredentialReader
  start(), stop()
  onRead(ReadEvent)       -- { raw, identifierType, uid?, readerId, occurredAt }
  capabilities()          -- { types: [QR, BARCODE, HF_TAG], continuous: bool }

BadgePrinter
  capabilities()          -- { media: [{w_mm, h_mm}], dpi, colour, duplex, encoder }
  print(PrintJob)         -- -> PrintResult { status, printerError?, confirmedBy }
  status()                -- READY | PAPER_OUT | RIBBON_LOW | HEAD_OPEN | OFFLINE | ERROR

CredentialEncoder
  readUid()               -- -> uid
  encode(Credential)      -- -> { uid, chipType }

HealthReporter
  snapshot()              -- battery, storage, app version, queue depth, peripherals
```

Three rules for these interfaces:

1. **Capability negotiation, not lowest common denominator.** A printer that cannot print colour
   says so; the template (`22`) degrades rather than the interface shrinking to what every printer
   can do.
2. **Errors are normalized.** "Paper out" is one status whatever the vendor calls it, because the
   command center (`53`) alerts on it.
3. **Every adapter has a fake.** The app is developable and testable with no hardware attached
   (`79`). A `FakePrinter` that fails on the fifth job is how print-job recovery gets tested.

## Two implementations of one decision

`71` says the access decision "compiles to both" server and device. In practice the server is PHP
and devices are TypeScript or native, so **the same code cannot run in both places**. What can be
shared is the specification.

**Conformance by golden vectors.** Export the table-driven cases behind `AccessDecisionService`'s
35 tests as a language-neutral JSON file — context in, decision out — and run the identical file
against every device implementation in CI. A device build that disagrees with the server on any
vector fails.

This is the only realistic way to meet `24`'s requirement that "two implementations that disagree
at a door" never happens. The same approach applies to identifier recognition (`38`).

## Vendor commitment

`120` R5 and `04` both say hardware choice gates engineering. The recommended stance:

- **Commit to one printer family and one reader family for the first event.** Procurement lead
  times make this necessary anyway.
- **Build the second adapter only when a real event requires it.** An abstraction proven against
  one vendor is a guess; against two, it is a design. The interface above is the guess, kept
  deliberately small.

## Testing

| Layer | Approach |
|---|---|
| Adapter logic | Unit tests against fakes |
| Decision parity | Golden-vector conformance in CI (above) |
| Real hardware | A hardware-in-the-loop rig — one of each supported printer and reader — run before every event release (`79`) |
| Failure modes | Paper out, head open, USB unplug, printer power-cycle mid-job, reader disconnect |

## Open questions

- **Print host platform** — *resolved in `96`*: Windows, as an Electron application that is also the kiosk and desk app, in TypeScript.
- **Is a print host per desk or per site?** *Resolved in `96` and `102`*: per desk — one print host and one printer per desk or kiosk.
- **Commit to vendors now?** Recommended for the first family; `102` owns the purchase.
- **Language for the print host** — *resolved in `96`*: TypeScript, sharing the golden-vector runner and recognizers with the scanner app (`94`).

## Related

`36-rfid-nfc.md` · `38-scanner-platform.md` · `39-printer-integration.md` ·
`40-device-management.md` · `24-access-control.md` · `71-realtime-architecture.md` ·
`79-testing-strategy.md` · `102-hardware-procurement.md` · `103-hardware-deployment.md` ·
`120-risk-register.md`
