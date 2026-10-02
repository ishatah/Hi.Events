# Accessibility

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Extend + Fix · **Priority:** P1 for attendee and kiosk surfaces (unnumbered — add to `136` when scheduled) · **Phase:** fixes now; kiosk standard before the first public kiosk
**Depends on:** `82-localization.md`, `128-definition-of-done.md`
**Blocks:** `19-kiosk-system.md`, `96-kiosk-application.md`, `87-ui-ux-system.md`

---

Every surface should be usable by everyone who has to use it. ARZO's are unusual in two ways: kiosks
are unattended public terminals, and staff screens are used at speed by tired people. Both make
accessibility an operational property, not a compliance checkbox.

## Current state — `PARTIAL`, ~35%

| Fact | Evidence |
|---|---|
| Real WCAG relative-luminance and contrast-ratio maths | `frontend/src/utilites/themeUtils.ts:193-246` |
| The homepage designer warns the organizer when button text or accent fails | `themeUtils.ts:252-268`; `ThemeColorControls/index.tsx:44` |
| Surfaces and text colours are derived, not customizable; **accent and background are** | `getDerivedColors`, `themeUtils.ts:99-117`; the two `ColorInput`s in `ThemeColorControls` |
| Text on the accent is chosen by a **gamma-encoded luma threshold**, not by WCAG luminance | `calculateLuminance` / `getContrastColor`, `themeUtils.ts:78-97` |
| `<html lang="en">` for every locale; nothing sets it at runtime | `frontend/index.html:2`; no `documentElement.lang` or Helmet `htmlAttributes` in `src` |
| 117 `aria-*` attributes (73 `aria-label`) in 32 files, 23 `role=` — across 378 components | Search |
| No skip link on any layout | Search for skip-link patterns: none |
| `prefers-reduced-motion` honoured in **one** stylesheet; Mantine's `respectReducedMotion` not set | `AuthLayout/Auth.module.scss:478`; `App.tsx:66-75` |
| Focus ring is Mantine 9.2.2's default, not overridden | `App.tsx:68-74` |
| No `eslint-plugin-jsx-a11y`; no axe in unit or E2E tests | `.eslintrc.cjs:3-19`; `package.json` files |
| E2E locates elements by role and label (`getByRole`) — a weak positive signal that names exist | `e2e/tests/account/taxes-fees.spec.ts:122` |

Measured contrast (WCAG 2.x ratio, computed from the source values):

| Pair | Ratio | Verdict |
|---|---|---|
| White on upstream default accent `#8b5cf6` (the baseline default) | 4.23:1 | **Fails** 4.5:1 — the designer warns on its own default |
| White on the rebrand's `#1B7E99` (uncommitted at audit time) | 4.68:1 | Passes, narrowly |
| Tertiary text `#737373` on dark surface `#1f1f1f` | 3.48:1 | **Fails** — used for footer text at 0.85rem |
| White on `#00A000` (chosen by the luma heuristic) vs `#1a1a1a` on it | 3.48 vs 5.00 | The heuristic picks the failing colour |

## Defects — fix now, independent of the roadmap

| # | Defect | Evidence | Severity |
|---|---|---|---|
| A1 | `<html lang>` is always `en`: screen readers read the 18 translated locales with an English voice — WCAG 3.1.1, level A | `index.html:2` | **Medium** — and the same attribute carries `dir` for Arabic (`82`) |
| A2 | Dark-mode footer text on event and organizer pages is 3.48:1 — SC 1.4.3 | `themeUtils.ts:110-116`; `EventHomepage.module.scss:869-875`; `EventHomepage/index.tsx:154` | Low |
| A3 | `getContrastColor` chooses white or near-black by a luma threshold of 128, so for mid-tone accents it picks the colour that fails, then `hasContrastIssues` warns about a problem the function created | `themeUtils.ts:78-97,252-268` | Low–Medium — fix: pick whichever of the two has the higher `getContrastRatio` |
| A4 | No skip link | Search | Low |
| A5 | Reduced motion ignored everywhere but the auth layout | `Auth.module.scss:478` | Low |
| A6 | Nothing checks accessibility automatically, so every fix above can regress silently | Above | Medium — structural |

A1, A2 and A3 are each a few lines.

## Decision: WCAG 2.2 AA, not 2.1

The scaffold asked whether the target is 2.1 AA. **It should be 2.2 AA.** WCAG 2.2 is the current W3C
Recommendation (`https://www.w3.org/TR/WCAG22/`, status "W3C Recommendation 12 December 2024",
accessed 2026-09-29). Its AA additions land squarely on ARZO's surfaces:

| New in 2.2 | Level | Where it bites |
|---|---|---|
| 2.5.8 Target Size (Minimum) | AA | Kiosks, scanner buttons, the attendee PWA |
| 2.4.11 Focus Not Obscured (Minimum) | AA | Sticky checkout summaries, cookie banner, drawers |
| 3.3.8 Accessible Authentication (Minimum) | AA | Login, future attendee accounts (`30`) — no cognitive-test-only login |
| 2.5.7 Dragging Movements | AA | The badge designer (`22`) and seat maps (`26`) need a non-drag alternative |
| 3.3.7 Redundant Entry | A | Checkout and accreditation forms must not ask twice |
| 3.2.6 Consistent Help | A | Kiosk "get help" in the same place on every screen |

2.2 keeps every 2.1 AA criterion except 4.1.1 Parsing, which it removed as obsolete — so meeting 2.2
AA also answers anyone who asks for 2.1.

### The legal position — `UNVERIFIED`

Qatar's National e-Accessibility Policy (MCIT, 2011) names digital kiosks among the platforms it
covers and promotes WCAG 2.1, per Mada, Qatar's assistive-technology centre
(`https://nafath.mada.org.qa/nafath-article/mcn2306/`, accessed 2026-09-29). Whether it **binds a
private company's** ticketing site or kiosks is not established by that source. Government clients
may impose it through procurement. **Owner: legal (`66`).** The engineering answer does not change:
2.2 AA is the target either way.

## Scope by surface

| Surface | Users | Target | When |
|---|---|---|---|
| Event page, checkout, ticket, order emails, attendee PWA (`91`) | Public | 2.2 AA, in every supported language including Arabic | P1 |
| **Kiosk** (`19`, `96`) | Public, unattended | 2.2 AA plus the kiosk requirements below | Before the first public kiosk |
| Accreditation and exhibitor forms (`23`, `32`) | External, untrained | 2.2 AA | With those features |
| Organizer admin | Trained staff | 2.2 AA as the target; keyboard and labels first | P2 |
| Scanner, desk and ops apps (`94`, `97`) | Staff under pressure | AA contrast and the operational rules below | Phase 4 |
| PDFs — invoices, tickets | Public | Best effort; tagged PDF from dompdf is `UNVERIFIED` | Revisit with `22` |

## Kiosk requirements

A kiosk nobody can operate unaided is a queue with a screen attached. These are requirements, not
polish:

| Requirement | Rule |
|---|---|
| Reach and angle | Screen, scanner window and printer slot within a seated user's reach, per a published reach-range standard ARZO adopts (which applies in Qatar is `UNVERIFIED` — `66`, `102`) |
| Target size | 48 × 48 CSS px minimum on kiosks — above 2.5.8's 24 px, because gloves, haste and glare |
| Timeouts | The idle reset (`19`) warns and offers "more time" with at least 20 s to respond (SC 2.2.1); it never wipes a half-completed flow silently |
| Always a way out | A "get help" control on every screen, in the same place (3.2.6), that summons staff — no dead ends |
| Language | Arabic and English offered on the attract screen, switchable at any step (`82`) |
| Contrast | 7:1 for body text on kiosks — they sit near windows and doors |
| Non-visual path | Audio output where the hardware allows; otherwise the staffed desk is the accessible path and signage says so |
| Scanner placement | QR window reachable from a wheelchair; a staffed alternative always open |

## Operational clarity on staff screens

- Every scan result is distinguished by **colour, icon, text and sound** together (`38`'s feedback
  table) — never colour alone (SC 1.4.1). Red–green is the most common colour-vision deficiency and
  the exact pair a door uses.
- The attendee's name is the largest thing on the screen; the reason category is second.
- No toast that disappears before a busy operator reads it; results persist until the next scan.

## Testing

| Layer | Mechanism |
|---|---|
| Lint | `eslint-plugin-jsx-a11y` as a **ratchet**, like the existing lint baseline in `frontend-tests.yml:51` — new errors fail, old ones are paid down |
| E2E | `@axe-core/playwright` on the smoke journeys (event page, checkout, login, scanner); fail on new serious or critical violations |
| Manual | Keyboard-only pass and a screen-reader pass (VoiceOver, NVDA; Arabic voice once `82` lands) per release touching attendee surfaces |
| External | An independent audit before the first public kiosk and before any SaaS launch; Mada offers accreditation of digital services (fit and cost `UNVERIFIED`) |

This makes `128` gate 14 checkable rather than asserted.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | A1 (`lang` from the resolved locale, server-rendered), A2, A3 | Now — small |
| 2 | Skip link in the app and event layouts; `respectReducedMotion` in the Mantine theme | Now |
| 3 | `jsx-a11y` ratchet in CI | With `83`'s ARZO pipeline |
| 4 | axe in the E2E smoke set | Phase 1 |
| 5 | Kiosk requirements written into `96` and the procurement spec (`102`) | Before kiosk hardware is bought |
| 6 | External audit | Before the first public kiosk |

## Open questions

- **Is ARZO legally bound**, and by what? `66` with Qatari counsel; the target does not depend on the answer.
- **Organizer admin at full AA, or keyboard-and-labels only?** Full AA if ARZO sells SaaS to government; otherwise staged.
- **Who tests with an Arabic screen reader?** Needs a native speaker; a paid tester per release is realistic.
- **Accessible PDF tickets** — the ticket in the email and PWA is the accessible version; is a tagged PDF also required?

## Related

`19-kiosk-system.md` · `96-kiosk-application.md` · `82-localization.md` · `87-ui-ux-system.md` ·
`88-design-system.md` · `38-scanner-platform.md` · `91-attendee-platform.md` · `66-compliance.md` ·
`102-hardware-procurement.md` · `128-definition-of-done.md` · `80-qa-strategy.md`
