# Changelog

## 14.0.0 (2026-11-18)

### Changed

- TYPO3 14.3 only: `typo3/minimal ^14.3`, `ext_emconf.php` depends on TYPO3 14.3.
- Version numbers follow the TYPO3 major from now on: 14.x for TYPO3 14. TYPO3 13 stays on 4.x
  (`^4.2`).
- Includes the fixes of 4.2.0 (below): short links follow slug and visibility changes, decode
  caches only notFound answers.

### Tests

- The backend user is authenticated on the real clock: TYPO3 14 checks the session against the
  current time, the pinned test clock (2026-01-01) made every DataHandler test fail with
  "Can not initialize backend user".

## 4.2.0 (2026-11-18)

### Fixed

- Short links no longer redirect to an outdated URL after the target changed. The decoder cached
  the finished URL for up to a week, and only a backend save of a record of the configured table
  dropped it. These stayed stale:
  - renaming the detail page or one of its parents, even in the backend
  - renaming, hiding or deleting records through plain SQL, importers or upgrade wizards
  - changes to the site configuration until the next cache flush
- The notFound page is linked with its current URL; it was cached for up to an hour.

### Changed

- Found decode answers are not cached any more: the record is looked up and the URL is built on
  every request. Correctly checking a cached answer needs the same queries as resolving it.
  Measured on a TYPO3 13.4 site with Extbase route enhancers (local development machine, 1080
  redirects): building the URL takes about 0.3 ms; a redirect takes about 10 ms longer than a 4.1
  cache hit (median 31 ms instead of 20 ms).
- notFound answers are still cached (at most an hour, bots probe number ranges), now as an answer
  only. The cache key changed (`decode-v2-…`), so entries written by 4.1 are never read; they
  expire on their own.
- Encoded short URLs are cached as before. `ShortNrCacheInvalidator::invalidateTable()` is still
  the way for plain SQL writers to update them and notFound answers immediately.

## 4.1.2 (2026-11-05)

### Fixed

- PHP 8.2 and 8.3: `ExtensionSetup` used PHP 8.4 syntax (`new X()->method()`), a parse error on
  every request although `composer.json` allows `^8.2`.
- Package name in `composer.json` is `cpsit/cps-shortnr`, as on Packagist (was `cpsit/shortnr`;
  the README install command failed).
- `ext_emconf.php`: version `4.1.2`, state `stable`, PHP constraint.

### New

- CI: lint and the integration tests run on PHP 8.2, 8.3, 8.4 and 8.5 for every pull request.

## 4.1.1 (2026-11-04)

### Fixed

- Records that exist only in a non-default language (`l10n_parent = 0`, free mode) are decoded
  again. 4.1.0 treated them as "default language record not visible" and answered not found.
  With `languageFallback`, a short number in another language serves such a record in its own
  language.
- A short number that names a visible translation (e.g. `p5253-1`, the uid of one of two English
  translations) serves exactly that translation again, as 4.0.x did; the lowest-uid rule only
  decides when the short number names the base record or an invisible translation.

### New

- `ShortNrRepository::findVisibleLanguageFamily()` (base uid + all visible rows);
  `findVisibleLanguageVariants()` is kept.

## 4.1.0 (2026-11-04)

Decode now answers for an anonymous visitor, and cached answers follow record changes.

### Behaviour changes (visible to visitors)

- **Invisible records are not found.** Hidden, scheduled (starttime in the future), expired
  (endtime passed) and workspace-only records and translations are no longer decoded. Before, a
  short number to such a record redirected to its (404) URL, or for pages to the site root.
  The default language record not being visible makes its translations not found too.
- **Translation choice.** Several translations in one language: the lowest visible uid wins.
  Before: the last row returned by the database, often a hidden draft.
- **Sysfolders and spacers** are not found.
- **`notFound: ""`** passes the request on to TYPO3 (404). Before: uncaught exception (500).
- **Caching.** Entries are dropped when a record of their table changes (DataHandler, Extbase)
  and expire at the next starttime/endtime; a cached notFound answer lives at most one hour.
  Before: cached for a week regardless.

### New

- Config key `languageFallback` (bool, default `true`, per item or in `_default`): what to do
  when the requested language has no visible translation (`true`: default language record,
  `false`: not found).
- `ShortNrCacheInvalidator::invalidateTable()` for code writing records with plain SQL.

### Fixed

- Broken YAML config is logged once and no longer parsed on every request; short numbers pass
  through until it is fixed (retried every 5 minutes). Surfaces as `ShortNrInvalidConfigException`.
- Not found is logged at debug level; errors are logged once, without stack trace.
- Patterns with an additional named group no longer fail in the language overlay.
- Encoding without `$GLOBALS['BE_USER']` (CLI, scheduler) no longer warns.

### Breaking for code using internals

- `ConfigItemInterface::isLanguageFallbackEnabled()` added (custom implementations must add it).
- `ShortNrRepository::resolveCorrectUidWithLanguageUid()` replaced by
  `findVisibleLanguageVariants()`; `resolveTable()` returns visible records only.
- `DecoderService::decode()` returns `null` instead of throwing when no notFound target exists.

Custom processors extending `PluginProcessor`/`PageProcessor` are not affected.
