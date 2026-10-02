# On-Site Infrastructure

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Business commitment · **Priority:** P1 — business; no ARZ id · **Phase:** any operated event
**Depends on:** `71-realtime-architecture.md`, `102-hardware-procurement.md`, `37-hardware-integration.md`
**Blocks:** `107-event-day-runbook.md`, `131-event-readiness-checklist.md`

---

Network, power and contingency at the venue. The assumption from the scaffold stands and is the
entire justification for `71`: **the venue network will fail.** It does not follow that the network
does not matter. Offline-first makes a failure degrade instead of stop; a good network is still what
keeps data fresh, revocations moving and the command center live. Build the network, then assume it
fails.

## Current state — `MISSING`, 0%

The infrastructure is a business commitment with no current practice recorded. What the platform
imposes on it is `CONFIRMED`:

| Fact | Consequence | Evidence |
|---|---|---|
| Scanning is online-only today; no service worker, no local store | A network drop stops check-in entirely | `71` |
| The web scanner needs **two** hosts: the SSR frontend to load or reload the page, the API for every scan | Reloading the page during an outage loses the scanner, not just the scan | DigitalOcean frontend, Vapor backend (`85`); no service worker |
| Backend region is AWS eu-west-1 | Every online scan from Qatar travels to Europe and back | `deploy.yml:95` |
| Round-trip time from a Qatar venue to eu-west-1 | `UNVERIFIED` — measure in the survey | — |
| `74` targets an online access decision under 150 ms p95 | That budget includes the round trip and may be unattainable from Qatar; if so, the local decision (`71`) becomes the normal path, not the fallback | `74-performance.md` |
| The server never connects into the venue LAN; print hosts pull jobs | No inbound ports, no port forwarding at the venue | `39` |
| Realtime is planned on WebSockets (Reverb) | Venue proxies that block the WebSocket upgrade degrade the command center to polling | `71`; behaviour at venues `UNVERIFIED` |
| Who provides connectivity at ARZO's venues | `UNVERIFIED` | — |

## Decision: ARZO brings its own operations network; the venue provides the backbone

Venue wifi is shared with attendees, and it saturates exactly when they arrive. ARZO cannot control
it. Critical positions — badge desks, the ops room, fixed gates — are few. So ARZO runs a dedicated
operations network (router, SSID, cellular failover) for them, over the venue's wired drops and
power, which the venue contract must specify. A network contractor takes over for large events; the
size at which that happens is `UNVERIFIED` until the pilot.

The objection is cost and set-up time. Both are smaller than one gate outage at doors-open.

| Responsibility | Venue | ARZO | Contractor (large events) |
|---|---|---|---|
| Wired drops to agreed positions, power, circuit plan | Yes | Specifies | — |
| Ops router, device SSID, cellular failover | — | Yes | Yes |
| Attendee wifi | Yes | Never used for devices | — |
| Named on-site IT contact during the event | Yes | Device Lead | Yes |

## Network survey — before T-7d

A site visit, at a similar time of day to doors-open. What to measure at **every** planned position:

| Measure | Why it matters |
|---|---|
| Signal on the planned SSID; channel congestion | Dead spots at a gate are the classic failure |
| Throughput and round trip to the API host | Sync must finish inside a 15 s cadence (`71`); online decisions have a 150 ms budget (`74`) |
| Packet loss | Loss hurts a sync more than low bandwidth does |
| Captive portal, client isolation, blocked ports, TLS interception | A captive portal silently breaks every device sync |
| WebSocket upgrade allowed | Realtime (`71`) |
| NTP reachable | Clock skew breaks offline replay (`40`, `71`) |
| Cellular signal, per carrier | Failover only works where there is signal — halls are often poor |
| Power outlets, circuit, distance to a cable run | UPS and desk layout |

Pass thresholds are set with the network contractor, not guessed here. The survey is **necessary,
not sufficient**: nobody can simulate thousands of phones arriving. That is what the offline design
and the drill are for.

Output: a **position map** — each access point, desk, kiosk and the ops room, with its connectivity
class (below), its power, and its cellular fallback. It is evidence for `131`.

## Wired or wireless, per position

| Position | Primary | Secondary | Why |
|---|---|---|---|
| Badge desk print host | **Wired** | Cellular via the ops router | Fixed and high-value; printers on USB, so printing survives a LAN loss (`39`) |
| Kiosk | Wired where a drop exists | Device SSID | Fixed and unattended; must report its own health (`19`) |
| Mounted gate device | Device SSID | Device SIM | Tablets rarely take Ethernet; offline mode covers the gaps |
| Handheld scanner | Device SSID | Device SIM | It roams |
| Supervisor tablet | Cellular | Device SSID | Walks between halls |
| Ops room and command center | **Wired** | Cellular failover | The one place that must stay online |
| Staff phones (lead capture) | Their own cellular | Attendee wifi | Hold no roster (`33`) |

**Devices never join the attendee wifi.** A dedicated SSID on its own VLAN, WPA2 or WPA3 with a
pre-shared key rotated per event (802.1X if the venue supports it — `UNVERIFIED`), client isolation
on.

## Cellular failover

- The ops router has two uplinks — the venue's wired line and cellular — and fails over
  automatically. Where possible, two SIMs on **different carriers**, because saturation is per cell.
- **Stated honestly:** cellular saturates at crowd arrival too. Failover rescues a venue-network
  fault; it does not rescue a saturated cell. Neither link is guaranteed, which is why `71` exists.
- Data allowance: per-device sync volume per hour × devices × operating hours. Pre-sync happens at
  base (`103`), so it is excluded. Cost to be quoted.

## UPS for print stations

- **Every print host and thermal printer on a UPS**, one per desk, plus the ops router and switch.
- Sizing: VA ≥ Σ load watts ÷ UPS power factor × 1.25 headroom; runtime ≥ 15 minutes — long enough
  to finish in-flight jobs and ride through a circuit reset or generator changeover. Both factors are
  planning assumptions; load watts are `UNVERIFIED` per model — measure with a plug meter at staging
  (`103`).
- Laser printers are not on site; pre-printing happens at base (`39`).
- Test at T-24h: pull the plug on one desk and print.

## Power

- An outlet map per position from the survey; print stations on circuits not shared with catering
  or AV, where one kettle trips a breaker.
- Cable routing and hazard rules are the venue's — `UNVERIFIED` per venue; ask in the contract.
- Outdoor and generator-fed events: the UPS also conditions dirty power.
- The ops-room charging station (`103`) is a real load; include it.

## Decision: no on-site server — the devices and the print host cover it

The scaffold asked whether a local server is worth it for very large events. **Not in v1, and
probably not ever as a server.**

| Option | Gives | Costs | Verdict |
|---|---|---|---|
| **No on-site server** — offline-first devices, a print host per desk | Doors, printing and lookup survive internet loss (`71`) | During an outage, cross-door checks stop: global anti-passback, zone capacity across doors, revocation propagation. `71`'s emergency mode says so on screen | **v1** |
| Print host as a **LAN relay** | Deny-list gossip and cross-device anti-passback on the LAN without internet (`37`, `71`) | A new protocol; a LAN dependency; a role for one host per site | Phase 4+, only when an event needs it |
| Full on-site replica — Laravel and Postgres on site, syncing to the cloud | Everything works with no internet | A second deployment topology; server-to-server conflict resolution **on top of** device sync; running a database at a venue | **No** |

The print host already does most of what an on-site server would: it is always on, on the LAN,
holds cached templates and credential data, and prints with no internet. A full replica would
re-centralize the system on the venue LAN — the network this document assumes fails — and double the
sync problem `71` already solves once.

Revisit only for an event where the internet is unusable for its whole duration, where zone capacity
must hold across doors during an outage, or where a client requires offline revocation within
seconds. Even then, extend the print host into a relay; do not deploy a server.

## Offline drill — at T-24h, on the installed configuration

`59` makes this blocking at T-24h. It runs longer than `71`'s emergency-mode threshold (30 minutes
by default), or it tests only the first half of the behaviour.

| # | Step | Owner |
|---|---|---|
| 1 | Announce the drill; record the start time | Device Lead |
| 2 | Cut the **WAN** at the ops router, leaving venue wifi up; on one gate also switch wifi off | Device Lead |
| 3 | At every gate, canary scans: valid, wrong zone, revoked before the drill | Gate supervisors |
| 4 | From a laptop on a separate cellular link, revoke one more canary; scan it offline | Accreditation lead |
| 5 | At every desk: print a badge; capture a walk-in if walk-ins are in scope (`17`) | Badge desk lead |
| 6 | Pass the emergency threshold; confirm every banner shows data age (`71`) | Gate supervisors |
| 7 | Restore the WAN; every device returns to zero unsynced within the sync target | Device Lead |
| 8 | The reconciliation report shows the step-4 admission as a **retrospective violation**, print jobs reconciled, no duplicates | Device Lead, security lead |
| 9 | Attach the report to the drill task as evidence (`58`, `59`) | Device Lead |

**Before ARZ-101 the result is known in advance: check-in stops.** Until then the drill exercises
the **paper fallback** instead (`107` F): print the rosters, run ten minutes on paper at one gate,
time it, and record the throughput.

## Out-of-band communications

When the network goes, chat apps go with it. The runbook (`107`) relies on radios — venue-supplied
or rented (`102`) — and a printed phone tree. Radio check at T-2h is a readiness item (`131`).

## Open questions

- **Should the backend move closer to Qatar?** Measure the round trip in the first survey. If `74`'s 150 ms budget cannot be met from eu-west-1, that is an input to `84` and to data residency (`65`), not a reason to add an on-site server.
- **Who signs connectivity requirements into venue contracts?** The table above is useless unless it is in the contract before the site visit.
- **Contractor threshold** — at what event size does ARZO stop running its own ops network? Decide after the pilot.
- **802.1X or pre-shared keys?** Pre-shared keys rotated per event are enough for v1; revisit if a client's security review requires more.

## Related

`71-realtime-architecture.md` · `37-hardware-integration.md` · `39-printer-integration.md` ·
`74-performance.md` · `102-hardware-procurement.md` · `103-hardware-deployment.md` ·
`107-event-day-runbook.md` · `131-event-readiness-checklist.md` · `59-event-readiness.md` ·
`84-infrastructure.md` · `85-deployment.md`
