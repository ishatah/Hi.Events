# Phase 4 — Hardware and Offline

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d` · **Complexity:** XL
**Classification:** Derived (from `113`, `136`) · **Prerequisite:** Phases 2 and 3 complete — credentials, rules, badges, sessions
**Depends on:** `71-realtime-architecture.md`, `37-hardware-integration.md`, `40-device-management.md`, `94-mobile-scanner.md`, `96-kiosk-application.md`, `102-hardware-procurement.md`
**Blocks:** `118-phase-5.md`

---

## Objective

The phase that separates an event platform from a ticketing website: **the venue network fails and
check-in, badge printing and access control keep working**, then reconcile cleanly.

The largest and riskiest phase. Offline changes the shape of every write path it touches; the
access decision must be provably identical on server and device; and it needs test infrastructure —
partition, clock skew, replay — that does not exist today (`79`).

## Where it stands

| Area | State at `e7228c1d` |
|---|---|
| `devices` table | Exists with key prefix/hash, pairing code, heartbeat fields; model + repository; **no guard, no endpoints** (`40`) |
| `access_logs.client_generated_id` UNIQUE, `occurred_at`/`recorded_at` | **Done** — the idempotency and replay foundations are in the schema |
| `access_logs.device_id` | **Missing** — add before any device writes a log (`40`) |
| Pure decision function | **Done** (`AccessDecisionService`) — the thing devices must reproduce |
| Realtime | None — stub config, no Reverb (`53`, `71`) |
| Service worker, local store, sync endpoint | None |
| Print hosts, printer registry | None; `badge_print_jobs` has only a free-text `printer_identifier` (`39`) |
| RFID/NFC | Two unindexed, non-unique columns on `credentials` (`36`) |

## Decisions carried in

| Topic | Decision | Doc |
|---|---|---|
| App form | **Native** door scanner and kiosk; PWA for attendee and lead capture | `30`, `94`, `96` |
| Printers | Behind a **local print host** per desk or site; server never connects into the venue LAN; hosts pull jobs | `37`, `39` |
| Print format | **Raster at printer DPI**, PDF kept as the record — Arabic shaping decides it | `39` |
| Decision parity | **Golden vectors**: the server's table-driven cases exported as JSON and run against every device build in CI | `37` |
| Device auth | Own keys (`arzod_`), scopes by type, expire with the event; lifecycle separate from connectivity | `40`, `48` |
| Tags | `credential_media` replaces `rfid_uid`/`nfc_uid`; HF 13.56 MHz; SUN verification for high-security | `36` |
| Keyboard wedge | Read `KeyboardEvent.code`, not `e.key` — Arabic layouts break scanners today | `38` S1 |
| Remote wipe | A convenience, **not a breach control**; encryption at rest + key expiry + revocation are | `40` |

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-100 Device registry, enrolment, heartbeat, commands | `40` | New |
| ARZ-092 Device-scoped keys | `48`, `40` | New |
| `device_id` on `access_logs`, `session_attendance`, `badge_print_jobs` (unnumbered) | `40` | Schema |
| ARZ-101 Local store + sync protocol | `71` | New |
| ARZ-102 Conflict resolution + reconciliation report | `71` | New |
| ARZ-103 Emergency / degraded mode UX | `71` | New |
| ARZ-104 Realtime transport (Reverb, persistent host) | `71`, `84` | Infrastructure |
| ARZ-120 Scanner pipeline + recognizer registry | `38` | New |
| ARZ-121 Print host + printer registry + raster adapters | `37`, `39` | New |
| ARZ-122 RFID/NFC via `credential_media` | `36` | New |
| ARZ-123 Device health + fleet view | `40` | New |
| ARZ-152 Native scanner app | `94` | New |
| ARZ-110 Kiosk app — self check-in + print | `19`, `96` | New |
| ARZ-111 Walk-in registration at the door | `17` | New |
| ARZ-112 Queue management from throughput snapshots | `20` | New |

## Build order — prototype the hard part first

```
1. Sync protocol prototype: one device, one event, partition + replay tests      (R4)
2. Golden-vector export + TypeScript decision implementation passing all vectors
3. Device registry, keys, heartbeat; device_id columns
4. Native scanner on the prototype's store; recognizers; operator sign-in by staff credential
5. Print host + one printer family (the one procured — 102); printer registry
6. Reverb + fleet view
7. Kiosk; walk-in; RFID/NFC (only if tags are procured)
8. Queue management from throughput snapshots
```

Step 1 comes first because R4 — offline complexity underestimated — is the phase's defining risk. A
happy-path demo is not the work; the partition and replay tests are.

## Exit criteria

| # | Criterion |
|---|---|
| 1 | A device enrols with a scoped key and pre-syncs a 10,000-attendee roster in < 60 s |
| 2 | **The network is disconnected and check-in continues** — local decisions < 50 ms p95 |
| 3 | An offline scan survives an app restart and reconciles on reconnect |
| 4 | The same offline scan replayed twice produces exactly one `access_logs` row |
| 5 | Every golden vector passes on the server and on every device build, in CI |
| 6 | A badge prints from a fully offline print host using cached template and credential data |
| 7 | Two devices admitting to a capacity-1 zone both log; the violation is **flagged, not corrected** |
| 8 | A revoked credential admitted offline is flagged as a retrospective violation after sync |
| 9 | Clock skew is detected from heartbeats and reported; `occurred_at` and `recorded_at` differ correctly |
| 10 | A device offline beyond the threshold shows a degraded banner with the data age |
| 11 | The on-device roster is encrypted at rest; a revoked or expired key causes the app to wipe its store on next contact and makes the store useless without it |
| 12 | A scan appears on the fleet/command view within 2 s while online |
| 13 | A kiosk completes self check-in and badge print with no operator, and takes itself out of service after repeated printer failure |
| 14 | A USB scanner works with the host keyboard set to Arabic |
| 15 | Queue depth per access point is derived from throughput snapshots and displayed as an estimate |

Criterion 11 replaces the earlier "can be remotely wiped", which overstated what software can do to a
device that never reconnects (`40`).

## Non-engineering gates

- **All hardware procured, staged and imaged** — scanners, printers, print hosts, kiosk units, tags if in scope (`102`, `103`)
- **On-site infrastructure plan** — survey, wired positions, cellular failover, UPS at print stations (`104`)
- **Staff trained on degraded-mode procedures** and an **offline drill run** on a real venue configuration (`107`, `59`)
- **MDM** for ARZO-owned kiosks and scanners (`40`, `96`)

## Out of scope

- The command center and analytics (Phase 5) — the transport lands here, the dashboard there
- Peer-to-peer deny-list gossip — the print host is its natural future home (`37`)
- **Offline card payment — permanently out of scope** (PCI, chargebacks)
- UHF walk-through portals (`36`)

## Risks

| Risk | Mitigation |
|---|---|
| R4 Offline complexity underestimated | Build order step 1; no kiosk date before the prototype passes partition and replay tests |
| Server and device decisions diverge | Golden vectors gate every build (criterion 5) |
| R5 Hardware not procured in time | Printer and reader families chosen before the phase starts; they drive adapter work (`102`) |
| R14 Vendor SDK shapes the interface | Interfaces written from ARZO's needs before reading any SDK (`37`) |
| T3 Lost device exposes PII | Native keystore encryption, event-scoped keys, short retention (`40`, `64`) |
| R8 Revoked credential opens an offline door | Prioritized deny-list deltas; state the residual risk to clients |
| R17 Reverb unproven, and does not fit Lambda | Spike early; host it on a persistent process (`84`); SSE fallback for dashboards |

## Related

`113-roadmap.md` · `115-phase-2.md` · `116-phase-3.md` · `118-phase-5.md` ·
`71-realtime-architecture.md` · `36-rfid-nfc.md` · `37-hardware-integration.md` ·
`38-scanner-platform.md` · `39-printer-integration.md` · `40-device-management.md` ·
`94-mobile-scanner.md` · `96-kiosk-application.md` · `102-hardware-procurement.md` ·
`136-master-backlog.md` · `120-risk-register.md`
