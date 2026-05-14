# WordPress Enhancements

A collection of lightweight, dependency-free enhancements for WordPress, built around a simple principle: full control over your site without relying on bloated third-party plugins. Everything here was written for a real production site — [Cal Talaia](https://cal-talaia.cat) — and reflects actual problems solved in the field.

The WordPress ecosystem has drifted toward a business model where basic functionality is locked behind "professional" paywalls. This project is a practical answer to that: small, focused mu-plugins and utilities that do exactly what they need to do and nothing more.

---

## Philosophy

Minimal third-party dependencies. Full control over the code. Simple solutions to real problems. Respect for WordPress architecture. No unnecessary feature bloat.

The code is not perfect — it was developed iteratively with AI assistance and shaped by the constraints of a live site running on WordPress 6.x and PHP 8.3. It works for our needs and may serve as a solid starting point for yours.

---

## Plugin Loader

The entry point for the entire enhancement suite. Rather than scattering small snippets across a theme's `functions.php` or maintaining a dozen micro-plugins, the Plugin Loader provides a single, organized entry point that loads each enhancement on demand.

Enabling or disabling a feature is as simple as commenting out a single line. The loader keeps the codebase navigable and avoids polluting the WordPress plugin directory with internal utilities.

---

## Gutenberg Enhancements

### Lightbox for Carousel Slider Block

The native Carousel Slider Block (by Virgildia) does not support a unified lightbox experience — images open in isolation rather than as a browsable sequence. This enhancement adds a proper overlay lightbox with forward/backward keyboard navigation across all images in a carousel, matching the behavior that the Gallery block provides natively.

Visual control over the lightbox background (color, opacity, and related parameters) is also exposed via CSS, since Gutenberg provides no native way to configure it.

### Inline Images and Custom Fonts in Paragraphs

Gutenberg does not allow assigning a different font to a text fragment within a paragraph, nor does it support placing an image inline within a text flow — a limitation that becomes particularly painful when using the Footnotes block, where an inline image breaks the block entirely.

This enhancement works around both constraints without touching the block structure. Fonts are applied via inline styles; images are rendered as CSS backgrounds on a controlled inline element, with full lightbox support included.

### Accordion Auto-Scroll

When a user opens an item in the native WordPress Accordion block, the opened content may appear partially or fully off-screen — especially on mobile or with sticky headers. This enhancement automatically scrolls the opened item header into view after it opens.

It uses a `MutationObserver` to detect state changes driven by the Interactivity API (`data-wp-*` / `is-open`), handles `openByDefault` items correctly on page load (no unwanted jump), and skips scrolling entirely when the header is already visible. Frontend-only, dependency-free.

---

## Multilingual Support

### Language Router and Language Switcher (LSFLR)

A complete, code-driven multilingual system for single-site WordPress installations. Built for small to medium sites that need genuine language control without WPML, Polylang, or a multisite setup.

The router provides language-based URL routing (`/de/`, `/fr/`, `/ca/`, etc.), per-post translation linking via a TRID system, early locale switching (critical for plugins like Vik Booking that behave differently depending on the active locale), and SEO-ready output including canonical tags and `hreflang` attributes.

It integrates fully with WordPress Full Site Editing: rather than duplicating themes or fighting the template hierarchy, the router hooks into the template resolution layer and swaps `wp_template` and `wp_template_part` entries dynamically per language. A search template named `search-de` is automatically loaded for German visitors; a `header-fr` template part is picked up for French — no code changes required once the naming convention is in place.

One notable capability: the router allows running WordPress internally in `en_US` (useful for admin-facing plugins that behave better under that locale) while serving the site's primary content in another language — Catalan in our case — and routing English to a `/en/` subfolder. This is the kind of edge case that large multilingual plugins handle poorly or not at all.

A detailed CHANGELOG is maintained in the plugin source, reflecting the complexity of language routing as a domain.

### MU Meta Description for SEO

A minimal mu-plugin that gives editors direct control over SEO meta descriptions without pulling in a full SEO framework. A custom field is added to each post and page edit screen; if left empty, the plugin generates a fallback from the excerpt or page content automatically.

Outputs `meta description`, Open Graph description, and Twitter description tags. Manually written descriptions are preserved exactly as entered; auto-generated fallbacks are truncated to a sensible length using multibyte-safe string functions. No frontend dependencies, no options pages, no bloat.

---

## WPEnhance AI

An AI assistance framework built as an mu-plugin, adding an editorial panel to the Gutenberg meta box with three AI-powered features.

**Meta Description** generates a ready-to-use SEO meta description from the post content and title, using the post's language metadata to write directly in the correct language.

**Excerpt Generator** produces a concise post excerpt, again language-aware, without the editor having to summarize manually.

**Translation** translates the full post or page content to a target language while preserving all WordPress block comments, HTML structure, shortcodes, and element attributes — only visible text is translated. Native WordPress footnotes (`_footnotes` post meta) are translated in the same API call to keep terminology consistent, and applied to the editor in a single click. Translations are never applied automatically: results appear in a review panel where the editor can inspect, copy, or apply the output before anything changes.

All three features support result caching: a SHA-256 hash of the inputs detects whether the content has changed since the last generation. Cached results are marked clearly with a badge in the UI; a refresh control is available to force a new API call when needed.

The plugin supports three AI providers — Anthropic Claude, OpenAI, and Google Gemini — selectable from a Settings page. API keys are stored encrypted (AES-256-CBC, keyed from WordPress auth salts) so plaintext credentials never touch the database. Existing setups using environment variables or PHP constants continue to work without changes.

Short-output features (meta description, excerpt) use `claude-haiku-4-5-20251001` for speed and cost; translation uses `claude-sonnet-4-6` at low temperature for higher fidelity on long multilingual content.

---

## SVG Icon System

A lightweight alternative to icon font libraries like Font Awesome. Rather than loading an entire icon font via CSS — with its associated payload and render overhead — this module loads a custom SVG sprite file containing only the icons the site actually uses.

A small Node.js build script (`build-icons.js`, under `Helpers/`) generates the sprite from a curated `icons-list.json`. Icons extracted from Font Awesome retain their original attribution in the SVG source. The resulting icons work as direct inline elements and can serve as links without additional wrappers.

---

## Disclaimer

Everything here was developed with AI assistance but required extensive review, correction, and time to reach a reliable result. The code works for our specific setup and may not behave identically in other environments. It may serve as a base for more complex or commercial plugins.

No attribution required. We are not interested in taking on additional work or responsibility related to this code. It's all yours.

**Environment:** WordPress 6.x · PHP 8.3
