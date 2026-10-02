# Launch Checklist

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 · **Phase:** any — per capability
**Depends on:** `127-production-readiness.md`, `126-disaster-recovery-plan.md`, `99-pricing.md`, `123-release-strategy.md`, `107-event-day-runbook.md`
**Blocks:** nothing directly

---

What must be true before a capability is announced to clients. Readiness (`127`) asks whether the
software is fit to carry load. Launch asks whether ARZO — people, hardware, contracts, support — is
fit to stand behind it.

## Current state — `MISSING`, 0%

| Fact | State | Evidence |
|---|---|---|
| Any capability launched by ARZO on its own platform | `MISSING` | No ARZO environment exists to run an event on (`126`); `vapor.yml` and `deploy.yml` are upstream's |
| An event run by ARZO on this codebase | `UNVERIFIED` | Not evidenced in the repository. Business to confirm |
| Readiness reviews | `MISSING` | `127` — none performed |
| A security contact of ARZO's own | `MISSING` | `SECURITY.md:9` sends reports to `security@hi.events` |
| Pricing for on-site capabilities | `MISSING` | `99` is a scaffold |
| Event-day runbook, hardware, on-site infrastructure | `MISSING` | `107`, `102`, `103`, `104` are scaffolds; no hardware committed (R5) |
| Legal position for offering the platform to clients | `UNVERIFIED` | AGPL-3.0 (`LICENCE`); advice not evidenced (R1) |

## Decision: "launched" means it survived one real ARZO event

ARZO is internal-first: it runs events itself before it sells software. So launch is defined by
operation, not by a date or a deploy.

| Stage | Definition | Who may use it |
|---|---|---|
| **Built** | Merged behind a flag | Engineers |
| **Ready** | `127` decision `READY` or `READY_WITH_WAIVERS` | Internal pilot |
| **Piloted** | Used at a real ARZO event at **reduced scope** — one door, one desk, with a paper fallback | ARZO staff at that event |
| **Launched** | Used at a real ARZO event at **full intended scope**, and the post-event review (`108`) records: no silent data loss; no SEV1 or SEV2 incident (`60`) caused by it, or each closed with its fix shipped; figures settled | ARZO events generally |
| **Offered** | Launched, **plus** sections D and G below | Named clients, under contract |
| **Self-serve** | Offered, plus MFA, external pen test and re-test (`124`), tenant global scope (ARZ-013) | External SaaS tenants — only if `04` answers that ARZO sells SaaS |

Objection: a single event is a small sample. It is; it is also the first time real attendees, real
staff and a real network meet the software, which no rehearsal reproduces. One event is the floor, not
the proof — the post-event review decides whether a second pilot is needed.

**Choose the first event deliberately:** small, friendly, internal or a forgiving client. Never a
government or VIP event.

## The checklist

Owner abbreviations: **Eng** engineering lead, **Ops** operations lead (`106`), **Biz** business
owner. Column "Applies": **All** capabilities, or **On-site** only.

### A. Readiness

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| A1 | `127` decision recorded; no waiver expires before the event | Review record | Eng | All |
| A2 | `124` catalogue rows for this capability pass | CI run | Eng | All |
| A3 | `125` scenario for this path passed at the event's profile, on the production-like environment | Result stored with the review | Eng | All |
| A4 | `126` runbook entry exists and its drill has a record | Rehearsal record (`63`) | Eng | All |
| A5 | Released before the event freeze window (`123`) — recommended: nothing first-used at an event deployed after T-7d | Release log | Eng | All |

### B. Documentation

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| B1 | `02` and `136` reflect the shipped state | The PR (`129` G15) | Eng | All |
| B2 | Operator guide: how to use it, what it cannot do | Guide | Ops | All |
| B3 | Known limitations written for clients — e.g. an offline door can admit a recently revoked badge (R8); offline capacity is not enforced (`24`) | Client-facing note | Ops + Biz | On-site |
| B4 | API and webhook changes documented (Scramble; the webhook table in `config/scramble.php`) | OpenAPI export | Eng | All |

### C. Support

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| C1 | Named on-call engineer for the event, with authority to act (`86`) | Rota | Eng | All |
| C2 | Escalation path from site to engineering, tested once | Test call | Ops | On-site |
| C3 | Top failure modes with steps a non-engineer can follow (`128` gates 19, 23) | Runbook section in `107` | Ops | All |
| C4 | Paper fallback kit: printed lists, tally sheets, deny-list | Kit checklist (`131`) | Ops | On-site |
| C5 | How to capture diagnostics — device logs, access-log export | Procedure | Eng | On-site |

### D. Commercial

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| D1 | Price set (`99`), or explicitly "included in the pilot at no charge" | Price list | Biz | All |
| D2 | Contract language for the residual risks in B3, and any availability commitment (`101`) | Contract template | Biz | Offered |
| D3 | Hardware: rent, sell or bundle decided (`99`, `102`) | Decision record | Biz | On-site |

### E. Rollback

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| E1 | Feature flag off restores the previous behaviour — for access control, the dual-write in `24` step 2 is that property | Tested toggle | Eng | All |
| E2 | Data written under the flag survives turning it off | Test | Eng | All |
| E3 | Mid-event rollback decided by Ops, executed by Eng, script for the client ready | Runbook | Ops | On-site |

### F. On-site capabilities

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| F1 | Hardware procured with spares (`102`) | Inventory | Biz | On-site |
| F2 | Devices staged, imaged, enrolled; keys expire with the event (`103`, `40`) | Device list | Ops | On-site |
| F3 | Venue network plan and its failure plan (`104`) | Site plan | Ops | On-site |
| F4 | Staff trained on the capability **and** on degraded mode; a dry run done | Training record | Ops | On-site |
| F5 | Offline drill at T-24h (`59` blocking check); T-7d, T-24h and T-2h reviews held (`131`) | Readiness reviews | Ops | On-site |
| F6 | Real-hardware field test passed (`128` gate 24) | Test record | Eng + Ops | On-site |
| F7 | Printed runbook on site (`107`) | Present at T-2h | Ops | On-site |

### G. Legal and privacy

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| G1 | DPIA or privacy note for any new data class — photos, ID documents, movement, leads (`65`) | Document | Biz | All touching personal data |
| G2 | AGPL position resolved before software is offered to anyone outside ARZO (R1) | Legal advice | Biz | Offered |
| G3 | Consent and notice texts reviewed, in the event's languages | Texts | Biz | All touching personal data |
| G4 | ARZO security contact published | `SECURITY.md` | Eng | Offered |

### H. Announcement

| # | Item | Evidence | Owner | Applies |
|---|---|---|---|---|
| H1 | Release notes; client communication; sales briefed on the limits in B3 | Notes | Biz | Offered |
| H2 | Launch record: stage reached, event used, review link, waivers carried | Record in `63` | Ops | All |

## Worked example: access control's first event

What the access engine's first real use should look like, given `127`'s verdict:

- **Stage: Piloted, not Launched.** One staffed door into one zone, online only, with printed lists and a supervisor able to override.
- A3 uses profile S from `125`; scenario 1 must have passed after the ARZ-307 fix.
- F1, F2 and F6 are **waived for the pilot** only if the door runs a thin internal screen on phones ARZO already owns, with a paper fallback; the waivers expire at the end of that event (`127`). F3, F4, F5 and F7 are not waived — training, the network plan and a network-drop drill apply to any door.
- D1: included at no charge — pricing on-site capabilities (`99`) should follow evidence from the pilot, not precede it.
- Launch follows at the next event if the post-event review is clean.

## Open questions

- **Is the first ARZO event on this platform scheduled?** It anchors every date in `113`. Business.
- **Does a capability launch per event type?** Access control at a conference is not access control at a stadium. Leaning yes: "Launched" is recorded per event type, and a new type starts at Piloted.
- **Who signs the launch record?** Recommend Ops, with Eng and Biz countersigning their sections.
- **Can a client event be the pilot?** Only with the client told, in writing, that it is a pilot — and never for a client whose event carries safety or security obligations.

## Related

`127-production-readiness.md` · `126-disaster-recovery-plan.md` · `124-security-testing-plan.md` ·
`125-performance-testing-plan.md` · `99-pricing.md` · `101-enterprise.md` · `102-hardware-procurement.md` ·
`103-hardware-deployment.md` · `104-onsite-infrastructure.md` · `107-event-day-runbook.md` ·
`108-post-event-closeout.md` · `123-release-strategy.md` · `131-event-readiness-checklist.md` ·
`59-event-readiness.md` · `60-incident-management.md` · `65-privacy-gdpr.md` · `04-product-strategy.md`
