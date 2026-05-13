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

## [1.1.9] - 2026-05-13

### Technical Notes

- Improved multilingual URL normalization and canonicalization robustness.
- Hardened permalink reconstruction logic against duplicated language prefixes and malformed path concatenation.
- Added infrastructure-level duplicate slash normalization for malformed multilingual requests.
- Improved archive and alternate URL path normalization handling.

### Changed

- Language-aware permalink generation now rebuilds normalized paths more defensively.
- Archive hreflang generation now strips existing language prefixes before rebuilding alternate URLs.

### Fixed

- Fixed edge cases where malformed multilingual URLs could generate duplicated language prefixes.
- Fixed potential double-slash multilingual URL variants being propagated by crawlers.
- Fixed canonicalization inconsistencies for malformed multilingual requests.

---

## [1.1.8] - 2026-05-12

### UX Improvements

- Improved Quick Edit usability by restricting parent page selection to objects written in the currently filtered language.
- Parent selection in Quick Edit now behaves consistently with the active language filter in the content overview.

### Technical Notes

- Parent page filtering is now applied server-side via the native `get_pages` filter.
- The Quick Edit parent selector automatically respects the persistent `my_lang_filter` admin preference.

### Added

- Server-side language-aware filtering for Quick Edit parent page dropdowns.

### Changed

- Quick Edit parent page retrieval now integrates directly with the language router filtering system.

### Fixed

- Fixed Quick Edit showing parent pages from unrelated languages.

---

## [1.1.7] - 2026-05-12

### UX Improvements

- Restored template assignment support for Pages and Posts while maintaining protection against accidental Full Site Editing entity persistence.
- Added a dedicated sidebar Template selector compatible with block themes and FSE templates.
- Improved compatibility with multilingual template workflows (`page-en`, `page-de`, `single-fr`, etc.).
- Language-root URLs such as `/de/`, `/fr/`, `/en/` now dynamically resolve to the translated front page using the TRID system.

### Technical Notes

- `supportsTemplateMode` remains disabled intentionally to prevent Gutenberg from offering:
  - template saves
  - navigation saves
  - template part persistence
  - synced pattern overrides
- Template assignment is now handled independently via `_wp_page_template` meta handling.
- FSE templates are resolved directly from the `wp_template` post type instead of relying on `wp_get_theme()->get_page_templates()`, improving compatibility with block themes.
- Front-page language routing now dynamically resolves translated homepage objects via TRID relationships without hardcoded aliases.

### Added

- Dedicated Template meta box for Posts and Pages.
- Dynamic language-root handling for:
  - `/de/`
  - `/fr/`
  - `/en/`
  - and all registered languages.
- Support for FSE template slug enumeration through `wp_template` objects.

### Changed

- Improved frontend language-root redirect handling using dynamic TRID resolution.
- Improved block-theme compatibility for template assignment workflows.
- Improved protection against unintended Full Site Editing entity modifications from content editors.

### Fixed

- Fixed inability to assign templates after disabling Gutenberg template mode.
- Fixed incorrect detection/loading of currently assigned FSE templates in the custom template selector.
- Fixed potential accidental persistence of navigation entities and template parts during normal page editing workflows.

---

## [1.1.6] - 2026-05-09

### Technical Notes
- Fix inconsistent FSE handling to avoid altering theme parts while editing posts, pages...

### Added
- Improved Gutenberg editing safety by disabling template editing mode from normal page and post editors.
- Improved separation between content editing and Full Site Editing workflows to avoid accidental template modifications while editing content.
- Preserved full Theme Editor functionality while restricting template editing access within content contexts.

### Changed
- Disabled `supportsTemplateMode` for standard post and page editing contexts.
- Theme Editor and Full Site Editing remain fully available through the Appearance → Editor interface.

### Fixed
- Fixed accidental template modifications being saved while editing normal page content.
- Fixed unintended Full Site Editing interactions from Gutenberg content editing screens.

---

## [1.1.5] - 2026-05-08

### Technical Notes
- Major stabilization improvements.

### Added
- Frontend AJAX language propagation via automatic lang injection into jQuery AJAX requests
- Language-aware handling for AJAX requests through admin-ajax.php
- Translation graph completion when linking translations

### Changed
- Refactored locale handling to use determine_locale as primary mechanism
- Refined pre_get_posts handling to target only relevant frontend main queries
- Restricted language filtering to archives, home, and search queries only
- Improved query isolation between frontend, admin, AJAX, REST, and CLI contexts

### Fixed
- AJAX requests losing language context
- VikBooking translations failing during AJAX requests
- Locale inconsistencies caused by stale language cookies
- Admin page listings showing “No title”
- Query pollution caused by globally forcing language vars
- Search requests incorrectly treated as homepage queries
- Translation cache not invalidated after post updates

---

## [1.1.4] - 2026-04-24

### Technical Notes
- Language router did not respect query vars of third parties, now it should maintain query vars correclty

### Added
- Support for query vars other than standard search

### Changed
- Refactored redirects to respect query vars

### Fixed
- Respects query vars given now.

---

## [1.1.3] - 2026-04-20

### UX Improvements
- Automatic template assignment based on language when switching content language (e.g. `page-en`, `single-de`)
- Improved editor workflow: language change now preserves user control over templates while applying sensible defaults on language assignment
- Translation relationships now propagate automatically across existing language equivalents (no need to manually re-link all languages)
  which reduces workload and errors.
- Cleaner and more predictable behavior when creating new translations from a primary language
- Language switcher improvements (stability and consistency when switching languages in the frontend)

### Technical Notes
- Introduced graph-based translation expansion to ensure full consistency across translation groups
- Implements meta `_lang_previous` to detect real language changes reliably
- Hardened template resolution logic using `wp_template` post type (FSE-compatible)
- Refined homepage redirect logic to avoid conflicts with query-based requests (e.g. search)
- Maintained compatibility with block themes and custom template hierarchy

### Added
- Automatic propagation of translation relationships across all linked posts/pages
- Language-aware template resolution helper functions
- Safeguards for template auto-assignment (only applies when no custom template is set)
- Improved debug coverage for template assignment and routing behavior
- Page and post templates are now handled like search templates and automatically assigned on setting language if a language specific
  template exists for a given type.

### Changed
- Refactored `wp_after_insert_post` logic for better separation of concerns (language, template, translation graph)
- Improved robustness of homepage redirect to avoid interfering with search and query-based requests
- Optimized translation grouping logic to ensure consistency across all languages

### Fixed
- Search requests being redirected to language specific homepage equivalent under certain language conditions
- Language Switcher creating unavailable and unnecessary url resource in the frontend with 404 response

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
