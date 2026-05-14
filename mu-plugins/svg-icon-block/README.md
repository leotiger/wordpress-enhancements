# SVG Icon Block

A WordPress must-use plugin that registers a custom Gutenberg block (`custom/svg-icon`) for displaying SVG sprite icons with an optional label and link. Built for Full Site Editing (FSE) themes.

## Features

- **SVG sprite-based rendering** — icons are loaded from a single cached sprite file for fast page loads, with `<link rel="preload">` and `<link rel="prefetch">` injected automatically.
- **Fuzzy multilingual search** — find icons in English, Spanish, or Catalan, with tag support.
- **Link support** — optional URL with `target="_blank"`, `noopener noreferrer`, and a custom `rel` attribute.
- **Three button variants** — Primary, Outline, Ghost.
- **Four icon positions** — Left, Right, Above, Below the label.
- **Size, rotation, and flip controls** — pixel size, 0–360° rotation, horizontal/vertical flip.
- **Color controls** — separate icon color and text/label color pickers.
- **Transient cache** — JSON icon data is cached for 12 hours; flush manually by visiting `?flush_icons_cache` as an admin.

## Requirements

- WordPress 6.3+ (block API v3)
- An FSE-compatible theme that ships an SVG sprite at `assets/icons/icons.svg`

## Installation

Drop the `svg-icon-block/` folder into your `wp-content/mu-plugins/` directory. No activation step is needed — mu-plugins load automatically.

```
wp-content/
└── mu-plugins/
    └── svg-icon-block/
        ├── svg-icon-block.php
        ├── block.json
        ├── block.js
        ├── editor.css
        ├── icons.json
        ├── icons-meta.json
        └── icons-i18n.json
```

## Icon sprite

By default the plugin looks for the sprite at:

```
{stylesheet_directory}/assets/icons/icons.svg
```

Override this with the `sib_sprite_url` filter:

```php
add_filter( 'sib_sprite_url', function ( $url ) {
    return 'https://example.com/path/to/custom-icons.svg';
} );
```

## Flushing the cache

Visit any front-end URL as an administrator with `?flush_icons_cache` appended. Requires `manage_options` capability.

## Block attributes

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `icon` | string | `""` | Icon ID from the sprite |
| `label` | string | `""` | Optional text label |
| `size` | number | `24` | Icon size in pixels |
| `rotation` | number | `0` | Rotation in degrees |
| `justifyContent` | string | `"flex-start"` | Flex justify value for the wrapper |
| `alignItems` | string | `"center"` | Flex align value for the wrapper |
| `url` | string | `""` | Link URL |
| `opensInNewTab` | boolean | `false` | Opens link in a new tab |
| `rel` | string | `""` | Custom `rel` attribute for the link |
| `variant` | string | `"primary"` | `primary`, `outline`, or `ghost` |
| `iconColor` | string | `""` | Icon fill/stroke color |
| `textColor` | string | `""` | Label text color |
| `iconPosition` | string | `"left"` | `left`, `right`, `above`, or `below` |
| `flipH` | boolean | `false` | Flip icon horizontally |
| `flipV` | boolean | `false` | Flip icon vertically |

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Author

Uli Hake
