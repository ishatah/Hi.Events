# AI Capabilities — Feasibility Assessment

**Status:** WRITTEN · **Audit date:** 2026-09-28

---

## Approach

The brief asked for AI capabilities to be **evaluated**, not assumed. Each is assessed on value,
feasibility, data dependency, privacy exposure, and cost — with a recommendation that is sometimes
"no".

Most of these need data that will not exist until several events have run through the full stack.
Predictive models trained on three events produce confident nonsense.

`CONFIRMED`: the codebase already has AI infrastructure — `laravel/ai ^0.11.0`, `config/ai.php`, and
a working use case in `EventSpamCheckJob` (AI-assisted event screening with an admin approve/confirm
queue, `$tries=3`, `ShouldBeUniqueUntilProcessing`). So the plumbing exists and has a precedent.

## Recommended — build when the data exists

### A1 — Automated post-event reports
**Value: high · Feasibility: high · Data: available after Phase 5**

Turn attendance, session, and lead data into a written client report. This is summarization over
structured data — what LLMs are genuinely reliable at, with figures computed conventionally and only
the prose generated.

**Why it is safe:** the numbers come from `access_logs`, not from the model. The model writes
sentences about numbers it is given.
**Guardrail:** never let the model compute a figure. Template the metrics; generate only narrative.

### A2 — Intelligent lead scoring
**Value: high to exhibitors · Feasibility: medium · Data: needs lead history**

Rank leads by likely value using engagement signals — sessions attended, dwell time, booth visits,
qualification answers.

**Blocker:** needs several events of lead outcomes to be anything but a guess. Start with a
transparent rules-based score, which is explainable to exhibitors, and only add learning once outcome
data exists.

### A3 — Accreditation triage
**Value: medium-high · Feasibility: high · Data: available immediately**

Pre-screen accreditation applications: flag incomplete submissions, verify a press domain against
known outlets, detect duplicates, surface anomalies for review.

**Hard constraint: assist, never decide.** A rejected journalist may escalate, and "the model said no"
is not a defensible answer. Keep the human in the loop and record who approved (`67`).
Extends the existing spam-check pattern.

### A4 — Predictive queue and staffing warnings
**Value: high on the day · Feasibility: medium · Data: needs arrival history**

Project queue build-up 15–30 minutes ahead from arrival curves, so staff can be moved before a queue
forms rather than after.

**Note:** this may not need ML at all. A trend extrapolation on arrival rate is probably 80% as good,
explainable, and debuggable at 2am. Try that first.

## Assess later — plausible, not yet justified

### A5 — Predictive attendance
Forecast turnout from registration, historical no-show, weather, day of week. Needs many comparable
events; ARZO will not have that in year one. Until then, historical no-show rate is a simpler and
more honest estimate.

### A6 — Schedule optimization
Suggest session times minimizing conflicts and balancing room use. A constraint-solver problem more
than an AI one — `27` already has the conflict data and a GiST constraint. Solve it deterministically
before reaching for a model.

### A7 — AI attendee assistant
"Where is my next session?" in natural language. Value depends entirely on the app existing (`30`) and
on attendees preferring chat to a good agenda screen. A well-designed agenda beats a chatbot for most
queries.

### A8 — AI event assistant for organizers
Natural-language querying of event data. Genuinely useful for ad-hoc questions. Needs careful
authorization — a model with database access must respect per-event permissions (`09`), or it becomes
the easiest cross-tenant leak in the system. **Do not build before `09` lands.**

### A9 — Automated incident detection
Infer incidents from signals: denial spike at one gate, device offline cluster, occupancy anomaly.
Mostly thresholds and anomaly detection, not LLM work. Fold into `53` alerting rather than treating it
as AI.

## Not recommended now

### A10 — Face recognition
**Recommendation: do not build without legal clearance.**

Biometric processing under Qatar's PDPL and GDPR is special-category data requiring explicit lawful
basis and almost certainly a DPIA. Throughput gains over RFID are modest; RFID is faster, cheaper,
and carries none of the liability.

If a client demands it, treat it as a separate project with legal sign-off first, explicit consent, a
non-biometric alternative always available, and a defined retention and deletion policy for templates.
Tracked as ARZ-260 with a legal gate.

### A11 — AI staffing recommendations
Needs historical shift performance data ARZO does not have. Also touches employment decisions, where
algorithmic recommendation carries fairness obligations. Revisit only with real data and a policy
position.

### A12 — AI-generated event content
Marketing copy, session descriptions. Low value, high brand risk for a business whose positioning is
deliberate and austere. ARZO's brand voice is specific; generated copy will not match it.

## Cross-cutting guardrails

Applied to every item above:

1. **Never let a model compute an authoritative number.** Metrics come from the database; models write prose about them.
2. **Assist, never auto-decide** anything contestable — accreditation, access, employment.
3. **Authorization applies to models too.** An assistant with data access must respect per-event permissions, or it is a leak vector (`09`).
4. **Attendee data in prompts is a data transfer.** It needs a lawful basis and a processor agreement (`65`).
5. **Log every AI-assisted decision** with the input, output, and the human who accepted it (`67`).
6. **Cost is per-call and scales with events.** Budget it, and prefer a deterministic algorithm whenever one exists.

Point 6 deserves emphasis: three of the twelve items above are better solved without AI at all
(A4, A6, A9). Reaching for a model where arithmetic suffices adds cost, latency, and a debugging
problem.

## Recommendation

Build **A3** (accreditation triage) in Phase 2 — it extends a proven pattern and has data now.
Build **A1** (post-event reports) in Phase 5 when the data exists.
Solve **A4**, **A6** and **A9** deterministically and call them features, not AI.
Defer the rest. Refuse **A10** pending legal clearance.

## Related

`132-future-capabilities.md` · `134-advanced-analytics.md` · `65-privacy-gdpr.md` ·
`09-permissions-and-roles.md` · `53-live-event-command-center.md` · `120-risk-register.md`
