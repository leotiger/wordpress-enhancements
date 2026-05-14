# Changelog

All notable changes to the SVG Icon Block plugin are documented here.

## [1.1] — 2026-05-14

### Security

- `flush_icons_cache` GET parameter now requires `manage_options` capability. Previously any visitor could trigger a full cache flush by appending `?flush_icons_cache` to any URL.

### Bug Fixes

- Fixed `align-items` inconsistency between editor and frontend: the PHP render callback was outputting `align-items: flex-start` on the button wrapper while the JS editor used `center`. Both now use `center`, so icon and label are vertically centred on the frontend as well.
- Added `stroke` to the SVG inline style in the PHP render callback to match the editor (`IconSVG` component already set `stroke: iconColor`). Colour rendering is now consistent between editor preview and frontend output.
- Removed `rel` attribute link when removing a link via the toolbar "Remove link" button — previously `rel` was left behind as orphaned data.

### New Features

- **`rel` attribute** — blocks now persist a custom `rel` value. It is exposed in the Inspector Controls sidebar (under a new "Link" panel) and also picked up from `LinkControl`'s `onChange` callback. The PHP render callback already auto-set `noopener noreferrer` from `opensInNewTab`; that fallback is preserved when no custom `rel` is provided.

### Improvements

- Defined `SIB_CACHE_KEY` as a PHP constant (`'sib_icon_data_v1'`) to avoid the string being duplicated between `sib_get_icon_data()` and the `flush_icons_cache` handler.
- Removed unused `color` attribute from `block.json` (superseded by the separate `iconColor` and `textColor` attributes introduced in an earlier revision).
- Removed unused `AlignmentToolbar` and `Icon` imports from `block.js`.
- Fixed extra whitespace in the `IconSVG` `transform` template literal — the multi-line string was inserting a leading newline and spaces before `rotate(…)`.
- Added `.sib-link` CSS rule so the anchor wrapper (`<a class="sib-link">`) no longer inherits unwanted underlines or colour overrides from theme link styles.
- Added `align-items: center` to the `.sib-button` base rule in `editor.css` for consistent baseline alignment without relying on inline styles alone.
- Cleaned up all commented-out dead code across `svg-icon-block.php` and `block.js` (old `sprite` key, unused import comments, duplicate rotation approach, legacy `ColorPalette` block).

## [1.0] — initial release

- SVG sprite-based icon block with label, link, size, rotation, flip, variant, and colour support.
- Fuzzy + multilingual icon search (EN / ES / CA + tags).
- Transient-cached JSON data with 12-hour TTL and manual flush via `?flush_icons_cache`.
- Three button variants: Primary, Outline, Ghost.
- Icon position: Left, Right, Above, Below.
