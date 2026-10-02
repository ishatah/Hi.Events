# Technical Debt Register

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Derived (from `02` and documents 13–140) · **Priority:** — · **Phase:** hardening track
**Depends on:** `02-current-state-audit.md`
**Blocks:** nothing — it feeds `136`

---

## What counts as debt here

Something that works today and costs more tomorrow: a shortcut, an inconsistency, a dead artefact, a
latent bug. **Live defects** — things wrong today — are tracked as ARZ-300-series items in `136` and
listed in `113`'s hardening track; they appear here only where they are also structural.

Each item has a cost-of-delay rating: **High** — compounds with every change or blocks a phase;
**Medium** — a trap for the next person; **Low** — tidiness.

## Process and repository

| # | Debt | Evidence | Cost | Action |
|---|---|---|---|---|
| D1 | **ARZO's code has no repository of its own and no CI.** Upstream is the only remote; 14 commits are local-only | `123` | **High** — single copy; no gate enforced | ARZ-300 |
| D2 | **Upstream divergence grows unmanaged.** The uncommitted rebrand modifies ~130 tracked upstream files (config, views, 20+ language files, components, icons) plus new brand assets; new domains add more. Each upstream merge gets more expensive | `git status` | High over time | `123` — a sync cadence and a rebrand isolation strategy (brand values from config, not edited files) |
| D3 | Changes land without review — commits minutes apart, no PR | `git log` | Medium | Branch protection after ARZ-300 |
| D4 | Documentation drift — the plan was stale within a day of being written (the index lagged by 12 documents; `136` by three commits) | This audit | Medium | `129` sync rule, enforced |

## Architecture and data

| # | Debt | Evidence | Cost | Action |
|---|---|---|---|---|
| D5 | Static tenant state, no global scopes (F11) | `User.php:43` | **High** | ARZ-013 |
| D6 | Imperative-only authorization; default role gate is a no-op (F12) | `IsAuthorizedService` | **High** | ARZ-011 |
| D7 | Two check-in write models (F10) | `18` | **High** | ARZ-041 |
| D8 | **Phase 2 schema landed before Phase 1 RBAC** — the review UI will be tempting to ship on account-wide access | `115` | High | Treat ARZ-011/012 as a gate for ARZ-051 |
| D9 | Webhook payloads coupled to REST resources (F14) | `49` | Medium | ARZ-091 |
| D10 | `BaseRepository` stateful builder; subclasses may build queries outside `runQuery()` (F15) | `02` | Medium — `UNVERIFIED` which subclasses | Targeted audit; now 90 repository bindings, 31 of them new |
| D11 | Two disagreeing hydration paths | `BaseRepository:548` `@todo` | Low | Converge |
| D12 | `timestamp` (legacy) vs `timestamptz` (new) | Deliberate divergence | Low now; Medium when joined in reports | Converge on `timestamptz` table by table, never mixed within a query without explicit conversion |
| D13 | Sparse foreign keys on legacy tables | `07` §0.1 | Medium | `122` D12 — report first |
| D14 | **Data backfills in migrations** — run inside deploys, unrehearsed on data | `000008` | High for large tables | `122` principle 1 |
| D15 | `session_attendance` direction `IN` vs `access_logs` `ENTRY` | `54` | Low now, Medium later | ARZ-314 |
| D16 | Booth and seat allocation status on venue-scoped rows | `35` | Medium | ARZ-314 |
| D17 | `rfid_uid`/`nfc_uid` scalar columns | `36` | Low — unused | ARZ-314 |
| D18 | `access_logs`, `session_attendance`, `badge_print_jobs` lack `device_id` | `40` | Medium | ARZ-314 |
| D19 | Occupancy computed per scan by transferring every group to PHP | `74` | **High** once scanning is live | ARZ-307 |
| D20 | `event_logs` — never written, `entity_type bigint` | `67` | Low | ARZ-319: retire for `audit_events` |
| D21 | `order_audit_logs` has no actor column | `67` | Medium | ARZ-319 |
| D22 | Money as `double precision` in `affiliates` | `45` | Low | ARZ-316 |

## Frontend

| # | Debt | Evidence | Cost | Action |
|---|---|---|---|---|
| D23 | Vestigial `components/common` primitives beside Mantine-direct usage | `87` | Medium | Decide in `87`: commit to one |
| D24 | Three overlapping theme systems, inconsistent alphas | `87` | Low | Consolidate |
| D25 | Organizer theming as inline styles; portals do not inherit | `87` | Medium | Before more themed surfaces |
| D26 | `ssrManifest` loaded and ignored; `.ssr-loader` overlay hides SSR output | `74` | Medium — measurable LCP | Cheap win |
| D27 | No `manualChunks` vendor boundary | `74` | Low | |
| D28 | `networkMode: "always"` globally | `30`, `95` | High for the app | Scope per route |
| D29 | Manifest without `start_url`/`scope`, no service worker — installable and blank | `30` | Medium | With ARZ-150 |
| D30 | Keyboard wedge reads `e.key` | `38` | High in Qatar | ARZ-309 |
| D31 | `Order.company_name` rendered, never sent | `32` | Low | Delete or implement |
| D32 | Frontend `QuestionType` lacks `PHONE` | `43` | Low | With ARZ-140 |
| D33 | Event-report CSV assembled in the browser | `51` | Medium | ARZ-308 |

## Dead code and artefacts

| # | Item | Evidence | Action |
|---|---|---|---|
| D34 | `RazorpayOrderDomainObject` extends a non-existent abstract | `50` | Delete |
| D35 | `@react-pdf/renderer` — zero imports | `22` | Remove |
| D36 | Sanctum installed; `personal_access_tokens` unused | `48` | Remove at ARZ-090 |
| D37 | `PromoCodesExport` referenced nowhere | `51` | Delete |
| D38 | `getCheckedInStats` with no caller; frontend calls a non-existent `check_in_stats` route | `51` | Delete both |
| D39 | `routes/channels.php` stub, `BROADCAST_DRIVER` key | `53` | Replace with Reverb config (ARZ-104) |
| D40 | Duplicated LQIP logic in `BackfillImageMetadataCommand` | `73` | Consolidate |
| D41 | `schema.sql` named as if current; it is the 2020 baseline | `07` | Rename or annotate |
| D42 | `public/widget.js` committed build artefact | `02` | Build in CI |

`CLAUDE.md` makes dead code a rule, not a preference: "code that has no production callers … must be
deleted". D34–D38 violate it today.

## Decisions on debt

- **Pay High items through the hardening track and the phase that owns them**, not a separate debt
  sprint. Most High items are also prerequisites for something (`119`).
- **The `HiEvents\` namespace rename** stays rejected — 1,659 files, zero user benefit, and every
  upstream merge would conflict on every file (D2 makes this decisive).
- **Delete dead code on sight** in any PR touching the same area; D34–D38 are small enough to clear
  in one change.

## Open questions

- **Rebrand strategy for upstream merges (D2)** — keep ARZO branding in configuration and a thin theme layer, or accept manual conflict resolution each sync? The first is more work now and much less later.
- **Who owns the debt register** between audits? Without an owner it decays like the index did.

## Related

`02-current-state-audit.md` · `136-master-backlog.md` · `113-roadmap.md` · `119-dependency-map.md` ·
`122-migration-plan.md` · `123-release-strategy.md` · `74-performance.md` · `87-ui-ux-system.md`
