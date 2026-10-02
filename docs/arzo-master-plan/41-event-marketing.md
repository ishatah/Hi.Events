# Event Marketing and Acquisition

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep + Extend · **Priority:** P2 · **Phase:** any — independent of the space/time work
**Depends on:** `45-affiliate-referrals.md`, `46-promo-codes.md`, `65-privacy-gdpr.md`
**Blocks:** `42-email-marketing.md` (audiences), `51-reporting.md` (sales-by-source)

---

## Current state — `PARTIAL`, and one widely held assumption is wrong

### `account_attributions` does not attribute ticket sales

`CONFIRMED`: the table records **how a platform account signed up** — marketing the SaaS, not the
event. UTM is captured only in `AuthLayout` (`index.tsx:132`), stored first-touch in localStorage,
sent on registration, written once in `CreateAccountHandler.php:91-111`, and read by one
super-admin report (`api.php:547`).

**There is no UTM, referrer or click-id capture on orders or attendees.** A search of the live
schema for `%utm%` columns returns only `account_attributions`. The embeddable widget appends
`utm_source=embedded_widget` to the checkout URL (`SelectProducts/index.tsx:252`), and the backend
never reads it.

So an organizer cannot answer the first question of event marketing: **which channel sold these
tickets?** Earlier documents listed "`account_attributions` captures UTM" as a marketing
capability; for organizers, it is not one.

### What does work

| Capability | State | Evidence |
|---|---|---|
| Tracking pixels — Meta, GA4, GTM, TikTok | `CONFIRMED` | `utilites/trackingPixels/index.ts:8-13`; configured per organizer in `organizer_settings.tracking_pixels`, max 10 |
| Funnel events — PageView, ViewContent, InitiateCheckout, Purchase | `CONFIRMED` | `useOrganizerTrackingPixels.ts:25`; `Checkout/index.tsx:258-286`; Purchase de-duplicated per session |
| Sitemaps — events and organizers | `CONFIRMED` | `api.php:681-683`; `SitemapGeneratorService.php` |
| JSON-LD `Event` schema, Open Graph, Twitter cards | `CONFIRMED` | `EventDocumentHead/index.tsx:81-133` |
| Per-event and per-organizer SEO fields | `CONFIRMED` | `seo_title`, `seo_description`, `seo_keywords`, `allow_search_engine_indexing` |
| Affiliate attribution | `CONFIRMED` | `45` |
| Promo codes | `CONFIRMED` | `46` |
| Embeddable checkout widget | `CONFIRMED` | `embed/widget.js` |

## Defects — fix independently of any roadmap phase

| # | Defect | Evidence | Why it matters |
|---|---|---|---|
| M1 | **Pixels load without consent by default.** When `VITE_COOKIE_CONSENT_ENABLED` is not `'true'`, consent resolves to all-granted; `.env.example` sets it `false`. | `useCookieConsent.ts:14-16`; `frontend/.env.example:31` | Advertising cookies without consent under PDPL and GDPR. For ARZO's own deployment the default must be on. |
| M2 | **Purchase fires for unpaid offline orders** (`AWAITING_OFFLINE_PAYMENT`) | `Checkout/index.tsx:269` | Ad platforms optimize toward conversions that may never pay |
| M3 | **Event pages hardcode `robots: index, follow`**, ignoring the event's own `allow_search_engine_indexing` | `EventDocumentHead/index.tsx:127` | A private event the organizer excluded is still indexable. The organizer page respects its flag; the event page does not. |
| M4 | JSON-LD `eventStatus` is always `EventScheduled` | `EventDocumentHead` | Cancelled events are advertised as on to search engines |
| M5 | Sitemap ignores organizer status and the organizer's indexing flag | `EventRepository.php:221-235` | An archived organizer's events stay in the sitemap |
| M6 | Frontend treats `fbclid` as a paid click; backend classifies fbclid-only visits as referral | `utm.ts:15` vs `AttributionSourceClassifier.php:39-41` | Signup attribution disagrees with itself |

M1 and M3 are privacy defects, not marketing polish, and belong in the next release.

## Target

### 1. Order-level source attribution — the one real gap

```
order_attributions
  id, order_id UNIQUE → orders, event_id,
  channel,          -- DIRECT | WIDGET | AFFILIATE | EMAIL | PAID | ORGANIC | REFERRAL
  utm_source, utm_medium, utm_campaign, utm_term, utm_content NULL,
  referrer_url NULL, landing_page NULL,
  gclid NULL, fbclid NULL, ttclid NULL,
  first_seen_at timestamptz, created_at
  INDEX (event_id, channel)
```

- Captured on the storefront at landing, stored per event in the browser, submitted with order
  creation — the same mechanics as the affiliate code (`45`), which already works.
- **Last non-direct touch** within a window, stated in the UI. First-touch versus last-touch is a
  policy choice organizers should see, not discover.
- `channel` reuses `AttributionSourceClassifier` so signup and order attribution classify alike
  once M6 is resolved.
- Output: a **sales-by-source** report (`51`).

### 2. Conversion accuracy

Fixing M2 is the cheap half. Server-side conversion APIs (Meta Conversions API, GA4 Measurement
Protocol) are more accurate and **send buyer data server-to-server to ad platforms** — a data
transfer needing a lawful basis and a processor position (`65`). Deferred until someone asks for it
with that understood.

### 3. Discoverability

- Fix M3–M5.
- Arabic event pages and `hreflang` once `82` decides Arabic scope.
- A public listing page of an organizer's upcoming events already exists; nothing further planned.

## Out of scope

Stated so they do not accrete (`04` non-goals):

- Paid-acquisition management — campaigns are run in the ad platforms
- A landing-page builder beyond the existing homepage designer
- Social scheduling and posting
- Attendee "invite a friend" referral programmes — an affiliate per attendee is possible later (`45`) but is not planned

## Open questions

- **Is paid acquisition managed inside ARZO at all?** Recommendation: no — attribute it, don't run it.
- **Attribution window** — 30 days to match affiliates, or shorter? Consistency with `45` argues 30.
- **Should organizers see signup attribution?** No — it is platform marketing data about ARZO's own funnel, and stays super-admin only.
- **Event-level pixels** in addition to organizer-level — useful for agencies running one client's event under a shared organizer. Low priority.

## Related

`42-email-marketing.md` · `45-affiliate-referrals.md` · `46-promo-codes.md` · `51-reporting.md` ·
`65-privacy-gdpr.md` · `82-localization.md` · `04-product-strategy.md`
