# Check-In

**Status:** WRITTEN · **Authority:** AUTHORITATIVE for check-in consolidation · **Audit date:** 2026-09-28
**Classification:** Refactor + Replace

---

## Current state — `PARTIAL`, and bifurcated

There are **two independent check-in implementations** with different storage, different quality,
and different side effects. `CONFIRMED` by reading both paths.

| | Path A — dashboard | Path B — public scanner |
|---|---|---|
| Route | `POST /events/{id}/attendees/{pid}/check_in` | `POST /public/check-in-lists/{uuid}/check-ins` |
| Action | `CheckInAttendeeAction` | `CreateAttendeeCheckInPublicAction` |
| Handler | `CheckInAttendeeHandler` | `CreateAttendeeCheckInPublicHandler` |
| Domain service | **none** — logic inline in the handler | `CreateAttendeeCheckInService` |
| Writes | `attendees.checked_in_at` / `_by` **columns** | `attendee_check_ins` **rows** |
| Transaction | no | yes |
| Idempotency | none | `UniqueConstraintViolationException` handled |
| Domain event | **none → no `checkin.created` webhook** | yes |
| Authorization | `isActionAuthorized` | **none** — the UUID is the credential |

Checking someone in through the dashboard and through the scanner produces **different data**, and
only one fires webhooks. This is finding F10.

## Three defects to fix

### F1 — offline scans are lost, and retry is blocked · `CONFIRMED`

`frontend/src/components/layouts/CheckIn/index.tsx`:

```
L388:  processedBarcodesRef.current.add(attendeePublicId);   // marked processed...
L393:  await handleCheckInAction(attendee, "check-in");      // ...before the call
```

```
L261-268:  onError: (error) => {
             recordScan(attendee, attendee.public_id, "error");
             if (!networkStatus.online) { showError(t`You are offline`); return; }
```

On failure the check-in is **not queued, not retried, not persisted** — the only trace is an
in-memory list capped at 20 entries and destroyed on reload. And because the barcode was marked
processed before the await, re-scanning is rejected as "just scanned" rather than retried.

A further detail: `handleQrCheckIn` is `async` and `await`s `handleCheckInAction`, which is **not
async and returns void** — so the `isProcessingRef` mutex releases before the network call resolves.

**Severity: high.** At a real event a wifi blip admits people without recording them, and staff
cannot fix it by re-scanning.

### F3 — no operator identity · `CONFIRMED`

`/check-in/:checkInListShortId` authenticates on the **URL short ID alone**. No PIN, no device
pairing, no staff identity, no per-scan attribution. `ALLOWED_UNAUTHENTICATED_PATHS` includes
`check-in`, so 401/403 does not redirect.

Anyone with the link can check anyone in, and the log cannot say who did. The link-sharing
convenience is genuinely useful for volunteers, so the replacement must stay nearly as easy or it
will be worked around.

### F2 — the schema forbids re-entry · `CONFIRMED`

```sql
CREATE UNIQUE INDEX attendee_check_ins_unique_attendee_list
  ON attendee_check_ins (attendee_id, check_in_list_id) WHERE deleted_at IS NULL;
```

Check-out is a **soft-delete** of the row (`api.php:661`), destroying the history. Re-entry and
anti-passback are structurally impossible here.

## What is genuinely good and must survive

The scanner UI is 3,752 LOC of thoughtful work, and a rewrite would lose it:

- Dual input: camera (`qr-scanner`, 1 scan/sec, torch, multi-camera picker) and USB/HID keyboard wedge with focus tracking and a 100ms flush
- Audio + haptic feedback, mute persisted
- Undo via `showSuccessWithUndo`
- Occurrence filtering for recurring events
- Tab state in the URL hash, so browser back works
- Live throughput over a 5-minute window
- Graceful handling of expired, inactive and cancelled lists with a countdown

**Extend this; do not replace it.**

## Target

One check-in concept, writing to one append-only place, with identity.

```
Scan (camera | USB | RFID | NFC | manual | kiosk)
  -> resolve identifier -> credential            (23)
  -> access decision (pure function)             (24)
  -> write access_logs row                       (24)
  -> optional session_attendance row             (27)
  -> update advisory counters
```

`attendees.checked_in_at` becomes a **derived convenience** — kept for existing queries and reports,
no longer the source of truth.

## Migration — the one risky sequence in the plan

Parallel run. At every point before step 5, disabling the new path restores exactly the old behaviour.

| Step | Action | Rollback |
|---|---|---|
| 1 | Build `access_logs`, rules and grants. Nothing reads them. | Drop tables |
| 2 | **Dual-write:** both check-in paths also write `access_logs` at the venue's default access point | Stop dual-write |
| 3 | Consolidate Path A onto a domain service; make it dispatch `CheckinEvent` (fixes F10) | Revert handler |
| 4 | Backfill historical `attendee_check_ins` → `access_logs` (`source = IMPORT`) | Delete imported rows |
| 5 | Move UI and report reads to `access_logs`; keep dual-write | Point reads back |
| 6 | New scanner writes only `access_logs`; `attendee_check_ins` becomes read-only legacy | Re-enable old writes |
| 7 | Decide whether to drop `attendee_check_ins` — **deferred**, it is the rollback path | — |

Step 3 is where F10 closes: the dashboard path finally emits the webhook it should always have.

## Retain `check_in_lists` as an organizer concept

It maps loosely onto "access point + product predicate + time window", and the public scanner link
depends on it. Cleanest path: keep it as the organizer-facing abstraction that **compiles to access
rules**, so existing UI and shared links keep working while the engine underneath changes.

## Fix order

Independent of the roadmap, and worth doing first:

1. **F1** — swap the dedupe ordering, make `handleCheckInAction` async, add a retry path. Small, isolated, high value.
2. **F3** — device enrolment plus operator identity. Needs `40`, so Phase 4.
3. **F10** — consolidate the two paths. Phase 1.
4. **F2** — resolved by `access_logs` existing at all. Phase 1.

## Open questions

- **Operator identity mechanism at a shared device:** PIN, staff badge scan, or login? Badge scan is the most elegant and needs `23` first.
- **Does check-in remain usable by a bare link for volunteers?** Losing that is a real operational regression; consider a device-bound link with a short-lived token.
- **Session check-in vs zone access at the same door** — `27` assumes both rows are written when a session room is access-controlled. Confirm during Phase 3.

## Related

`24-access-control.md` · `23-accreditation.md` · `71-realtime-architecture.md` ·
`38-scanner-platform.md` · `40-device-management.md` · `02-current-state-audit.md` (F1, F2, F3, F10)
