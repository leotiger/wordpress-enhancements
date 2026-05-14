# Cal Talaia Gutenberg Enhancements

**Author:** Uli Hake  
**Version:** 1.2.0  
**Type:** WordPress MU-Plugin  

A lightweight must-use plugin that extends the WordPress block editor (Gutenberg) with two inline rich-text tools — an inline font-family selector and an inline image inserter with lightbox support — plus the supporting front-end scripts and editor styles.

---

## Features

### 1. Inline Font Selector

Adds a **Font Family** dropdown to the block toolbar's format controls. When text is selected in any rich-text block (paragraph, heading, list, etc.), the dropdown lets you apply any font registered in the active theme's `theme.json` as an inline `<span>` with the appropriate `has-{slug}-font-family` utility class.

- Reads font families dynamically from the block editor store (`core/block-editor` → `__experimentalFeatures.typography.fontFamilies`) so it always reflects what the active theme provides — no hardcoding required.
- Wraps the selected text in `<span class="has-inline-font-family has-{slug}-font-family">`.
- Choosing **Default** removes the inline font wrapper entirely.

### 2. Inline Image Tool

Adds an **Inline Image** button (image icon) to the block toolbar's format controls. Clicking it opens a popover with the following controls:

| Control | Description |
|---|---|
| **Width (px)** | The rendered width of the inline image in pixels. |
| **Height (px)** | The rendered height of the inline image in pixels. |
| **Enable Lightbox** | When checked, clicking the image on the front-end opens it fullscreen. |
| **Alignment** | Float the image left, center, or right within the text flow. |
| **Insert Image** | Opens the WordPress Media Library to pick a new image and insert it at the cursor. |
| **Update Current** | Re-applies the current popover settings (size, alignment, lightbox) to the already-selected inline image without replacing the image URL. |
| **Replace Image** | Opens the Media Library to swap the image URL of the currently selected inline image. |

Images are rendered as a pair of nested `<span>` elements. The outer `inline-image-wrapper` holds layout metadata as `data-*` attributes; the inner `inline-image` span renders the image as a CSS `background-image`.

**Clicking an inline image in the editor** selects it, pre-fills the popover with its saved settings, and reopens the controls — so you can edit any previously inserted image without re-inserting it.

### 3. Front-end Lightbox

When a visitor clicks an inline image that has **lightbox enabled**, a full-screen overlay fades in showing the full image. The overlay can be dismissed by clicking anywhere on it or pressing `Escape`. The open/close transition is a CSS opacity fade (0.25 s).

---

## File Structure

```
gutenberg-enhancements/
├── gutenberg-enhancements.php   # Plugin entry point — all PHP and inline JS
├── gutenberg-enhancements.css   # Editor + front-end styles
├── CHANGELOG.md                 # Version history
└── README.md                    # This file
```

---

## How It Works

### PHP Side

The plugin registers three WordPress hooks:

| Hook | Purpose |
|---|---|
| `after_setup_theme` | Calls `add_theme_support('editor-styles')` and registers the CSS file as an editor stylesheet via `add_editor_style()`. |
| `enqueue_block_editor_assets` | Enqueues `wp-media` and registers an inline JavaScript bundle (`gb-enhance-inline-tools`) that provides both the font selector and the inline image tool. |
| `wp_enqueue_scripts` | Registers an inline JavaScript bundle (`gb-background-lightbox`) that powers the front-end lightbox click handler. |

Both JS bundles are registered with `wp_register_script( ..., false, ... )` (no external file) and then attached via `wp_add_inline_script()`, keeping the plugin self-contained in a single PHP file.

### JavaScript Side (Editor)

The editor bundle is an IIFE that uses the global `window.wp` APIs:

- **`wp.richText`** — `registerFormatType`, `toggleFormat`, `insert`, `create`, `remove`
- **`wp.blockEditor`** — `BlockFormatControls`
- **`wp.element`** — `createElement`, `Fragment`, `useState`, `useEffect`
- **`wp.components`** — `DropdownMenu`, `Button`, `TextControl`, `Popover`
- **`wp.data`** — subscribes to block editor settings to hydrate the font list

The inline image tool locates the editor iframe (`iframe[name="editor-canvas"]`) to bind click events and inspect the DOM for data attributes when editing an existing image.

### CSS

The stylesheet handles two concerns:

- **Editor feedback:** `.inline-image-wrapper` gets a blue outline on hover and when selected (`.is-selected`), giving visual confirmation of which image is active.
- **Lightbox animation:** `.caltalaia-lightbox` uses an opacity transition with `.is-visible` (open) and `.is-closing` (close) class toggles driven by the front-end JS.

---

## Installation

This plugin is designed to be placed in WordPress's `mu-plugins` directory. MU-plugins load automatically — no activation required.

1. Copy the entire `gutenberg-enhancements/` folder to:
   ```
   wp-content/mu-plugins/gutenberg-enhancements/
   ```
2. The plugin loader file must sit at the root of `mu-plugins/`. If your setup does not auto-load subdirectories, add a single-line loader file:
   ```php
   // wp-content/mu-plugins/gutenberg-enhancements.php
   require WPMU_PLUGIN_DIR . '/gutenberg-enhancements/gutenberg-enhancements.php';
   ```

No configuration, no admin settings page, no database writes. The plugin is active as soon as the files are in place.

---

## Requirements

| Requirement | Notes |
|---|---|
| WordPress | 6.0 or later recommended (block editor, `wp.richText` API) |
| Theme | Must use `theme.json` and declare font families under `typography.fontFamilies` for the font selector to populate. The inline image tool and lightbox work with any theme. |
| PHP | 7.4 or later (uses `<<<'JS'` nowdoc syntax) |

---

## Usage

### Applying an inline font

1. Open any post or page in the block editor.
2. Select some text inside a paragraph, heading, or similar rich-text block.
3. Click the **Font Family** dropdown (text-colour icon) in the toolbar.
4. Choose a font from the list. The text is wrapped in the appropriate span class.

### Inserting an inline image

1. Place the cursor (or make a selection) where you want the image in a rich-text block.
2. Click the **Inline Image** button (image icon) in the toolbar.
3. Adjust width, height, alignment, and lightbox settings in the popover.
4. Click **Insert Image** to open the Media Library and choose an image.

### Editing an existing inline image

1. Click the inline image directly in the editor canvas.
2. The popover reopens pre-filled with the image's current settings.
3. Adjust settings and click **Update Current** — or click **Replace Image** to swap the image source.

### Front-end lightbox

No additional configuration is needed. Any inline image saved with **Enable Lightbox** checked will automatically be clickable on the front-end. Visitors click the image to open it fullscreen and click anywhere (or press `Escape`) to close it.

---

## Output HTML

An inline image inserted by this plugin renders in the saved post content as:

```html
<span
  class="inline-image-wrapper is-block [has-lightbox]"
  data-inline-id="img-{timestamp}"
  data-width="{px}"
  data-height="{px}"
  data-align="left|center|right"
  data-lightbox="true|false"
  contenteditable="false"
>
  <span
    class="inline-image is-fixed is-left|is-center|is-right"
    style="background-image:url({url});width:{px}px;height:{px}px;"
    data-bg="{url}"
  ></span>
</span>
```

The `data-bg` attribute (added in v1.2.0) is the canonical image URL used by the lightbox and update logic. The inline `background-image` style is also present for direct rendering without JavaScript.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.

---

## License

This plugin is proprietary to Cal Talaia. All rights reserved.
