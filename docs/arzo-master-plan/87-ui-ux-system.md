# UI/UX System

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + Process · **Priority:** P2 (unnumbered — add to `136` when scheduled); U1, U2, U6, U7 fix now · **Phase:** fixes now; consolidation in Phase 1, before the first new surface ships
**Depends on:** `88-design-system.md`
**Blocks:** `89-admin-platform.md`, `90-organizer-platform.md`, `91-attendee-platform.md`, `92-staff-platform.md`, `93-exhibitor-platform.md`

---

ARZO's frontend is about to serve four contexts that want opposite things: a planner at a desk, a
steward at a gate in the sun, a kiosk nobody supervises, and an attendee on a phone with one bar of
signal. Today it has rules for the first and, implicitly, the last. This document sets the rules
for all four and decides the component strategy. Tokens and brand are `88`.

## Current state — `PARTIAL`, ~45%: a competent Mantine app for one context

| Fact | Evidence |
|---|---|
| 298 of 378 component files import a `@mantine/*` package; 289 import `@mantine/core` | `git grep` at `e7228c1d` over `frontend/src/components/**/*.tsx` |
| Mantine 9.2.2 with the v8 CSS-variable resolver | `package.json`; `App.tsx:66-75` |
| No RTL support: no `DirectionProvider`, no `dir` handling; 242 physical-direction declarations (`margin-left`, `right:` …) against 12 logical ones in 184 SCSS files | search, `frontend/src/**/*.scss` |
| `<html lang="en">` for every one of the 20 locales; nothing sets `lang` at runtime | `index.html:2`; search `documentElement.lang`, `htmlAttributes` → 0 |
| A white, fixed, `z-index: 1000` overlay hides the SSR output until the client effect runs | `App.tsx:49-65`; also `74` |
| The check-in surface is styled like an admin page: 43 of 70 `font-size` declarations are 13px or smaller; sound feedback, no haptic | `layouts/CheckIn/**/*.scss`; `CheckIn/index.tsx:86-190`, no `vibrate` |
| ESLint carries 768 pre-existing errors, gated so the count cannot grow | `.github/workflows/frontend-tests.yml` |

### The primitives layer is vestigial

`components/common/` holds 132 directories. Eight of them wrap a Mantine primitive; the wrappers
lose to direct use almost everywhere:

| Primitive | Importers of the wrapper | Direct `@mantine/core` importers | State of the wrapper |
|---|---|---|---|
| `Badge` | **0** | 50 | Takes no props, renders an empty badge (`common/Badge/index.tsx:3-7`) — dead |
| `Button` | 6 | 168 | Spreads props, then **overwrites the caller's `className`**, under `@ts-ignore`; its SCSS is entirely commented out (`common/Button/index.tsx:5-8`) |
| `Modal` | 42 | 25 | Spreads props, then forces `size="xl"` (`common/Modal/index.tsx:15,20`): **11 call sites that ask for `sm`, `md` or `lg` render `xl`** |
| `Tooltip` | 3 | 36 | Adds touch/focus events — a theme default in disguise |
| `Table` | 7 | 21 | Card + scroll container |
| `Popover` | 3 | 13 | Title/target convenience |
| `Center` | 2 | 4 | A div |
| `Card` | 57 | 3 | A div with variants — the one wrapper that won |

Half the codebase gets the wrapper's behaviour and half does not: the 25 direct `Modal` users close
on an outside click unless they opt out, the 42 wrapped ones cannot. That inconsistency is the cost
of the half-measure.

### Three theme mechanisms, two opposite scopes

| Mechanism | Scope | Where | Accent "soft" (light / dark) | Border |
|---|---|---|---|---|
| Platform | `:root`, root `MantineProvider` + `--hi-*` tokens | `App.tsx:66-75`, `themeColors.ts:7-10`, `global.scss:3-46` | 12% `color-mix` (`global.scss:42`) | `rgba(0,0,0,.08)` (`:34`) |
| Checkout | **`:root`**, a nested `MantineProvider` with no `cssVariablesSelector`, plus `forceColorScheme` on `<html>` | `CheckoutThemeProvider.tsx:195-200`; Mantine `MantineProvider.mjs:34` | 0.10 / 0.20 (`:141-143`) | `#e5e7eb` / `#333333` (`:20,29`) |
| Event and organizer homepages | Inline CSS custom properties on one subtree | `EventHomepage/index.tsx:148-161,230`; `OrganizerHomepage/index.tsx:100-113,132` | 0.08 / 0.15 (`themeUtils.ts:124`) | `rgba(0,0,0,.1)` (`:106,115`) |

The palettes disagree too. The platform generates ten shades with `@mantine/colors-generator` and
fills at shade 8; checkout hand-rolls lighten/darken with the accent at shades 5–7 and fills at 7
(6 in dark mode) (`CheckoutThemeProvider.tsx:57-68,83`). One organizer accent renders as different colours on the
event page, in checkout and in the admin.

**Verified consequence of the subtree scope.** `CookieConsentBanner` is mounted at the app root
(`App.tsx:94`) but its styles read `--event-*` / `--organizer-*` variables
(`CookieConsentBanner.module.scss:10-11,49,61`) that exist only inside the homepage subtree — so it
always renders its fallbacks, including Hi.Events violet `#8b5cf6`. `ContactOrganizerModal` is a
portalled Mantine `Modal` (`ContactOrganizerModal/index.tsx:64-70`) and renders on the platform
theme, not the organizer's. `JoinWaitlistModal` works around the gap by wrapping itself in a second
`CheckoutThemeProvider` (`JoinWaitlistModal/index.tsx:97`) — which then rewrites `:root` and the
document colour scheme for the whole page while open. Violet fallbacks survive in 13 places in 9
files.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| U1 | **Every page declares English.** Screen readers mispronounce the other 19 locales (WCAG 3.1.1), and there is nowhere to put `dir="rtl"` | `index.html:2` | Medium |
| U2 | Cookie banner reads variables that are never in scope; renders violet on ARZO pages whenever consent is enabled | above; `App.tsx:94` | Low |
| U3 | Organizer theme does not reach portals: modals and notifications show platform colours on a branded page | above | Low |
| U4 | The nested checkout provider rewrites `:root` and forces the colour scheme on `<html>` while mounted | `CheckoutThemeProvider.tsx:195-200` | Low — leaks by design, effect `UNVERIFIED` |
| U5 | White SSR overlay: on a venue network the attendee sees a blank page for the whole JavaScript download | `App.tsx:49-65` | Medium |
| U6 | Wrapper defects: `Button` discards `className`; `Modal` ignores `size`; `Badge` dead | table above | Low |
| U7 | `useEffect` after a conditional early return — a rules-of-hooks violation that throws if a mounted page goes from "not found" to found | `EventHomepage/index.tsx:135,144`; `OrganizerHomepage/index.tsx:56,96` | Low — runtime `UNVERIFIED` |
| U8 | Check-in is sized for a desk, not a gate | above | Medium, for Phase 4 |

**Fix now, independent of the roadmap:** U1 (set `lang` from the resolved locale at SSR), U2 (the
banner uses platform tokens), U6 (delete `Badge`; fix `Modal`'s prop order until it goes), U7.

## Decision: four surface classes, four rule sets

A surface belongs to exactly one class, and the class sets its rules. A new screen states its class
before it is designed.

| Class | Who, where | Surfaces |
|---|---|---|
| **Desk** | Organizers, ARZO ops, platform admin; trained, daily, at a laptop | `89`, `90`, badge designer, reports |
| **Event-day staff** | Stewards, badge desk, supervisors, exhibitor staff; standing, rushed, outdoors, gloves | Scanner (`94`), badge desk, lead capture (`33`), supervisor mobile (`97`) |
| **Kiosk** | Members of the public, unsupervised, once | `19`, `96` |
| **Attendee** | The public on their own phone, often on a poor network | `91`, `95`, the widget |

| Rule | Desk | Event-day staff | Kiosk | Attendee |
|---|---|---|---|---|
| Minimum target | 24 × 24 px (WCAG 2.2 SC 2.5.8, AA) | 48 × 48 px | 64 × 64 px | 44 × 44 px |
| Minimum body text | 14px | 18px; result text 32px+ | 24px | 16px |
| Status contrast | 4.5:1 | **7:1** — sunlight | 7:1 | 4.5:1 |
| Meaning by colour alone | Never (WCAG 1.4.1) | Never: colour + icon + word + sound + haptic | Never | Never |
| Density | Dense tables allowed | One decision per screen | One question per screen | One column |
| Mistakes | Confirm destructive actions | Undo for seconds; no confirm dialogs mid-queue | Every state times out to attract and **clears personal data** (`19`) | Guest checkout; recoverable links |
| Network | Assume online | Show data age; never block on the network (`71`) | Degrade to "see the desk" | Ticket works offline (`30`) |
| Language | Organizer's choice | Operator's choice, switchable per device | Bilingual toggle on every screen | Attendee's choice; Arabic first-class |

The pixel values for event-day and kiosk are proposals, not standards; `UNVERIFIED` against real
use and to be tuned at the pilot. Physical kiosk requirements (reach height, screen angle) are
`81`'s and `19`'s, and the applicable accessibility regulation is `UNVERIFIED` — `81` names it.

**Sunlight is a contrast problem.** Outdoor glare washes out mid-tones first, so event-day screens
use near-black on white or white on near-black for anything that must be read, and reserve the
accent for the primary action. Light-grey helper text — `--hi-color-text-tertiary` is `#9ca3af`,
2.54:1 on white — never appears on an event-day screen.

## Decision: Mantine-direct, with theme defaults and a thin domain layer

The scaffold asked: rebuild the primitives layer, or commit to Mantine-direct? **Commit to
Mantine-direct and delete the wrappers.**

Mantine already is the primitive layer. A wrapper that re-exports it produces two APIs for one
button, and the survey above shows how that ends: the wrapper loses, drifts, and grows bugs nobody
notices because most callers bypass it. The consistency wrappers were meant to provide belongs in
the theme's `components` defaults (`defaultProps`, `classNames`, `vars`), which every direct
import inherits automatically.

| Wrapper | Action |
|---|---|
| `Badge`, `Center`, `Popover` | Delete; use Mantine |
| `Button` | Delete; any shared styling becomes a theme default |
| `Tooltip` | Move `events={{hover, focus, touch}}` into the theme; delete |
| `Modal` | Move its overlay, close-button and outside-click defaults into the theme — which also fixes the 25 direct callers — then delete |
| `Table` | Fold into one `DataTable` built on the existing `TanStackTable` |
| `Card` | Keep, renamed `Surface`: the brand's hairline-bordered surface (`88`) |

**Build ARZO components only where they carry domain meaning Mantine lacks:**

| Component | Why it must be shared |
|---|---|
| `StatusPill` | One mapping from every domain status — order, accreditation, credential, device, access result — to colour, icon and label. Admin and scanner must never disagree about what "revoked" looks like |
| `ScanResult` | The event-day full-screen result (`38` feedback table) |
| `DataAge` | "Figures as of 14:02 — 3 devices not synced" — scanner, command center (`53`), app (`71`) |
| `DataTable` | Sorting, column visibility, bulk actions, and a card layout at phone width |
| `EmptyState`, `PageHeader` | Every new page needs both |

The objection is lock-in. It is already total — 298 of 378 files — and wrappers do not reduce it;
leaving Mantine would be a rewrite either way.

## Decision: one theme engine, document-scoped on public pages

Consolidate rather than add a fourth mechanism. Each portal this plan adds — accreditation (`23`),
exhibitor (`93`), agenda (`29`) — would otherwise pick one of the three at random.

- **Platform:** one `createArzoTheme()` from the token module (`88`).
- **Organizer branding:** one function derives the organizer variables — keep `themeUtils`'s
  guardrails, which rightly let organizers choose only an accent, background and font — and applies
  them at **document level** on public routes. On a public page the whole document is the
  organizer's, and document scope reaches portals, notifications and the cookie banner.
- **Why the subtree existed:** in-admin previews. The homepage designers already preview through an
  iframe (`HomepageDesigner/index.tsx:38-149`), so the subtree mechanism can go.
  `ProductPreview.tsx:69` is the one inline preview left; move it into the same iframe.
- One palette function, one alpha scale, one border rule; delete the violet fallbacks.

## Layout rules that recur

- **Agenda:** grid on desktop; at phone width a chronological list with track labels and a pinned
  "now / next" — never a scaled-down grid (`29`).
- **Tables:** admin tables may scroll horizontally; attendee and event-day tables become cards with
  the two or three fields that matter and actions in a menu.
- **Forms:** one column on phones, label above input, errors inline via
  `useFormErrorResponseHandler`.
- **Time:** venue local time, with the zone named when the viewer's device differs (`29`).
- **Offline:** every event-day and attendee screen that shows live data shows `DataAge`.

## RTL is a layout property, not a translation

Adding `ar` is a translation task for Lingui and a layout task for everything else: `lang` and `dir`
set at SSR from the locale (U1 first), Mantine's `DirectionProvider`, logical CSS properties
instead of physical ones, mirrored directional icons, and a real Arabic face (`88`). Start with the
attendee surfaces, which hold 49 of the 242 physical declarations (`91`). Digit shapes and date
formats are `82`'s decision.

## Enforcement

A system nobody owns drifts back within a quarter. Mechanical checks, in order of value:

1. ESLint `no-restricted-imports` on each deleted wrapper, so it cannot return.
2. Stylelint on new SCSS: no hex or `rgba()` literals outside the token module; no physical
   direction properties.
3. `@axe-core/playwright` on the `@smoke` E2E specs (`79`, `81`).

## Migration

| Step | Change | When |
|---|---|---|
| 1 | U1, U2, U6, U7 | **Now** — small, independent |
| 2 | Theme defaults for Modal, Tooltip, Button; delete the wrappers; lint gate | Phase 1 |
| 3 | One theme engine; organizer theme at document scope; remove the subtree mechanism and violet fallbacks (U2–U4) | Phase 1, before the first new portal |
| 4 | Remove the SSR overlay after fixing the hydration mismatches it hides (U5) | With `74` |
| 5 | `StatusPill`, `DataAge`, `ScanResult`, `DataTable` | Phase 2 — the badge desk is the first event-day screen |
| 6 | RTL enablement, attendee surfaces first | When `ar` is scheduled (`82`) |
| 7 | Event-day rule set applied to the scanner (U8) | Phase 4, with `94` |

## Open questions

- **Who owns design?** Without a named owner the rules above are advice. A business decision, `UNVERIFIED`; the answer belongs in `106`.
- **Target standard?** Recommend WCAG 2.2 AA for every surface, with the event-day contrast and target sizes above as ARZO's own stricter floor. `81` decides.
- **Dark mode?** Desk stays light — dense data, printing, support screenshots. Event-day gets a high-contrast dark variant for night events; it earns its cost only there.
- **Do native scanner and kiosk apps share these rules?** Yes, through tokens and the `StatusPill` vocabulary, not through shared components — `94` may not be React.

## Related

`88-design-system.md` · `89-admin-platform.md` · `90-organizer-platform.md` ·
`91-attendee-platform.md` · `92-staff-platform.md` · `93-exhibitor-platform.md` ·
`94-mobile-scanner.md` · `96-kiosk-application.md` · `19-kiosk-system.md` ·
`29-agenda-scheduling.md` · `30-mobile-event-app.md` · `38-scanner-platform.md` ·
`71-realtime-architecture.md` · `74-performance.md` · `81-accessibility.md` · `82-localization.md`
