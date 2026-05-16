# Changelog – class-based edition

All notable changes to the Language Router (class-based refactor) are documented here.
Earlier history is preserved in the root `CHANGELOG.md` of the procedural version.

---

## [1.3.3] - 2026-05-16

### Added

- **Internal Link Fixer (`LSFLR_Link_Fixer`)** — a new admin-only class that scans
  translated posts and pages for internal links that still point to the source-language
  version of a page and offers to repoint them to the correct language equivalent.

  The fixer surfaces as a **Fix Links (XX)** button in the posts/pages list toolbar,
  visible only when a language filter is active. Clicking it opens a modal overlay with:
  - A dry-run scan of all published content in that language.
  - A table showing each affected post, and the exact from/to URL for every link that
    can be repointed using the TRID translation group.
  - Per-row **Fix** buttons and a **Fix All** action (sequential to avoid DB contention).

  The fix is content-only: `handle_save_post` is temporarily unhooked around
  `wp_update_post` so that TRID assignments, language meta, and translation timestamps
  are not disturbed. Links with no known translation in the target language are left
  untouched.

### Changed

- **Minimum PHP version raised from 7.4 to 8.0.** The codebase now uses `str_starts_with()`
  and `str_contains()` throughout in place of `strpos() === 0` and `strpos() !== false`.
  Affected locations:
  - `class-language-router.php`: locale prefix check in `locale_from_lang()`, both
    cookie hyphen checks in `detect_lang_safe()` / `detect_lang()`, `name="lang"`
    presence check in `fix_search_form()`, template slug prefix guards in
    `render_template_meta_box()`.
  - `class-lsflr-link-fixer.php`: all three internal-URL prefix checks.
- `Requires PHP: 8.0` added to the plugin file header.

---

## [1.3.2] - 2026-05-15

### Fixed

- **Cannot add footnotes to imported pages** — After import, the target page's
  `footnotes` postmeta retained stale UUID data from its previous content. The imported
  `post_content` had no inline `data-fn` markers and no `<!-- wp:footnotes /-->` block
  (both stripped in v1.3.1), but the meta still held footnote definitions from before
  the import. The block editor initialised its footnotes store from this stale meta,
  producing an inconsistent state (meta has UUIDs, content references none). When the
  user then tried to add a new footnote, rendering `core/footnotes` against this broken
  store caused the "This block has encountered an error" crash and `*` display.

  Fixed by writing `'[]'` to the `footnotes` meta immediately after `wp_update_post`.
  This is the same value WordPress uses for a page that has never had footnotes,
  giving the imported page the identical clean starting state as a fresh page.

---

## [1.3.1] - 2026-05-15

### Changed

- **Footnotes stripped from imported content** — After repeated failed attempts to copy
  Gutenberg footnotes across pages (UUID remapping, meta write ordering, kses filters, JS
  error boundaries), all footnote import code has been removed for the second time.
  Gutenberg footnotes are tightly coupled to post-specific UUIDs across `post_content`
  and `footnotes` postmeta, and the block editor's internal state makes cross-page
  copying brittle. The import now actively strips footnote markup from the source content
  before saving it to the target: `<!-- wp:footnotes /-->` block comments and inline
  `<sup data-fn="…">…</sup>` markers are both removed, leaving clean prose without
  broken footnote references. The `footnotes` postmeta on the target is not touched.

### Added

- **Source Footnotes metabox** — A read-only metabox (labelled "Source Footnotes") is
  shown on all non-source translation pages that have a linked source. It reads the
  source page's `footnotes` postmeta and renders the footnote contents as a numbered
  list, giving the translator a reference to recreate them manually in the block editor.
  On source-language pages the metabox explains that footnotes are edited directly in
  the block editor.

### Removed

- All footnote import/fix code from versions 1.2.1–1.3.0: UUID remapping,
  `protect_footnotes_meta` filter, `sanitize_footnotes_block_on_save` filter,
  `enqueue_editor_fixes` JS HOC, `allow_footnote_data_fn` kses filter,
  `purge_empty_footnotes` and `sanitize_all_footnotes_blocks` migrations.
  These will be reconsidered once the exact Gutenberg footnotes lifecycle is understood.

---

## [1.3.0] - 2026-05-15

### Fixed

- **Block Logic JS fix not loading** — The `enqueue_editor_fixes()` method was echoing
  a raw `<script>` tag inside the `enqueue_block_editor_assets` action. That action fires
  during WordPress's script-enqueueing phase, before any block editor scripts have been
  executed; `wp.hooks` and `wp.element` did not exist yet, so the early-exit guard
  `if (!wp.hooks || !wp.element) return` silently aborted every time. Switched to
  `wp_add_inline_script( 'wp-edit-post', $script )`, which appends the code as an inline
  block immediately after `wp-edit-post.js` in the page output — at that point all block
  editor globals are guaranteed to be available.

- **`data-fn` attribute stripped by `wp_kses_post`** — Added a permanent
  `wp_kses_allowed_html` filter (`allow_footnote_data_fn`) that explicitly allows `data-fn`
  on `<sup>` tags in the `post` context. WordPress 6.3 added this to its own allowlist
  when footnotes were introduced, but older or patched installs may not include it, causing
  `wp_update_post` to silently strip inline footnote markers from imported content.

---

## [1.2.9] - 2026-05-15

### Fixed

- **Block Logic crash on `core/footnotes` after import** — Importing a page that has
  footnotes copies the `<!-- wp:footnotes /-->` block into the target's `post_content`.
  On next editor load, Block Logic's `editor.BlockEdit` HOC processes every block
  including `core/footnotes`, attempts a synced-pattern store lookup, gets `null`, and
  crashes with `TypeError: Cannot read properties of null (reading 'isSynced')`. The fix
  from v1.2.4 was removed as part of the v1.2.7 clean-slate reset and is now reinstated:
  `enqueue_editor_fixes()` registers an `editor.BlockEdit` HOC at priority 999 (outermost,
  executes first at React render time) that strips the `blockLogic` attribute from
  `core/footnotes` props before Block Logic's HOC can process them.

- **Footnotes meta overwritten during `wp_update_post` hooks** — The footnotes meta was
  previously written before `wp_update_post`. Any `wp_after_insert_post` hook could then
  overwrite it. Moved the `update_post_meta( 'footnotes', … )` call to after
  `wp_update_post` returns, so it is the last write before the AJAX response is sent.

- **JSON encoding mismatch** — `wp_json_encode` was used to encode the remapped footnotes
  array; PHP's default `json_encode` escapes forward slashes (`<\/p>`) while JavaScript's
  `JSON.stringify` does not (`</p>`). Switched to
  `json_encode( $new_footnotes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )` so the
  stored JSON format is identical to what the block editor writes.

---

## [1.2.8] - 2026-05-15

### Added

- **Footnote UUID remapping on import** — `ajax_import_translation()` now correctly
  transfers footnotes from source to target without breaking the block editor.

  WordPress footnotes exist in two places that must stay in sync: inline anchors in
  `post_content` (`data-fn="UUID"`, `href="#fn-UUID"`, `href="#fnref-UUID"`,
  `id="fn-UUID"`, `id="fnref-UUID"`) and a JSON array in the `footnotes` postmeta
  (`[{"id":"UUID","content":"…"}]`). A verbatim copy of both leaves source and target
  sharing the same UUIDs; the block editor's footnotes store treats duplicate UUIDs as
  the same footnote and collapses them to the pre-init fallback character `*`.

  The import now:
  1. Reads the source `footnotes` meta and decodes the JSON array.
  2. Generates a fresh UUID (`wp_generate_uuid4()`) for every footnote.
  3. Builds an `old → new` UUID map and rewrites all five reference patterns in
     `post_content`.
  4. Writes the remapped `footnotes` meta to the target **before** calling
     `wp_update_post`, so every `wp_after_insert_post` hook sees `post_content` and
     `footnotes` already in a consistent state.
  5. If the source has no footnotes (`''` or `'[]'`), the target's `footnotes` meta
     is left untouched — WordPress manages that key internally and the plugin does
     not interfere.

---

## [1.2.7] - 2026-05-15

### Removed

- **All footnote import handling removed (clean-slate reset)** — Versions 1.2.1–1.2.6
  introduced progressive fixes for copying `footnotes` postmeta during AJAX import: meta
  transfer, empty-value guards, revision fallback, a `update_post_metadata` protection
  filter, a `wp_insert_post_data` block sanitizer, a JS error boundary for the Block Logic
  crash, and UUID regeneration on import. None of these fully resolved the issue: imported
  pages continued to display footnotes as `*` instead of sequential numbers, and the
  `protect_footnotes_meta` filter caused its own regression (editor/DB sync loss on target
  pages).

  Root cause (confirmed): WordPress footnotes use UUIDs shared between `post_content`
  (`data-fn="uuid"`, `href="#fn-uuid"`, etc.) and the `footnotes` postmeta JSON. A plain
  copy of both leaves the source and target sharing identical UUIDs; the block editor's
  footnotes store treats these as the same footnote and collapses them to the pre-init
  fallback character `*`. The correct fix requires generating fresh UUIDs for the target
  and rewriting all five reference patterns in content and meta atomically — but that
  approach also failed in testing, likely due to editor initialisation timing.

  **Decision:** Strip all footnote-related code back to a neutral baseline before
  designing a new approach. The import now only copies `post_title` and `post_content`
  (via `parse_blocks` / `serialize_blocks`); it does not touch `footnotes` postmeta at
  all. This means footnotes are not imported, but it also means the import can no longer
  *break* footnotes on the target page. A correct implementation will be designed
  separately once the clean baseline is verified.

  **Removed:**
  - `const FOOTNOTES_META_KEY` class constant
  - `protect_footnotes_meta()` method and its `update_post_metadata` hook (was v1.2.2–1.2.4)
  - `sanitize_footnotes_block_on_save()` method and its `wp_insert_post_data` hook (was v1.2.3)
  - `enqueue_editor_fixes()` method and its `enqueue_block_editor_assets` hook (was v1.2.4)
  - `sanitize_all_footnotes_blocks()` one-time migration (was v1.2.3)
  - `purge_empty_footnotes()` one-time migration (was v1.2.2)
  - All footnote meta read/write logic in `ajax_import_translation()`

---

## [1.2.6] - 2026-05-15

### Fixed

- **Imported footnotes display as `*` instead of numbers** — The import was copying the
  source page's `footnotes` postmeta verbatim, including the exact UUIDs that WordPress
  uses to link inline references (`data-fn="uuid"`, `href="#fn-uuid"`,
  `id="fnref-uuid"`) to footnote definitions in the meta JSON. Both the source page and
  the target page then shared identical footnote UUIDs. The block editor uses these IDs
  internally; duplicate UUIDs across pages confuse its state and cause footnotes to
  render as `*` (the pre-initialisation fallback) instead of sequential numbers.

  Fixed in `ajax_import_translation()`: for each footnote in the source, a fresh UUID
  is generated with `wp_generate_uuid4()`. Every occurrence of the old UUID in the
  post content (`data-fn`, `href="#fn-…"`, `href="#fnref-…"`, `id="fn-…"`,
  `id="fnref-…"`) is replaced with the new UUID before the content is saved. The
  `footnotes` postmeta is written with the new UUIDs as well. Source and target pages
  now each own a distinct set of footnote identifiers.

---

## [1.2.5] - 2026-05-15

### Fixed

- **`protect_footnotes_meta` filter removed** — The `update_post_metadata` filter
  introduced in v1.2.2 blocked any empty write to the `footnotes` meta key when the DB
  already held a non-empty value. Its intent was to prevent WP core from silently wiping
  source-post footnotes during REST API saves. In practice it also blocked the block
  editor from updating (or clearing) footnotes on *target* translation pages, because the
  editor's in-memory state and the DB would fall out of sync: the editor sent one value,
  the filter silently dropped it, and on the next reload the DB value came back. This
  mismatch caused footnotes to display as `*` on imported pages and made it impossible to
  edit footnotes normally on any target page.

  The filter is no longer needed:
  - The import now writes footnotes *before* `wp_update_post` (v1.2.4), so the PHP save
    path never goes through the REST controller that caused blank writes.
  - The Block Logic JS fix (v1.2.4) prevents the editor crash that previously caused the
    block editor to send empty `meta.footnotes` on save.

  WordPress now manages `footnotes` meta without interference from this plugin.

---

## [1.2.4] - 2026-05-15

### Fixed

- **Footnotes block crashes in Gutenberg (editor.BlockEdit HOC from Block Logic)** —
  Block Logic registers a `blockLogic` attribute on *every* block via a
  `blocks.registerBlockType` JS filter and then wraps every block that has that attribute
  in an `editor.BlockEdit` HOC. The HOC attempts a synced-pattern store lookup; for
  `core/footnotes` the lookup returns `null`, and the HOC immediately crashes on
  `null.isSynced`. This happened even when adding a brand-new footnote on a fresh post,
  making footnotes completely unusable in the editor.

  Fixed by adding an `enqueue_block_editor_assets` hook that outputs a
  `blocks.registerBlockType` JS filter at priority 999. It runs after Block Logic's
  filter and removes the `blockLogic` attribute from the `core/footnotes` definition.
  With the attribute absent, Block Logic's HOC has nothing to process for that block and
  falls straight through to the original `core/footnotes` edit component.

  **Why `blocks.registerBlockType` alone does not work:** `wp-block-library` (which
  registers `core/footnotes`) loads before our plugin script. By the time our
  `blocks.registerBlockType` filter is registered, `core/footnotes` has already been
  registered and the filter never fires for it. The `editor.BlockEdit` approach is
  immune to this race because it applies at React render time, not registration time.

  The `blocks.registerBlockType` filter is kept as a belt-and-suspenders measure for
  environments where load order differs. The `wp_insert_post_data` PHP filter (v1.2.3)
  continues to strip injected attributes from saved post HTML.

- **Footnotes block crashes in Gutenberg after AJAX import** — `ajax_import_translation()`
  was writing the `footnotes` postmeta *after* calling `wp_update_post`. This created a
  window where all `wp_after_insert_post` hooks — including `handle_save_post` and any
  third-party hooks such as Block Logic — fired with inconsistent state: `post_content`
  already contained `data-fn` UUID inline references but the `footnotes` postmeta still
  held the previous (stale) value. The Gutenberg block editor initialises its footnotes
  store from this state, causing `core/footnotes` to crash with "This block has
  encountered an error and cannot be previewed."

  Fixed by moving the footnotes fetch (`get_post_meta`) and `update_post_meta` call to
  *before* `wp_update_post`. By the time any hook fires, both
  `post_content` and `footnotes` meta are already consistent.

---

## [1.2.3] - 2026-05-15

### Fixed

- **Gutenberg editor crash on posts with footnotes** — Third-party plugins such as
  Block Logic inject their own attributes (`blockLogic`, `className`) onto every
  registered block, including `core/footnotes`. The Gutenberg footnotes editor component
  does not tolerate unknown attributes and crashes with:
  `TypeError: Cannot read properties of null (reading 'isSynced')`.
  Fixed by:
  1. A `wp_insert_post_data` filter (`sanitize_footnotes_block_on_save`) that strips all
     attributes from `wp:footnotes` block delimiters on every save, keeping the block as
     the attribute-free `<!-- wp:footnotes /-->` that Gutenberg expects.
  2. A one-time DB migration (`sanitize_all_footnotes_blocks`) run on the 1.2.3 version
     bump that retroactively cleans all existing posts with polluted footnotes blocks.

### Changed / Cleaned

- **`define_lang_constant()` simplified** — The method previously called `detect_lang()`
  (URL + cookie), checked validity (always true — source language is always valid), and
  then called `detect_lang_safe()` again from scratch. The dead first call and unreachable
  else-branch are removed; `MY_LANG` is now set directly from `detect_lang_safe()`.

- **Unused `$lang` variable removed from `ajax_import_translation()`** — The sanitized
  `$_POST['lang']` value was assigned but never read; the method already uses
  `$original_lang = $this->get_lang( $target_id )`. Removed.

- **`extract_block_text()` double-append removed** — In the `core/details` branch, child
  text was unconditionally appended and then appended again when content length was under
  200 chars. Since `extend_posts_search` uses `LIKE` (not frequency counting), the
  duplication had no search effect. Removed.

- **`purge_empty_footnotes()` uses `$wpdb->prepare()`** — The raw SQL query now uses a
  prepared statement instead of string interpolation for the meta key.

- **`render_lang_filter_dropdown()` GET param guard fixed** — The previous `??` null-
  coalescing chain fell through to user meta only when the key was absent; an empty-string
  form submit bypassed the stored preference. Switched to `!empty()` with explicit
  `sanitize_text_field()`.

- **Invalid CSS `vertical-align: center` removed** from `.lsflr-current` — `center` is
  not a valid value for `vertical-align`; the rule was also redundant since `inline-flex`
  with `align-items: center` already handles vertical alignment.

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
