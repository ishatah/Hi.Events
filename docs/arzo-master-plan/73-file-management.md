# File and Media Management

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep (images) + Extend (image types, processing) + New (documents) · **Priority:** P1 — ARZ-074 photo capture and ARZ-051 accreditation documents depend on it; M3 fix now · **Phase:** 2 (photos, documents); 3 (sponsor logos, attachments)
**Depends on:** `65-privacy-gdpr.md`, `64-security.md`, `70-background-jobs.md`
**Blocks:** `21-badge-management.md`, `22-badge-design-printing.md`, `23-accreditation.md`, `34-sponsor-management.md`, `58-task-management.md`, `60-incident-management.md`, `63-event-documentation.md`

---

## Current state — images `PARTIAL` ~55%; documents `MISSING`

The image path is tidy and built for **public marketing pictures**: event covers and logos. The plan
now needs to store faces and identity documents, which are neither public nor marketing, and the
current defaults are wrong for them.

| Fact | Evidence |
|---|---|
| `images`: polymorphic `entity_type`/`entity_id`, `type`, `disk`, `path`, `size`, `mime_type`, `width`, `height`, `avg_colour`, `lqip_base64`, `account_id`, soft delete | `\d images` |
| `ImageType` has 5 cases — `GENERIC` (→ user), `EVENT_COVER`, `TICKET_LOGO` (→ event), `ORGANIZER_LOGO`, `ORGANIZER_COVER` (→ organizer) — with minimum dimensions per type | `ImageType.php:14-75` |
| The four non-generic types are one-per-entity; uploading replaces the previous row | `CreateImageHandler.php:19-24,90-99` |
| Validation: `image`, `max:5120` KB, `mimes:jpeg,png,jpg,webp`, per-type minimum, 4000×4000 maximum — in two request classes | `CreateImageRequest.php:20-26`; `RulesHelper.php:23-29` |
| Storage: the **original** goes to `filesystems.public` with `visibility: public`, named from the client's filename plus five random characters | `ImageStorageService.php:26-43` |
| Processing: dimensions, 1×1 average colour, 16 px WebP LQIP at q60; `stripImage()` on the LQIP only; Imagick resources cleared; returns `null` without Imagick | `ImageMetadataService.php:20-95` |
| No resize, no variants, no re-encode, no EXIF strip or orientation fix on the original | Search |
| Imagick: installed in the backend image and the Vapor Dockerfiles, **not** in the all-in-one image | `backend/Dockerfile:12`; `production.Dockerfile`; `Dockerfile.all-in-one:25` |
| `BackfillImageMetadataCommand` duplicates the LQIP and colour logic line for line | `BackfillImageMetadataCommand.php:197-257` vs `ImageMetadataService.php:55-95` |
| Deleting or replacing an image soft-deletes the row; the file is removed only by account anonymization or hard deletion | `DeleteImageHandler.php:31`; `AccountAnonymizationService.php:83`; `AccountHardDeletionService.php:172` |
| The private disk is used only for answer exports: written to a hardcoded `s3-private`, served by a 10-minute `temporaryUrl`, never deleted | `ExportAnswersJob.php:27-31`; `JobPollingService.php:12,65` |
| No malware scanning | Search for `clamav`, `virus`, `malware` |
| Phase 1–2 schema already points at `images`: `persons.photo_image_id`, `speakers.photo_image_id`, `badge_templates.background_image_id`; `accreditations.documents jsonb`; `badges.rendered_pdf_path` | Live schema; `23` says `documents` holds "image ids" |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| M1 | Originals keep **EXIF — including GPS for phone photos** — and are publicly readable | `ImageStorageService.php:34-43` | Low for covers; **High** the day badge photos use this path |
| M2 | Deleted and replaced images stay **public forever** | Soft delete only | Medium; High for personal photos |
| M3 | Answer exports (personal data) are **never deleted** from the private bucket | `ExportAnswersJob.php:27-31` | Medium — **fix now** with a bucket lifecycle rule |
| M4 | Private disk hardcoded as `s3-private` in two places, bypassing `filesystems.private`; a self-host on local disk cannot export answers | `ExportAnswersJob.php:30`; `JobPollingService.php:12` | Low |
| M5 | The all-in-one image lacks Imagick, so self-hosts never get dimensions or LQIP | `Dockerfile.all-in-one:25` | Low |
| M6 | An unknown `image_type` reaches `constant()` inside `rules()` and errors (500) before validation runs | `CreateImageRequest.php:13-15`; `BaseEnum.php:12-15` | Low |
| M7 | Duplicated metadata logic | Above | Low |
| M8 | No orientation normalization — a portrait phone photo can render sideways on a server-rasterized badge | Absence of any orientation handling | Medium for badges |

## Decision: images for pictures people look at; documents for evidence people check

Two models, not one. An ID scan stored as an `image` inherits a public disk, a public URL and a
filename derived from what the uploader called it. Mixing sensitivities in one table makes the
dangerous default the easy one.

| | `images` | `documents` |
|---|---|---|
| Content | Covers, logos, speaker and person photos, badge backgrounds, floor plans | ID scans, supporting files, incident attachments, task evidence, contracts, dossier files |
| Visibility | `PUBLIC` or `PRIVATE`, per type | **Private only** |
| Processing | Re-encoded, normalized, variants | Stored as uploaded; never transformed |
| Access | CDN URL (public) or signed URL (private) | Authorized download, signed URL of 60 s, audited |
| Before use | Scanned, re-encoded | Scanned; unavailable until clean |

This corrects `23`: `accreditations.documents` should hold **document** ids.

## Images: new types and a processing pipeline

| Type | Entity | Min px | Output | Visibility | Retention (provisional — `65`) |
|---|---|---|---|---|---|
| `PERSON_PHOTO` | `persons` | 300×400 | 600×800 JPEG, 3:4 crop, auto-oriented, EXIF stripped | **PRIVATE** | Event end + 30 days, unless the person consents to reuse |
| `SPEAKER_PHOTO` | `speakers` | 400×400 | 800×800 + 400×400 | PUBLIC | While the speaker record exists |
| `SPONSOR_LOGO` (`34`) | company or sponsorship | 400×200 | PNG, ≥ 1000 px wide kept for print | PUBLIC | Sponsorship end + 12 months |
| `BADGE_BACKGROUND` | `badge_templates` | 1011×638 (CR80 at 300 dpi) | As uploaded, re-encoded | PRIVATE — may carry security artwork | While the template exists |
| `FLOOR_PLAN` (`35`) | venue or event | 1600 wide | 2400 + 1200 wide | Per event | While the plan is current |

**SVG logos are not accepted in v1.** SVG can carry script, and badge rendering needs a raster at
printer resolution anyway (`22`). A high-resolution PNG serves both.

Face detection for automatic cropping is **out**: the desk operator crops in the capture UI
(ARZ-074). It is faster to correct by hand than to explain an automatic crop that cut off a chin
(`133`).

```
ALTER TABLE images
  ADD COLUMN visibility varchar(16) NOT NULL DEFAULT 'PUBLIC',   -- derived from type
  ADD COLUMN status     varchar(16) NOT NULL DEFAULT 'READY',    -- PENDING | READY | REJECTED
  ADD COLUMN original_path text NULL,     -- private disk; kept only where the type needs it
  ADD COLUMN variants jsonb NULL,         -- {"800": {"path": ..., "width": ..., "height": ...}}
  ADD COLUMN sha256 char(64) NULL,
  ADD COLUMN scanned_at timestamptz NULL;
```

Pipeline, as a `bulk`-class job (`70`): original to a private quarantine path → malware scan →
**decode and re-encode** (strips EXIF, applies orientation, and destroys most polyglot payloads — an
HTML-in-JPEG does not survive a re-encode) → variants → metadata (one implementation, M7) → `READY`
→ public variants copied to the public disk if the type is public. Existing rows stay `READY`
and `PUBLIC`.

Stored paths use a UUID, never the client filename; the original name is kept as metadata.

## Documents

```
documents
  id, short_id, account_id, event_id NULL,
  owner_type,        -- ACCREDITATION | INCIDENT_ENTRY | EVENT_TASK | EVENT_DOSSIER | VENDOR
                     -- | SPEAKER | EVENT_EXHIBITOR
  owner_id,
  document_class,    -- ID_DOCUMENT | SUPPORTING | EVIDENCE | CONTRACT | ATTACHMENT | PRESENTATION
  sensitivity,       -- STANDARD | RESTRICTED  (ID documents; medical and safeguarding — 60)
  original_filename, mime_type,     -- sniffed from content, not the client's declaration
  size_bytes, sha256,
  disk, path,        -- private disk only; opaque UUID path
  status,            -- UPLOADING | SCANNING | AVAILABLE | QUARANTINED | DELETED
  scan_engine NULL, scan_signature NULL, scanned_at NULL,
  uploaded_by_type,  -- USER | PERSON (magic-link portals, 32) | DEVICE
  uploaded_by_id,
  retain_until timestamptz NULL, purged_at NULL,
  timestamps, deleted_at
  INDEX (owner_type, owner_id), INDEX (retain_until) WHERE purged_at IS NULL
```

- **Uploads go direct to the bucket** by presigned POST with size and content-type conditions, then
  the client calls "complete". This keeps large files out of the PHP request — and off Lambda's
  synchronous payload limit, which the 5 MB image cap already sits close to.
- **Downloads** pass authorization first: the owner's permission, plus a specific permission for
  `RESTRICTED` (`09`). Every `RESTRICTED` access is written to the audit log (`67`). The response
  is a 60-second presigned URL with `Content-Disposition: attachment`; documents are never rendered
  inline.
- `task evidence` (`58` `evidence jsonb`), incident `ATTACHMENT` entries (`60`), speaker materials
  (`28`) and exhibitor documents (`32`) reference `documents.id`. Versioning of speaker slides is
  deferred.

## Malware scanning

**Every document and every image original is scanned before it is used.** The interface is a
`MalwareScanner` with a ClamAV adapter (`clamd` over TCP) running beside the persistent services
(`84`); a managed bucket-scanning service is an acceptable alternative if the host provides one
(`UNVERIFIED` for ARZO's host). The development adapter marks files `UNSCANNED` visibly — never
"clean".

| Scanner down | Documents | Images |
|---|---|---|
| Behaviour | **Fail closed**: stay `SCANNING`, owner sees "processing" | **Fail open after re-encode**, flagged for rescan |
| Why | An ID upload can wait an hour | A cover image blocking publication is worse than the residual risk after a re-encode |

## Retention and purge

Provisional, owned by `65`. A daily `bulk` job deletes the object, then sets `purged_at`; the row
stays as a tombstone so the audit trail can say what existed.

| Class | Retention |
|---|---|
| `ID_DOCUMENT` | Decision + 30 days, then purge; keep only "verified by X at T" |
| Accreditation `SUPPORTING` | Event end + 90 days |
| `PERSON_PHOTO` | Event end + 30 days, unless consented reuse |
| Incident attachments | With the incident record — `UNVERIFIED`; legal to answer (`60`, `66`) |
| Contracts, dossier, task evidence | Close-out + a period set by legal and finance — `UNVERIFIED` |
| Answer and report exports | **24 hours** |
| Rendered badge PDFs | Event end + 7 days — they can be re-rendered |

With bucket versioning on (`77`), a purge must delete **all versions**, or erasure is not erasure.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | M3: lifecycle rule on the private bucket's export prefix; M4: use `filesystems.private` | Low — **now** |
| 2 | M6 validation order; M7 consolidate into `ImageMetadataService`; M5 add Imagick to the all-in-one | Low |
| 3 | `images` columns; re-encode and variant pipeline; M1, M8 closed for new uploads | Low — before ARZ-074 |
| 4 | M2: delete files on image delete and replace, after a grace period | Low |
| 5 | `PERSON_PHOTO`, `SPEAKER_PHOTO`, `BADGE_BACKGROUND` types | With ARZ-070, ARZ-074 |
| 6 | `documents`, presigned upload, authorized download, scanner | With ARZ-051 |
| 7 | `SPONSOR_LOGO`, `FLOOR_PLAN`, attachments for `58` and `60` | Phase 3 |
| 8 | Retention purge job | With `65`'s schedule |

## Open questions

- **Store ID scans at all?** Minimization says verify the document by sight at pickup and record the fact. Store scans only when a client, typically a government one, requires it — and then as `RESTRICTED` with the shortest retention.
- **Existing EXIF in public covers** — re-encode the back catalogue, or leave covers alone? Recommend a one-off re-encode; covers are rarely phone photos, but some are.
- **Storage region** for photos and ID documents — a data-residency question for `65` and `84`.
- **Scanner hosting** — sidecar on the persistent host, or managed? Decide with `84`.

## Related

`21-badge-management.md` · `22-badge-design-printing.md` · `23-accreditation.md` · `34-sponsor-management.md` ·
`58-task-management.md` · `60-incident-management.md` · `63-event-documentation.md` · `65-privacy-gdpr.md` ·
`64-security.md` · `67-audit-logging.md` · `70-background-jobs.md` · `77-disaster-recovery.md`
