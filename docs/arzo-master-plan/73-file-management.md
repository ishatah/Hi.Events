# File and Media Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 21, 23


---

## Purpose

Uploads, storage, and delivery.

## Current state

`CONFIRMED` solid: S3 public/private disks via Flysystem, polymorphic `images` table, `ImageType` enum carrying per-type minimum dimensions and entity mapping, optional Imagick pipeline generating average colour and a 16px WebP LQIP with correct resource cleanup and graceful degradation when Imagick is absent.

## Key decisions

- Extend `ImageType` for badge photos and speaker portraits rather than adding a parallel media system.
- Badge photos are personal data with a different retention profile than event covers (`65`).
- Note: no resizing or variant pipeline exists — originals are stored as uploaded. Badge photos may need normalization.

## Open questions

- Virus scanning on uploads — currently none, and accreditation accepts document uploads.
- Should the duplicated LQIP logic in `BackfillImageMetadataCommand` be consolidated into `ImageMetadataService`?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `21` · `23`
