# Changelog

## [1.0.6] — 2026-05-15

### Added

* **Configurable model endpoints** — a new **Models** section in Settings → WPEnhance AI
  lets administrators override the AI model string for each provider and tier without editing
  any code. Fields are organised in a table: one row per provider (Anthropic, OpenAI, Gemini),
  one column per tier (Light and Quality). Leaving a field blank falls back to the built-in
  default, which is displayed as placeholder text so it is always visible. An "overridden"
  badge appears next to any field that carries a custom value. Saving an empty value resets
  a model to the built-in default. Only the active provider's models are used at runtime —
  the others can be pre-configured before switching.

* **Two-tier model abstraction** — features no longer reference model strings directly.
  `Config::model(string $tier)` resolves the correct string at runtime: it reads the stored
  option `wpenhance_ai_model_{provider}_{tier}` for the active provider, and falls back to
  the hard-coded defaults in `Config::MODEL_DEFAULTS`. Two helpers are also exposed:
  `Config::default_model(string $provider, string $tier)` (used by the settings page to
  populate placeholders) and `Config::all_model_defaults()` (returns the full defaults map).

### Changed

* **All four features wired to `Config::model()`** — `Translation` and `ContentGenerator`
  now call `Config::model('quality')`; `MetaDescription` and `ExcerptGenerator` call
  `Config::model('light')`. When a new Sonnet or Haiku version ships, updating the Quality
  or Light field in settings is all that is needed — no code change, no deployment.
* **`ProviderFactory` fallback updated** — the no-config path (rarely hit) now calls
  `Config::model('light')` instead of reading from a private `DEFAULT_MODELS` constant,
  keeping the single source of truth in `Config`.
* `Config.php` now imports `WPEnhance\AI\Core\Config` — all four feature files gained this
  `use` statement.

---

## [1.0.5] — 2026-05-15

### Added

* **Chunk translation mode** — a new **Mode** selector in the Translation panel offers two
  options: *Full post* (the existing behaviour) and *Translate chunk*. In chunk mode a
  **Text to translate** textarea appears; the user pastes any snippet of text — a footnote,
  a heading, a sentence — clicks the action button, and receives only that snippet translated.
  The result shows a Copy button with no *Apply to Editor*, since the user applies it manually
  wherever it is needed. This is the recommended workaround for any content that the full-post
  path handles unreliably (see Known Limitations below).
* **Conditional field visibility** — a generic `data-condition-field` / `data-condition-value`
  attribute system in `MetaBox.php` and `admin.js` allows any UI field to declare a visibility
  condition. `initConditionalFields()` runs on `DOMContentLoaded` and wires up `change`
  listeners, hiding or showing wrapped fields automatically. The chunk textarea uses this to
  appear only when Mode = *Translate chunk*; any future conditional field requires no new JS.
* **`templates/prompts/translation_chunk.txt`** — lightweight prompt for snippet translation:
  auto-detects source language, preserves HTML and block comments, outputs only the translated
  text with no separators or commentary.

### Changed

* **Metabox context moved from `'side'` to `'normal'`** — the WPEnhance AI panel now appears
  in the main column below the editor instead of the narrow sidebar. This gives textareas,
  result areas, and the chunk input field the full page width they need to be usable. The
  previous sidebar placement made large result textareas effectively invisible without
  manually resizing the panel.
* **Feature groups rendered as cards** — each feature group now has a light border and
  background, visually separating it from its neighbours. Groups use `flex-wrap` so they
  sit side-by-side in the wider main column, and their fields use a CSS grid that places
  select controls next to each other while keeping textareas on their own full-width row.
* **Copy button scoped to its own result container** — the click handler now resolves the
  target textarea from `button.closest('.wpenhance-ai-content-result')` instead of
  `button.closest('.wpenhance-ai-panel')`, preventing it from accidentally copying a field
  textarea from a different feature group.
* Asset version bumped to `1.0.5` in `MetaBox::enqueue()` to force a browser cache refresh
  for the updated JS and CSS.

### Known Limitations

* **Footnotes must be translated manually using chunk mode.** WordPress stores footnotes in
  a separate `_footnotes` post meta field as a JSON array. When the full-post translation
  runs, the footnote JSON is appended to the prompt and the model is instructed to return a
  translated `===FOOTNOTES===` section. In practice this is fragile: long posts push the
  combined prompt close to context limits, and the model sometimes omits or corrupts the
  footnotes section even when the content body translates correctly.

  **Workaround:** for each footnote, copy its text from the WordPress editor's footnote
  panel, switch the Translation Mode to *Translate chunk*, paste the text into the
  **Text to translate** field, run the translation, and copy the result back into the
  footnote field. This is more manual but reliable for any length and complexity of footnote.
  The full-post translation can still be used for the content body — the two workflows are
  independent.

---

## [1.0.4] — 2026-05-15

### Fixed

* **Unsaved footnotes ignored during translation** — `collectParams()` in
  `admin.js` was reading `meta._footnotes` from the Gutenberg editor store, but
  the block editor exposes the meta key as `footnotes` (no leading underscore)
  via `getEditedPostAttribute('meta')`. The underscore prefix only exists in the
  database (`_footnotes` post meta); Gutenberg strips it in the JS store.
  As a result the live in-editor footnote state was never forwarded to the
  translation request — the PHP fallback always read from the last-saved DB
  value instead. Fixed by changing `meta._footnotes` → `meta.footnotes`.

* **Translated footnotes overwritten on post save** — the "Apply to Editor"
  button was persisting translated footnotes via a direct `POST /footnotes/{id}`
  REST call that wrote to `_footnotes` post meta immediately. Because Gutenberg
  was still holding the original (untranslated) `footnotes` value in its store,
  clicking Save in the editor would flush the store back to the DB and silently
  overwrite the translated footnotes. Fixed by passing footnotes through
  `editPost({meta: {footnotes: …}})` alongside the content — the same Gutenberg
  store path used for all other edits — and removing the now-redundant
  `/footnotes` REST endpoint.

* **Redundant client-side `<br>` stripping removed** — `admin.js` was
  stripping `<br>` tags from the output as a fallback for old cached values
  predating the PHP-side strip. Since `Translation.php` strips before writing
  to the cache and `FeatureController::strip_br_from_output` guards against
  re-injection by `wpautop` plugins, the JS layer was dead code. Removed.

---

## [1.0.3] — 2026-05-14

### Fixed

* **Footnotes not translated** — the translation prompt template ended with
  "nothing else before or after the content body", which directly contradicted
  the footnotes instructions appended afterward telling the model to output an
  `===FOOTNOTES===` section. The model obeyed the earlier restriction and
  silently dropped the footnotes output. Fixed by introducing an
  `{{extra_output}}` placeholder in `translation.txt` so that footnote and
  block-attribute instructions are injected *inside* the template as part of a
  single coherent prompt, rather than appended after a conflicting constraint.
  The same change applies to `===ATTRS===` (block attribute translations).

---

## [1.0.2] — 2026-05-14

### Fixed

* **Root cause of `<br>` corruption identified and fixed** — the actual source
  was `escapeHtml()` in `admin.js`, which used the `div.innerText /
  div.innerHTML` DOM trick to escape HTML. The browser converts every `\n`
  newline to a `<br>` element when reading `innerHTML` back, so every newline
  between a Gutenberg block comment and its inner HTML —
  `<!-- wp:paragraph -->\n<p>…` — was silently rewritten to
  `<!-- wp:paragraph --><br><p>…` before the string was placed into the
  textarea. This happened client-side, after all PHP and JS strip passes had
  already run, which is why none of the earlier fixes had any effect.
  Replaced with a plain string-replacement escape that only substitutes `&`,
  `<`, `>`, `"`, and `'` — leaving newlines intact.

---

## [1.0.1] — 2026-05-14

### Fixed

* **`<br>` tags re-introduced by `wpautop` in REST responses** — root cause
  identified: some plugins/themes apply `the_content` filter (which includes
  `wpautop`) to REST API responses. `wpautop` converts every single newline
  between a Gutenberg block comment and its inner HTML into a `<br />` tag,
  breaking the block parser. The PHP-level strip added in 1.0.0 ran *before*
  these filters, so it had no effect on the final response. Fixed by adding a
  `rest_pre_echo_response` hook at priority 999 in `FeatureController` — the
  very last step before WordPress echoes the response — which strips any `<br>`
  from the `output` field after all other filters have run.

* **JS `<br>` strip not loading in browser** — the `admin.js` `<br>` strip
  added in 1.0.0 was not being picked up because the enqueued asset version
  was already `1.0.0`, so WordPress served the cached pre-strip file.
  Asset versions bumped to `1.0.1` in `MetaBox::enqueue()` to force a fresh
  browser fetch. The JS strip now serves as a final client-side safety net
  in case any `<br>` survives the server-side strip.

---

## [1.0.0] — 2026-05-14

First major release. Collects four fixes discovered during final testing.

### Fixed

* **Apply to Editor had no effect in Gutenberg** — WordPress renders legacy
  meta boxes inside a hidden `<iframe>`. Scripts inside that iframe have their
  own `window`, so `wp.data.dispatch('core/editor')` was dispatching to the
  iframe's isolated store, not the main editor's. Changed to
  `window.parent.wp.data.dispatch('core/editor')` which crosses the iframe
  boundary to reach the live editor store. Classic editor meta boxes are not
  iframed, so the `#content` / `#title` fallback path is unchanged.

* **Post title was not translated** — the title was passed to the model as
  context only; the prompt instructed the model to return the content body
  alone, and the JS only dispatched `content` to the editor. The translated
  title now comes back via a `===TITLE===` separator, is included in the
  payload as `translated_title`, and is applied to the editor alongside
  content in the same **Apply to Editor** click.

* **`<br>` tags injected into block markup** — models reliably hallucinate
  `<br>` tags between blocks to "preserve" newlines, breaking the Gutenberg
  block parser. Fixed with two layers: an explicit prompt rule in both
  `translation.txt` and `content-generator.txt` ("do not introduce `<br>`
  tags"), and an unconditional `preg_replace` safety net in both
  `Translation::run()` and `ContentGenerator::run()`. Stripping all `<br>`
  tags is safe because the translation prompt already instructs the model to
  preserve HTML exactly — any `<br>` legitimately present in the original
  should survive that instruction; any it hallucinates is removed. The worst
  case is losing a soft line break (Shift+Enter) inside a paragraph, a trivial
  manual fix, whereas leaving a stray `<br>` breaks block structure entirely.
  Cached translations must be force-refreshed via **↺ Refresh** to pick up
  the fix.

* **All features shared a single result panel** — clicking any feature
  replaced the output of every other feature. Each feature group now has its
  own result container directly below its action button, so Translation,
  Content Generator, Meta Description, and Excerpt results are all visible
  at the same time and never overwrite each other.

### Changed

* `templates/prompts/translation.txt` — model now opens its response with
  `===TITLE===` + translated title before the content body.
* `Translation::run()` — parses `===TITLE===` first; includes
  `translated_title` in the payload; adds `$post->post_title` to the cache
  hash so a title change invalidates cached translations.
* `admin.js` Apply to Editor handler — uses `window.parent.wp.data` for
  Gutenberg; passes `title` alongside `content` in `editPost()`; sets
  `#title` in the classic editor fallback.
* `admin.js` `renderContentResult()` — stores `data.translated_title` in
  `data-translated-title` on the result container.
* `MetaBox::render()` — `.wpenhance-ai-result` moved inside each
  `.wpenhance-ai-feature-group` (was a single div after the loop).
* `admin.js` feature-button handler — resolves result via
  `button.closest('.wpenhance-ai-feature-group').querySelector('.wpenhance-ai-result')`.
* `admin.js` refresh handler — resolves result via
  `button.closest('.wpenhance-ai-result')` (refresh button is already inside it).
* Asset version bumped to `1.0.0` in `MetaBox::enqueue()`.

---

## [0.9.0] — 2026-05-14

Fixes silent translation gaps for native Gutenberg blocks whose visible text
is stored in block comment JSON attributes rather than in the HTML body —
most notably `wp:details` (the accordion / disclosure block), where the
summary text lives in `{"summary":"…"}` and would revert to the original
language on the first editor load after translation.

### The Problem

The translation prompt instructs the model to "translate visible text content
between tags" and to "preserve all block comments exactly as they appear."
That works perfectly for static blocks (`wp:paragraph`, `wp:heading`, etc.)
whose text is in the HTML — but for `wp:details` and similar blocks, the
visible text is a string value inside the block comment JSON.  The model
never sees it as translatable text; the JSON attribute comes back untouched,
and the block editor re-renders the heading from the original-language JSON
when the post is opened, silently overwriting the translated HTML version.

### Solution

A pre/post-processing step extracts attribute strings before the API call and
reinserts translated strings after, keeping the main translation prompt and
block grammar completely intact.

### UX Improvements

* `wp:details` summaries, `wp:image` alt text and captions, search block
  labels and placeholders, and any other block using a known translatable
  attribute name are now fully translated in a single **Translate Content**
  click — no extra steps or manual fixup.
* The change is transparent: editors see the same Apply / Copy flow. The
  only difference is that the translated result is now complete.

### Technical Notes

* Extraction uses WordPress's native `parse_blocks()` / `serialize_blocks()`
  functions, which handle all block depths and nested structures correctly
  without custom parsing.
* A whitelist of attribute names drives extraction (`summary`, `alt`,
  `caption`, `label`, `placeholder`, `buttonText`, `title`, `description`).
  This intentionally avoids guessing — only known user-facing fields are
  touched; structural keys, IDs, CSS classes, and URLs are never considered.
* Placeholders take the form `__WPAI_N__` (underscore-fenced, uppercase,
  numeric index).  The translation prompt is updated to explicitly tell the
  model to treat them as opaque tokens, making accidental translation
  extremely unlikely.
* Attribute translations are requested in the **same API call** as the HTML
  content, appended after an `===ATTRS===` separator (matching the
  established footnotes pattern).  Single-call translation keeps terminology
  consistent between body text and attribute strings.
* Translated values are JSON-escaped before substitution via `json_encode`
  + outer-quote stripping, so double-quotes, backslashes, and other special
  characters in the translation never corrupt the block comment grammar.
* When no translatable attributes are found, `BlockTextExtractor::extract()`
  returns the original content unchanged and skips the `serialize_blocks()`
  round-trip entirely, so posts with no qualifying blocks pay zero overhead.

### Added

* **`BlockTextExtractor` class** (`includes/Core/BlockTextExtractor.php`) —
  `extract(string $content): array` walks the parsed block tree, replaces
  translatable attribute values with `__WPAI_N__` placeholders, and returns
  `[modified_content, map]`.  `reinsert(string $content, array $translations)`
  JSON-escapes translated values and substitutes them back in place.

### Changed

* `Translation::run()` — imports `BlockTextExtractor`; calls `extract()`
  after the cache check; uses placeholder content in the prompt; appends an
  `===ATTRS===` section to the prompt when the map is non-empty; parses the
  attrs section from the response before the footnotes section; calls
  `reinsert()` to apply translated attribute values to `$translated_content`.
* `templates/prompts/translation.txt` — new rule: `__WPAI_N__` tokens inside
  block comment JSON values are placeholders and must not be translated.

---

## [0.8.0] — 2026-05-14

Adds a dedicated **Hints** field to the Content Generator, giving editors a clean way to seed generation with key points, ideas, or a rough structure without mixing that input with the post body.

### UX Improvements

* A **Hints** textarea now appears above the Tone and Output selectors in the Generate Content
  panel. Editors can jot down bullet points, topic ideas, or a rough outline before generating —
  the AI builds the full draft from those notes rather than from scratch.
* When the Hints field is left empty, the feature falls back to the existing post body as context
  (the previous behaviour), so the change is non-breaking for existing workflows.

### Technical Notes

* Hints are sanitized (`sanitize_textarea_field`) and capped at 2 000 characters server-side
  before being injected into the prompt. The cap keeps hints focused while staying well within
  token budgets.
* When hints are provided they replace the existing-content section entirely — the two are never
  mixed, keeping the prompt unambiguous.
* The cache hash now includes the hints value, so changing the Hints field correctly invalidates
  any previously cached result for the same tone + output-type combination.
* `collectParams()` in `admin.js` is extended to query `.wpenhance-ai-input-textarea[data-feature-ref]`
  elements alongside the existing `.wpenhance-ai-select` query, so hints are included in the
  request payload without any feature-specific JS.
* `MetaBox.php` now renders a `textarea` field type in its UI loop (in addition to `select`),
  making the pattern available to any future feature that declares a `textarea` in
  `get_ui_fields()`.

### Added

* **Hints textarea** — new `textarea` UI field in `ContentGenerator::get_ui_fields()`. Rendered
  above the Tone and Output selectors; collected and sent as `hints` in the JSON request body.

### Changed

* `ContentGenerator::run()` — `$hints` (sanitized, max 2 000 chars) is extracted from `$params`.
  If non-empty, it replaces the existing-content section in the prompt (`"Hints and key points to
  build from:\n…"`); otherwise the post body fallback is used unchanged.
* `ContentGenerator::get_field_defaults()` — returns `hints: ''` as the empty default.
* `CacheStore` hash now includes `$hints` as an input, ensuring cache invalidation when hints change.
* `templates/prompts/content-generator.txt` — closing instruction added: the model is told to use
  any provided seed content as the foundation and fall back to the title alone when none is present.
* `MetaBox.php` — `textarea` field type now supported in the UI rendering loop (outputs
  `<textarea class="wpenhance-ai-input-textarea" data-field="…" data-feature-ref="…">`).
* `admin.js` `collectParams()` — selector extended to include
  `.wpenhance-ai-input-textarea[data-feature-ref]` alongside `.wpenhance-ai-select[data-feature-ref]`.

---

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
