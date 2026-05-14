# Accordion Scroll

A lightweight WordPress must-use plugin that smoothly scrolls the clicked accordion heading into view when a user opens a `core/accordion` item — accounting for sticky headers so the toggle never hides behind the navigation bar.

## How it works

The plugin registers a `MutationObserver` that watches every `.wp-block-accordion-item` element on the page. When an item gains the `is-open` class (i.e. the user opens it), the script measures the current scroll position and the sticky header height, then calls `window.scrollTo` with `behavior: 'smooth'` so the heading lands just below the header.

Two edge cases are handled automatically:

- **Open on load** — items marked as open by default (`openByDefault`) are tracked at `DOMContentLoaded` and skipped on the first mutation, so the page doesn't jump when it first paints.
- **Already visible** — if the heading is already in the upper portion of the viewport (within `visibilityThreshold` of the top), no scroll is triggered.

## Installation

Drop the `accordion-scroll/` folder into your `wp-content/mu-plugins/` directory. WordPress loads mu-plugins automatically — no activation step required.

```
wp-content/
└── mu-plugins/
    └── accordion-scroll/
        ├── accordion-scroll.php
        └── accordion-scroll.js
```

The script is only enqueued on pages that contain a `core/accordion` block, so there is no performance cost on the rest of the site.

## Configuration

The PHP file injects a `window.accordionScrollConfig` object before the script runs. You can override any value by writing to that object earlier in your theme (e.g. in `functions.php` via `wp_add_inline_script`, or directly in a `<script>` tag that precedes this plugin's output):

| Key | Default | Description |
|---|---|---|
| `headerSelector` | `'.site-header'` | CSS selector for the sticky header element used to measure the offset height. |
| `fallbackOffset` | `110` | Pixel offset used when the header element is not found in the DOM. |
| `visibilityThreshold` | `0.3` | Fraction of the viewport height. If the heading is already above this line, no scroll fires. |

**Example override in `functions.php`:**

```php
add_action('wp_enqueue_scripts', function () {
    wp_add_inline_script('accordion-scroll', '
        window.accordionScrollConfig = {
            headerSelector: ".my-custom-header",
            fallbackOffset: 80,
            visibilityThreshold: 0.2,
        };
    ', 'before');
});
```

## Requirements

- WordPress 6.0+ (core accordion block)
- No JavaScript dependencies — vanilla JS only

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Author

Uli Hake
