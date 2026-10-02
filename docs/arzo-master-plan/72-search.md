# Search

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep (Postgres) + New (device-local search) + Fix · **Priority:** P1 for device search (part of ARZ-101); S1 fix now; central improvements unnumbered · **Phase:** fixes now; device search with Phase 4
**Depends on:** `71-realtime-architecture.md`, `82-localization.md`
**Blocks:** `17-onsite-registration.md`, `19-kiosk-system.md`, `94-mobile-scanner.md`

---

## Current state — `PARTIAL`, ~45% central, 0% on device

Finding a person fast matters in two places: at the door, where a queue is waiting, and in the
back office, where it is merely annoying. Today both go to the server, and the door path cannot work
offline at all.

| Fact | Evidence |
|---|---|
| Substring `ILIKE '%q%'` search in **16 repositories and 2 admin handlers**; no full-text search, no search engine, no Scout | Search for `ilike`, `tsvector`, `to_tsquery`; `composer.json`, `package.json` |
| `pg_trgm` 1.6 is installed; 8 GIN trigram indexes from the 2020 baseline — `attendees` and `orders` on `first_name`, `last_name`, `email`, `public_id` | `\dx`; `schema.sql:358-368,627-637` |
| Attendee search ORs five arms, one of them the unindexed expression `first_name \|\| ' ' \|\| last_name` | `AttendeeRepository.php:71-83,120-130` |
| **That arm disables the trigram indexes.** With `enable_seqscan = off`, the five-arm query still plans a Seq Scan; without the concatenation it plans a `BitmapOr` over the trigram indexes | `EXPLAIN` on the dev DB (plan shape only — the DB is empty) |
| Per-event queries use `idx_attendees_event_id` and filter, which is adequate for one event's roster. **No existing attendee or order search can use the trigram indexes**: each ORs in an unindexed arm — the name concatenation, or `orders.short_id` in the admin search | Same `EXPLAIN`; `OrderRepository.php:40-51,242-248` |
| **The admin order search fails on any search term**: after joining `events` and `accounts`, the unqualified `email` is ambiguous | `OrderRepository.php:236-248`; the same predicate under `EXPLAIN` returns `column reference "email" is ambiguous` |
| Check-in search: frontend asks for 150 rows, first page only, 200 ms debounce; server caps at 250 via `simplePaginate`; **no `ORDER BY`**, so which 150 is arbitrary and the operator is not told the list is truncated | `CheckIn/index.tsx:78,121-125`; `AttendeeRepository.php:113-172` |
| The **public** check-in search matches email | `AttendeeRepository.php:130`; route `api.php:657`, global 180/min limit only |
| User input is concatenated into `LIKE` patterns unescaped — `%` and `_` act as wildcards | Every call site |
| `persons` has no name index; its email index is case-sensitive `btree (account_id, email)` | `\d persons` |
| No `unaccent`, no Arabic normalization; database collation `en_US.utf8` | `\dx`; `pg_database` |
| No device-local store, so no device search | `71` |

### Arabic and transliteration, measured

Read-only `SELECT`s against the dev database:

| Query | Result | Meaning |
|---|---|---|
| `'أحمد' ILIKE '%احمد%'` | **false** | Typing a name without the hamza misses it |
| `similarity('احمد', 'أحمد')` | **0.25** | Below `pg_trgm`'s default 0.3 threshold — fuzzy misses it too |
| `similarity('Mohammed', 'Muhammad')` | **0.2** | Transliteration variants are invisible to trigrams |
| `similarity('Mohammed', 'Mohamed')` | 0.7 | Doubled-letter variants are caught |
| `word_similarity('moham', 'Mohammed Al-Thani')` | 0.83 | Prefix search works |

Trigrams handle typos. They do not handle **orthographic variants** of Arabic or **transliteration**
between scripts — the two cases a Qatari door will meet most. Normalization has to come first.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| S1 | **Public check-in search matches email** — anyone holding a check-in link can test whether an address holds a ticket | `AttendeeRepository.php:130`; `api.php:657` | Medium — **fix now**: drop the email arm on the public path |
| S2 | Check-in results truncated at 150, unordered, with no signal | `CheckIn/index.tsx:124`; `AttendeeRepository.php:113-172` | Medium on site |
| S3 | `LIKE` wildcards unescaped — `_` matches everything, a cheap way to request expensive scans | All call sites | Low |
| S4 | The unindexed arms make the trigram indexes dead weight | `EXPLAIN` above | Low today; matters for cross-event search |
| S5 | **Admin order search errors on any term** — unqualified columns in a three-table join | `OrderRepository.php:236-248` | Low–Medium — a support tool that does not work; **fix now** by qualifying with `orders.` |

## Decision: the door searches locally; the server is not in the path

`71` already requires full offline attendee lookup. A server search engine, however fast, is one
network hop from failing exactly when the venue wifi saturates. Device search is therefore the
primary on-site path, and central search is for the back office.

## Device-local search

### The normalizer — one specification, golden vectors

Server and devices must agree on what a name normalizes to, or a name found in the dashboard is not
found at the door. The same method as the access decision (`37`): a written specification and
language-neutral **golden vectors** run in CI against the PHP, native and PWA implementations.

| Step | Rule |
|---|---|
| Unicode | NFKC; Arabic-Indic digits to ASCII |
| Latin | Lower-case; strip diacritics (`é` → `e`) |
| Arabic | Remove tashkeel (U+064B–U+0652) and tatweel (U+0640); fold `أ إ آ ٱ` → `ا`; `ى` → `ي`; `ة` → `ه`; `ؤ` → `و`; `ئ` → `ي` |
| Tokens | Split on space and hyphen (`Al-Thani` → `al`, `thani`); index the `ال` / `al` prefix as a separate token, not a replacement |
| Skeleton | For Latin tokens, a consonant skeleton: drop vowels, collapse doubled letters — `Mohammed`, `Muhammad`, `Mohamed` all yield `mhmd`; `Ahmed`, `Ahmad` yield `hmd` |

The skeleton is deliberately crude arithmetic, not a model (`04`, `133`). It over-matches; ranking
puts exact and prefix matches first, so over-matching costs a longer list, not a wrong admission.

### Store and query

```
-- on the device (SQLite on native, IndexedDB on PWA); built from the synced roster
search_tokens   token TEXT, kind TEXT, credential_id,     -- kind: NAME | SKELETON | CODE | COMPANY
                PRIMARY KEY (token, kind, credential_id)
email_hashes    email_sha256 TEXT, credential_id,         -- salted per event; exact match only
                PRIMARY KEY (email_sha256, credential_id)
```

- **Prefix** by range scan: `token >= q AND token < q + U+FFFF`, the usual key-range idiom. It works
  identically in SQLite and IndexedDB, so nothing relies on a full-text extension being compiled
  into every platform's SQLite.
- **Fuzzy** only when prefix returns few results: edit distance ≤ 1 (≤ 2 for tokens of 7+ characters)
  over tokens sharing a first character. A 10,000-person roster is ~30,000 tokens; a bounded linear
  pass is cheap. Target **p95 < 100 ms** on the reference device — provisional, like `74`.
- **Email is held only as a salted hash.** Staff can look someone up by the exact address they give;
  nobody can list the roster's emails from a lost device. The salt is per event, so hashes do not
  correlate across events. A guessed address can still be confirmed; exact match is the trade-off.
- **Ranking:** exact credential or ticket code → exact full name → every token prefix-matched → some
  tokens → skeleton → fuzzy. Ties: not yet admitted first, then name.
- **Results show what disambiguates:** photo where held, company, ticket or accreditation type. The
  list states its total and asks for another letter rather than truncating silently (S2).

### Fuzzy at the door creates duplicates — design against it

A walk-in desk (`17`) that cannot find "Mohamed" will create a new person who is really "Mohammed".
So walk-in creation **runs the fuzzy search first** and shows candidates; if the operator creates
anyway, the new record carries `possible_duplicate_of` for server review. Never auto-merge (`71`).

### Kiosk self-lookup

A kiosk (`19`) puts search in attendees' hands. It must not browse the roster: exact code, or surname
plus confirmation code, returning one person or none.

## Central search: Postgres first

**Decision: stay on Postgres with `pg_trgm`; add a search engine only when a trigger fires.**

The strongest objection to an engine is not cost but **tenancy**. Isolation today rests on the
Action layer (F11); an external index would need its own tenant filter on every query, a second
place for the same bug. Meilisearch or Typesense also add a stateful service and a sync pipeline.

Fixes first:

```
ALTER TABLE persons   ADD COLUMN search_name text NULL;   -- normalizer output, written on save
ALTER TABLE attendees ADD COLUMN search_name text NULL;   -- until attendees read names from persons
CREATE INDEX persons_search_name_trgm   ON persons   USING gin (search_name gin_trgm_ops);
CREATE INDEX attendees_search_name_trgm ON attendees USING gin (search_name gin_trgm_ops);
CREATE INDEX persons_account_email_lower ON persons (account_id, lower(email));   -- dedupe (23, 32)
```

- Query `search_name` instead of the concatenation (S4); keep the exact-match fast path for public
  and short ids (`idx_attendees_public_id_lower` already exists).
- Escape `%`, `_` and `\` in every `ILIKE` (S3); minimum query length 2.
- Rank with `similarity()` for organizer-wide person search (`32`, `47`); per-event search keeps the
  event filter first.

| Trigger for an engine | Measured how |
|---|---|
| Organizer-wide or admin search p95 > 1 s at production volume | `78` HTTP latency by route |
| Search across many fields with typo tolerance and relevance ranking that Postgres cannot express | A concrete product requirement, e.g. public event discovery |
| Faceted search | Same |

None applies today. `125` should include a person-search test at ARZO's largest account size.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | S1: drop the email arm from the public check-in search; S5: qualify the admin order search columns | Low — **now** |
| 2 | S2: order check-in results; return a `has_more` flag and show it | Low — now |
| 3 | S3: wildcard escaping helper in `BaseRepository`; minimum length | Low |
| 4 | Normalizer specification and golden vectors; PHP implementation | Low — precedes device work |
| 5 | `search_name` columns, backfill, trigram indexes; queries switched (S4) | Low — additive |
| 6 | Device search store and ranking with the native scanner (`94`) and kiosk (`96`) | Phase 4 |
| 7 | Walk-in duplicate check (`17`) | With ARZ-111 |

## Open questions

- **Dual-script names?** If `82` confirms Arabic scope, accreditation should capture a name in each script (`persons.name_alt`); transliteration by rule is a fallback, not a substitute.
- **Should the dashboard search keep matching email?** Yes for authenticated organizers; the enumeration concern is the public path.
- **Reference device for the 100 ms target** — decided with `102` procurement.
- **Phone search** once phones are captured (`43`) — exact E.164 match only, same reasoning as email.

## Related

`71-realtime-architecture.md` · `17-onsite-registration.md` · `19-kiosk-system.md` ·
`94-mobile-scanner.md` · `96-kiosk-application.md` · `82-localization.md` · `37-hardware-integration.md` ·
`23-accreditation.md` · `08-multi-tenancy.md` · `125-performance-testing-plan.md`
