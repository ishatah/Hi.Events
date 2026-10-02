# RFID and NFC Credentials

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P2 (ARZ-122) · **Phase:** 4
**Depends on:** `23-accreditation.md`, `37-hardware-integration.md`, `38-scanner-platform.md`
**Blocks:** nothing directly; raises throughput for `24` at busy gates

---

## Current state — `MISSING`, one premature column pair

`CONFIRMED`:

| Fact | Evidence |
|---|---|
| `credentials.rfid_uid`, `credentials.nfc_uid` exist — `varchar(128)`, nullable | `2026_09_30_000001:134-135` |
| **Neither is indexed; neither is unique** | Live DB |
| Nothing reads or writes them | Search beyond generated DomainObject getters |
| `access_logs.identifier_type` is free `varchar`, default `QR` | `2026_09_29_000006` |
| No Web NFC, WebUSB, Web Serial, WebHID or Web Bluetooth anywhere in the frontend | Search for `NDEFReader`, `navigator.usb/serial/hid/bluetooth` |
| Credential identifiers are issued as 40 random lower-case alphanumerics, stored with a SHA-256 `identifier_hash` | `CredentialIssuanceService.php:86-93` (`e7228c1d`) |

Two consequences of the landed columns, both cheap to fix now because they hold no data:

1. **No index** means an RFID lookup is a sequential scan of `credentials` at the exact moment
   latency matters most (`24` targets < 150 ms online).
2. **No uniqueness** means two active credentials can carry the same UID. At a door the scan is
   then ambiguous, and a cloned tag is indistinguishable from a data-entry error.

## Why invest at all

**Throughput and robustness at gates.** A tap is faster and more forgiving than aiming a phone
screen at a camera in sunlight, and a card or wristband does not run out of battery. `20`'s
`access_point_throughput_snapshots.median_service_seconds` gives the measurement to prove it: run
one gate QR and one RFID at the pilot event and compare. Until then the gain is `UNVERIFIED` and
should be stated as such to clients.

The second reason is **non-transferability**: a closed wristband cannot be handed over the fence the
way a QR screenshot can.

## Technology choices

| Family | Security | Phone-readable | Verdict |
|---|---|---|---|
| **LF 125 kHz** (EM4100-class) | UID only, trivially copied with cheap tools | No | **Reject** |
| **HF 13.56 MHz, MIFARE Classic** | Proprietary crypto publicly broken; cloneable | Partly | **Reject** for anything security-relevant |
| **HF, NTAG21x** (NFC Forum Type 2) | UID + weak password; UID copyable onto "magic" cards | Yes — Android and iPhone (native) | **Default for standard events** — cheap, universal |
| **HF, NTAG 424 DNA** | AES-128; **SUN** messages — each tap yields a fresh cryptographically signed payload with a rolling counter | Yes | **High-security events** — anti-clone without special readers |
| **HF, MIFARE DESFire EV2/EV3** | AES mutual authentication, multi-application | Native apps only | Where a venue's existing access system already uses it |
| **UHF RAIN (EPC Gen2)** | EPC copyable unless the chip supports authentication (`UNVERIFIED` per chip) | **No** | Only for walk-through portals at stadium or festival scale — a separate project |

**Recommendation: HF 13.56 MHz.** NTAG21x-class for standard events; NTAG 424 DNA where cloning
matters. HF is the one family a phone can read, which keeps the scanner abstraction (`38`)
uniform and lets a staff phone act as a reader.

UHF radio regulation differs by country; the permitted band in Qatar is `UNVERIFIED`. Another
reason UHF is not the default.

## Binding a tag to a credential

| Model | How | Clone resistance | Verdict |
|---|---|---|---|
| **Association** | Read the tag's factory UID at issue; store it against the credential | UID copy needs a "magic" tag — possible, not casual | **Default** |
| Write the credential identifier into tag memory (NDEF) | Encode at issue | Copying NDEF to any blank tag is trivial | **Reject** — it puts the credential on the tag in the clear |
| **SUN verification** (NTAG 424 DNA) | Associate by UID, then verify each tap's signature and require the counter to increase | Strong: a replayed or copied tap fails | High-security |
| Application keys (DESFire) | Diversified keys per tag | Strong | Only with venue infrastructure |

**Honest limit:** association by UID is not anti-clone; it raises the bar. The mitigations are clone
detection — the same UID granted at two distant access points within seconds (`68`) — and photo
verification at supervised points (`38`).

## Decision: `credential_media`, not two columns

A credential may be carried by several physical media over its life: a printed QR, a badge inlay, a
replacement wristband after the first is lost. Two scalar columns cannot record that history, and
history is exactly what clone detection and dispute investigation need.

```
credential_media
  id, short_id, event_id, credential_id → credentials,
  media_type,           -- QR | HF_TAG | UHF_TAG | MOBILE_WALLET
  chip_type NULL,       -- NTAG21X | NTAG424_DNA | DESFIRE_EV3 | EPC_GEN2
  form_factor NULL,     -- CARD | BADGE_INLAY | WRISTBAND | STICKER
  uid NULL, uid_hash NULL,
  status,               -- ACTIVE | LOST | REVOKED | REPLACED
  last_sun_counter bigint NULL,   -- replay detection for SUN-capable chips
  encoded_at, encoded_by NULL → users, encoded_on_device_id NULL → devices,
  revoked_at NULL, revocation_reason NULL,
  replaces_media_id NULL → credential_media,
  metadata jsonb, timestamps
  UNIQUE (event_id, uid_hash) WHERE status = 'ACTIVE'
  INDEX (credential_id)
```

A lost wristband marks its medium `LOST` and issues a new one. **The credential survives**, unless
misuse is suspected — the same badge-versus-credential distinction as `21`.

`rfid_uid` and `nfc_uid` should be **dropped before first use**. `23` is authoritative for
credentials, so this change is recorded there first; it is a follow-on migration, not an edit to
the landed one.

## Encoding at issue

- **Badge desk:** a USB PC/SC desktop reader reads the factory UID while the badge prints. Handled
  by the print host (`37`, `39`).
- **Card printers with contactless encoder modules** (Evolis, Zebra card lines) read or encode in
  the same pass. A procurement option (`102`), not a requirement.
- **Wristbands** are usually pre-manufactured: the supplier ships a UID manifest, imported and
  associated at collection.

## Offline

The device store (`71`) gains `uid_hash → credential_id` alongside identifier lookups, and
revocations of a medium travel in the deny-list delta. SUN verification works offline if the
device holds the verification key. That key then has to be protected on the device — another
reason high-security events use native apps (`94`).

## Phones as readers

| Platform | NFC access |
|---|---|
| Android, native | Full HF access |
| Android, Chrome PWA | Web NFC — NDEF-oriented; tag serial number readable |
| iPhone, native | Core NFC — reads NDEF and ISO 14443 tags in a native app |
| **iPhone, web** | **None** — Safari has no Web NFC |

**RFID/NFC is therefore another argument for a native scanner** (`94`), on top of at-rest
encryption. A PWA scanner fleet would have to be all-Android.

## Open questions

- **Form factor per event type** — inlay in the paper badge, PVC card or wristband? Galas and conferences lean badge; multi-day festivals lean wristband.
- **Mobile wallet passes** with NFC (Apple Wallet, Google Wallet) — both require platform approval or partnership; lead time `UNVERIFIED`.
- **Do any target venues run their own access control** (turnstiles with DESFire readers)? If so, integration is with their system, not a parallel one.
- **Consumable cost per attendee** — recurring, and owned by `102`.

## Related

`23-accreditation.md` · `24-access-control.md` · `37-hardware-integration.md` ·
`38-scanner-platform.md` · `39-printer-integration.md` · `68-fraud-prevention.md` ·
`71-realtime-architecture.md` · `94-mobile-scanner.md` · `102-hardware-procurement.md`
