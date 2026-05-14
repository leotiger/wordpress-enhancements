# Changelog

All notable changes to **Accordion Scroll** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.1.0] - 2026-05-14

### Added
- PHP now injects configurable scroll settings (`headerSelector`, `fallbackOffset`,
  `visibilityThreshold`) via `wp_add_inline_script` before the JS file loads.
  Themes can override any value by writing to `window.accordionScrollConfig`
  earlier in the page, without touching plugin files.
- JS reads `window.accordionScrollConfig` at startup and falls back gracefully
  to the same defaults that shipped in 1.0.

### Changed
- PHP uses `filemtime()` for the JS version string, so browsers automatically
  bust the cache whenever the file is saved — no manual version bump required.
- Magic numbers (`110` px offset, `0.3` viewport threshold) promoted to named
  constants `FALLBACK_OFFSET` and `VISIBILITY_THRESHOLD` for readability.
- Header selector (`.site-header`) extracted to `HEADER_SELECTOR` constant and
  sourced from the config object.
- Renamed internal variable `isVisible` → `isNearTop` to better reflect its
  meaning (button is already in the upper portion of the viewport).
- Inline comments updated for clarity throughout JS.
- Version header bumped to `1.1` in both files.

### Fixed
- Removed dead commented-out line `//const OFFSET = 80;`.

---

## [1.0.0] - Initial release

### Added
- `MutationObserver` watches `.wp-block-accordion-item` elements for `is-open`
  class changes and smooth-scrolls the accordion toggle button into view when
  an item is opened by the user.
- Skips items that are open on page load (`openByDefault`) to avoid an
  unexpected scroll on first paint.
- Respects sticky header height via `.site-header` `offsetHeight`, with a
  `110 px` fallback when the element is absent.
- Loaded in the footer via `wp_enqueue_script` with no additional dependencies.
- Conditional load: script is only enqueued on pages that contain a
  `core/accordion` block.
