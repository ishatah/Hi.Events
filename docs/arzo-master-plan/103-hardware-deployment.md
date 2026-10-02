# Hardware Deployment

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Business commitment (process), with software touch-points in `40` · **Priority:** P1 — business; software via ARZ-100, ARZ-123 · **Phase:** 4 for the fleet; the today-version applies now
**Depends on:** `102-hardware-procurement.md`, `40-device-management.md`, `71-realtime-architecture.md`
**Blocks:** `107-event-day-runbook.md`, `108-post-event-closeout.md`

---

How devices get from base to a working position at the venue and back, and how they leave the event
holding no attendee data. The principle from the scaffold stands: devices are staged, imaged,
enrolled and pre-synced **before** leaving base — never configured at the venue under time pressure.

## Current state — `MISSING`, ~5%

| Fact | State | Evidence |
|---|---|---|
| Device table | Exists; no route, pairing, heartbeat or sync endpoint | `2026_09_30_000003:132-167`; `40` |
| Native scanner or kiosk app | `MISSING` | `94`, `96` are scaffolds; ARZ-152, ARZ-110 TODO |
| What today's web scanner keeps on the device | Preferences only — scan mode, sound, a "description seen" flag — in `localStorage`; **no roster** | `CheckIn/index.tsx:73,91,152,158,167-175` |
| What today's scanner depends on | The check-in list link, a bearer secret (`38`); disabled for scans by `expires_at` | `CheckInListActivityValidator.php:16-25` |
| Scans attributable to a device | **No** — `access_logs` has no `device_id`; `AccessScanService::scan()` accepts `$deviceId` and drops it | `AccessScanService.php:38,197-229` |
| Asset tag or serial on `devices` | `MISSING` — only `metadata jsonb` | Migration above |
| Photo size control for roster sync | `MISSING` — no resize pipeline | `73`, `22` |

So there is almost nothing to stage today, and almost nothing on a device to sanitize. Both change
completely with the native apps (`71`: a device then carries names, photos and possibly ID data).

### Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| H1 | **An expired check-in list can still undo check-ins.** `CheckInListActivityValidator` guards scans, attendee lookups and stats, but `DeleteAttendeeCheckInService::deleteAttendeeCheckIn()` never calls it, so the public undo route keeps working after `expires_at` | `DeleteAttendeeCheckInService.php:22-51`; validator callers | Low — needs a check-in short id, but it means "expire the link" does not fully retire it. **Fix now** |
| H2 | `scan()` takes `$deviceId` and silently discards it | `AccessScanService.php:38`, `recordLog` `:197-229` | Low — a known gap (`40` step 2), but a parameter that does nothing invites callers to trust it |

Two known defects change the procedure below: **S1** (`38`) — an Arabic keyboard layout silently
breaks keyboard-wedge scanners, so staging must set the layout — and the missing photo pipeline,
which leaves pre-sync payload size uncontrolled.

## Decision: operations owns device readiness; engineering owns the build

The scaffold asked. **Operations**, through a named Device Lead per event (`106`). The kit is
physical and runs on the event's timeline; if engineering is on the critical path of staging, events
end up depending on one developer's laptop. Engineering owns what the Device Lead consumes: the app
release, the golden vectors (`37`), the pre-sync verification tool and on-call support.

The objection — early events may have no technical operations person — is fair. Then an engineer
does it, **in the Device Lead role, against this checklist**, not ad hoc.

## Timeline

Relative to `doors_open_at` and `breakdown_ends_at` (`56`), in venue-local time.

| When | Stage | Owner | Done when |
|---|---|---|---|
| T-14d | Kit allocated from `102` formulas; rentals confirmed | Device Lead | Kit list signed |
| T-7d | Staging and imaging | Device Lead | Every device passes the staging checklist |
| T-5d | Enrolment and access-point assignment | Device Lead | Every device `ACTIVE`, assigned, purpose set (`38`) |
| T-3d to T-2d | Full roster pre-sync, then soak test | Device Lead | Verification passes on every device |
| T-2d | Pack, seal, transport | Device Lead | Manifest counted and signed |
| Build-up | Venue install, after `104`'s network is up | Device Lead + gate supervisors | Canary scans pass at every position |
| T-24h | Offline drill (`104`), readiness (`131`) | Device Lead | Evidence attached |
| Last exit | Final sync, teardown | Device Lead | Every device reports zero unsynced |
| T+2d | Sanitization complete, evidence filed | Device Lead | Record per device in the dossier (`63`) |

A multi-day event runs this chain once. Between days it is charge, delta-sync and re-check, not
re-stage.

## Staging and imaging — per device, at base

1. **OS version pinned; automatic OS and app updates deferred** until after `breakdown_ends_at`. An
   update at 07:00 on event day is an outage.
2. App build is the release that passed the hardware-in-the-loop rig and golden vectors (`37`);
   version recorded against the device.
3. MDM profile: kiosk lockdown for kiosks (`19`); camera and NFC permissions pre-granted; screen
   timeout per role (`40`: MDM is an operational tool, not integrated).
4. **Clock:** NTP sync verified. Clock skew corrupts offline replay (`71`).
5. **Keyboard layout US** on any host with a keyboard-wedge scanner until S1 is fixed (`38`).
6. Battery health checked; replace below a threshold set after the first soak (`UNVERIFIED`).
7. Peripherals: test print, test read; asset label applied.
8. Event network profile loaded — SSID and pre-shared key per `104`.

**Today's version:** charge, open the check-in list link, set the wedge layout, scan one test ticket.

## Enrolment

Per `40`: create the device against the event with type, access point and purpose; the device
exchanges a 10-minute pairing code for its own key, stored in the OS keystore. The key expires at
event end plus a grace period. Zone spares are enrolled with the zone's default access point and
reassigned on swap.

## Full roster pre-sync

`71` targets **under 60 s for 10k attendees** per device. That target is `UNVERIFIED`, and its
payload is dominated by photos whose size nothing controls today (`73`). Photos must be normalized
before they reach devices, or the target is meaningless.

- **Where:** at base, on a wired link — never at the venue, whose bandwidth is unknown (`104`).
- **When:** as late as possible (T-3d to T-2d) to shrink the on-site delta, early enough to redo.
- **Schedule:** elapsed ≈ max(t_device, n · P / B) for n devices, roster payload P and base uplink B.
  If bandwidth-bound, sync in batches.
- **Verification**, per device: cursor equals the server's; local roster count equals the server's
  count of `ACTIVE` credentials; three known credentials resolve with photos; a revoked test
  credential is denied.
- **On site:** deltas only, deny-list first (`71`). A registration made after a device went offline
  is unknown to that device — the person goes to a desk.

**Soak test** after pre-sync: each device runs on battery for one shift length on a scripted scan
set, including an offline stretch and a reconnect. It yields the measured battery life the charging
plan needs.

## Asset tracking

- Every item carries an asset tag. Enrolled devices hold it in `devices.metadata` until `40`'s
  follow-on migration gives it a column; a label shows the device `short_id` as a QR for lookup.
- A manifest per case, counted at five hand-offs: **pack, arrival, install, teardown, return**.
- An enrolled device missing at any count: **revoke its key immediately** (`device.manage`), raise
  an incident (`60`) and tell the privacy owner — it may hold a roster (`65`).
- The register is a spreadsheet until the fleet outgrows it. ARZO is not building asset management
  (`04`); non-enrolled kit — UPS units, routers, cables — lives only there.

## Charging

Measured battery hours b (soak) against operating hours H. If b < H, each position needs ⌈H/b⌉ − 1
swaps; with no mid-day charging, the charged pool per zone ≥ positions × (⌈H/b⌉ − 1). A swap is a
**device** swap — the charged spare is already enrolled, so the Device Lead only reassigns the
access point. Full charge at pack; a charging station in the ops room sized for the pool.

## Transport and custody

Rugged cases, tamper seals, manifest in the case and in the Device Lead's hand. A named custodian
from base to venue and back. Devices travel powered off, so the local store stays encrypted at rest
(`71`, `94`). Cases are never left unattended at a loading dock.

## Venue install — per position

1. Mount, power, network per `104`'s position map.
2. Fleet view (ARZ-123): online, synced, clock within threshold, app version current.
3. Access point and purpose correct; scan direction set on bidirectional points (`54`).
4. **Canary scans** with test credentials: valid → `GRANTED`; wrong zone → `DENIED_NO_GRANT`;
   revoked → `DENIED_REVOKED`; outside hours → `DENIED_TIME_WINDOW` — the last catches `131` K1.
5. Printers: test badge, media calibrated, stock counted.
6. Position labelled with the access-point code.

Canary credentials belong to a test accreditation type excluded from audience metrics by
`credential_type` (`54`); valid canaries are revoked before doors open (`107`).

## Teardown

1. At last exit the command center calls a final sync; every device must report **zero unsynced
   records** (heartbeat `unsynced_count`, `40` step 1).
2. A device that died holding unsynced records is **not wiped**. It goes back powered off for
   recovery at base, and the window it covers stays provisional (`108`).
3. Power down, count against the manifest, seal. Collect and count every paper roster.
4. Rented units are separated for sanitization before return.

## Device sanitization — after the event, with evidence

**Order is load-bearing: sync → settle → revoke → wipe.** Wiping before sync destroys access logs,
which are evidence (`71`). Remote wipe is a convenience for recovered devices, not a breach control
(`40`); the controls are encryption at rest, key expiry and revocation.

| Where | Holds | Sanitize by |
|---|---|---|
| Native scanner or kiosk store (`71`) | Credential roster with names, types, photos; grants; access logs; deny-list; config and sync cursor | Confirm zero unsynced; revoke or expire the key; the app wipes its store on the refused sync (`40`) or on a `WIPE` command; MDM factory reset if leaving ARZO custody |
| OS keystore | Device key | Server-side revocation; reset with the device |
| Kiosk walk-in queue (`17`) | Names, emails, phones, cash records, desk photos (ARZ-074) | As above, after walk-ins are reconciled (`108`) |
| Print host | Cached templates, credential data, rendered badge rasters, **OS print spool**, logs | App purge and spool clear; re-image if rented |
| Thermal printer | Job buffer or memory — `UNVERIFIED` per model | Vendor reset procedure |
| Dye-sublimation card ribbons | May retain a legible negative of each printed card — `UNVERIFIED` per ribbon type; ask the vendor | Destroy as personal data |
| Misprints, voided and unclaimed badges | Names, photos, credential identifiers valid until `valid_until` | Shred on site; count sheet |
| Staff phones running lead capture (`33`) | Pending captures; synced lead details | Sync, then sign-out clears the store — a requirement on `33`'s app |
| Ops laptops | Downloaded exports with personal data (`51`); screenshots | Delete downloads; checklist line |
| Paper | Fallback rosters, printed deny-lists, incident notes | Numbered copies back; shred |
| Network kit | Pre-shared key, VPN credentials, DHCP logs with MAC addresses | Rotate the key per event; factory-reset rented kit |
| Web scanner (today) | The list link in browser history | Expire the list and clear site data — but see H1 |

Evidence, one record per device per event — a paper form in the dossier until `device_commands`
exists, then the acknowledged `WIPE` row is the evidence:

```
device sanitization record
  event, device short_id, asset tag, device_type, owned | rented,
  final_sync_at, unsynced_at_teardown,        -- must be 0, or an exception below
  key_revoked_at | key_expired_at,
  method,          -- APP_STORE_WIPE | MDM_FACTORY_RESET | DISK_REIMAGE | DESTROYED
  wiped_by, witnessed_by, wiped_at,
  exception NULL   -- lost, died unsynced, returned to vendor (certificate reference)
```

Deadline: before any device leaves ARZO custody, and by T+2d. G4 cannot pass without it (`108`).

## Migration — the software this process needs

| Step | Change | When |
|---|---|---|
| 1 | Fix H1: the public undo path asserts list activity | Now |
| 2 | Asset tag and serial on `devices`, with `40`'s step-1 migration | ARZ-100 |
| 3 | Heartbeat `unsynced_count` — the teardown gate | ARZ-100, ARZ-123 |
| 4 | `device_commands` `WIPE` with acknowledgement — the sanitization evidence | ARZ-100 |
| 5 | Pre-sync verification: per-device cursor and roster count against the server | ARZ-101 |
| 6 | Registry checks `devices.all_synced_past_event_end` and `devices.all_sanitized` (proposed keys) | With ARZ-204 |

## Open questions

- **Nightly re-sync on multi-day events?** Recommended: charge and delta-sync nightly; full re-sync only on an app or roster-schema change.
- **Synced-log retention on the device during a multi-day event** — `71` leaves it open. Recommend the current day plus the previous one; everything goes at teardown.
- **Do rental vendors certify their resets?** `UNVERIFIED`. ARZO wipes before return regardless; the contract should require the vendor's reset as well.
- **Battery replacement threshold** — set after the first soak test, not guessed.

## Related

`40-device-management.md` · `71-realtime-architecture.md` · `102-hardware-procurement.md` ·
`104-onsite-infrastructure.md` · `107-event-day-runbook.md` · `108-post-event-closeout.md` ·
`37-hardware-integration.md` · `38-scanner-platform.md` · `33-exhibitor-lead-capture.md` ·
`63-event-documentation.md` · `65-privacy-gdpr.md` · `131-event-readiness-checklist.md`
