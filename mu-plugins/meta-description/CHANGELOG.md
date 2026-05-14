# Changelog

All notable changes to **MU Meta Description** are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.1.0] – 2026-05-14

### Added
- **`register_post_meta`** call on `init` — exposes `meta_description` to the REST API and Gutenberg's meta channel, with a proper `auth_callback` and `sanitize_callback`.
- **Character counter** in the meta box UI — live feedback as you type, colour-coded green (120–160 chars), amber (161–200), red (outside range).
- **`maxlength="320"`** attribute on the textarea — hard browser cap to prevent excessively long values reaching the DB.
- **Hint text** below the textarea: "Aim for 120–160 characters. Leave empty to use the excerpt fallback."
- **Version header** (`Version: 1.1.0`) added to the plugin file header.

### Fixed
- **Undefined variable `$custom`** — initialised to `''` before the `is_singular()` block in `wp_head`, eliminating a PHP notice on archive and home pages where the block was never entered.
- **Empty-save now deletes meta** — saving a blank field calls `delete_post_meta` instead of storing an empty string, keeping the `wp_postmeta` table clean and avoiding orphaned rows.
- **`[…]` / `[&hellip;]` excerpt artifact** — automatic WordPress excerpt ellipsis is stripped and replaced with a plain `...` before output, preventing it from leaking into the rendered `<meta>` tag.
- **Consistent excerpt logic** — `wp_head` now uses the same `post_excerpt → trimmed content` chain as the meta box, so the fallback description displayed in the editor matches what gets rendered in the `<head>`.

### Changed
- `$custom` variable is now explicitly initialised to `''` at the top of the `wp_head` closure so its scope is unambiguous.
- Source comments and inline docs tidied up.

---

## [1.0.0] – initial release

- Meta box on all public post types with nonce-verified save.
- Fallback chain: custom field → excerpt → trimmed content → site description.
- Outputs `<meta name="description">`, `<meta property="og:description">`, `<meta name="twitter:description">`.
- Automatic fallback descriptions truncated at 190 characters; custom descriptions are never truncated.
