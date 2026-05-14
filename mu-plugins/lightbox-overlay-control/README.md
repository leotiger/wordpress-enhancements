# Lightbox Overlay Carousel for Carousel Slider Block

**Version:** 1.1.3  
**Author:** Uli Hake  
**Type:** WordPress Must-Use (MU) Plugin

---

## What it does

This MU plugin extends the native WordPress / Gutenberg lightbox with two complementary features:

### 1 — Overlay Style Controls (Image & Gallery blocks)

Adds a **Lightbox Overlay** panel to the block inspector sidebar whenever you edit a `core/image` or `core/gallery` block in the block editor. From there you can set:

| Control | Range | Default |
|---------|-------|---------|
| Overlay color | any color | `#000000` |
| Opacity | 0 – 1 (step 0.05) | `1` |
| Blur | 0 – 30 px | `0` |

The chosen values are serialised as a `data-lightbox-overlay` attribute on the saved block markup and picked up on the frontend to style the WP lightbox scrim in real time — without touching WordPress core.

**Gallery → Image inheritance:** when you configure the overlay on a Gallery block, child Image blocks that have no individual settings inherit the gallery-level values automatically (non-destructive — images with their own settings are never overwritten).

### 2 — Carousel Lightbox (Carousel Slider Block)

When an image inside a **Swiper-based Carousel Slider Block** is clicked and the WordPress native lightbox opens, the plugin replaces the default single-image overlay with a full **prev / next carousel** so the visitor can browse the entire slide set without closing the lightbox.

Features:
- Keyboard navigation (← →, Esc) with a single, properly-cleaned-up `keydown` handler
- Touch / swipe navigation on mobile (swipe replaces prev/next buttons; close button stays visible)
- Proactive lazy-load hydration — on first hover over a carousel the plugin eagerly resolves all `data-src` / `data-srcset` attributes (including `<picture>` / `<source>` elements) so images are ready before the lightbox opens
- Accessible markup: `role="dialog"`, `aria-modal`, labelled buttons, decorative `alt=""`

---

## File structure

```
lightbox-overlay-control/
├── lightbox-overlay-control.php   # Plugin entry point; enqueues all assets
├── assets/
│   ├── editor.js                  # Block editor: registers attribute + inspector panel
│   ├── frontend.js                # Frontend: reads data attribute, styles WP scrim
│   ├── carousel-lightbox.js       # Carousel overlay logic (Swiper integration)
│   └── carousel-lightbox.css      # Carousel overlay styles
│   └── style.css                  # Shared frontend styles
└── CHANGELOG                      # Version history
```

---

## How it works — technical overview

### Editor side (`editor.js`)

Uses three Gutenberg JS filter hooks:

- `blocks.registerBlockType` — injects the `lightboxOverlay` attribute into `core/image` and `core/gallery`
- `editor.BlockEdit` (HOC) — renders the inspector panel and hydrates default values on first load
- `blocks.getSaveContent.extraProps` — writes `data-lightbox-overlay="…"` onto the saved HTML element

A `subscribe` listener handles gallery-to-image attribute propagation inside the editor.

### Frontend side (`frontend.js`)

A lightweight IIFE listens for clicks on lightbox-enabled images, reads the `data-lightbox-overlay` JSON, and applies `background-color` (as `rgba`) and a `--loc-blur` CSS custom property to the `.wp-lightbox-overlay` scrim via a `MutationObserver` (debounced at 50 ms).

### Carousel lightbox (`carousel-lightbox.js`)

A `MutationObserver` watches for the WordPress lightbox overlay to appear in the DOM. When triggered inside a Swiper carousel context it:

1. Collects all slide images
2. Renders a custom overlay (`role="dialog"`) with prev / next / close controls
3. Wires keyboard and touch events (single handler, cleaned up on close)

### Lazy-load hydration (inline footer script)

A `pointerenter` listener fires once on hover over any `.wp-lightbox-container` inside a `.swiper`. It immediately promotes all `data-src` / `data-srcset` attributes to real `src` / `srcset` — including `<picture><source>` elements — and clears lazyload CSS classes, ensuring images are network-fetched before the user clicks.

---

## Installation

Because this is an MU plugin the folder must live inside `wp-content/mu-plugins/`. WordPress loads it automatically — no activation step required.

```
wp-content/
└── mu-plugins/
    └── lightbox-overlay-control/
        └── lightbox-overlay-control.php   ← WordPress autoloads this
```

> **Note:** WordPress only autoloads PHP files directly inside `mu-plugins/`, not inside subdirectories. If you need to load it from a subdirectory, add a thin loader file at `mu-plugins/lightbox-overlay-control.php` that includes the main file.

---

## Requirements

- WordPress 6.4+ (native lightbox API)
- A Carousel Slider Block plugin that renders Swiper markup (`.swiper` / `.swiper-slide`) for the carousel lightbox feature
- No additional npm dependencies — all assets are vanilla JS

---

## Changelog

See [CHANGELOG](./CHANGELOG) for the full version history.
