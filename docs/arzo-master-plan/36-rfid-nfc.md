# RFID and NFC

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 23, 38  


---

## Purpose

Contactless credentials: reading, encoding, and lifecycle.

## Current state

`MISSING`. Zero hits.

## Key decisions

- `credentials` already reserves `rfid_uid` and `nfc_uid` (`23`), so the data model is ready.
- Encoding happens at badge issue; the reader is one more scanner implementation behind the abstraction (`38`).
- RFID materially raises throughput at gates — this is the main reason to invest.

## Open questions

- Which frequency and standard? Determines reader and card cost, and interoperability.
- Wristbands vs cards vs embedded badges — ARZO's event mix decides.
- Cloning risk: UID-only credentials are trivially copied. Do we need cryptographic cards?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `23` · `38`
