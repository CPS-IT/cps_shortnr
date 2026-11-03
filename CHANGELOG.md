# Changelog

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
