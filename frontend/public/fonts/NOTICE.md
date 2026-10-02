# Vendored fonts

## SF Pro Display — `sf/sf-pro-display-{400,500,700}.woff2`

The only webfont the app loads. Three upright weights, self-hosted and declared as `@font-face`
in `frontend/src/styles/global.scss`, then named in the Mantine theme (`frontend/src/App.tsx`)
and on `body`.

Apple-licensed, and not cleared for web redistribution. Self-hosting it here is a deliberate
owner decision, taken with the `-apple-system` system-stack alternative on the table and
declined. It is not an oversight. The same three files, and the same decision, are recorded at
`ryzo-website/app/fonts/NOTICE.md` on the ARZO marketing site.

The stack keeps `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial,
sans-serif` behind it, so removing these three files degrades to the native UI font on every
platform rather than breaking the layout.

**No Arabic coverage.** SF Pro Display carries no glyphs in U+0600–06FF. The app ships no `ar`
locale today, so nothing renders Arabic; adding one means adding a real Arabic face here.

**Not used by the homepage designer.** The organizer-facing font catalogue
(`frontend/src/constants/homepageFonts.ts`, mirrored by
`backend/app/DomainObjects/Enums/HomepageFontFamily.php`) resolves every entry through the Bunny
CDN. SF Pro is self-hosted with no CDN entry and is deliberately absent from that list.
