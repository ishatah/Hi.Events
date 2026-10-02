# Localization

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Extend (i18n) + New (RTL, Arabic, content translation) · **Priority:** P1 if Arabic is confirmed for ARZO's own events (`04`); unnumbered — add to `136` when scheduled · **Phase:** fixes now; the Arabic programme can start after Phase 1 and run in parallel
**Depends on:** `04-product-strategy.md` (the Arabic decision), `87-ui-ux-system.md`
**Blocks:** `135-global-expansion.md`, `22-badge-design-printing.md`, `43-sms-notifications.md`, `81-accessibility.md`

---

Languages, formats and direction. The platform is well internationalized for left-to-right
European and East Asian languages. It has never rendered a right-to-left page, and ARZO is a Qatar
business.

## Current state — `PARTIAL`, ~50%: strong LTR i18n, Arabic 0%

| Fact | Evidence |
|---|---|
| **20 Lingui catalogues**: en, zh-cn, es, fi, fr, nl, hu, pt-br, ru, de, pt, vi, tr, se, it, pl, sk, el, ko, zh-hk (ja, id, cs, ga commented out) | `frontend/lingui.config.ts:4-29` |
| **19 selectable**: `ru` is absent from `availableLocales` and from the backend `Locale` enum; ~2,804 of ~3,104 `ru` entries are empty | `locales.ts:24`; `app/Locale.php:11-31`; `ru.po` |
| The other 18 catalogues have no empty entries; translation **quality** is `UNVERIFIED` — strings are written straight into `.po` files, with no translation tool and no reviewer | `/translations` skill; `.po` files |
| Backend strings in 19 `lang/*.json` plus PHP validation files for en, es, tr | `backend/lang/` |
| Unlocalized JSX strings are a lint **error** — the rule dominates the 768-error lint baseline | `.eslintrc.cjs:12`; `frontend-tests.yml:46-47` |
| Web locale: cookie, then browser language, prefix-matched, else `en` | `locales.ts:74-90,126-140`; `entry.server.tsx:18-22` |
| API locale: cookie, then the user's locale, then `Accept-Language` | `SetUserLocaleMiddleware.php:24-70` |
| Order locale captured at checkout, copied to attendees; every transactional mail sets `->locale()` per recipient | `CreateOrderActionPublic.php:52`; `CompleteOrderHandler.php:194`; `SendOrderDetailsService.php:94,123` |
| **Custom email templates have no locale** — one template serves every recipient | Live DB `\d email_templates` |
| **Event content is single-language** — no translation column or table anywhere | `information_schema` search |
| `persons` holds one name, no alternate-script name, no preferred locale | Live DB `\d persons` |
| dayjs locale loaders for 16 locales; none for `pl` or `se` | `locales.ts:92-109` |
| `formatCurrency` formats by `navigator.language`, and by `en-US` during SSR | `utilites/currency.ts:1-10` |
| **No RTL at all**: no `dir`, no `DirectionProvider` (Mantine 9.2.2 exports one, unused), zero matches outside the catalogues | Search at `e7228c1d` |
| 242 physical `left/right` declarations in 78 of 184 SCSS files; 12 logical-property uses; 19 inline physical style props; 59 directional chevron and arrow icons | `git grep` at `e7228c1d` |
| No Arabic-capable font: baseline loads Outfit and Plus Jakarta Sans from Bunny; the uncommitted rebrand self-hosts SF Pro Display, whose notice says it has **no** U+0600–06FF glyphs; the 19 homepage-designer fonts include no Arabic-script family | `e7228c1d:frontend/index.html`; `public/fonts/NOTICE.md:18-19` (untracked); `homepageFonts.ts:19-39` |
| Email layout has no `lang`/`dir`; the theme hardcodes `text-align: left` six times | `layout.blade.php:3`; `themes/default.css:28,49,59,68,75,260` |
| Invoice PDF: dompdf 3.1.5, `<html lang="en">`, DejaVu Sans. Arabic **shaping** is `UNVERIFIED` | `invoice.blade.php:14,27`; `composer.lock` |
| Attendee search is raw substring `ILIKE` on first and last name; no normalization; no `unaccent` | `AttendeeRepository.php:117-131`; `pg_extension` |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| L1 | `<html lang="en">` is static, so no page declares its real language — and `dir` has nowhere to live | `index.html:2` (shared with `81` A1) | **Medium** — fix now |
| L2 | Swedish is tagged `se` (Northern Sami): `Accept-Language: sv-SE` falls back to English, and any `Intl` or hreflang use of the tag is wrong | `LocaleService.php:22-31`; `locales.ts:133-139` | Low — accept `sv` as an alias now |
| L3 | A custom email template overrides every locale: a German buyer receives the organizer's English text | `email_templates` schema | Medium for bilingual events |
| L4 | Currency formats by browser locale on the client and `en-US` on the server — the two renders disagree | `currency.ts:1-10` | Low |
| L5 | Polish and Swedish dates render English month names | `locales.ts:92-109` | Low |
| L6 | `getCurrencySymbol` returns ﷼ for QAR, SAR **and** OMR, so the three look identical in price inputs | `currency.ts:43,57,71` | Low — use `ر.ق` or the ISO code |

## Decision: Arabic is a programme, sequenced attendee-first — not a locale file

Adding `ar` to `lingui.config.ts` takes a minute and produces Arabic words in a left-to-right
layout: unusable. The work is direction, typography, content and data, in this order:

| Stage | Scope | Why this position |
|---|---|---|
| **A0 Foundations** | Server-rendered `<html lang dir>`; Mantine `DirectionProvider`; an OFL-licensed Arabic webfont chosen with the brand (candidates: IBM Plex Sans Arabic, Noto Sans Arabic — brand fit `UNVERIFIED`); a lint ratchet that forbids **new** physical `left/right` in SCSS; icon-mirroring helper; `<bdi>` for mixed-direction values | Everything else sits on it |
| **A1 Attendee web** | Event page, checkout, order confirmation, ticket, waitlist; system emails in Arabic with an RTL layout (`42`); convert the SCSS modules these routes use to logical properties | Attendees are public, untrained and local — the audience that cannot work around English |
| **A2 On-site** | Badge rendering with shaped Arabic (raster at printer DPI, `39`); kiosk (`19`); scanner result strings; SMS templates (`43`) | Needed by the first event that prints Arabic names |
| **A3 External portals** | Accreditation application, exhibitor portal (`23`, `32`) | External users, lower volume |
| **A4 Organizer admin** | Only if ARZO's operations staff or its customers need it | ARZO's operators may work in English — `UNVERIFIED` |

Converting all 78 SCSS files up front is not required. The ratchet stops the count growing; each
stage converts the modules its routes touch.

**Independent of the programme, fix now:** the keyboard-wedge defect `38` S1. A scanner on a host
with an Arabic layout silently fails today, whether or not the UI is ever translated.

### Arabic surface by surface

| Surface | Requirement | Owner |
|---|---|---|
| Web | `dir="rtl"`, logical properties, mirrored directional icons, Latin-then-Arabic font stack | This document |
| Rich text | TipTap paragraphs with `dir="auto"` so bilingual descriptions render each line correctly | `87` |
| Emails | `lang`/`dir` on the layout; `text-align: start` instead of `left` | `42` |
| Invoices | Render an Arabic name through dompdf and inspect it. If letters come out unjoined or reversed, invoices move to the same renderer badges choose | `15`, `22` |
| Badges | Raster at printer DPI so shaping is done by the renderer, never by printer fonts | `39` |
| SMS | UCS-2: 70 characters per segment, 67 when concatenated — Arabic templates written short | `43` |
| Keyboard wedge | Read `KeyboardEvent.code`, not `e.key` | `38` S1 |
| Names | Normalized search keys; two names per person (below) | `16`, `23` |
| Digits | Western digits for money, codes and IDs (below) | This document |
| Calendar | Gregorian; week start per locale via Mantine `DatesProvider`. Hijri display is an open question | `29` |

## Decision: content translation is separate from UI translation

Lingui translates the product. It cannot translate the organizer's event title, ticket names or
questions. Bilingual events are normal in Qatar, so attendee-facing content needs its own model:

```
content_translations
  id, account_id,
  translatable_type,   -- EVENT | PRODUCT | PRODUCT_CATEGORY | QUESTION | SESSION
                       -- SPEAKER | ZONE | ACCESS_POINT | ACCREDITATION_TYPE
  translatable_id, field, locale,
  value text,          -- HtmlPurifierService applied when the field is rich text
  status,              -- DRAFT | APPROVED — only APPROVED is shown publicly
  updated_by → users, timestamps
  UNIQUE (translatable_type, translatable_id, field, locale)

event_settings  + default_locale, + published_locales jsonb   -- which languages this event offers
email_templates + locale NULL                                 -- NULL = every locale (today's behaviour); fixes L3
```

**Why a side table, not a column per field:** the translatable set grows with every phase — session
titles, zone names on badges, access-point names on scanner screens. A column per field per table is
a migration each time. **The trade-off** is a polymorphic reference with no foreign key; deleting the
parent must delete its translations, enforced in the owning service and covered by a test.

AI may draft a translation (`133`); it lands as `DRAFT` and a person approves it. A machine-translated
refund policy published unread is a liability.

## Decision: two names per person, and normalize for search only

Accreditation in the Gulf commonly needs the name as written in the passport or ID **and** in Arabic
(`UNVERIFIED` for ARZO's clients). Transliteration is not a function — Mohammed, Muhammad and Mohamed
are one person — so the platform stores both rather than deriving one:

```
persons  + local_first_name varchar(128) NULL,
         + local_last_name  varchar(128) NULL,   -- the name in the person's own script
         + preferred_locale varchar(10)  NULL    -- drives SMS and staff-app language
```

Search matches either name through a **normalized key**: diacritics (tashkeel) and tatweel removed;
أ إ آ folded to ا; ى to ي; ة to ه. It is an expression index with `pg_trgm`, which is already
installed. **Stored names are never altered** — a badge must match the identity document exactly.

## Decision: Western digits for money, codes and identifiers

Order references, ticket codes and badge numbers are typed, read aloud and scanned. Mixing
Arabic-Indic and Western digits across screens, badges and emails causes lookup failures at the desk.
Arabic UI uses `ar-QA-u-nu-latn` for numbers and dates by default. Whether ARZO's brand prefers
Arabic-Indic digits in running text is `UNVERIFIED` — the copy owner decides; codes stay Western
regardless.

## Translation workflow

The current workflow — extract, write translations into `.po` files, compile — suits languages the
team can check. Arabic needs a **native reviewer** for every attendee-facing string, and a
professional one for legal, payment and accreditation text. Target: a CI check that the `ar`
catalogue has no untranslated or fuzzy entries in files under the A1 routes.

## Migration

| Step | Change | Risk / when |
|---|---|---|
| 1 | L1, L2 (alias), L4, L5, L6; `38` S1 | Low — now |
| 2 | `email_templates.locale` and locale-aware template lookup (L3) | Low — now |
| 3 | A0 foundations and the SCSS ratchet | Medium — after the Arabic decision |
| 4 | `content_translations`, `event_settings` locale fields; organizer UI for bilingual content | Medium |
| 5 | A1 attendee surfaces and Arabic system emails | L |
| 6 | `persons` name fields, normalized search index | Low — with `23` |
| 7 | A2 on-site, A3 portals; A4 only on demand | Phases 2–4 |

## Open questions

- **Is Arabic required for ARZO's own events?** `04`'s open question. If yes, this outranks much of Phase 3 and the ordering in `113` is wrong.
- **Arabic-Indic digits in running text?** Brand decision; codes stay Western.
- **Hijri dates alongside Gregorian?** Probably only for government clients; no by default.
- **Who reviews Arabic?** A named native reviewer and a budget line, not a volunteer.
- **SF Pro licensing.** The vendored font's notice says it is not cleared for web redistribution (`public/fonts/NOTICE.md:9`), and the all-in-one image redistributes it. Out of scope here; flagged to `66`.

## Related

`135-global-expansion.md` · `04-product-strategy.md` · `38-scanner-platform.md` · `39-printer-integration.md` ·
`43-sms-notifications.md` · `42-email-marketing.md` · `22-badge-design-printing.md` · `81-accessibility.md` ·
`87-ui-ux-system.md` · `16-attendee-management.md` · `23-accreditation.md` · `112-arzo-differentiators.md` ·
`133-ai-capabilities.md`
