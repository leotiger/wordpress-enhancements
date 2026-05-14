# WordPress Enhancements — MU Plugin Suite

A collection of lightweight, dependency-free enhancements for WordPress, built around a simple principle: full control over your site without relying on bloated third-party plugins. Everything here was written for a real production site — [Cal Talaia](https://cal-talaia.cat) — and reflects actual problems solved in the field.

The WordPress ecosystem has drifted toward a business model where basic functionality is locked behind "professional" paywalls. This project is a practical answer to that: small, focused mu-plugins and utilities that do exactly what they need to do and nothing more.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Directory Structure](#directory-structure)
- [Plugin Loader](#plugin-loader)
- [Gutenberg Enhancements](#gutenberg-enhancements)
  - [Lightbox for Carousel Slider Block](#lightbox-for-carousel-slider-block)
  - [Inline Images and Custom Fonts in Paragraphs](#inline-images-and-custom-fonts-in-paragraphs)
  - [Accordion Auto-Scroll](#accordion-auto-scroll)
- [Multilingual Support](#multilingual-support)
  - [Language Router and Language Switcher (LSFLR)](#language-router-and-language-switcher-lsflr)
  - [MU Meta Description for SEO](#mu-meta-description-for-seo)
- [WPEnhance AI](#wpenhance-ai)
- [SVG Icon System](#svg-icon-system)
- [Philosophy](#philosophy)
- [License](#license)
- [Disclaimer](#disclaimer)

---

## Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.x (tested on 6.4–6.7) |
| PHP       | 8.2 or higher (developed on 8.3) |
| Node.js   | 18+ (SVG build script only) |

No Composer dependencies. No npm runtime dependencies. The Node.js requirement applies only to the optional SVG sprite build step.

---

## Installation

### As a single MU plugin (recommended)

1. Copy the plugin loader file and its companion directories into your `wp-content/mu-plugins/` folder:

```
wp-content/
└── mu-plugins/
    ├── plugin-loader.php        ← entry point
    ├── gutenberg/
    ├── multilingual/
    ├── ai/
    ├── svg/
    └── Helpers/
```

2. WordPress loads every file in `mu-plugins/` automatically — no activation step needed.
3. Open `plugin-loader.php` and comment out any module you do not need (see [Plugin Loader](#plugin-loader)).

### As individual snippets

Each enhancement is self-contained. If you only need one feature, copy the relevant file into `mu-plugins/` on its own. Just be aware of the few cross-module dependencies noted in each section below.

---

## Directory Structure

```
wordpress-enhancements/
│
├── plugin-loader.php                   # Entry point — require_once each module
│
├── gutenberg/
│   ├── lightbox-carousel.php           # Lightbox for Carousel Slider Block
│   ├── lightbox-carousel.js
│   ├── inline-content.php              # Inline images & custom fonts
│   ├── inline-content.js
│   ├── accordion-autoscroll.php        # Accordion auto-scroll
│   └── accordion-autoscroll.js
│
├── multilingual/
│   ├── lsflr.php                       # Language Router (core)
│   ├── lsflr-switcher.php              # Language Switcher widget
│   └── mu-meta-description.php         # SEO meta description
│
├── ai/
│   ├── wpenhance-ai.php                # AI panel — main plugin file
│   ├── wpenhance-ai-settings.php       # Settings page (API keys, provider)
│   ├── wpenhance-ai-api.php            # Provider abstraction layer
│   └── wpenhance-ai-editor.js          # Gutenberg sidebar panel
│
├── svg/
│   └── svg-icons.php                   # SVG sprite loader
│
└── Helpers/
    ├── build-icons.js                  # Node.js sprite builder
    └── icons-list.json                 # Curated icon list
```

---

## Plugin Loader

**File:** `plugin-loader.php`

The entry point for the entire enhancement suite. Rather than scattering small snippets across a theme's `functions.php` or maintaining a dozen micro-plugins, the Plugin Loader provides a single, organized entry point that loads each enhancement on demand.

### Usage

Enable or disable any module by commenting out the corresponding `require_once` line:

```php
<?php
/**
 * WordPress Enhancements — Plugin Loader
 * Drop this file (and the companion directories) into wp-content/mu-plugins/.
 */

$base = __DIR__;

// Gutenberg
require_once $base . '/gutenberg/lightbox-carousel.php';
require_once $base . '/gutenberg/inline-content.php';
require_once $base . '/gutenberg/accordion-autoscroll.php';

// Multilingual
require_once $base . '/multilingual/lsflr.php';
require_once $base . '/multilingual/lsflr-switcher.php';
require_once $base . '/multilingual/mu-meta-description.php';

// AI assistance
require_once $base . '/ai/wpenhance-ai.php';

// SVG icons
require_once $base . '/svg/svg-icons.php';
```

---

## Gutenberg Enhancements

### Lightbox for Carousel Slider Block

**File:** `gutenberg/lightbox-carousel.php` + `lightbox-carousel.js`

The native Carousel Slider Block (by Virgildia) does not support a unified lightbox experience — images open in isolation rather than as a browsable sequence. This enhancement adds a proper overlay lightbox with forward/backward keyboard navigation across all images in a carousel.

#### Features

- Unified lightbox overlay spanning all images in a carousel
- Keyboard navigation: `←` / `→` to browse, `Escape` to close
- CSS variables for full visual control over the lightbox overlay
- Matches the browsable-sequence behavior of the native Gallery block

#### CSS Customisation

The lightbox background, opacity, and transition are exposed as CSS custom properties on the overlay element. Override in your theme stylesheet:

```css
.mu-lightbox-overlay {
    --lightbox-bg: #000;
    --lightbox-opacity: 0.92;
    --lightbox-transition: opacity 0.25s ease;
}
```

#### JavaScript API

The lightbox is initialised automatically on `DOMContentLoaded`. It attaches to every `.wp-block-carousel` container found on the page. No manual initialisation is required.

---

### Inline Images and Custom Fonts in Paragraphs

**File:** `gutenberg/inline-content.php` + `inline-content.js`

Gutenberg does not allow assigning a different font to a text fragment within a paragraph, nor does it support placing an image inline within a text flow — a limitation that becomes particularly painful in the Footnotes block.

#### Features

- Per-fragment font override via inline styles (survives block serialisation)
- Inline images rendered as CSS backgrounds on a controlled inline element
- Inline images are lightbox-enabled (reuses the lightbox module)
- Compatible with the Footnotes block (does not break block structure)

#### Dependencies

This module uses the lightbox overlay initialised by `lightbox-carousel.php`. Load that module first (or load both — the loader handles ordering).

#### Usage

Inline images are inserted by wrapping a `<span>` with the `mu-inline-img` class and setting the image URL as a CSS background:

```html
<span class="mu-inline-img" 
      style="--img-src: url('/wp-content/uploads/icon.png')" 
      data-lightbox-src="/wp-content/uploads/icon.png">
</span>
```

Custom fonts are applied with a `data-mu-font` attribute on any inline element:

```html
This sentence contains <span data-mu-font="Georgia, serif">a different typeface</span>.
```

---

### Accordion Auto-Scroll

**File:** `gutenberg/accordion-autoscroll.php` + `accordion-autoscroll.js`

When a user opens an item in the native WordPress Accordion block, the opened content may appear partially or fully off-screen — especially on mobile or with sticky headers.

#### Features

- Automatically scrolls the opened item header into view after it opens
- Uses `MutationObserver` to detect `data-wp-*` / `is-open` state changes driven by the Interactivity API
- Handles `openByDefault` items correctly on page load (no unwanted scroll jump)
- Skips scrolling when the header is already fully visible in the viewport
- Frontend-only, no dependencies, ~1 KB minified

#### Configuration

The scroll offset (useful when a sticky header is present) can be tuned by adding a `data` attribute to the accordion container, or by overriding the default in the PHP file:

```php
// gutenberg/accordion-autoscroll.php
define( 'MU_ACCORDION_SCROLL_OFFSET', 80 ); // px — adjust to match sticky header height
```

---

## Multilingual Support

### Language Router and Language Switcher (LSFLR)

**Files:** `multilingual/lsflr.php`, `multilingual/lsflr-switcher.php`

A complete, code-driven multilingual system for single-site WordPress installations. Built for small to medium sites that need genuine language control without WPML, Polylang, or a multisite setup.

#### Features

| Feature | Details |
|---------|---------|
| URL routing | Language prefix per locale: `/de/`, `/fr/`, `/ca/`, etc. |
| Translation linking | TRID system: each post stores references to its translations |
| Early locale switching | Locale is set before plugins initialise — critical for locale-sensitive plugins (e.g. Vik Booking) |
| SEO output | Canonical tags and `hreflang` attributes on every page |
| FSE integration | Auto-resolves `wp_template` / `wp_template_part` by language suffix (`search-de`, `header-fr`) |
| Admin/content locale split | WordPress admin can run in `en_US` while the site serves content in another primary language |

#### TRID — Translation Reference ID

Each post stores its TRID in post meta. Posts sharing a TRID are considered translations of each other. The router uses this to build the `hreflang` map and power the Language Switcher.

**Setting a TRID on a post:**

```php
update_post_meta( $post_id, '_lsflr_trid', 'my-unique-trid-string' );
update_post_meta( $post_id, '_lsflr_lang', 'de' ); // the language of this post
```

#### FSE Template Resolution

Name your FSE templates and template parts with a language suffix:

```
templates/
├── search.html          # default / fallback
├── search-de.html       # loaded for German visitors
└── search-ca.html       # loaded for Catalan visitors

parts/
├── header.html
├── header-fr.html       # loaded for French visitors
└── header-de.html       # loaded for German visitors
```

No code changes are needed once the naming convention is established.

#### Admin/Content Locale Split

To run WordPress admin in `en_US` while serving a non-English primary language, set the following in `wp-config.php`:

```php
define( 'WPLANG', '' ); // WordPress admin locale: en_US
```

Then configure LSFLR with your primary content language and its URL behaviour:

```php
// In lsflr.php or a config file loaded before it:
define( 'LSFLR_DEFAULT_LANG', 'ca' );   // primary language served at /
define( 'LSFLR_ADMIN_LANG',   'en' );   // admin panel language
```

#### Language Switcher

The switcher renders a list of available language links for the current page, using TRID metadata to find translations. Include it in any template:

```php
<?php if ( function_exists( 'lsflr_language_switcher' ) ) : ?>
    <?php lsflr_language_switcher(); ?>
<?php endif; ?>
```

Or use it as a Gutenberg shortcode block:

```
[lsflr_switcher]
```

#### Hooks and Filters

| Hook | Type | Description |
|------|------|-------------|
| `lsflr_supported_languages` | filter | Array of language codes the router handles |
| `lsflr_default_language` | filter | The language served at the root URL |
| `lsflr_hreflang_output` | filter | Raw `hreflang` tag HTML before it is echoed |
| `lsflr_locale_for_lang` | filter | Map a language code to a full WordPress locale string |
| `lsflr_before_locale_switch` | action | Fires immediately before the locale is changed |
| `lsflr_after_locale_switch` | action | Fires immediately after the locale is changed |

**Example — registering supported languages:**

```php
add_filter( 'lsflr_supported_languages', function ( array $langs ): array {
    return [ 'ca', 'en', 'de', 'fr' ];
} );
```

---

### MU Meta Description for SEO

**File:** `multilingual/mu-meta-description.php`

A minimal mu-plugin that gives editors direct control over SEO meta descriptions without pulling in a full SEO framework.

#### Features

- Custom meta description field on every post and page edit screen
- Auto-generates a fallback from the excerpt or post content when the field is left empty
- Outputs `<meta name="description">`, Open Graph `og:description`, and Twitter `twitter:description`
- Multibyte-safe truncation (`mb_substr`) — safe for CJK, Arabic, Catalan, etc.
- No frontend dependencies, no options pages, no bloat

#### Output

```html
<meta name="description" content="Your description here." />
<meta property="og:description" content="Your description here." />
<meta name="twitter:description" content="Your description here." />
```

#### Truncation length

The default fallback truncation length is 155 characters. Override it with a constant in `wp-config.php`:

```php
define( 'MU_META_DESC_LENGTH', 160 );
```

#### Post meta key

The manually entered description is stored in post meta. You can pre-populate it programmatically:

```php
update_post_meta( $post_id, '_mu_meta_description', 'Hand-crafted description.' );
```

---

## WPEnhance AI

**Files:** `ai/wpenhance-ai.php`, `ai/wpenhance-ai-settings.php`, `ai/wpenhance-ai-api.php`, `ai/wpenhance-ai-editor.js`

An AI assistance framework built as an mu-plugin, adding an editorial panel to the Gutenberg meta box area with three AI-powered features.

### Features

#### Meta Description Generator

Generates a ready-to-use SEO meta description from the post content and title. Language-aware: uses the post's `_lsflr_lang` metadata to write the description directly in the correct language.

#### Excerpt Generator

Produces a concise post excerpt, language-aware, without the editor having to summarise manually.

#### Translation

Translates the full post or page content to a target language while preserving:

- WordPress block comments (`<!-- wp:paragraph -->`)
- HTML structure and attributes
- Shortcodes
- Native WordPress footnotes (`_footnotes` post meta — translated in the same API call for terminology consistency)

Translations are **never applied automatically**. Results appear in a review panel where the editor can inspect, copy, or apply the output.

### Supported AI Providers

| Provider | Models used |
|----------|-------------|
| Anthropic Claude | `claude-haiku-4-5-20251001` (short output), `claude-sonnet-4-6` (translation) |
| OpenAI | `gpt-4o-mini` (short output), `gpt-4o` (translation) |
| Google Gemini | `gemini-1.5-flash` (short output), `gemini-1.5-pro` (translation) |

The active provider is selected from **Settings → WPEnhance AI**.

### API Key Storage

API keys are stored encrypted in the WordPress database using **AES-256-CBC**, keyed from WordPress auth salts. Plaintext credentials never touch the database. Existing setups using environment variables or PHP constants continue to work:

```php
// wp-config.php — alternative to storing keys via the settings page
define( 'WPAI_ANTHROPIC_KEY', 'sk-ant-...' );
define( 'WPAI_OPENAI_KEY',    'sk-...' );
define( 'WPAI_GEMINI_KEY',    'AIza...' );
```

### Result Caching

All three features support result caching. A SHA-256 hash of the inputs (content + title + language) determines whether the content has changed since the last generation. Cached results are marked with a badge in the UI; a Refresh button forces a new API call.

### Hooks and Filters

| Hook | Type | Description |
|------|------|-------------|
| `wpai_provider` | filter | Override the active AI provider at runtime |
| `wpai_prompt_meta_description` | filter | Modify the meta description generation prompt |
| `wpai_prompt_excerpt` | filter | Modify the excerpt generation prompt |
| `wpai_prompt_translation` | filter | Modify the translation prompt |
| `wpai_before_api_call` | action | Fires before any API request is dispatched |
| `wpai_after_api_call` | action | Fires after a successful API response is received |
| `wpai_cache_ttl` | filter | Cache lifetime in seconds (default: no expiry until content changes) |

**Example — switching provider programmatically:**

```php
add_filter( 'wpai_provider', function ( string $provider ): string {
    // Force Anthropic for all translation requests
    return 'anthropic';
} );
```

---

## SVG Icon System

**Files:** `svg/svg-icons.php`, `Helpers/build-icons.js`, `Helpers/icons-list.json`

A lightweight alternative to icon font libraries like Font Awesome. Rather than loading an entire icon font via CSS, this module loads a custom SVG sprite containing only the icons the site actually uses.

### Building the Sprite

1. Edit `Helpers/icons-list.json` to list the icon names you need:

```json
[
  "arrow-right",
  "calendar",
  "phone",
  "envelope",
  "map-marker-alt"
]
```

2. Run the build script from the `Helpers/` directory:

```bash
node build-icons.js
```

The script generates `svg/sprite.svg`, which is inlined into the page `<body>` by `svg-icons.php`.

### Using Icons in Templates

```php
// Output a single icon (renders an <svg> element referencing the sprite)
mu_svg_icon( 'arrow-right' );

// With a custom class
mu_svg_icon( 'calendar', 'icon icon--sm' );
```

In HTML/Twig/template files:

```html
<svg class="icon" aria-hidden="true">
    <use href="#icon-arrow-right"></use>
</svg>
```

### Using Icons as Links

Icons can be used as direct link elements without additional wrappers:

```html
<a href="tel:+34123456789" class="icon-link" aria-label="Call us">
    <svg class="icon"><use href="#icon-phone"></use></svg>
</a>
```

### Attribution

Icons extracted from Font Awesome retain their original MIT licence attribution in the SVG source comments.

---

## Philosophy

Minimal third-party dependencies. Full control over the code. Simple solutions to real problems. Respect for WordPress architecture. No unnecessary feature bloat.

The code is not perfect — it was developed iteratively with AI assistance and shaped by the constraints of a live site running on WordPress 6.x and PHP 8.3. It works for our needs and may serve as a solid starting point for yours.

---

## License

This project uses a two-tier licensing model depending on the module:

**GNU General Public License v3.0 (GPL-3.0)**
- `ai/` — WPEnhance AI
- `multilingual/` — Language Router and Language Switcher (LSFLR)

These modules are distributed under the GNU GPL v3. You are free to use, modify, and distribute them, but any derivative work must also be released under the GPL v3, and you must include the original copyright notice and a copy of the licence. See the `LICENSE` file in the respective directory.

**MIT License**
- `gutenberg/` — Lightbox for Carousel Slider Block, Inline Images and Custom Fonts, Accordion Auto-Scroll
- `svg/` — SVG Icon System
- `Helpers/` — build scripts and utilities
- `plugin-loader.php`

These modules are distributed under the MIT License. You are free to use, modify, and distribute them in any project — including commercial ones — provided that the original copyright notice and licence text are retained in all copies or substantial portions of the software. See the `LICENSE` file in the respective directory.

---

## Troubleshooting

### Modules not loading

- Confirm `plugin-loader.php` is directly inside `wp-content/mu-plugins/` (not in a subdirectory).
- PHP errors in any `require_once` will silently prevent subsequent modules from loading. Check `WP_DEBUG_LOG`.

### Language Router — wrong locale served

- Verify `_lsflr_lang` post meta is set on the relevant post.
- Check that `LSFLR_DEFAULT_LANG` matches the language you expect at the root URL.
- If a plugin (e.g. Vik Booking) ignores the locale, confirm LSFLR is loaded before that plugin initialises. MU plugins load before regular plugins, but order within `mu-plugins/` is alphabetical — rename `plugin-loader.php` to `000-plugin-loader.php` if needed.

### WPEnhance AI — API key not working

- Keys entered via the Settings page are stored encrypted. If the auth salts change (e.g. after a security incident), re-enter the key.
- Keys defined as PHP constants (`WPAI_ANTHROPIC_KEY`, etc.) always take precedence over the database value.
- Enable `WP_DEBUG` and check the browser console and PHP error log for API error responses.

### SVG icons not displaying

- Run `node build-icons.js` after editing `icons-list.json`.
- Confirm `svg/sprite.svg` exists and is readable by the web server.
- Check for Content Security Policy headers that block inline SVG.

---

## Disclaimer

Everything here was developed with AI assistance but required extensive review, correction, and time to reach a reliable result. The code works for our specific setup and may not behave identically in other environments. It may serve as a base for more complex or commercial plugins.

Attribution requirements vary by module — see the [License](#license) section above. We are not interested in taking on additional work or responsibility related to this code.

**Environment:** WordPress 6.x · PHP 8.3 · Node.js 18+ (SVG build only)
