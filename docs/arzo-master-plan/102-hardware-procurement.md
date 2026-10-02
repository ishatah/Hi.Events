# Hardware Procurement

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Business commitment · **Priority:** P1 — business (R5); no ARZ id · **Phase:** evaluation units before Phase 2 exit; fleet before Phase 4
**Depends on:** `22-badge-design-printing.md`, `36-rfid-nfc.md`, `37-hardware-integration.md`, `39-printer-integration.md`, `62-procurement.md`
**Blocks:** `103-hardware-deployment.md`, `104-onsite-infrastructure.md`

---

What ARZO must buy or rent to run an operated event, how many of each, and which purchases gate
engineering. This is a business commitment, not code (`01`, `113`): `36`–`40` are not deliverable
without it, and `120` rates the missing commitment a high risk (R5). No price, lead time or vendor
specification appears below. Every figure that depends on one says **to be quoted** or `UNVERIFIED`.

## Current state — `MISSING`, 0%

| Fact | State | Evidence |
|---|---|---|
| Hardware commitment — buy, rent or partner | `UNVERIFIED` — recorded nowhere | R5 open in `120-risk-register.md`; `04` "Decisions needed before Phase 2" |
| ARZO-owned event hardware | `UNVERIFIED` — ask operations | No inventory, asset register or purchase record in the repository |
| Printer, reader or kiosk vendor code | `MISSING` | No ZPL, Zebra, Evolis, Brother, PC/SC, WebUSB, Web Serial or WebHID references (`37`, `39`) |
| Hardware the software drives today | Camera, keyboard-wedge scanner, `window.print()` | `InlineCameraScanner.tsx`, `CheckIn/index.tsx:429-474` (`37`) |
| Device registry | Table only; **no serial number or asset-tag column** | `2026_09_30_000003:132-161` |
| Procurement software | `MISSING` — `62` is a scaffold; ARZ-203 TODO | `136-master-backlog.md` |
| Target event profile to size against | `UNVERIFIED` | `04` open question; `74` calls its own assumptions "low confidence" |

The last row governs the document. Without the largest event ARZO intends to run, quantities can
only be formulas. They are given as formulas.

## Decision: size by formula, commit by pilot

Kit is sized by **peak arrival rate and service time**, not by attendee count. Ten thousand people
over eight hours needs little; the same ten thousand in thirty minutes needs a lot (`74`). Service
times are `UNVERIFIED` until measured, and the pilot event is where they get measured —
`access_point_throughput_snapshots.median_service_seconds` (`20`) for gates, print-job timestamps
(`39`) for desks. So: buy **evaluation units** now, size the **fleet** from pilot measurements.

### Inputs

| Symbol | Meaning | Source | State |
|---|---|---|---|
| A | Credentials issued | `event_operations.expected_attendance` (`56`) | Per event |
| p | Show rate | `54`; later `55` benchmarks | `UNVERIFIED` — use 1.0 until measured; it over-provisions |
| f, W | Share of arrivals inside the peak window; window length in minutes | `54` arrival curves | `UNVERIFIED` |
| λ | Peak arrivals per minute = A · p · f / W, split per access point or desk | Derived | |
| s | Seconds of service per person at a gate lane or desk | `20`, `39`; `17` targets < 60 s per walk-in | `UNVERIFIED` — measure at the pilot |
| ρ | Target utilization | Planning assumption: **0.8** | In the simplest queue model, waiting grows as ρ/(1−ρ): 4 at 0.8, 9 at 0.9 |

**Positions = ⌈λ · s / 60 / ρ⌉** — lanes per access point, desks per badge area.

### A consistency check on `74`

`74` assumes 20 concurrent scanners and 600 scans a minute at peak. Together those require every
scanner to finish a scan every 2 seconds at 100% utilization. That is optimistic for a lane where a
steward reads a screen and a person looks for their phone. At s = 6 s (equally `UNVERIFIED`) and
ρ = 0.8, 600 scans a minute needs **75 lanes**; at s = 2 s it needs 25. The pilot decides. Until
then `74`'s scanner count must not be used to buy.

The same applies to printers. `74`'s "60+ badges per hour per printer" is a floor far below its own
target of under 10 s per physical badge. Size desks by the desk's service time, then confirm the
printer's **measured** sustained rate exceeds the desk's; do not size printers from the floor.

## Per-event kit — bill of materials by device class

Every cost is **to be quoted**. Candidates are families named in `36`–`39`, not recommendations of
a model.

| Class | Purpose | Quantity per event | Spares | Candidates / note |
|---|---|---|---|---|
| Gate scanner | `ACCESS` and `SESSION` purposes (`38`) | One per lane; lanes per access point by the formula | N+1 per zone, hot | Dedicated Android scanners (Zebra, Honeywell — `38`) or phones; native app (`94`) |
| Supervisor tablet | Override, lookup, incidents (`40` `TABLET`, `97`) | One per gate supervisor, one per ops-room seat | +1 per site | Any tablet the native app supports |
| Print host | Drives USB printers and PC/SC readers (`37`) | **One per badge desk** (decision below) | N+1 per site, hot | Mini-PC or laptop; OS follows the printer family (`37`) |
| Thermal label printer | On-demand paper badges (`39`) | **One per desk**; desks by the formula | N+1 per desk cluster, hot | Zebra ZD-series, Brother QL (`39`) |
| Desktop laser | Pre-printing known attendees at base (`39`) | 1–2 at base; none on site | — | Any office printer |
| Card printer (CR80) | Photo ID, RFID cards | Only when a client requires them | N+1 | Evolis, Zebra ZC, HID Fargo (`39`) |
| HF desktop reader | UID association at issue (`36`) | One per desk issuing RFID media | N+1 per site | PC/SC USB; chosen after the chip family |
| Kiosk unit | Self check-in (`19`) | None before ARZ-110 | N+1 if deployed | Tablet + enclosure + printer; platform per `96` |
| Network kit | Ops router with cellular failover, access points, switch, cables | Per the `104` survey | Router N+1 | `104` owns the specification |
| Power | UPS per print station, power strips, chargers | One UPS per desk; one ops-room UPS | +1 UPS | Sizing in `104` |
| Charging and transport | Charging station, rugged cases, tamper seals | Per device count | — | `103` |
| Comms | Two-way radios — the out-of-band channel `107` depends on | One per supervisor and lead, plus the ops room | +2 | Venue-supplied or rented |
| Ops room | Laptops, a display for the command center (`53`) | 1–2 laptops, 1 display | +1 laptop | — |

### Decision: one print host and one printer per desk

`37` and `39` left both open. **Per desk.** A host or printer failure then stops one desk, not the
badge area, and every unit is interchangeable with the spare. A shared host per site puts a LAN
between desks and printing — the network `104` assumes will fail. The cost is one small PC and one
printer per desk, which is the price of failure isolation.

## Spares policy — N+1 minimum per class, and why

1. **N+1 per class per site, minimum.** Plus ⌈N/10⌉ for classes above ten units — a planning
   assumption, to be replaced by the failure rate `device_status_events` records (`40`) after three
   events.
2. **Spares for single-point positions are hot:** imaged, enrolled, synced and on the desk cluster.
   A spare in a box costs a pairing (10-minute code, `40`) plus a full roster sync (`71`: < 60 s for
   10k, `UNVERIFIED` on venue bandwidth) — during the outage, with a queue watching.
3. **Spares sit where the swap happens** — per zone for scanners, per desk cluster for printers.

The justification: the failure that matters is one unit dying at doors-open. Nobody can diagnose a
jammed printer with forty people waiting; a spare turns an outage into a two-minute swap, and the
fault is diagnosed later. N+1 covers one failure. It does **not** cover a systemic failure — bad
firmware on every unit, a wrong image. That is what the hardware-in-the-loop rig (`37`), the staging
soak (`103`) and the paper fallback (`107`) are for.

## Consumables per event

Opex per event, not capital. Roll up to event cost through `62`.

| Item | Quantity | Assumption |
|---|---|---|
| Badge stock | C · (1 + r) + w per printer | C = badges to print, pre-print and on demand; r = reprint rate, **planned at 10%** (`UNVERIFIED` — replace with the measured rate from `51`'s badges report); w = calibration waste per printer, vendor figure |
| Pre-print sheets | ⌈C_pre / k⌉ plus a spoil allowance | k = badges per sheet for the chosen stock; no-show badges are waste by design (`39`) |
| Ribbons | ⌈badges / ribbon yield⌉ + 1 per printer | Only for ribbon printers; yield is a vendor figure |
| Lanyards and holders | C · (1 + l) | l = loss and breakage, `UNVERIFIED` |
| RFID media | C_rfid · (1 + r) + encoder test waste | Only when RFID is committed (`36`); pre-manufactured wristbands with a UID manifest are the longest-lead consumable |
| Wristbands | Attendees × days if the colour changes daily; attendees otherwise | Policy per event |
| Printer cleaning kits | One per printer per event | Vendor guidance |
| Printed runbooks, fallback rosters | Per `107` | Personal data — counted out and back for shredding (`103`) |
| Cellular data | Per SIM | To be quoted (`104`) |

## Buy versus rent

Buy an item when **E · L · R > P + L · M + S − V**, where E = operated events per year that need it,
L = useful life in years, R = rental cost per event, P = purchase price, M = annual support, S =
storage and re-staging labour over its life, V = resale value. Every input is to be quoted, and E
depends on ARZO's event calendar (`UNVERIFIED`).

Cost is not the only term:

| Factor | Favours | Why |
|---|---|---|
| The device stores the roster (scanner, kiosk, print host) | **Buy** | A rented device that held names and photos goes back to a third party; the wipe must be one ARZO trusts (`103`) and the contract must say so |
| Native app, MDM enrolment, imaging | Buy | Staging is per device (`103`); re-imaging a rental pool every event is labour and risk |
| Parity with the tested hardware (`37`) | Buy one family | Adapters and the HIL rig are proven against one printer and one reader family; a rental pool of mixed models breaks that |
| Surge for one large event | Rent | Peak far above typical need |
| Peak-season availability | Buy the baseline | Rental pools are contended when events cluster — `UNVERIFIED` for ARZO's calendar |

**Recommendation:** own a baseline fleet sized for ARZO's typical operated event, in one printer
family and one scanner family. Rent surge units **from the same families only**, and never rent a
storage-bearing device without a written sanitization clause. Kiosks are deferred to ARZ-110 and
rented for their first deployments. Consumables are always bought.

## Procurement decisions that gate engineering

**Latest responsible moment (LRM):** evaluation units by *start of the dependent engineering item −
evaluation-unit lead time*; the fleet by *first operated event − (fleet lead time + staging (`103`) +
pilot)*. Lead times are to be quoted; the formula is the commitment.

| Decision | Drives | Engineering item | LRM |
|---|---|---|---|
| **Thermal printer family** (Zebra ZPL or Brother raster) | `39` adapter language; render DPI and media sizes (`22`, badges print as raster at printer DPI); print-host OS (`37`) | ARZ-071, ARZ-121 | Evaluation units **before ARZ-071 chooses its renderer** — it must render at the printer's DPI and pass the Arabic test (`39`) |
| Card printers at all | Encoder path (`36`), card templates | ARZ-121, ARZ-122 | When a client first requires photo ID or RFID cards |
| **HF chip family** (NTAG21x default, NTAG 424 DNA high-security) and desktop reader | `credential_media.chip_type`, SUN verification (`36`); `CredentialEncoder` (`37`) | ARZ-122 | Before ARZ-122 starts; media earlier if wristbands are pre-manufactured |
| Dedicated scanners or phones; Android-only or iOS too | `94` native stack; NFC on iPhone (`36`); wedge versus native (`38`) | ARZ-120, ARZ-152 | Before ARZ-152 chooses its stack |
| Kiosk platform | `19` lockdown, printer drivers (`96`) | ARZ-110 | Before ARZ-110 |
| MDM product | Staging (`103`), kiosk lockdown (`19`) | None — an operational tool (`40`) | Before the first staging |

**Recommendation now:** buy evaluation units — one thermal printer from each shortlisted family, a
PC/SC HF reader with NTAG21x and NTAG 424 DNA samples, one dedicated Android scanner. They equip the
hardware-in-the-loop rig (`37`) and the Arabic raster test (`39`). This is what R5's mitigation,
"decide before Phase 2 exit", costs in practice, and it is small beside adapter rework.

## Budget envelope

Not estimable without the event profile (`04`). The template, filled per event and captured in
`62`:

```
per-event hardware cost =
    Σ class ( units × (rental per event | purchase amortized over E · L) )
  + Σ consumable ( quantity from the table above × unit cost )
  + transport + staging labour hours (103) + network and data (104)
-- every unit cost: to be quoted
```

## Open questions

- **Who signs the hardware commitment?** R5's owner is "business". Name a person and a date, or Phase 2 exits without it.
- **Which event is the pilot?** It is where s, p and the reprint rate stop being guesses. It should be small, ARZO-operated and tolerant of a paper fallback.
- **Is RFID in scope for any event in the next year?** If not, drop the reader and media lines and defer ARZ-122.
- **Do target venues supply scanners, turnstiles or network?** `36` asks the same about access systems. Venue kit changes the bill of materials more than any formula.
- **Who refills stock and clears jams at kiosks?** `19` says the answer decides whether kiosks are viable; it is a staffing cost to add here once answered (`106`).

## Related

`22-badge-design-printing.md` · `36-rfid-nfc.md` · `37-hardware-integration.md` ·
`38-scanner-platform.md` · `39-printer-integration.md` · `40-device-management.md` ·
`62-procurement.md` · `74-performance.md` · `103-hardware-deployment.md` ·
`104-onsite-infrastructure.md` · `120-risk-register.md`
