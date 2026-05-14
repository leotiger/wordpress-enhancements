# Language Router for WordPress — Class-based refactor (v1.2.0)

A drop-in replacement for the procedural Language Router plugin, rewritten as two collaborating classes. All `my_*` wrapper functions are preserved so existing theme code keeps working without changes.

---

## File structure

```
class-based/
├── language-router.php          ← Entry point: boots classes, defines MY_LANG, registers wrappers
├── includes/
│   ├── class-language-router.php   ← Core singleton: routing, translation, admin, SEO, search
│   └── class-lsflr-switcher.php    ← Language switcher block (injected dependency)
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

### Entry point (`language-router.php`)

Boots both objects and then declares thin wrapper functions that delegate to the instances:

```php
$language_router = Language_Router::get_instance();
$lsflr_switcher  = new LSFLR_Switcher( $language_router );
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

Place `.mo` files in the `languages/` directory (one level up — the plugin loads from `WPMU_PLUGIN_DIR/language-router/languages/`):

```
languages/
  vikbooking-ca.mo
  vikbooking-de_DE.mo
  complianz-gdpr-ca.po
  …
```

SEO plugin hreflang output is suppressed automatically when `my_hreflang_mode` is `'custom'`. Confirmed compatible with: **Vik Booking**, **Complianz GDPR**, **Yoast SEO**, **Rank Math**, **AIOSEO**, **SEOPress**.

---

## Performance

On first activation (and on version bump), `check_db_version()` creates a composite index on `wp_postmeta (meta_key, meta_value(10))` to speed up `_lang` queries across large sites.

Translation lookups are wrapped in WordPress object cache. Caches are invalidated automatically on post save.

---

## Installation

This version is intended as a **must-use plugin** — a drop-in replacement for the procedural version:

1. Replace the `language-router/` folder in `wp-content/mu-plugins/` with this `class-based/` folder (rename it to `language-router/`)
2. No other changes needed — all wrapper functions remain intact

---

## Requirements

- WordPress 6.3 or later (block theme / FSE recommended)
- PHP 8.0 or later (typed properties and union types are used throughout)
- Permalink structure set to anything other than plain

---

## Author

Uli Hake
