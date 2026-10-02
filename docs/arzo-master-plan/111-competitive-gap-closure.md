# Competitive Gap Closure

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Derived (from `110`, `113`, `136`) · **Priority:** — · **Phase:** all
**Depends on:** `110-evento-comparison.md`, `113-roadmap.md`, `04-product-strategy.md`
**Blocks:** nothing

---

## The rule this document applies

`04` says "not Evento feature-matching for its own sake". So every gap in `110` is sorted by one
question first: **does an ARZO event need it, or only a hypothetical tender?** Closing a gap nobody
uses is the most expensive way to reach parity.

Where the answer is unknown, it is marked as such — most of these hinge on ARZO's actual event mix,
which `04` records as unanswered.

## Where each gap closes

| Evento capability | ARZO today (`e7228c1d`) | Closes in | Needed by ARZO events? |
|---|---|---|---|
| Online registration, tiers, promos, early bird | ~90% | — | Yes — done |
| **Bulk SMS** | 0% — and phone numbers are not collected (`43`) | Phase 3 (ARZ-140) | **Yes** for operational messages; bulk marketing SMS **no** |
| **RSVP** | Tables landed, no behaviour (`12`) | Phase 3 (ARZ-190) | Yes — galas, government ceremonies |
| QR scanning | Works online; breaks under an Arabic keyboard layout (`38`) | Hardening (ARZ-309) | Yes |
| **Self-service kiosks** | 0% | Phase 4 (ARZ-110) | `UNVERIFIED` — depends on who clears jams (`19`) |
| **Instant badge printing** | 0%; schema landed | Phase 2 software, Phase 4 hardware | **Yes** for any accredited event |
| Walk-in registration | Backoffice only | Phase 4 (ARZ-111) | Yes |
| **Queue management** | Throughput gauge capped at 4/min (`51`) | Phase 4 (ARZ-112) | Nice-to-have; measurement is cheap once access logs flow |
| Offline operation | 0% — scans no longer lost, but not queued (`38`) | Phase 4 | **Yes — and the main way to pass Evento** (`110`) |
| Multi-level access, zones | Engine core done; UTC windows defect (`115`) | Phase 2 | Yes |
| **VIP and media accreditation** | Schema only | Phase 2 (ARZ-051) | Yes |
| **Photo badges** | 0% | Phase 2 (ARZ-074) | Yes for media and staff |
| **RFID / NFC** | Two unused columns | Phase 4 (ARZ-122) | `UNVERIFIED` — decided by throughput need and tag cost (`36`) |
| **Face recognition** | 0% | **Not planned** — legal clearance first (`133` A10) | No |
| **Seating** | Table only | **Deferred** (`26`) | `UNVERIFIED` — galas need table seating, not seat maps |
| Mobile event app | 0%; installable-but-blank trap | Phase 3 (ARZ-150) | Yes for conferences; no for concerts |
| **Lead capture** | 0% | Phase 3 (ARZ-133) | Only if ARZO runs exhibitions |
| **CRM integration** | 0% | Phase 5 (ARZ-180) | For ARZO's own sales — the one CRM it uses |
| **API integration** | 0% machine auth | Phase 3 (ARZ-090) | Yes |
| **eRaffle** | 0% | Phase 5, or late Phase 3 (ARZ-211) | Cheap; parity item |
| Real-time dashboard | Polling only | Phase 5 (ARZ-170) | Yes on event day |
| **Session tracking** | Schema only | Phase 3 (ARZ-082) | Conferences |
| **Demographics** | Attributes exist on `persons` | Phase 5 (ARZ-173) | With consent only |
| Exhibitor lead retrieval | 0% | Phase 3 | Exhibitions only |
| Staff scheduling | 0% | Phase 5 (ARZ-200) | Yes if ARZO staffs its own events |
| **Manpower supply, trained staff** | n/a | **Business decision** (`57`) | — |
| **Badge stock, lanyards, holograms** | n/a | **Procurement** (`102`) | — |
| IT consultancy | n/a | Not software | — |

## What parity actually costs

| Tier | Gaps | Where |
|---|---|---|
| Software parity for ARZO's likely events | Accreditation, badges, zones, SMS, RSVP, app, offline | End of Phase 4 |
| Full Evento software list | + kiosks, RFID, lead capture, queue management | End of Phase 4, if procured |
| Evento's physical offer | Printers, stock, staff | Not software — `102`, `57` |
| Deliberately never | Face recognition without legal clearance; seat-map authoring unless a real event needs it | — |

The honest summary from `110` stands: software parity arrives with Phase 4, and it does not equal
capability parity. Evento arrives with printers and trained staff.

## Passing, not matching

The four places ARZO can be ahead, each already in the plan and none requiring extra scope:

1. **Offline as a tested guarantee** — Phase 4's exit criteria are the contract; a reconciliation report from a deliberately disconnected drill is the sales demonstration (`112`).
2. **Evidence-based reporting** — attendance and sponsor figures that reconcile to append-only logs (`52`, `34`).
3. **Self-serve depth** — waitlists, capacity pools, recurring events, affiliates, VAT, the embeddable widget: already there.
4. **A real API and working webhooks** — once ARZ-090 and ARZ-304 land. Today the webhooks' retries do not run, so this advantage is currently overstated (`49`).

## Gaps first for ARZO's first real event

The scaffold asked which gaps a real first event needs. Without a named target event the answer is
conditional, so here is the rule to apply once one exists:

| If the first event is… | Close first |
|---|---|
| A gala or ceremony | RSVP, accreditation, photo badges, table seating |
| A conference | Accreditation, badges, sessions and agenda, attendee app, SMS |
| An exhibition | Exhibitors, booths, lead capture, badges |
| A concert or festival | Offline scanning, re-entry, possibly wristbands (`36`) |

## Open questions

- **What is ARZO's first target event on the new stack?** Every "needed by ARZO events?" cell marked `UNVERIFIED` resolves once this is named.
- **Is any tender (RFP) driving parity?** If a specific client's requirements exist, they outrank this table.
- **Evento's advertised features are marketing copy** (`110`) — parity with a brochure is a weak target; parity with what ARZO's clients ask for is the real one.

## Related

`110-evento-comparison.md` · `112-arzo-differentiators.md` · `113-roadmap.md` · `04-product-strategy.md` ·
`115-phase-2.md` · `116-phase-3.md` · `117-phase-4.md` · `118-phase-5.md` · `102-hardware-procurement.md` ·
`57-manpower-and-staffing.md` · `26-seating-management.md`
