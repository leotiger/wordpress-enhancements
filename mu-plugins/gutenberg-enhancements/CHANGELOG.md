# Changelog — Cal Talaia Gutenberg Enhancements

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.2.0] — 2026-05-14

### Fixed

- **Critical — Lightbox showed blank image.**
  `buildHTML()` never set a `data-bg` attribute on the inner `.inline-image` span,
  so the front-end lightbox handler always read an empty string and rendered a
  broken `<img>`. Fixed by adding `data-bg="<url>"` to the span. The `updateCurrentImage()`
  helper was also updated to read from `data-bg` first, with a CSS `url()` regex
  as a fallback for images saved before this version.

- **Lightbox checkbox showed wrong state when re-selecting a non-lightbox image.**
  The click handler called `setLightbox(l)` where `l` is the raw string value of
  the `data-lightbox` attribute (`"true"` / `"false"`). The string `"false"` is
  truthy in JavaScript, so reopening any previously saved image incorrectly checked
  the lightbox box. Fixed to `setLightbox(l === 'true')`.

- **Editor styles never loaded.**
  `add_editor_style()` referenced `./gutenberg-editor-enhancements.css`, a file
  that does not exist. Corrected to `gutenberg-enhancements.css` and now resolved
  via `plugin_dir_url(__FILE__)` so the path is always correct regardless of where
  WordPress is installed.

- **`add_theme_support('editor-styles')` called too late.**
  It was called inside `enqueue_block_editor_assets`, which fires after theme
  setup. Moved to the `after_setup_theme` hook where WordPress expects it.

### Improved

- **Removed duplicate `wp.richText` and `wp.element` destructuring.**
  Both were declared at the outer IIFE scope and again inside the inline-image
  `edit()` function. The inner copies are removed; `remove` is now included in
  the top-level `wp.richText` destructuring.

- **Lightbox fade animation.**
  The JS already toggled `.is-opening`, `.is-visible`, and `.is-closing` classes
  on the overlay but the CSS file defined no rules for them. Added `opacity`
  transition rules so the open/close fade actually renders.

- **Lightbox backward compatibility for pre-1.2.0 images.**
  The front-end lightbox handler now falls back to parsing the inline `background-image`
  style URL when `data-bg` is absent, so images saved before this version still
  open correctly.

- **CSS version header synced** to `1.2.0` (was `1.0`).

---

## [1.1.0]

- Added inline image tool with lightbox support, alignment, and size controls.
- Font family selector reads available fonts from block editor settings.

## [1.0.0]

- Initial release.
