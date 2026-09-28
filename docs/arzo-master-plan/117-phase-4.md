# Phase 117 — Hardware and Offline

**Status:** SCAFFOLD · **Audit date:** 2026-09-28 · **Complexity:** XL
**Prerequisite:** Phases 2 and 3 complete — credentials, access rules, badges, sessions all exist

---

## Objective

The phase that separates an event platform from a ticketing website: the venue network fails and everything keeps working.

This is the largest and riskiest phase. Offline changes the shape of every write path it touches, the access decision function must be provably identical on server and device, and it needs test infrastructure (network partition, clock skew, replay) that does not exist today.

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-100 Device registry + enrolment | `40` | New |
| ARZ-092 Device-scoped API keys | `48` | New |
| ARZ-101 Local store + sync protocol | `71` | New |
| ARZ-102 Conflict resolution + reconciliation reporting | `71` | New |
| ARZ-103 Emergency / degraded mode UX | `71` | New |
| ARZ-104 Realtime transport (Reverb) | `71` | Infrastructure |
| ARZ-120 Scanner abstraction | `38` | New |
| ARZ-121 Printer abstraction | `39` | New |
| ARZ-122 RFID/NFC read + encode | `36` | New |
| ARZ-123 Device health + fleet dashboard | `40` | New |
| ARZ-110 Kiosk app — self check-in | `96` | New |
| ARZ-111 Walk-in registration at the door | `17` | New |
| ARZ-112 Queue management | `20` | New |
| ARZ-152 Native scanner app | `94` | New |

## Non-engineering gates

This phase is **not deliverable on software alone**.

- **All hardware procured, staged and imaged** — scanners, printers, kiosk units, RFID readers and cards (`102`, `103`)
- **On-site infrastructure plan** — network survey, cellular failover, UPS for print stations (`104`)
- **Staff trained** on degraded-mode procedures (`107`)

## Exit criteria

| # | Criterion |
|---|---|
| 1 | A device is enrolled with a scoped key and pre-synced with a 10,000-attendee roster in under 60s |
| 2 | **The network is disconnected and check-in continues working** — decisions in under 50ms p95 |
| 3 | A scan taken offline is queued, survives an app restart, and reconciles on reconnect |
| 4 | The same offline scan replayed twice creates exactly one log row (idempotency key) |
| 5 | The offline decision function and the server decision function agree on 100 table-driven cases |
| 6 | A badge prints from a fully offline device using cached data |
| 7 | Two devices admitting to a capacity-1 zone both log, and the violation is **flagged not corrected** |
| 8 | A revoked credential admitted offline is flagged as a retrospective violation after sync |
| 9 | Device clock skew is detected and reported; `occurred_at` and `recorded_at` differ correctly |
| 10 | A device offline beyond the threshold shows a degraded banner stating the data age |
| 11 | The roster on a device is encrypted at rest and the device can be remotely wiped |
| 12 | A scan appears on the command center within 2s while online |
| 13 | A kiosk completes self check-in and badge print with no operator present |
| 14 | Queue depth per access point is derived and displayed |

## Out of scope

- Command center and analytics (Phase 5) — the transport lands here, the dashboard there
- Peer-to-peer deny-list gossip between devices on a venue LAN
- Offline card payment — **permanently out of scope** (PCI, chargebacks)
- Staffing and ops workflows (Phase 5)

## Risks

| Risk | Mitigation |
|---|---|
| Offline complexity underestimated (R4) | Prototype the sync protocol **before** committing to kiosk dates. Do not mistake a happy-path demo for the work. |
| Server and device decision functions diverge | One specification, table-driven conformance tests run against both implementations |
| Hardware not procured in time (R5) | Procurement decision required before this phase starts — lead times can exceed the software timeline |
| Vendor SDK shapes the abstraction (R14) | Write the interface from ARZO's needs **before** reading the SDK |
| Lost device exposes attendee PII (T3) | Encryption at rest mandatory; scoped revocable keys; remote wipe. This is the argument for native over PWA. |
| Revoked badge opens an offline door (R8) | Inherent. Short sync intervals, prioritized deny-list deltas, and **state the residual risk to clients** |
| Reverb unproven on Laravel 13 (R17) | Spike first; SSE as a dashboard-only fallback |

## Status of this document

SCAFFOLD. Scope, exits and risks are captured from the audit. Expand into work packages
(`137-engineering-work-packages.md`) when the phase is scheduled — a package written
several phases early is fiction.

## Related

`113-roadmap.md` · `118-phase-5.md` · `71-realtime-architecture.md` · `37-hardware-integration.md` · `40-device-management.md` · `136-master-backlog.md` · `128-definition-of-done.md` · `120-risk-register.md`
