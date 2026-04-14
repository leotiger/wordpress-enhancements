# Changelog

All notable changes to the Language Router will be documented in this file.

## [Unreleased]

### Added
- New feature X

### Changed
- Improved performance of Y

### Fixed
- Bug in Z

---

## [1.1.0] - 2026-04-14

## [1.1] - 2026-04-14

### 🚀 Added
- Language-aware search using `?lang=` parameter (e.g. `/?lang=en&s=query`)
- Custom search handling via `parse_query` to enforce correct `is_search()` behavior
- Dynamic loading of language-specific search templates (`search-{lang}`) from database (FSE compatible)
  (needs templates copied and saved as search-en.html, search-de.html, etc. you can adapt afterwards with Theme Editor)
- Automatic injection of hidden `lang` input into Gutenberg search block forms
- Structured search indexing via `_search_content` post meta
- Gutenberg block content extraction for search (paragraphs, headings, lists)
- Support for accordion (`core/details`) blocks in search (summary + first meaningful content)

---

### 🔄 Changed
- Standardized search execution to root (`/?lang=xx&s=...`) instead of language-prefixed URLs
- Improved language detection to include `$_GET['lang']` (safe early detection)
- Updated language switcher to preserve query parameters and context (including search)
- Adjusted `home_url` filtering to avoid incorrect language prefixing during search
- Refined redirect logic to exclude search requests and prevent conflicts

---

### 🐛 Fixed
- Search results incorrectly treated as homepage (`is_home` vs `is_search`)
- Language switcher generating wrong URLs on search pages (e.g. `/en/` instead of `/?lang=en`)
- Front page rendering issues caused by `pre_get_posts`
- Site icon / logo links not respecting language-aware routing in all contexts
- Search ignoring relevant Gutenberg content (accordion, structured blocks)

---

### ⚙️ Technical Notes
- Fully compatible with Full Site Editing (FSE) using `wp_template` post type
- Object-based implementation (no DOM parsing)
- Language isolation via `_lang` meta with TRID relationships preserved
- Compatible with Vik Booking (locale overrides supported)

---

### 💡 UX Improvements
- Users can repeat searches across languages while preserving query
- Transparent multilingual behavior (no hidden fallback logic)
- Improved search relevance via normalized content extraction

---

## [1.0.0] - 2026-04-08

### Added
- Initial public release
