# Event Day Runbook

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 — needed for the first operated event, on today's software · **Phase:** any; target lines apply after Phase 4
**Depends on:** `106-event-operating-model.md`, `103-hardware-deployment.md`, `104-onsite-infrastructure.md`, `71-realtime-architecture.md`
**Blocks:** `131-event-readiness-checklist.md`

---

The template for the day itself, written for people under pressure: short, ordered, one owner per
step. It is copied per event at G2, filled in, printed at T-24h and rehearsed. Role abbreviations
are `106`'s: ED, DM, AL, SL, GS, BL, TL, CCO, ENG, DPL.

## Current state — `MISSING`, 0%

No runbook exists. What matters more is what the software does **today**, because the degraded
procedures below must work with it:

| Capability | Today | Target |
|---|---|---|
| Scanning when the network drops | **Stops** — "not checked in, scan again" (`38`) | Continues locally; amber banner with data age (`71`, ARZ-101, ARZ-103) |
| Badge printing | None — tickets print through `window.print()` (`22`) | Print host per desk, retry and reprint (ARZ-072, ARZ-121) |
| Access override | No code path writes one (`AccessResult.php:17` unused) | Logged `GRANTED_OVERRIDE` with operator and reason (`24`) |
| Command center | None; the check-in throughput gauge caps at 4 per minute (`51` R7) | `53` (ARZ-170) |
| Revoked credential at a gate | A cancelled ticket shows "ticket is cancelled" (`CreateAttendeeCheckInService.php:220-221`) | `DENIED_REVOKED`; offline admissions surface at reconciliation (`71`) |
| Closing a gate in software | **Not possible** — see B1 | An access point marked inactive denies |

Every procedure therefore carries a **Today** line. When filling the template, strike whichever of
Today or Target does not apply to this event's software.

### Defects that change the procedure

| # | Defect | Evidence | Severity |
|---|---|---|---|
| B1 | **An inactive access point still admits.** The scan path loads the access point filtered only on `deleted_at`, never on `is_active`, and the decision function is not told | `AccessScanService.php:61-64` | Medium before first use — "close gate 3" in the admin UI would do nothing. **Fix now**; the scan route was uncommitted, in progress at audit time |

Known defects the procedures work around: exports stop silently at 10,000 rows (`51` R1) — the paper
roster; the throughput gauge (`51` R7) — the CCO reads counts, not rates; an Arabic keyboard layout
breaks wedge scanners (`38` S1) — staging (`103`).

## Principles on the day

1. **Doors never wait for the software.** If in doubt, admit on paper and write it down.
2. **Exits are never blocked.** An exit scan is a record, not a gate: the decision function skips
   capacity, entry limits and anti-passback for exits (`AccessDecisionService.php:103-111`), and a
   denied exit scan never stops anyone leaving.
3. **Swap first, diagnose later.** Spares are hot for this reason (`102`).
4. **Say what you do not know.** If a screen shows data age, read it out on the radio.
5. **Every override, fallback and exception is written down** — time, place, who, why.

## Page 1 — contacts

| Role | Name | Mobile | Radio channel | Backup |
|---|---|---|---|---|
| Event Director (ED) | | | | |
| Duty Manager (DM) | | | | |
| Security Lead (SL) | | | | |
| Accreditation Lead (AL) | | | | |
| Badge Desk Lead (BL) | | | | |
| Device Lead (TL) | | | | |
| Command Center (CCO) | | | | |
| Engineering on-call (ENG) | | | | |
| Venue security control | | | | |
| Venue IT | | | | |
| Venue medical | | | | |
| Client representative | | | | |

Escalation order when someone does not answer within 2 minutes: the backup, then the DM.

## Timeline of the day

Venue-local times. Anchored on `doors_open_at` (`56`).

| When | Step | Owner | Done when |
|---|---|---|---|
| T-3h | Ops room open; radios issued; radio check | DM | Every lead answers |
| T-3h | Devices on, online, synced; printers on, stock counted | TL, BL | Fleet view green (today: every scanner loads its list) |
| T-2h30 | Staff sign in at the Staff sign-in point (`57`) | GS | Rostered headcount present per open gate |
| **T-2h** | **G3 readiness review (`131` T-2h)** | DM runs, **ED decides** | Decision recorded (`59`) |
| T-90m | Gate briefings: position, access in plain words, the override rule, where the paper pack is | GS | Every steward briefed |
| T-60m | Canary scans at every gate: valid, wrong zone, revoked, outside hours | GS | All four results as expected |
| T-30m | Revoke the valid canaries; final sync | AL, TL | Deny-list synced on every device |
| T-15m | Paper packs sealed at every gate and desk; CCO starts the watch | GS, CCO | — |
| **T-0** | **Doors open**; first admissions reported | GS → CCO | — |
| Every 30 min for 2 h, then hourly | Status to DM: arrivals, denials by reason, silent devices, printer stock, queues, open incidents | CCO | — |
| Shift change | Handover: open incidents, device problems, overrides so far | Outgoing → incoming GS | Handover line signed in the log |
| Last entry | Close entry points; exits stay open | GS | — |
| Close | Final sync; teardown per `103`; paper packs collected and counted | TL, DM | Every device at zero unsynced |

## Degraded-mode procedures

Each: **You see** → **First** → **Then** → **Escalate** → **Record**. Printed one page per procedure,
plus a single laminated position card with all eight.

### D1 — One device offline
- **You see:** amber banner "Offline — decisions based on data from HH:MM" (`71`); fleet view shows it silent (`40`); or the operator calls it in.
- **First (operator):** keep scanning — the device decides locally. Do not restart or reload it.
- **Then (TL):** are the other devices at this gate online? If yes: power, wifi, SIM. If dead: swap from the zone pool and reassign the access point.
- **Escalate:** offline beyond 30 minutes → emergency mode → tell the GS that capacity and anti-passback checks are off at that device. Two or more devices in one area → D4.
- **Today:** an offline web scanner cannot check anyone in. Move the lane to another device, or to F.
- **Record:** SEV3 incident if offline more than 30 minutes.

### D2 — Printer jammed, out of stock or dead
- **You see:** printer `PAPER_OUT`, `HEAD_OPEN` or `OFFLINE`; a job stuck at `SENT`; no badge comes out; command-center alert (`53`, High).
- **First (desk operator):** move the queue to the hot spare. Do not clear a jam with people waiting.
- **Then (BL):** clear or refill. A jam is a **retry** of the same job; a damaged badge is a **reprint** — new badge, old one voided (`39`, `21`). Voided badges go in the shred bag.
- **Escalate:** two printers down, or stock below one hour of expected demand → DM. Badge area stopped with a queue → SEV2 (`60`).
- **Today:** no badge printing exists; desks hand out pre-printed badges.
- **Record:** stock count after every refill.

### D3 — Backend unreachable, venue network fine
- **You see:** every device fails to sync at once while other sites load; every web scan errors; ENG sees it in Sentry.
- **First (every operator):** keep scanning offline. **Do not reload any page** — with no service worker, a reload needs the frontend host, and a failed reload loses the scanner (`104`).
- **Then:** TL rules out the local network (D4). ENG diagnoses. DM tells ED.
- **Escalate:** beyond 15 minutes → SEV2. Beyond 30 → every device in emergency mode and revocations stop propagating (`120` R8): SL decides whether supervised points use the printed deny-list. A fix during the freeze needs ED and EL (`106`).
- **Today:** check-in stops. DM declares F at every gate at once.
- **Record:** incident with start and end times; closeout marks the window (`108`).

### D4 — Network down venue-wide
- **You see:** every device offline; the ops router shows the uplink down; radios still work.
- **First (TL):** confirm cellular failover took over (`104`). If it did, devices recover without help.
- **Then:** gates continue offline — or F, today. DM declares degraded mode. Comms move to radio.
- **Escalate:** SEV2 as soon as a queue forms. ENG informed; they will watch reconciliation.
- **Recovery:** TL watches every device return to zero unsynced. Never restart or wipe a device that still holds unsynced records.
- **Record:** incident; every figure for the window is provisional until settled (`52`).

### D5 — Revoked credential presented
- **You see:** red, "not valid" — `DENIED_REVOKED`. Today: "ticket is cancelled".
- **First (operator):** do not admit. Stay polite; send the person to the GS. Keep the badge only if the SL has said so for this event.
- **Then (GS):** AL checks the record. A replaced credential (lost-badge reissue, `21`) → the desk reprints. A genuine revocation → refuse. A suspected clone or shared badge (`68`) → SL.
- **Escalate:** suspected fraud → SL, SEV3; SEV2 at high-security events.
- **Note:** a device that has not yet synced the revocation will admit. That surfaces later as a retrospective violation (`71`, `108`); nothing to do at the gate.
- **Record:** if the GS admits after all, it is an override, with a reason.

### D6 — Capacity breach
- **You see:** zone at or above capacity (`53`, High) — meaningful only where exits are scanned (`54`); a steward's count; venue safety calls it. Devices in emergency mode do not check capacity (`71`).
- **First (GS):** hold entry at that zone's access points — **physically**; software cannot close a gate today (B1). Exits stay open.
- **Then (DM):** with the venue, decide on redirecting to an overflow area.
- **Escalate:** SEV2 (`60`). Any crowd risk → SEV1: the venue's emergency procedures take over.
- **Record:** incident; the times the hold began and ended.

### D7 — Denial spike at one gate
- **You see:** many denials with one reason (`53`): `DENIED_NO_GRANT` — a rule is wrong; `DENIED_TIME_WINDOW` right at doors-open — time rules (see `131` K1: windows are evaluated in UTC today); `DENIED_ANTIPASSBACK` — a direction mode is wrong (`54`).
- **First (GS):** admit clearly valid holders by override, reason "rule fault". Today there is no override: admit on paper (F) and record.
- **Then (AL, SL):** fix the rule; devices pick it up at the next sync; confirm with a canary scan.
- **Escalate:** DM if it lasts more than 10 minutes or spreads to other gates.
- **Do not:** disable rules wholesale.

### D8 — Device lost or stolen
- **First (TL):** revoke its key immediately (`device.manage`, `40`).
- **Then:** SEV3 incident; DPL told at once — a device carrying the roster may be a personal-data breach, and whether it was encrypted at rest decides how serious (`65`, `71`).
- **Record:** asset tag, last seen, last sync.

### F — Paper fallback
1. GS opens the gate's sealed pack: roster sorted by surname with each ticket or credential code, a walk-up sheet, pens.
2. For each person: find the name, check ID where this event requires it, tick with the time.
3. Not on the list: send to the desk. No admission on paper for an unknown person unless the GS decides and writes it down.
4. When service returns, resume scanning. **Do not back-enter paper ticks** unless the DM orders it — back-entry loses the real times and creates duplicates. Sheets go to the DM for closeout.

**Today's roster source** is the attendee export, which carries names, status and the ticket code
(`AttendeesExport.php:54-68`). It **includes attendees of cancelled orders** and **stops silently at
10,000 rows** (`AttendeeRepository.php:51-57`, `51` R1): remove `CANCELLED` rows and compare the
count with the dashboard total before printing.

## Paper copies

The failure case may be the system that holds the runbook, so it exists on paper.

| What | Copies | Afterwards |
|---|---|---|
| This runbook, filled | One per lead, two in the ops room | One copy to the dossier (`63`); the rest shredded |
| Contacts sheet | Every gate, desk and lead | Shredded — it holds personal numbers |
| Position card (D1–D8, F) | Every gate and desk, laminated | Kept if it holds no personal data |
| Fallback roster pack | One per gate and desk, sealed, numbered | Collected, counted, shredded (`103`) |
| Printed deny-list | Supervised points at high-security events only | Collected and shredded |

## Rehearsal and maintenance

- **Tabletop at T-7d:** the leads walk D1–D8 and F against this event's layout (`131`).
- **Drill at T-24h:** the offline drill in `104`, which exercises D3, D4 and F for real.
- **Owner between events:** the operations team's Duty Manager owns the template. Lessons from
  closeout (`108`) change it; engineering updates the Today and Target lines whenever a release
  changes device behaviour — a release-checklist item (`123`).

## Open questions

- **Override without software** — until the override path exists, is a GS paper log acceptable to clients with audit requirements? Probably, if it is transcribed at closeout.
- **Canary credentials** — a dedicated test accreditation type needs seeding (`23`) and exclusion from audience metrics (`54`).
- **Position-card language** — stewards may need Arabic; the card is part of the `82` question, not an afterthought.
- **Re-entry on today's scanner** — a second scan says "already checked in" (F2). Each event needs a written pass-out rule until anti-passback exists (ARZ-062).

## Related

`106-event-operating-model.md` · `103-hardware-deployment.md` · `104-onsite-infrastructure.md` ·
`71-realtime-architecture.md` · `53-live-event-command-center.md` · `60-incident-management.md` ·
`38-scanner-platform.md` · `39-printer-integration.md` · `21-badge-management.md` ·
`51-reporting.md` · `108-post-event-closeout.md` · `131-event-readiness-checklist.md`
