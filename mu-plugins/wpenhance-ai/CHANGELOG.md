# Changelog

## [0.7.0] — 2026-05-14

Adds a Content Generator feature that drafts or rewrites post content directly from the editor panel, with selectable tone and output type.

### UX Improvements

* Two new controls appear above the **Generate Content** button: a **Tone** selector
  (Informative, Persuasive, Storytelling, Technical, Conversational) and an **Output** selector
  (Full Article, Introduction only, Structured Outline). Both have sensible defaults so editors
  can fire off a generation with a single click.
* The result appears in the same review panel used by Translation — **Apply to Editor** and
  **Copy** buttons, with a summary line showing the combination used (e.g. *"Full Article ·
  Persuasive tone"*). Content is never applied automatically.
* When the post already has content, it is passed to the model as context. The model can
  extend or rewrite it rather than starting from a blank slate, which keeps the feature useful
  at every stage of drafting.

### Technical Notes

* Generated output uses native Gutenberg block markup (`<!-- wp:paragraph -->`,
  `<!-- wp:heading -->`, `<!-- wp:list -->`) so results drop straight into the block editor
  without post-processing.
* The cache is keyed per tone + output-type combination
  (`_wpenhance_cache_content-generator_informative_full_article`, etc.), so multiple variants
  of the same post can coexist in cache independently.
* `renderContentResult()` in `admin.js` now builds its meta summary line dynamically from
  `featureKey` and the response payload, rather than always writing "Translated to:". This
  makes the function generic for any future `type: content` feature without further JS changes.
* Existing content is stripped of HTML before being sent as context (`wp_strip_all_tags`),
  capped at 6 000 characters to stay well within token budgets even for long posts.

### Added

* **Content Generator feature** (`includes/Features/ContentGenerator.php`) — drafts or rewrites
  post content using `claude-sonnet-4-6` (8 192 token budget, temperature 0.6). UI fields:
  Tone × 5 options, Output × 3 options. Returns `type: content` for the existing Apply /
  Copy flow.
* **`templates/prompts/content-generator.txt`** — prompt template with `{{title}}`, `{{tone}}`,
  `{{content_type}}`, and `{{existing_content}}` placeholders. Instructs the model to produce
  clean Gutenberg block markup with no preamble or meta-commentary.

### Changed

* `Registry::init()` — registers `ContentGenerator` alongside the existing three features.
* `admin.js` `renderContentResult()` — meta summary line is now built dynamically:
  `translation` → *"Translated to: {language}"*; `content-generator` → *"{Output} · {Tone}
  tone"*; any future feature → *"Content ready"*. No other JS changes required.

---

## [0.6.0] — 2026-05-14

Adds a force-refresh control to bypass the cache on demand, and refactors the JS fetch logic
into a shared function to eliminate duplication.

### UX Improvements

* A **↺ Refresh** link appears below any cached result, accompanied by the hint
  *"Re-generates and updates the cached result."* The button is styled as a plain underlined
  link — visually distinct from primary actions — so it is discoverable without competing
  for attention with Apply to Editor or Copy.
* The refresh button only appears when the result came from cache. Fresh results show no
  refresh control, keeping the panel uncluttered on first generation.

### Technical Notes

* `force_refresh: true` is added to the JSON request body by the JS and read in each feature's
  `run()` via `empty($params['force_refresh'])`. Skipping the cache on a refresh still writes
  the new result back, so subsequent loads serve the updated cached value.
* The JS fetch + render logic is extracted into a shared `runFeature()` function used by both
  the main action button and the refresh button, removing the duplication that would otherwise
  exist between the two handlers.

### Added

* **Force-refresh button** — rendered below any cached result with an explanatory hint.
  Triggers a fresh API call for the same feature and language, then replaces the result
  panel (including the badge and refresh row) with the new output.

### Changed

* `MetaDescription::run()`, `ExcerptGenerator::run()`, `Translation::run()` — cache lookup is
  now conditional on `empty($params['force_refresh'])`. A refresh skips `CacheStore::get()` but
  still calls `CacheStore::set()` at the end, so the cache is always left in a fresh state.
* `admin.js` — `runFeature(featureKey, postId, params, resultEl)` extracted as a shared async
  function. Both the action-button handler and the new refresh handler delegate to it.
  `collectParams()` also extracted to keep param collection consistent between the two paths.
* `renderContentResult()` updated to accept `featureKey` and `postId` so it can pass them to
  `renderRefreshRow()` when the result is cached.

---

## [0.5.0] — 2026-05-14

Introduces result caching across all three features, eliminating redundant API calls when
post content has not changed since the last generation.

### UX Improvements

* A **"cached"** badge appears next to the result label whenever a stored result is returned
  instead of making a new API call — giving editors a clear signal that generation was
  instantaneous and no cost was incurred.

### Technical Notes

* Caching uses a SHA-256 hash of the inputs (content, title, locale, target language, footnotes
  as applicable) stored alongside the result in post meta. The cache is invalidated automatically
  when any input changes — there is no TTL. A post that is edited and then re-generated will
  always get a fresh result.
* Translation cache entries are per-language (`_wpenhance_cache_translation_fr`,
  `_wpenhance_cache_translation_ca`, etc.), so multiple language versions of the same post
  can be cached independently without overwriting each other.
* The null-byte `\x00` separator used in the hash input string prevents trivial collision attacks
  where concatenated inputs could otherwise produce the same hash from different values.
* Cache is stored in post meta with `autoload = false` (WordPress default for `update_post_meta`)
  so cached values are not loaded on every page request.

### Added

* **`CacheStore` class** (`includes/Core/CacheStore.php`) — `get()`, `set()`, `delete()`, and
  `hash()` helpers. `get()` returns `null` on miss or stale hash; `set()` writes the payload and
  hash atomically via `update_post_meta`.

### Changed

* `MetaDescription::run()` — checks cache (hash of `post_content + post_title + locale`) before
  calling the API; stores result on miss. Returns `cached: true` on a hit.
* `ExcerptGenerator::run()` — checks cache (hash of `post_content + locale`) before calling the
  API; stores result on miss. Returns `cached: true` on a hit.
* `Translation::run()` — checks a per-language cache (hash of `post_content + _footnotes +
  target_language`) before calling the API; stores both translated content and footnotes on miss.
  Returns `cached: true` on a hit. The `$footnotes_raw` read is hoisted to before the cache
  check so it is available for hashing without a second `get_post_meta` call.
* `admin.js` — renders a "cached" badge on any result where `data.cached === true`, for both
  short text outputs and full content translation panels.

---

## [0.4.0] — 2026-05-14

Adds footnote translation support and fixes a fatal error on Linux deployments caused by a
filename case mismatch.

### UX Improvements

* When a page has WordPress core footnotes, **Apply to Editor** now applies both the translated
  content and the translated footnotes in a single click. The result panel shows how many
  footnotes were translated alongside the language badge, so editors know what will be applied
  before confirming.

### Technical Notes

* Footnotes (`_footnotes` post meta) are translated in the same API call as the page content —
  not a separate request — so terminology stays consistent between body text and footnotes.
* The model is instructed to preserve all footnote `id` values unchanged and translate only the
  `content` field of each entry. The returned JSON is validated before use; if the model returns
  malformed JSON for the footnotes section, the content translation is still applied and the error
  is written to the PHP error log.
* Footnotes are saved via a new dedicated REST endpoint (`POST /wpenhance-ai/v1/footnotes/{id}`)
  rather than through the core WP REST API, avoiding any dependency on `_footnotes` being
  explicitly registered with `show_in_rest`.
* This release only handles WordPress 6.3+ native footnotes (`_footnotes` post meta). Footnotes
  stored via third-party plugins using shortcodes are translated as plain text within the content
  block and do not receive separate meta handling.

### Added

* **Footnote translation** — `Translation::run()` reads the post's `_footnotes` meta, includes it
  in the translation prompt, and returns a parsed `footnotes` key alongside the translated content.
* **`POST /wpenhance-ai/v1/footnotes/{id}`** REST endpoint — accepts a JSON-encoded footnotes
  array and writes it to `_footnotes` post meta, with capability and JSON validity checks.

### Changed

* `Translation::run()` response now includes a `footnotes` key (translated JSON string) when the
  post has native footnotes; the key is absent when there are none, so existing integrations are
  unaffected.
* `admin.js` `renderContentResult()` accepts a `postId` argument and stores the translated
  footnotes JSON in a `data-footnotes` attribute on the result container.
* **Apply to Editor** handler is now `async` — after dispatching content to the editor it
  POSTs translated footnotes to the new REST endpoint if present. A failure to save footnotes is
  non-fatal: content is still applied and a warning is written to the browser console.

### Fixed

* **Fatal error on Linux deployments** — `wpenhance-ai.php` required `includes/Core/Autoloader.php`
  (capital A) but the file on disk was `autoloader.php` (lowercase). macOS's case-insensitive
  filesystem masked the mismatch during development. The file has been renamed to `Autoloader.php`
  to match both the `require_once` path and PSR-4 convention.

---

## [0.3.0] — 2026-05-14

This release introduces full content translation, a third AI provider (Google Gemini), a
per-feature model factory, and a proper Settings page for API key management. It also resolves
five production-blocking bugs discovered during the pre-release audit.

### UX Improvements

* Translations are surfaced in a dedicated review panel — the translated content is never applied
  automatically. The editor must explicitly click **Apply to Editor** (or **Copy**) to act on the
  result, providing a clear approval step before any content changes.
* The Translation language selector is pre-populated from the post's `_lang` meta, so a French
  page (`_lang = fr`) already has French selected when the panel opens — no manual selection
  needed for the common case of translating imported content into the page's own language.
* API keys can now be entered and managed directly from **Settings → WPEnhance AI**. Each key
  field shows a status badge indicating whether the key is configured and where it comes from
  (database, environment variable, or PHP constant), making connection issues immediately visible
  without SSH access.

### Technical Notes

* API keys are encrypted with AES-256-CBC before being written to `wp_options`. The encryption
  secret is derived from WordPress's own auth salts (`wp-config.php`) — plaintext keys never
  touch the database. A custom secret can be set via `define('WPENHANCE_AI_SECRET', '…')`.
* The `WorkerConfig` factory pattern means model selection is declared per-feature, not per
  provider. Adding a new feature with a different model requires only a `get_worker_config()`
  override — no changes to provider classes or the factory.
* The API key fallback chain is backward-compatible: database (encrypted) → environment variable
  → PHP constant. Existing setups using `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` env vars or
  constants continue to work without any changes.
* Translation passes the full raw block content (up to 20 000 characters) and instructs the
  model to auto-detect the source language, so cross-language imports (e.g. Catalan content on a
  French page) are handled without requiring a source-language field.
* The `wp_remote_post` timeout for all providers is set to 120 seconds. Verify that your host's
  `max_execution_time` is at least as high — some managed hosts cap it at 30–60 s, which can
  cause long translations to fail at the PHP level before the HTTP request completes.

### Added

* **Translation feature** — translates full post/page content to a target language using
  `claude-sonnet-4-6` (8 192 token budget). Preserves all WordPress block comments, HTML tags,
  shortcodes, and element attributes; only visible text is translated.
* **Google Gemini provider** — full support for the Generative Language REST API, including
  correct mapping of system instructions, role names (`model` vs `assistant`), and generation
  config parameters.
* **`WorkerConfig` value object** — immutable DTO (`model`, `max_tokens`, `temperature`) passed
  to `ProviderFactory::make()` to configure a worker for a specific task.
* **`KeyStore` class** — AES-256-CBC encrypted storage for API keys in `wp_options`, with a
  `source()` helper that reports whether the active key comes from the database, an environment
  variable, or a PHP constant.
* **Settings page** (`Settings → WPEnhance AI`) — provider selector, per-provider API key fields
  (password inputs, masked when configured), source badges, remove-key checkboxes, and a
  security reference for wp-config.php-based configuration.
* **`get_worker_config(): WorkerConfig`** added to `FeatureInterface` — each feature declares
  its own model and generation parameters independently of the global provider setting.
* **`get_ui_fields(): array`** added to `FeatureInterface` — features can declare additional
  admin controls (e.g. select dropdowns) rendered in the meta box above their action button.
* **`get_field_defaults(int $post_id): array`** added to `FeatureInterface` — features can
  return post-specific default values for their UI fields (used by Translation to pre-select the
  target language from `_lang` post meta).
* **Catalan** (`ca`) added to the Translation target language list.
* **Apply to Editor** button on translation results — dispatches content to the Gutenberg block
  editor via `wp.data`; falls back to the Classic editor `#content` textarea.
* **Copy to clipboard** button on all AI-generated outputs.

### Changed

* `MetaDescription` and `ExcerptGenerator` now use `claude-haiku-4-5-20251001` (256 and 512
  token budgets respectively) — faster and more cost-effective for short, structured outputs.
* `Translation` uses `claude-sonnet-4-6` with a low temperature (0.2) for higher fidelity on
  long multilingual content.
* `MetaDescription` and `ExcerptGenerator` now read the target language from the post's `_lang`
  meta field (e.g. `fr`, `ca`, `de`) and fall back to `determine_locale()` only when the meta is
  absent.
* `ProviderFactory::make()` accepts an optional `WorkerConfig`; when omitted it falls back to
  sensible per-provider defaults (`claude-haiku-4-5-20251001` / `gpt-4o-mini` / `gemini-2.0-flash`).
* `Config::provider()` checks `wp_options` before the `WPENHANCE_AI_PROVIDER` constant, allowing
  the active provider to be changed from the Settings page without editing `wp-config.php`.
* `FeatureController::run()` extracts a JSON request body and passes it to `feature->run()` as
  `$params`, enabling features to receive UI field values (e.g. `target_language`).
* `admin.js` sends a JSON body (`Content-Type: application/json`) and collects per-feature UI
  field values before each request. Result rendering is split by output type: short text gets a
  plain textarea; full-content results (`type: content`) get the review panel with
  Apply / Copy actions.
* The translation prompt now explicitly instructs the model to auto-detect the source language,
  making cross-language imports reliable without a dedicated source-language field.
* All three providers now consistently return `null` (not an empty string) on any failure,
  giving features a single falsy value to check.

### Fixed

* `admin_enqueue_scripts` was enqueuing the plugin's JS and CSS on every WordPress admin page.
  Assets now load only on `post.php` and `post-new.php`.
* `file_get_contents()` on prompt template files was not checked for a `false` return. A missing
  template after a bad deployment caused a `TypeError` in PHP 8. Both `MetaDescription` and
  `Translation` now return `success: false` with a descriptive error if the file is unreadable.
* `MetaDescription` and `ExcerptGenerator` returned `success: true` with an empty `output` when
  the API produced no text (rate limit, invalid key, upstream error). Both now return
  `success: false` with an actionable error message.
* HTTP error responses from all three provider APIs (401 invalid key, 429 rate limit, 5xx server
  errors) were silently treated as "no output". Providers now check the HTTP status code, write a
  structured entry to the PHP error log, and return `null`.
* `random_bytes()` in `KeyStore::encrypt()` was not wrapped in a try/catch. On systems with
  insufficient entropy it could throw an unhandled `\Exception`. The call is now guarded; on
  failure the error is logged and `KeyStore::set()` returns `false` without writing to the
  database.
