# WordPress Enhancements — MU Plugin Suite

A collection of lightweight, dependency-free enhancements for WordPress, built around a simple principle: full control over your site without relying on bloated third-party plugins. Everything here was written for a real production site — [Cal Talaia](https://cal-talaia.cat) — and reflects actual problems solved in the field.

The WordPress ecosystem has drifted toward a business model where basic functionality is locked behind "professional" paywalls. This project is a practical answer to that: small, focused mu-plugins and utilities that do exactly what they need to do and nothing more.

> **Note:** The multilingual and AI features — Language Router (LSFLR), Language Switcher, MU Meta Description, and WPEnhance AI — have been extracted into a dedicated plugin, **[Lingua Forge](https://github.com/leotiger/lingua-forge)**. This repository now focuses exclusively on Gutenberg enhancements and the SVG icon system.

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

All modules in this repository are distributed under the **MIT License**:

- `gutenberg/` — Lightbox for Carousel Slider Block, Inline Images and Custom Fonts, Accordion Auto-Scroll
- `svg/` — SVG Icon System
- `Helpers/` — build scripts and utilities
- `plugin-loader.php`

You are free to use, modify, and distribute them in any project — including commercial ones — provided that the original copyright notice and licence text are retained in all copies or substantial portions of the software. See the `LICENSE` file for details.

The multilingual and AI modules (Language Router, Language Switcher, MU Meta Description, WPEnhance AI) have been moved to **[Lingua Forge](https://github.com/leotiger/lingua-forge)**, which is licensed under GPL-3.0.

---

## Troubleshooting

### Modules not loading

- Confirm `plugin-loader.php` is directly inside `wp-content/mu-plugins/` (not in a subdirectory).
- PHP errors in any `require_once` will silently prevent subsequent modules from loading. Check `WP_DEBUG_LOG`.

### SVG icons not displaying

- Run `node build-icons.js` after editing `icons-list.json`.
- Confirm `svg/sprite.svg` exists and is readable by the web server.
- Check for Content Security Policy headers that block inline SVG.

---

## Disclaimer

Everything here was developed with AI assistance but required extensive review, correction, and time to reach a reliable result. The code works for our specific setup and may not behave identically in other environments. It may serve as a base for more complex or commercial plugins.

Attribution requirements vary by module — see the [License](#license) section above. We are not interested in taking on additional work or responsibility related to this code.

**Environment:** WordPress 6.x · PHP 8.3 · Node.js 18+ (SVG build only)

> For multilingual support and AI-powered editorial tools, see **[Lingua Forge](https://github.com/leotiger/lingua-forge)**.
