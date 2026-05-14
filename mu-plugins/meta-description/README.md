# MU Meta Description

A WordPress must-use plugin that gives editors full control over meta descriptions with a smart fallback chain and `<head>` output for standard, Open Graph, and Twitter/X tags.

## Features

- **Meta box** on every public post type — works in both the classic editor and Gutenberg
- **Live character counter** with colour-coded feedback (green 120–160 · amber 161–200 · red outside range)
- **Fallback chain**: custom field → post excerpt → trimmed post content → site tagline
- **REST API / Gutenberg support** via `register_post_meta`
- **Three tags output** per page: `<meta name="description">`, `<meta property="og:description">`, `<meta name="twitter:description">`
- Automatic fallback descriptions are truncated at 190 characters; custom descriptions are never truncated
- Saving an empty field deletes the postmeta row instead of storing a blank string

## Installation

Drop `meta-description.php` into your `wp-content/mu-plugins/` directory. No activation needed — mu-plugins load automatically.

## Usage

Open any post or page in the editor. A **Meta Description** meta box appears below the content area. If you leave it blank, the plugin falls back through the chain described above. Aim for 120–160 characters for best SEO results.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
