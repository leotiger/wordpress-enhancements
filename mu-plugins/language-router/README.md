# Language Router for WordPress — Class-based refactor (v1.3.3)

A drop-in replacement for the procedural Language Router plugin, rewritten as two collaborating classes. All `my_*` wrapper functions are preserved so existing theme code keeps working without changes.

---

## File structure

```
class-based/
├── language-router.php          ← Entry point: boots classes, defines MY_LANG, registers wrappers
├── includes/
│   ├── class-language-router.php   ← Core singleton: routing, translation, admin, SEO, search
│   ├── class-lsflr-switcher.php    ← Language switcher block (injected dependency)
│   └── class-lsflr-link-fixer.php  ← Admin link fixer: scan & repoint internal links per language
└── assets/
    └── lsflr.css                ← Switcher styles
```

---

## Architecture

### `Language_Router` — singleton

All core logic lives here. Accessed globally via `Language_Router::get_instance()`.

The constructor does two things and nothing else:
1. Calls `define_lang_constant()` — resolves and defines `MY_LANG` immediately at file-load time, matching the timing of the procedural version
2. Calls `register_hooks()` — wires all WordPress actions and filters

Instance-level caches (`$cached_languages`, `$cached_source_language`) avoid redundant filter calls across a single request.

### `LSFLR_Switcher` — regular class with dependency injection

Receives a `Language_Router` instance via its constructor. Has no static state. Registers its own hooks in `register_hooks()`.

### `LSFLR_Link_Fixer` — admin-only utility class

Also injected with `Language_Router`. Adds a **Fix Links** button to the posts/pages list whenever a language filter is active. Clicking it opens a modal overlay with a dry-run scan of all internal links in that language's content that still point to a different language's version of the same page. Editors can fix posts individually or all at once. No changes to public-facing output.

### Entry point (`language-router.php`)

Boots all three objects and then declares thin wrapper functions that delegate to the instances:

```php
$language_router  = Language_Router::get_instance();
$lsflr_switcher   = new LSFLR_Switcher( $language_router );
$lsflr_link_fixer = new LSFLR_Link_Fixer( $language_router );
```

Theme templates can call `my_source_language()`, `my_get_translations()`, etc. exactly as before — each wrapper just forwards to the class method.

---

## How language detection works

Detection runs in priority order:

1. **URL segment** — `/de/` at the start of the path is the strongest signal
2. **`?lang=` query param** — used for search requests (`/?lang=de&s=query`)
3. **Cookie** — `my_lang` persists the last detected language across requests
4. **Fallback** — the configured source language

`detect_lang()` uses URL + cookie. `detect_lang_safe()` additionally checks the `$_GET['lang']` parameter (safe to call before WP is fully loaded). The result is stored in the `MY_LANG` constant.

---

## Translation model

Every translatable post carries four post-meta fields, all registered with `show_in_rest: true` and a proper `auth_callback`:

| Meta key | Type | Description |
|---|---|---|
| `_lang` | `string` | Two-letter language code |
| `_trid` | `string` | Shared translation group ID (UUID) |
| `_source_updated_at` | `number` | Unix timestamp of the last source-language save |
| `_translation_source_updated_at` | `number` | Source timestamp at the time the translation was last synced |

Translation groups are resolved with a graph-expansion algorithm: when you link posts A↔B and B↔C already exists, all three end up sharing the same TRID automatically.

Translation lookups are cached in the WordPress object cache (`wp_cache_set`) with a 1-hour TTL and invalidated on save via `handle_cache_clear`.

---

## Public API

### `Language_Router`

```php
$router = Language_Router::get_instance();

// Config
$router->source_language(): string
$router->languages(): array
$router->is_valid_lang( $lang ): bool
$router->locale_from_lang( $lang ): string
$router->language_label( $lang ): string

// Detection
$router->detect_lang(): string
$router->detect_lang_safe(): string

// TRID / meta
$router->get_trid( $post_id ): string
$router->set_trid( $post_id, $trid ): void
$router->get_lang( $post_id ): string
$router->set_lang( $post_id, $lang ): void
$router->get_translations( $post_id ): array   // ['de' => 42, 'fr' => 55, …]
$router->clear_translation_cache( $post_id ): void

// Outdated system
$router->mark_source_updated( $post_id ): void
$router->mark_translation_synced( $post_id ): void
$router->is_outdated( $post_id ): bool
$router->get_missing_languages( $post_id ): array

// Query helpers
$router->query( $args ): WP_Query            // auto-filters by MY_LANG
$router->query_fallback( $args ): WP_Query   // MY_LANG OR source language
$router->get_posts( $args, $fallback ): array

// Utilities
$router->safe_query_args( $url ): string
$router->is_system_request(): bool
$router->set_lang_cookie( $lang ): void
$router->hreflang_mode(): string
$router->build_search_content( $post_id ): void
$router->ensure_lang_index(): bool
$router->debug( $message, $context ): void
```

### `LSFLR_Switcher`

```php
$switcher = new LSFLR_Switcher( $router );

$switcher->get_languages(): array              // published translations with URLs
$switcher->translate_current_url( $lang, $post_id ): string
$switcher->render_switcher( $atts ): string
```

### Theme wrapper functions

All procedural wrapper functions remain available for backward compatibility:

```php
my_source_language()
my_languages()
my_is_valid_lang( $lang )
my_locale_from_lang( $lang )
my_language_label( $lang )
my_detect_lang()
my_detect_lang_safe()
my_get_trid( $post_id )
my_set_trid( $post_id, $v )
my_get_lang( $post_id )
my_set_lang( $post_id, $v )
my_get_translations( $post_id )
my_clear_translation_cache( $post_id )
my_mark_source_updated( $post_id )
my_mark_translation_synced( $post_id )
my_is_outdated( $post_id )
my_get_missing_languages( $post_id )
my_query( $args )
my_query_fallback( $args )
my_get_posts( $args, $fallback )
my_safe_query_args( $url )
my_is_system_request()
my_set_lang_cookie( $lang )
my_hreflang_mode()
my_build_search_content( $post_id )
my_ensure_lang_index()
my_debug( $message, $context )
my_lang_permalink( $url, $post )
my_lsflr_render_switcher( $atts )
my_lsflr_get_languages()
my_lsflr_translate_current_url( $target_lang, $post_id )
```

---

## Language Switcher (LSFLR)

The switcher reads the current post's TRID group, filters out non-published translations, and renders a dropdown or dropup list.

**From PHP / shortcode:**
```php
echo my_lsflr_render_switcher([
    'direction'   => 'down',       // 'down' | 'up'
    'show'        => 'label',      // 'label' | 'custom' | 'icon' | 'icon-label'
    'customLabel' => 'Language',
    'iconHtml'    => '<svg …/>',
]);
```

**Gutenberg block:** Search for **LSFLR Switcher** in the block inserter (category: Widgets). All options are in the Inspector sidebar.

---

## Configuration

Set the source language via filter (or edit the default in `Language_Router::source_language()`):

```php
add_filter( 'my_primary_language', fn() => 'ca' );
```

Override the active language list:

```php
add_filter( 'my_languages_list', fn() => ['ca', 'es', 'en', 'de', 'fr'] );
```

### Filters reference

| Filter | Default | Description |
|---|---|---|
| `my_primary_language` | `'ca'` | Source / default language code |
| `my_languages_list` | Auto from WP locales | Full list of active language codes |
| `my_lang_force_locale` | `['ca' => 'ca']` | Hard locale overrides (e.g. Vik Booking) |
| `my_lang_fallback_map` | `['en'=>'en_US', …]` | Locale fallbacks when no installed locale matches |
| `my_lang_default_fallback` | `'en_US'` | Last-resort locale |
| `my_hreflang_mode` | `'custom'` | Set to `'off'` to disable built-in hreflang output |

---

## Admin UX

The **Lang** column in the post list shows:

- The two-letter language code
- **⚠** if the translation is outdated (source was updated after the last sync)
- **⭕ DE, FR** listing any languages for which no translation exists yet

A language filter dropdown and an "Outdated only" filter are added to the post list toolbar. The active language filter persists per user via user meta.

The **Translations** sidebar meta box shows each language's linked post and an **Override** button that pulls the source content into the translation via AJAX.

Quick Edit includes a language selector for posts, pages, and navigation items. The parent page dropdown in Quick Edit is filtered server-side to only show pages in the active language.

### Link Fixer

When the post list is filtered by language, a **Fix Links (XX)** button appears in the toolbar. Clicking it opens a modal overlay that:

1. Scans all published posts and pages in that language for internal links that still point to a different language's version of the same page (a common result of importing source content).
2. Shows a dry-run table — each affected post, each link that would change, from/to path.
3. Lets editors fix individual posts or all at once with sequential AJAX calls.

The fixer uses the TRID group to find the correct language equivalent for every linked post. Links with no known translation are left untouched. The fix updates only `post_content`; language metadata, TRID assignments, and translation timestamps are not affected.

---

## WordPress language setup

Before the router can serve a language, WordPress must have that language installed. Go to **Settings → General → Site Language**, install each language you need, and verify it appears under **Dashboard → Updates → Translation files**.

The router builds its active language list automatically from the installed language packs. If you want to lock the list to a specific set regardless of what is installed, use the filter:

```php
add_filter( 'my_languages_list', fn() => ['ca', 'es', 'en', 'de', 'fr'] );
```

Every language in that list must have its language pack installed, otherwise WordPress will fall back silently and locale-dependent features (translated plugin strings, date formats, etc.) will not work correctly.

---

## WP site language vs. primary content language

These are two independent settings and it is intentional that they can differ.

**WordPress site language** (`Settings → General → Site Language`) controls the admin interface, the locale WordPress uses internally, and the baseline for plugins that load their own translations. This is typically set to a well-supported locale such as `en_US` or `de_DE`.

**Primary content language** (`my_primary_language` filter, default `'ca'`) is the language your actual content is written in — the language that maps to the root URL path (no prefix) and acts as the source for all translations.

A practical example: the site admin works in `en_US`, but the primary content is Catalan (`ca`). The WordPress site language is left at `en_US` so the admin backend stays in English. The plugin's source language is set to `ca` so Catalan content lives at `/your-page/` and other languages are served at `/es/your-page/`, `/de/your-page/`, etc.

This separation is especially useful for plugins like **VikBooking** that were developed in a specific language and produce the most reliable output when WordPress's runtime locale matches their own root language. The router's `my_lang_force_locale` filter lets you pin a locale for exactly this case:

```php
// Force Catalan to use the bare 'ca' locale rather than 'ca_ES',
// which is what VikBooking expects internally.
add_filter( 'my_lang_force_locale', function( $overrides ) {
    $overrides['ca'] = 'ca';
    return $overrides;
} );
```

The filter is applied before the installed-languages lookup, so the override always wins.

---

## FSE templates per language

The router can load a language-specific FSE template instead of the default one for pages, posts, and search results. Templates follow a slug naming convention:

| Content type | Slug pattern | Example |
|---|---|---|
| Page | `page-{lang}` | `page-de`, `page-fr`, `page-en` |
| Post (single) | `single-{lang}` | `single-de`, `single-fr` |
| Search results | `search-{lang}` | `search-de`, `search-fr`, `search-en` |

**How to create them:**

1. Open the Site Editor (**Appearance → Editor → Templates**)
2. Duplicate an existing template (e.g. copy your Page template)
3. Edit the duplicate and save it under the slug `page-de` (or whichever language suffix you need)
4. Repeat for every language and content type you want a custom template for

You do not need a language-specific template for every language. If `page-de` does not exist, WordPress falls back to the default `page` template. Only create language-specific templates when the layout actually needs to differ (e.g. different text direction, language-specific header blocks, or locale-specific components).

**Auto-assignment on language change:**

When an editor changes the `_lang` meta of a post or page, the router checks whether a matching template slug exists (`page-de`, `single-fr`, etc.) and assigns it automatically — but only if no custom template has already been set on that post. This avoids overwriting deliberate template choices while still giving new content a sensible default.

**Search templates:**

Search results use `/?lang=de&s=query` URLs. The router intercepts the `get_block_templates` filter and swaps in `search-de` (or the appropriate language variant) when one exists. Create these templates in the Site Editor just like page templates, saving them with the slug `search-{lang}`.

---

## FSE / Block theme behaviour

- `supportsTemplateMode` is disabled in standard content editors to prevent accidental saves of navigation menus, template parts, or synced patterns
- A **Template** meta box lets editors assign FSE templates (`page-de`, `single-fr`, etc.) without entering the Site Editor
- Language-root URLs (`/de/`, `/fr/`) resolve to the translated front page via TRID
- Template auto-assignment triggers on language change if a matching template slug exists

---

## Third-party compatibility

### Overriding plugin translation strings

Any `.mo` file placed in the `languages/` directory is loaded automatically at `init` priority 1, before plugins load their own translations. This lets you override the strings of any plugin without touching the plugin's own files.

Files must follow the standard WordPress naming convention:

```
{textdomain}-{locale}.mo
```

Examples:

```
languages/
  myplugin-de_DE.mo      ← overrides 'myplugin' strings for the de_DE locale
  myplugin-ca.mo         ← overrides 'myplugin' strings for the bare 'ca' locale
  another-plugin-fr_FR.mo
  …
```

The text domain is derived automatically from the filename, so no code changes are needed when adding a new plugin or a new language — just drop the file in.

> Keep custom `.mo` files under version control. They will be silently ignored by the overridden plugin's own update process, but may still need manual review after upstream string changes.

SEO plugin hreflang output is suppressed automatically when `my_hreflang_mode` is `'custom'`. Confirmed compatible with: **Yoast SEO**, **Rank Math**, **AIOSEO**, **SEOPress**.

---

## Performance

On first activation (and on version bump), `check_db_version()` creates a composite index on `wp_postmeta (meta_key, meta_value(10))` to speed up `_lang` queries across large sites.

Translation lookups are wrapped in WordPress object cache. Caches are invalidated automatically on post save.

---

## Known limitations

### Footnotes are not imported

Gutenberg footnotes cannot be copied reliably from a source page to a translation page in the current version. The underlying reason is architectural: WordPress stores footnotes in two separate locations that must stay in sync — inline `<sup data-fn="UUID">` markers inside `post_content` and a JSON array in the `footnotes` postmeta. Those UUIDs are page-specific, and copying them verbatim causes the block editor's footnotes store to crash or display `*` instead of numbers. Remapping them on import has proven equally fragile because of the block editor's internal state initialisation timing.

**What the import does instead:**

- All footnote markup (`<!-- wp:footnotes /-->` block and inline `<sup data-fn="…">` markers) is stripped from the imported content, leaving clean prose.
- The `footnotes` postmeta on the target page is reset to `[]` so the block editor starts from the same clean state as a freshly created page.
- The source page's footnotes are displayed as a **read-only numbered list** in the **Source Footnotes** meta box on the target page's edit screen.

**Workflow to recreate footnotes after import:**

1. Import the translation as usual via the **Override** button in the Translations meta box.
2. Open the imported page in the block editor.
3. Refer to the **Source Footnotes** meta box (visible in the right-hand column or below the editor) for the original footnote texts.
4. Place the cursor at each point in the text where a footnote belongs and add it through the block editor's standard footnote interface (`Insert → Footnote` or via the Format toolbar).
5. Copy the footnote content from the Source Footnotes meta box into the new footnote field.

This is an interim solution. Proper footnote import will be revisited once the Gutenberg footnotes store initialisation lifecycle is fully understood.

---

## Troubleshooting

### Newly added language returns 404 or is not routed

After adding a new language (by dropping a `.mo` file into `languages/` or installing a WP language pack), the rewrite rules that match URL prefixes like `/it/` or `/pt/` are not yet registered in WordPress's rewrite cache.

**Fix: flush the rewrite rules by re-saving Permalink settings.**

1. Go to **Settings → Permalinks** in the WordPress admin.
2. Click **Save Changes** — no need to change anything, just save.

This forces WordPress to rebuild and cache the rewrite rule set, which now includes the new language prefix. Without this step, requests to `/it/your-page/` will 404 even though everything else is configured correctly.

> This is a one-time step each time a language is added or removed. It is not needed for day-to-day content editing.

---

## Installation

This version is intended as a **must-use plugin** — a drop-in replacement for the procedural version:

1. Replace the `language-router/` folder in `wp-content/mu-plugins/` with this `class-based/` folder (rename it to `language-router/`)
2. No other changes needed — all wrapper functions remain intact

---

## Requirements

- WordPress 6.3 or later (block theme / FSE recommended)
- PHP 8.0 or later (`str_starts_with()` and `str_contains()` are used throughout; typed class properties require 7.4+ but 8.0 is the declared minimum)
- Permalink structure set to anything other than plain

---

## Author

Uli Hake
