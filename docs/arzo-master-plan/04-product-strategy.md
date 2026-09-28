# Product Strategy

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for scope decisions · **Audit date:** 2026-09-28
**Depends on:** `01`, `02`, `03` · **Blocks:** `98`, `99`, `100`, `112`

---

## The situation

ARZO is simultaneously two things, and the tension between them shapes every scope decision in this
plan:

1. **An events and talent business in Qatar** that runs its own events and represents the talent
   performing at them.
2. **The owner of a ticketing platform** (this codebase) that could be sold as SaaS.

A feature valuable to (1) is not automatically sellable as (2), and vice versa. Deciding per
capability rather than assuming both is the core discipline here.

## The two coherent positions

Stated plainly because the choice governs several phases, and the plan should not pretend it has been
made.

### Position A — ticketing product, partner for on-site

Stay what the codebase already is: a strong self-serve ticketing and registration platform. Partner
or subcontract accreditation, badging and on-site operations.

**Cost:** low. Phases 0–1 plus selective items from 3.
**Gives up:** the on-site market, and the ability to run ARZO's own large events end to end.
**Honest appeal:** the commerce third is genuinely good (~85% per `128`). Doubling down on it is
cheaper than becoming a different product.

### Position B — end-to-end event operations platform

Build accreditation, badges, access control, programme, exhibitors and offline on-site operation.

**Cost:** high. All five phases, plus hardware procurement (`102`) and possibly staffing (`57`).
**Gives up:** focus, and a quarter or more on Phase 4 alone.
**Honest appeal:** it is the only path to running ARZO's own multi-zone events in one system, and the
only path to the differentiators in `112`.

**This plan documents Position B**, per direction taken 2026-09-28, while naming per phase where the
commitment stops being code.

## Who the buyer is — `UNVERIFIED` and load-bearing

The single most valuable unanswered question in this plan.

| If the buyer is | Then |
|---|---|
| **ARZO's own operations team** | Optimize for ARZO's actual event mix. Skip Evento parity on capabilities ARZO never uses. SaaS packaging (`100`), enterprise SSO (`101`) and self-serve onboarding drop in priority. UX optimizes for trained staff. |
| **External organizers (SaaS)** | Tenant isolation must be hardened before scale (`08`, R2). Self-serve onboarding, billing and support matter. Feature breadth matters more than depth. **The AGPL position must be resolved first** (R1). |
| **Both** | The hardest option. Every feature needs two audiences considered, and internal needs will win arguments they should sometimes lose. |

Until this is answered, the roadmap is ordered by dependency rather than by market value — which is
the right default, but it is a default.

## Build, buy, or partner

Assessed per capability area, with the reasoning rather than just a verdict.

| Area | Verdict | Why |
|---|---|---|
| Ticketing, registration, payments | **Build** — already done | Mature and differentiated |
| Space, programme, accreditation, access | **Build** | The core of Position B; no credible off-the-shelf piece fits an existing data model |
| Badge **design canvas** | **Consider buying** | A drag-and-drop editor is a large UI project with no ARZO-specific value. `21` open question. |
| Badge **print pipeline** | **Build** | Must be offline-capable and queue-aware; vendor SDKs do not do this |
| Printers, RFID readers, kiosks | **Buy** | Hardware. `102`. |
| Badge stock, lanyards | **Buy** | Consumables |
| Seat-map authoring | **Buy or defer** | Large UI project; `26` questions whether ARZO's event mix needs it at all |
| SMS delivery | **Buy** | A provider, not a build. `43`. |
| Email campaign automation | **Integrate an ESP** | Building drip sequences and open/click tracking is a product in itself. Stay the source of truth for audiences, not the sender. `42`. |
| CRM | **Integrate** | Build the API well (`48`) and integrate the one CRM ARZO actually uses |
| On-site staffing | **Partner or hire** | Not software. `57` plans the system, not the agency. |
| Face recognition | **Refuse for now** | Legal exposure outweighs the throughput gain over RFID. `133` A10. |

The pattern: build what touches the domain model, buy what is a commodity, integrate what is someone
else's core competence.

## Non-goals

Named so they do not accrete by default:

- **Not** a rewrite. The commerce core stays.
- **Not** Evento feature-matching for its own sake (`110`, `111`).
- **Not** a venue CMS, marketing suite, or accounting system.
- **Not** abandoning self-serve ticketing — it funds the rest and serves real users.
- **Not** building AI where arithmetic suffices (`133` names three such cases).

## Decisions needed before Phase 2

Phase 2 is where proprietary work starts accumulating on top of AGPL code, so these are not
deferrable:

1. **The AGPL licensing position** (R1). Network-use copyleft applies to everything built here.
   Commercial licensing from Hi.Events is available. Cheap to resolve now, expensive later.
2. **Hardware commitment** (R5). Badge printers have lead times that can exceed the software
   timeline, and vendor choice drives `37`–`39` adapter work.
3. **The buyer question above**, at least provisionally.

## Open questions

- Is external SaaS a goal, or is this internal tooling with a ticketing product attached?
- Does ARZO's talent business share clients with its events business? `112` rates talent integration the strongest differentiator, and it is worthless if the two operate separately.
- Is Arabic required for ARZO's own events? If yes, `82` outranks much of this roadmap and the plan's ordering is wrong.
- What is the largest event ARZO intends to run? Without it, every performance target in `74` is provisional.

## Related

`01-executive-vision.md` · `03-gap-analysis.md` · `98-commercial-model.md` · `99-pricing.md` ·
`100-saas-tenancy.md` · `112-arzo-differentiators.md` · `120-risk-register.md`
