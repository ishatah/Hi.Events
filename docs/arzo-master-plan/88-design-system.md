# Design System

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix (licence, contrast) + New (token module) · **Priority:** P1 for D1–D3, which sit in the uncommitted rebrand; tokens P2 (unnumbered — add to `136` when scheduled) · **Phase:** D1–D3 before the rebrand is committed; tokens in Phase 1
**Depends on:** — (D2 needs a legal answer, not a document)
**Blocks:** `87-ui-ux-system.md`

---

Tokens, components and the ARZO visual language as applied to the product — not to the marketing
site, whose brand this document borrows from and does not govern. The principle from the scaffold
stands: **tokens first, components second.** The inconsistency catalogued in `87` comes from having
neither.

## Current state — `PARTIAL`, ~25%

### The rebrand is entirely uncommitted

At `e7228c1d` the product renders in Hi.Events purple with Outfit: `themeColors.ts:8` defaults the
primary to `#40296C`, `App.tsx:71` names `Outfit`, and `index.html:25` loads it from Bunny. The only
committed mention of the ARZO accent is a unit test (`themeUtils.test.ts:32-37`). Everything below
was in the working tree at audit time, uncommitted:

| Brand element | Value | Where (working tree) |
|---|---|---|
| Name | ARZO | `frontend/.env.example:8`, `docker/development/.env:1`, defaults in `App.tsx:85`, `Sidebar/index.tsx:86-87` |
| Primary / secondary | `#1B7E99` / `#146075` | `frontend/.env.example:9-10`, `docker/development/.env:11-12` |
| Homepage defaults | accent `#1B7E99`, background `#FFFFFF` (were `#8b5cf6` / `#f5f3ff`) | `themeUtils.ts:161-169` |
| Typeface | SF Pro Display 400/500/700, self-hosted | `@font-face` in `global.scss:3-25`; `App.tsx:71`; preloads in `index.html`; files in **untracked** `frontend/public/fonts/sf/` |
| Browser chrome | `theme-color` `#1B7E99`; manifest name | `index.html`, `public/site.webmanifest` |
| Logos | `arzo-*` SVGs added; `hi-events-*` deleted | `frontend/public/logos/` |

The brand's own definition lives in the marketing site's stylesheet (a separate repository,
`arzo-website/app/globals.css`): ink `#161616` (`:56`), hairline `rgba(0,0,0,.15)` (`:80`), radius 0
on surfaces (`:225`) with buttons opting into `--radius-control: 1rem` (`:505`), shadows kept for
modals only (`:211-216`), and an accent that is `#34B8D9` on black and **darkens to `#1B7E99` on
white** because the light cut fails as text (`:41`).

### How the accent reaches the product

One env value → `@mantine/colors-generator` → ten shades → the theme fills at `primaryShade: 8`
(`App.tsx:72`) → `--hi-*` tokens alias those shades (`global.scss:4-15`, baseline lines). Body text
is `--hi-text: var(--mantine-color-primary-9)` (`:4`) — **the brand colour is the text colour.**

### Tokens that exist

- About 40 `--hi-*` custom properties in one `:root` block, mixing semantic and legacy names
  (`--hi-pink` is primary shade 5, `:14`). Referenced 712 times.
- Alongside them: 370 hex literals (83 distinct values), 210 `rgba()` literals and 114 `box-shadow`
  declarations across 184 SCSS files; literal corner radii of 6, 8, 10, 12, 14 and 999px next to
  three radius vocabularies — `--hi-radius-*`, `--mantine-radius-*`, and `$radius-*` variables
  redeclared locally in individual modules (e.g. `EventHomepage.module.scss:10`).
- **Breakpoint names collide:** SCSS `md` is 768px (`mixins.scss:5-12`); Mantine `md` is 62em, 992px
  (Mantine `default-theme.mjs:97-103`). The sidebar hard-codes 768 (`Sidebar/index.tsx:25`).
- **No token module, no component documentation:** nothing beyond `themeColors.ts` (palette) and
  `themeUtils.ts` (organizer theme); no Storybook, no `*.stories.*` files; two frontend test files.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| D1 | **The rebrand fails WCAG AA contrast.** The generator puts `#1B7E99` at shade 9, so buttons fill at shade 8, `#338CA7`: white label on it is **3.85:1**. Body text is shade 9, `#1B7E99`, on the `#f7f6f8` page: **4.34:1**. Both need 4.5:1. With the committed purple the same pairs were 8.82:1 and 11.13:1 | computed with `@mantine/colors-generator` and the WCAG formula; `App.tsx:72`, `global.scss:4,8` | **High** once committed — every admin button and every line of body text |
| D2 | **SF Pro Display is served as a webfont against Apple's licence** | below | **High — legal** |
| D3 | The default homepage font no longer loads: the Bunny `outfit` link was removed from `index.html`, but `fontLoader.ts:13-15` still skips loading `Outfit` as "already global", and three SCSS modules name it first. Every event page on the default font falls back to the system stack | working-tree diff of `index.html`; `constants/homepageFonts.ts:41` | Low |
| D4 | Committed tokens fail as text, and are used as text: `--hi-color-warning` `#d97706` is 3.19:1 on white; `--hi-color-text-tertiary` `#9ca3af` is 2.54:1; together they are the `color:` of 21 declarations (e.g. `EventCard.module.scss:580`) | `global.scss:37,40` (baseline) | Medium |
| D5 | Breakpoint name collision | above | Low — a trap for every new layout |

**D1–D3 must be fixed before the rebrand is committed**; D4 is live today. The D1 fix is small: the
text colour becomes a neutral ink token, never the accent, and the theme fills at shade 9 so the
brand colour itself is the button (white on `#1B7E99` is 4.68:1).

## D2 — the typeface is a licence problem, not a design choice

`frontend/public/fonts/NOTICE.md:9-12` records the position candidly: the files are
"Apple-licensed, and not cleared for web redistribution. Self-hosting it here is a deliberate owner
decision".

Apple's published terms, read 2026-09-29:

- The fonts page states that "the Apple San Francisco font is to be used solely for creating
  mock-ups of user interfaces to be used in software products running on Apple's iOS, OS X or tvOS
  operating systems" and "you may not embed the Apple Font in any software programs or other
  products" (developer.apple.com/fonts).
- The Apple Design Resources licence (2023-06-21) says San Francisco is under a separate licence
  (§1B) and forbids using the design resources for "website content" or making them available over
  a network (§2B).

Serving the woff2 files from `/fonts/sf/` sends them to every visitor, most of them on Windows and
Android. That is distribution over a network to non-Apple platforms, which the terms exclude.
Aggravating factors: once committed, every build and every self-host image (`85`) redistributes
the files; `NOTICE.md:12` says the marketing site ships the same files (whether that site is live
with them is `UNVERIFIED`). The one escape the terms allow is permission "expressly … by Apple in
writing"; whether ARZO holds any is `UNVERIFIED`. **Who must answer: ARZO's owner, with legal
counsel.** This document records the owner's decision and that it is contrary to the licence text.

**Recommendation: do not commit the files.** The stack the NOTICE already names
(`-apple-system, BlinkMacSystemFont, 'Segoe UI', …`, `NOTICE.md:14-16`) renders San Francisco on
Apple devices legitimately — the operating system supplies it — and a native UI face everywhere
else. If a uniform face matters, license a commercial family with web rights that covers Latin
**and Arabic**.

Arabic makes the choice urgent rather than cosmetic. SF Pro has no Arabic glyphs (`NOTICE.md:18-19`);
SF Arabic is a separate Apple family under the same terms. The brand specification names **Graphik
Arabic** for right-to-left text, which was never supplied (`arzo-website/app/globals.css:429`);
Graphik is a commercial face, and its web-licence cost is `UNVERIFIED`.

## Decision: one TypeScript token module that emits both the CSS variables and the Mantine theme

```
frontend/src/theme/
  tokens.ts           -- the only file with raw colour, size and time values
  arzoTheme.ts        -- createTheme() from tokens: colours, radii, spacing,
                      --   breakpoints, component defaults (87's wrapper replacements)
  organizerTheme.ts   -- organizer accent → document-scoped variables; the
                      --   contrast guardrails from themeUtils, kept and tested
  tokens.test.ts      -- every semantic foreground/background pair asserts its
                      --   contrast floor (4.5:1 text, 3:1 UI, 7:1 event-day)
```

Three tiers, and SCSS may reference only the second:

| Tier | Example | Rule |
|---|---|---|
| Primitive | `teal-600: #1B7E99`, `space-4: 16px` | Never referenced outside `tokens.ts` |
| **Semantic** | `ink`, `ink-muted`, `surface`, `canvas`, `hairline`, `accent`, `accent-ink`, `focus`, `success`, `warning`, `danger`, `info` | The vocabulary of every stylesheet |
| Component | `button-fill`, `table-row-border` | Only when a component needs to diverge from its semantic default |

Proposed semantic values for light mode, drawn from the brand where it has spoken:

| Token | Value | Source / contrast |
|---|---|---|
| `ink` | `#161616` | Brand ink; 18.1:1 on white |
| `ink-muted` | `rgba(22,22,22,.6)` | Brand; 4.74:1 on white (`globals.css:66-71`) |
| `surface` | `#FFFFFF` | Brand |
| `canvas` | A neutral off-white, value for the design owner | Must keep `ink` above 7:1 |
| `hairline` | `rgba(0,0,0,.15)` | Brand |
| `accent` | `#1B7E99` | Brand light cut; 4.68:1 against white text |
| `success` / `danger` | `#15803d` / `#b91c1c` (existing) | 5.02:1 / 6.47:1 on white |
| `warning` | `#d97706` as a **fill with `ink` text** (5.68:1), never as text | Fixes D4 |

Breakpoints come from Mantine's names and values; the SCSS mixins read the same map (fixes D5).

## Decision: brand layer plus an operational grammar — not the marketing language verbatim

The scaffold asked whether the admin gets the full ARZO design language or only the brand layer.
**Neither extreme.** The marketing brand is editorial: one accent, square corners, no shadows,
hairlines. Translated literally, a dense order table has no row separation and a status column has
nothing but the accent to say "failed". The admin gets the brand's **materials** and its own
**grammar**:

| Brand element | In the product |
|---|---|
| One accent | Primary action, focus ring, selection, links. **Never** status and never body text (D1) |
| Hairlines | Adopted wholesale — the brand's `rgba(0,0,0,.15)` is exactly a table row border. Data tables keep row borders |
| Square corners | Surfaces, cards, tables and inputs square; buttons keep the brand's control radius. No third radius |
| No shadows | Surfaces separate by hairline and canvas tone; elevation only for overlays — menus, modals, toasts — which the brand itself allows |
| Near-black ground | Marketing default; the product stays light. Dark is reserved for event-day night use (`87`) |
| — | A semantic **status palette** the brand does not have: success, warning, danger, info, neutral, plus the access-result mapping in `StatusPill` |

The reason is function: a planner scans hundreds of rows a day. Brand fidelity that costs
legibility is not fidelity.

## Decision: document components in the repository, not in Storybook — for now

There are no stories today. Storybook for an SSR app with Mantine, Lingui and React Query providers
is a second application to maintain. Instead: a development-only gallery route rendering the domain
components (`StatusPill`, `ScanResult`, `DataAge`, `DataTable`, `Surface`) in every state, excluded
from production builds, plus `tokens.test.ts`. The existing Playwright suite can screenshot the
gallery for visual regression — Storybook's main selling point, without the second app.

Revisit when a design team outside engineering needs to browse components, or when native apps
(`94`, `96`) want a shared visual reference.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Decide D2; remove the SF files from the tree and the `@font-face` block unless licensed | **Before the rebrand is committed** |
| 2 | Fix D1 (neutral ink; fill at shade 9) and D3; commit the rebrand | Same change |
| 3 | Fix D4 — warning as a fill, tertiary text darkened | **Now** — committed tokens |
| 4 | `tokens.ts`, `arzoTheme.ts`, contrast tests; alias `--hi-*` to semantic tokens so nothing breaks | Phase 1 |
| 5 | Stylelint: no raw colours or radii outside tokens in new SCSS; migrate old SCSS opportunistically | Phase 1 onward |
| 6 | Gallery route; `StatusPill` and friends (`87`) | Phase 2 |
| 7 | Arabic face licensed and wired, scoped by `unicode-range` | When `ar` is scheduled (`82`) |

Step 4 aliases rather than renames: 712 references to `--hi-*` keep working while new code uses the
semantic names.

## Open questions

- **SF Pro — keep, replace, or license?** Recommendation above: system stack now; a licensed family covering Latin and Arabic if a uniform face is worth paying for. Owner and legal decide.
- **Which Arabic face?** The brand names Graphik Arabic; cost `UNVERIFIED`. An open-licence Arabic face is the fallback — the choice is the brand owner's, and it must be made before `ar` ships.
- **Should the organizer homepage font catalogue offer Arabic-capable faces?** Today every entry resolves through the Bunny CDN (`NOTICE.md:21-24`) and none is chosen for Arabic. Needed if organizers publish Arabic event pages.
- **Who approves how the brand is applied in the product?** The same owner question as `87`.

## Related

`87-ui-ux-system.md` · `81-accessibility.md` · `82-localization.md` · `85-deployment.md` ·
`89-admin-platform.md` · `90-organizer-platform.md` · `91-attendee-platform.md` ·
`135-global-expansion.md` · `02-current-state-audit.md`
