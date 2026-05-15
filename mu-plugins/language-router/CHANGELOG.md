# Changelog – class-based edition

All notable changes to the Language Router (class-based refactor) are documented here.
Earlier history is preserved in the root `CHANGELOG.md` of the procedural version.

---

## [1.2.2] - 2026-05-15

### Fixed

- **Root cause of footnotes being lost on import** — WordPress core (6.3+) writes
  `footnotes = ''` to `wp_postmeta` on every block editor REST API save for posts that
  have no footnotes in the editor's current in-memory state. This silently overwrote real
  footnote data on source posts whenever they were opened and re-saved for any reason.
  The next import would then read `''` from the source, treat it as "no footnotes", and
  call `delete_post_meta` on the target instead of copying. Fixed by adding a
  `update_post_metadata` filter (`protectfootnotes_meta`) that blocks an empty value from
  overwriting a non-empty `footnotes` in the DB, while still allowing genuine
  "user removed all footnotes" saves when the DB value is already empty.

- **Stale empty `footnotes` rows cleaned up** — `purge_emptyfootnotes()` is run as a
  one-time migration on the 1.2.2 version bump. It deletes all `footnotes` postmeta rows
  where the value is `''` or `'[]'`, removing the accumulated noise from prior WP core saves.

- **Import guard hardened for `'[]'`** — The empty-footnotes check in
  `ajax_import_translation()` now also rejects `'[]'` (an empty JSON array), which some
  WordPress configurations use instead of an empty string for posts with no footnotes.

- **Revision fallback for lost footnote data** — `getfootnotes_with_revision_fallback()`
  is used during import instead of a bare `get_post_meta`. If the source post's `footnotes`
  is empty (cleared by prior WP core saves), the method scans up to 25 recent revisions
  (most recent first) for a non-empty value. WordPress 6.4+ stores `footnotes` in
  revisions via `revisions_enabled`, so the last good value is typically recoverable even
  after the main meta was wiped.

- **AJAX import now surfaces errors** — The fetch callback in `print_admin_js()` previously
  called `location.reload()` regardless of the server response, silently swallowing
  `wp_send_json_error` replies. It now parses the JSON response, shows an `alert` on
  failure, and only reloads on success.

---

## [1.2.1] - 2026-05-15

### Fixed

- **Footnotes not copied on import** — `ajax_import_translation()` now explicitly transfers
  the `footnotes` post meta from source to target after calling `wp_update_post()`.
  WordPress stores footnotes outside `post_content` as a dedicated meta key (`footnotes`,
  introduced in WP 6.3); the previous implementation only copied post fields, so footnotes
  were silently dropped. If the source has no footnotes, any stale `footnotes` value on
  the target is deleted to prevent leftover data.

---

## [1.2.0] - 2026-05-14

### Technical Notes

- Full conversion from procedural / closure-based code to an OOP class structure.
- `Language_Router` implemented as a singleton (`Language_Router::get_instance()`).
  All WordPress hooks are registered in `register_hooks()` using `[$this, 'method']` syntax,
  giving each concern a named, navigable method instead of an anonymous closure.
- `LSFLR_Switcher` extracted into its own class and injected with the `Language_Router`
  instance (dependency injection), removing any implicit coupling between the two files.
- Entry point `language-router.php` boots both classes and exposes thin wrapper functions
  (`my_get_translations()`, `my_query()`, `my_lang_permalink()`, …) so existing theme
  template code continues to work without modification.
- `MY_LANG` constant is still defined at file-load time — the singleton constructor runs
  immediately on `require`, preserving the exact timing of the procedural version.
- PHP 7.4+ required for typed class properties (`private Language_Router $router`).

### Added

- `Language_Router::get_instance()` — global singleton accessor for use in theme / plugin code.
- `$cached_languages` and `$cached_source_language` instance properties — `languages()` and
  `source_language()` now compute their value once per request instead of calling
  `get_available_languages()` and `apply_filters()` on every invocation.
- `my_lang_permalink()` compatibility wrapper added to entry point (was missing from the
  initial class conversion; theme code calling the function directly would have fataled).
- Capability check (`current_user_can('edit_post', $target_id)`) added to
  `ajax_import_translation()` — the previous implementation only verified the nonce.

### Changed

- `locale_from_lang()` now normalises `$lang` to lowercase *before* checking the static cache,
  fixing a latent cache-miss bug where callers using uppercase codes (e.g. `'CA'`) would
  always bypass the cache and re-run the full lookup.
- `get_translations()` now casts `wpdb` result `post_id` values to `int`.
  `wpdb->get_results()` always returns column values as strings; the cast prevents subtle
  strict-comparison mismatches (`===`) in callers that received the translation map.
- `render_lang_column()` parameter `$id` changed from `int` type hint to untyped with an
  explicit `(int)` cast. WordPress passes the post ID as a string in some `manage_*_custom_column`
  code paths, causing `TypeError` under strict typing.
- `handle_pre_get_posts()` admin block cleaned up: the redundant `if (is_admin())` wrapper
  was dead code (the frontend branch always `return`s before reaching it). Removed the
  wrapper; fixed indentation of the admin block.
- `handle_save_post()` group-merge comparison changed from loose `==` to strict `===`.

### Fixed

- Cache miss for uppercase language codes in `locale_from_lang()`.
- `TypeError` in `render_lang_column()` when WordPress passes `$id` as string.
- Missing capability check in AJAX import handler (security hardening).
- Redundant `if (is_admin())` dead-code block in `handle_pre_get_posts()`.
- Loose `==` comparison in translation group merge loop.
- `wpdb` string post IDs propagated as strings into the translation map.
- Missing `my_lang_permalink()` theme-compatibility wrapper in the entry point.

---
