# Future Capabilities

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Derived (assessment) · **Priority:** P4 (ARZ-250, ARZ-251; touches ARZ-220, ARZ-223) · **Phase:** after Phase 5; commercial validation any time
**Depends on:** `112-arzo-differentiators.md`, `113-roadmap.md`, `04-product-strategy.md`
**Blocks:** nothing directly

---

Capabilities beyond the roadmap, assessed with the test `112` applies: is it something ARZO can do
that competitors **structurally cannot or will not**, and what does it cost? Each is rated on
defensibility and effort, with the honest objection and a verdict. The purpose is to decide what to
**validate** — cheaply, with the business — before anything here earns engineering time.

## Current state — `MISSING`, by definition; the foundations are partly in place

| Foundation | State | Evidence |
|---|---|---|
| `persons` — identity independent of orders | `CONFIRMED` | Live table, account-scoped; backfilled by email (`55`) |
| `speakers` — can be account-level (`event_id` nullable), **no `person_id`** | `CONFIRMED` | Live `information_schema`; `28` recommends adding `person_id` |
| Credentials, access logs, zones | `CONFIRMED` schema | `c34f6a59`, `e7228c1d` |
| Anything about talent, performers or rosters | `MISSING` | Search of `app/` and migrations: demo-content strings only |
| Mobile wallet passes | `MISSING` | Search for pkpass, wallet: an icon only. `36` reserves `MOBILE_WALLET` as a media type. |
| Vendors, marketplace | `MISSING` | No table; `61` is a scaffold |
| Links between editions of the same show | `MISSING` | `events` has no series or parent column; duplication records no source |
| `event_benchmark_facts` | `MISSING` | Designed in `55`, not built |

## The assessment

| Candidate | Defensibility | Effort | Honest objection | Verdict |
|---|---|---|---|---|
| **Talent-management integration** | **High** — needs a talent business | L, after `23`, `27`, `28` | Worthless if the talent and events businesses do not share clients | **Validate first** |
| Cross-tenant benchmarking (`55`) | Medium-high — grows with tenants | M on `event_benchmark_facts` | Needs many external tenants, contractual opt-in and ≥ 5 contributors per set | Defer until external SaaS exists (`98`) |
| Sponsor ROI evidence | Medium — rests on evidence-based reporting (`112` §3) | M | Sponsors want individual data ARZO must not give (`54`) | Build with `34`, aggregate-only |
| Mobile wallet passes | Low — common elsewhere | M (QR), L+ (NFC) | NFC wallet needs Apple and Google approval; lead time `UNVERIFIED` (`36`) | QR pass after `30`; NFC deferred |
| Government delegation and protocol | Medium — relationships, not code | M on `23` | Demand `UNVERIFIED`; ARZO's event mix unknown (`04`) | Ask, then decide |
| Hosted-buyer matchmaking (`31`) | Medium, B2B only | L | Only trade shows need it | Per `31` |
| Venue heatmaps (ARZ-223) | Low as a product | M | A view of evidence-based reporting, not a differentiator (`112`) | Phase 5 feature |
| Event digital twin (ARZ-250) | None as a project | XL if a project; ~0 if emergent | A visualization with nothing behind it | **Never as a project** |
| Vendor marketplace (ARZ-251) | Low until liquidity | XL | ARZO has neither side of the market | **Refuse** for now |
| Face recognition (ARZ-260) | — | XL | Biometric liability (`133` A10) | Refused pending legal |

## 1. Talent-management integration — the strongest, and the easiest to over-build

ARZO represents the talent that performs at its events. No event-technology vendor does, because none
is also an agency (`112` §1).

**What it would be, concretely:**

1. Talent profiles are **consumed** from the talent business's system of record — import or API —
   into account-level `speakers` linked to `persons`. ARZO does not rebuild an agency CRM. Which
   system the talent business uses is `UNVERIFIED`.
2. Casting a performer into a session (`27`, `28`) issues a `SPEAKER` or performer accreditation and
   credential automatically, with backstage and green-room zones (`23`, `24`).
3. Riders, travel and call times become tasks against the event timeline (`58`).
4. After the event: which talent's announcement moved sales, and which sessions filled. **Descriptive
   only** — sales velocity after an announcement, against comparable events (`134`). Causal claims from
   a handful of events are not credible.
5. **Not in scope:** talent contracts, fees and payments. Those stay in the agency's and ARZO's
   accounting systems (`04` non-goal). The platform stores a contract reference, not the contract.

**Privacy:** talent fees, riders and availability are commercially sensitive. They need their own
permission (`09`), not the organizer default.

**Missing data, found by this assessment:** announcement dates are not captured anywhere, and editions
of the same show are not linked. Without both, "did this headliner sell tickets?" has no answer.

**Validation, before any build:**

| Question to the business | Kills the idea if |
|---|---|
| How many of ARZO's events per year feature ARZO-represented talent? | A small minority |
| Do the same clients buy both talent and events? | They are different clients |
| Who books talent into an event, and in what tool? | It works fine and nobody wants a change |
| Which decision would talent-attendance data change? | None named |

Then a **manual pilot**: for the next few events with represented talent, record the session, the
announcement date and sales in a spreadsheet and produce the report by hand. If nobody reads it,
stop. If it changes a booking decision, build steps 1–2 first — they pay off operationally even if
the analytics never do.

## 2. Event digital twin — emergent, not a project

Once space (`25`), time (`27`), people (`23`), devices (`40`) and logs (`24`) exist, the "twin" is the
data model, and the command center (`53`) is its live view. Scheduling a twin as a deliverable
invites a 3D visualization with nothing behind it. ARZ-250 should close as "delivered by `53` and
ARZ-223" rather than be built.

## 3. Vendor marketplace — refused for now

A two-sided marketplace needs buyers and sellers in volume. ARZO has neither: its vendor relationships
are its own suppliers (`61`), and its organizers are, today, ARZO. Start with `61` — an internal
vendor register with ratings — which is useful to ARZO immediately and is the seed of any marketplace
later. Revisit only if external SaaS brings many organizers **and** they ask to find suppliers.

## 4. Cross-tenant benchmarking — the vendor's differentiator

"Your arrival peak versus 40 similar events" is compelling, and only a platform with many tenants can
say it. It depends on the vendor role (`98`), which ARZO has not chosen. `55` already fixed the rules:
opt-in in the contract, `event_benchmark_facts` only, at least five contributing accounts per
comparable set. Nothing more to design until external tenants exist.

## 5. Mobile wallet passes

Two different things share the name:

| Variant | What it is | Effort | Verdict |
|---|---|---|---|
| QR pass | The existing QR in Apple Wallet or Google Wallet; updates on change | M — pass signing, update service | Worth it once the attendee app (`30`) exists; attendees expect it |
| NFC pass | Wallet tapped at an NFC reader as the credential | L+ — platform programme approval, reader support (`36`) | Defer; approval and lead times `UNVERIFIED` |

The QR variant is convenience, not differentiation. Price it as such.

## Recommendation

1. **Validate talent integration commercially now** — interviews and a manual pilot cost almost
   nothing and decide whether the one unique asset is real.
2. **Build sponsor ROI evidence** with `34`, because it monetizes evidence-based reporting that the
   architecture produces anyway.
3. **QR wallet passes** after `30`.
4. **Defer** cross-tenant benchmarking until the vendor role is chosen.
5. **Close** the digital twin as emergent; **refuse** the marketplace and face recognition.

## Open questions

- **Does the talent business have a system of record ARZO can integrate with?** If it is spreadsheets, step 1 is an import, not an API.
- **Should editions of the same show be linked?** Yes — it serves talent analytics, `55` and `134`. A nullable series key on `events` is small; unnumbered, add to `136` when scheduled.
- **Are government delegations a real share of ARZO's events?** If so, protocol management may outrank everything here except talent.
- **Who owns commercial validation?** A business owner, not engineering — this document only frames the questions.

## Related

`112-arzo-differentiators.md` · `113-roadmap.md` · `04-product-strategy.md` · `98-commercial-model.md` ·
`133-ai-capabilities.md` · `134-advanced-analytics.md` · `55-event-intelligence.md` ·
`28-speakers-management.md` · `23-accreditation.md` · `36-rfid-nfc.md` · `30-mobile-event-app.md` ·
`31-networking.md` · `34-sponsor-management.md` · `53-live-event-command-center.md` · `61-vendor-management.md`
