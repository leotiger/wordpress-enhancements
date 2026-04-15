# Changelog

All notable changes to the Language Router will be documented in this file.

## [Log explanation]

### UX Improvements
- Implies added fuctionality sometimes

### Technical Notes
- Hints and other important information

### Added
- New feature X

### Changed
- Improved performance of Y

### Fixed
- Bug in Z

---

## [1.1.2] - 2026-04-15

### UX Improvements
- Reduced risk of inconsistent state when switching language or translation relationships in the editor
- Added Quick Edit support for language assignment on posts, pages, and navigation items for faster management

### Technical Notes
- Explicit REST permission handling added for all internal meta fields (`_lang`, `_trid`, `_source_updated_at`, `_translation_source_updated_at`)
- Prevents intermittent Gutenberg save failures caused by missing `auth_callback`
- Aligns Language Router meta handling with WordPress REST and block editor requirements

### Added
- Quick Edit language selector for `post`, `page`, and `wp_navigation`
- Language awareness extended to navigation entities

### Changed
- Editor change detection for language and translation fields now uses centralized event handling (including elements within `#my_trans`)
- Save flow now ensures data persistence before reload

### Fixed
- Intermittent error: *“Sorry, you are not allowed to edit the _source_updated_at custom field”* related with not handling permissions for auth_callback
- Language switcher now ignores non-published content when resolving translations
- Prevented accidental state loss when changing language without saving first

---

## [1.1.1] - 2026-04-15

### 💡 UX Improvements
- Enhanced admin language selector UX: selecting a language now prompts for confirmation, saves the post via Gutenberg API, and reloads automatically
- Added unified change handling for primary language selector and translation dropdowns
- Removed requirement for manual page reload to apply language changes

### ⚙️ Technical Notes
- The underlying trid system requires full page saves via reload at the current state to assure consistency of the language equivalents for a post type
  The use receive now a hint which avoids usage confussion and improves UX.

---

## [1.1.0] - 2026-04-14

### 🚀 Added
- Language-aware search using `?lang=` parameter (e.g. `/?lang=en&s=query`)
- Custom search handling via `parse_query` to enforce correct `is_search()` behavior
- Dynamic loading of language-specific search templates (`search-{lang}`) from database (FSE compatible)
  (needs templates copied and saved as search-en.html, search-de.html, etc. you can adapt afterwards with Theme Editor)
- Automatic injection of hidden `lang` input into Gutenberg search block forms
- Structured search indexing via `_search_content` post meta
- Gutenberg block content extraction for search (paragraphs, headings, lists)
- Support for accordion (`core/details`) blocks in search (summary + first meaningful content)
- Compatibility with some common SEO plugins (YOAST, RANK_MATH, AIOSEO, SEOPRESS) to avoid duplicated canonical links (needs testing)

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
