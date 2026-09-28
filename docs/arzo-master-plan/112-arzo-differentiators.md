# ARZO Differentiators

**Status:** WRITTEN · **Audit date:** 2026-09-28

---

## The test applied

A differentiator must be something ARZO can do that competitors structurally cannot or will not —
not merely a feature ARZO could also build. Feature parity is `111`; this document is about
advantage.

Each is rated on defensibility and effort, with the honest objection stated.

## 1. Talent-management integration — the strongest

ARZO represents the talent that performs at the events it runs. No event-technology vendor has that,
because none of them is also a talent agency.

**What it enables:** casting a performer into a session from the roster; booking, contracting and
paying them in the same system; issuing their accreditation and credential automatically; measuring
which talent drove attendance; feeding that back into casting.

**Defensibility: high.** A competitor would have to acquire a talent business.
**Effort: L**, and only after `23` (credentials) and `27` (sessions) exist.
**Objection:** it only matters if ARZO's talent and events businesses actually share clients. If they
operate separately, this is an integration nobody asked for. Worth validating before building.

## 2. Offline-first as a guarantee, not a hope

Evento advertises no offline story; most event platforms degrade silently. ARZO can make "the door
keeps working when the network dies" a contractual claim with a tested basis.

**Defensibility: medium-high.** Technically hard and architecturally invasive, so not quickly copied
— offline changes the shape of every write path.
**Effort: XL** — `71`, Phase 4.
**Objection:** it is invisible when it works. Selling it requires demonstrating failure, which is an
unusual sales motion. Mitigation: show the reconciliation report from a deliberately disconnected
drill.

## 3. Evidence-based reporting

Because `access_logs` and `session_attendance` are append-only with direction and both device and
server timestamps, every figure is reconstructible from primary records rather than counters.

**What it enables:** numbers that reconcile; disputes settled by evidence; retrospective violation
detection after offline replay; auditable attendance for sponsors and government clients.

**Defensibility: medium.** Copyable, but only by rebuilding the data model — which is exactly the
decision most platforms got wrong early (counters that drift).
**Effort: M**, and it falls out of `24` and `27` almost for free.
**Objection:** most clients do not ask for it. It wins the ones who do — government and sponsor-heavy
events.

## 4. One system from lead to closeout

ARZO operates events itself, so it feels the seams between CRM, ticketing, on-site, and post-event
reporting. Competitors sell one slice.

**Defensibility: medium.** Broad scope is imitable but expensive.
**Effort: L** — `56`, `105`, Phase 5.
**Objection:** breadth is only a virtue if each part is good enough. A mediocre CRM bolted onto a good
ticketing product loses to two specialists. This should be judged per module, not asserted.

## 5. Arabic and RTL done properly

ARZO is a Qatar business. `CONFIRMED`: the platform has 20 locales and **no Arabic**, and no RTL
support has ever been exercised across 378 components.

**Defensibility: low-medium** technically, **high** commercially in the GCC — most international
platforms treat Arabic as an afterthought.
**Effort: L** — `82`, `135`.
**Objection:** this is arguably not a differentiator but a **home-market requirement**. That it sits
on a differentiator list at all is a finding. If ARZO's own events need Arabic, it outranks much of
this plan.

## 6. Genuine multi-party access

Once `09` lands, ARZO can give exhibitors, speakers, contractors and staff scoped self-service access
rather than routing everything through an organizer.

**Defensibility: low.** Standard in mature platforms.
**Effort: M** on top of `09`.
**Objection:** table stakes rather than advantage. Listed because ARZO lacks it today.

## Assessed and not recommended now

Honest assessment rather than a wish list:

| Idea | Verdict |
|---|---|
| **Face recognition** | Defer. Biometric processing under Qatar PDPL and GDPR needs legal review and likely a DPIA. The throughput gain over RFID is modest; the liability is not. `133`. |
| **Event digital twin** | Emergent, not a project. Once space, time, people, devices and logs exist, the twin largely *is* the data model (`06`). Naming it as a deliverable invites building a visualization with nothing behind it. |
| **Vendor marketplace** | Two-sided marketplaces need liquidity ARZO does not have. Business-model decision first (`98`). |
| **AI everything** | `133` assesses each. Most need training data that will not exist for several events. Predictive attendance on three events of history is astrology. |
| **Venue heatmaps** | Genuinely useful and cheap once `access_logs` exists — but it is a feature of evidence-based reporting (3), not a separate differentiator. |

## Recommendation

Pursue **2** (offline-first) and **3** (evidence-based reporting) as the technical position — both
fall out of the architecture this plan already requires, so the marginal cost is low.

Validate **1** (talent integration) commercially before building; it is the only genuinely unique
asset, and also the easiest to over-invest in if the two businesses do not actually share clients.

Treat **5** (Arabic) as a requirement, not a differentiator, and decide its priority against ARZO's
actual event calendar.

## Related

`04-product-strategy.md` · `110-evento-comparison.md` · `111-competitive-gap-closure.md` ·
`132-future-capabilities.md` · `133-ai-capabilities.md` · `82-localization.md`
