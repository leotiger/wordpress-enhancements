# WPEnhance AI

An AI assistance framework for WordPress, delivered as an mu-plugin. Adds a lightweight editorial panel to the Gutenberg meta box that lets editors generate meta descriptions, post excerpts, full-content translations, and complete post drafts without leaving the editor — and without shipping an AI product disguised as a WordPress plugin.

Supports Anthropic Claude, OpenAI, and Google Gemini as interchangeable backends.

---

## Features

### Meta Description Generator

Generates a ready-to-use SEO meta description from the post title and content. The output is written in the language of the post, detected from the `_lang` post meta field (falling back to `determine_locale()` when absent), so a Catalan page gets a Catalan description without manual configuration.

Uses `claude-haiku-4-5-20251001` (256 token budget, temperature 0.4) — fast and cost-effective for short, structured outputs.

### Excerpt Generator

Produces a concise editorial excerpt of up to 240 characters, again language-aware. Useful for sites where editors frequently skip the excerpt field and the auto-generated fallback from content is too long or poorly framed.

Uses `claude-haiku-4-5-20251001` (512 token budget, temperature 0.4).

### Content Translation

Translates the full post or page content to a selected target language while preserving all WordPress block comments, HTML structure, shortcodes, and element attributes. Only visible text is translated — the block markup comes back intact and the result is immediately applicable to the Gutenberg editor.

When the post has native WordPress footnotes (`_footnotes` post meta, introduced in WP 6.3), they are translated in the same API call as the body content, keeping terminology consistent between text and footnotes. The model is instructed to preserve all footnote `id` values and translate only the `content` field of each entry.

Translations are never applied automatically. Results appear in a dedicated review panel; the editor clicks **Apply to Editor** to dispatch the content (and footnotes, if present) or **Copy** to handle it manually. A failure to save footnotes is non-fatal — content is still applied and a warning is written to the browser console.

The target language selector is pre-populated from the post's `_lang` meta, so a French page already has French selected when the panel opens.

Uses `claude-sonnet-4-6` (8 192 token budget, temperature 0.2) for higher fidelity on long multilingual content.

**Supported target languages:** English, Spanish, German, French, Italian, Portuguese, Dutch, Catalan, Polish, Russian, Chinese (Simplified), Japanese, Arabic.

### Content Generator

Drafts or rewrites post content directly from the editor panel. Three controls let the editor shape the output before generating:

**Hints** — a free-text field for key points, ideas, or a rough structure. The AI uses these notes as the foundation for the generated content, expanding them into a full draft in the selected tone and format.

**Tone** — Informative, Persuasive, Storytelling, Technical, or Conversational.

**Output type** — Full Article, Introduction only, or Structured Outline.

Results appear in the same review panel used by Translation: the content is never applied automatically. The editor clicks **Apply to Editor** to dispatch the result to the Gutenberg block editor, or **Copy** to handle it manually.

When hints are provided they take priority as the seed. When the Hints field is left empty and the post already has a body, that content is passed to the model as context instead (stripped of HTML markup, capped at 6 000 characters), so the model can extend or rewrite an existing draft. When both are absent the model generates from the title alone.

Generated output uses native Gutenberg block markup (`<!-- wp:paragraph -->`, `<!-- wp:heading -->`, `<!-- wp:list -->`) so results slot directly into the block editor without post-processing.

Uses `claude-sonnet-4-6` (8 192 token budget, temperature 0.6).

---

## Result Caching

Every feature caches its output in post meta. A SHA-256 hash of the inputs (content, title, locale, target language, and footnotes as applicable) is stored alongside the result. The cache is invalidated automatically when any input changes — there is no TTL.

When a cached result is returned, a **cached** badge appears in the UI so editors know generation was instantaneous and no API cost was incurred. A **↺ Refresh** link below any cached result forces a new API call and updates the cache in place.

Translation cache entries are keyed per language (`_wpenhance_cache_translation_fr`, `_wpenhance_cache_translation_ca`, etc.), so multiple language versions of the same post can be cached independently.

---

## Requirements

- WordPress 6.3 or later
- PHP 8.1 or later (developed and tested on PHP 8.3)
- An API key for at least one supported provider

---

## Installation

This plugin is designed to run as an **mu-plugin**. Copy the `wpenhance-ai` folder to `wp-content/mu-plugins/`. WordPress loads mu-plugins automatically — no activation step required.

```
wp-content/
  mu-plugins/
    wpenhance-ai/
      wpenhance-ai.php
      includes/
      assets/
      templates/
```

If you are using a Plugin Loader to manage mu-plugins, add the main file to your loader as you would any other enhancement.

---

## Configuration

### Choosing a Provider

Navigate to **Settings → WPEnhance AI** and select the active provider from the dropdown. The setting is stored in `wp_options` and takes precedence over the `WPENHANCE_AI_PROVIDER` constant.

Alternatively, define the constant in `wp-config.php`:

```php
define('WPENHANCE_AI_PROVIDER', 'anthropic'); // 'anthropic' | 'openai' | 'gemini'
```

### API Keys

Keys can be entered directly from **Settings → WPEnhance AI**. Each key field shows a status badge indicating whether the key is configured and where it is currently sourced from.

Keys entered via the Settings page are stored encrypted in `wp_options` using AES-256-CBC. The encryption secret is derived from WordPress's own auth salts (`wp-config.php`), so plaintext keys never touch the database. To use a custom secret instead:

```php
define('WPENHANCE_AI_SECRET', 'your-secret-here');
```

**Fallback resolution order** (highest to lowest priority):

1. Encrypted value in `wp_options` (set via the Settings page)
2. Server environment variable (`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY`)
3. PHP constant of the same name defined in `wp-config.php`

Existing setups using environment variables or constants continue to work without any changes.

### Provider Timeouts

All provider API calls use a 120-second `wp_remote_post` timeout. If your host caps `max_execution_time` below this (common on managed hosts at 30–60 s), long translations may fail at the PHP level before the HTTP request completes. Adjust your host's PHP timeout accordingly.

---

## Architecture

```
wpenhance-ai/
  wpenhance-ai.php              Plugin entry point
  includes/
    Core/
      Autoloader.php            PSR-4 class autoloader
      Plugin.php                Bootstrap: registers hooks, initialises features
      Config.php                Provider resolution (options → constant)
      KeyStore.php              AES-256-CBC encrypted API key storage
      CacheStore.php            SHA-256 hash-based result cache in post meta
    Contracts/
      AIProviderInterface.php   Contract all providers must satisfy
    Features/
      Contracts/
        FeatureInterface.php    Contract all features must satisfy
      Registry.php              Registers active features with the REST controller
      MetaDescription.php       Meta description generation feature
      ExcerptGenerator.php      Excerpt generation feature
      Translation.php           Full-content translation feature
      ContentGenerator.php      AI content drafting and rewriting feature
    Providers/
      ProviderFactory.php       Instantiates the active provider for a WorkerConfig
      WorkerConfig.php          Immutable DTO: model, max_tokens, temperature
      Anthropic.php             Anthropic Messages API client
      OpenAI.php                OpenAI Chat Completions API client
      Gemini.php                Google Generative Language API client
    Admin/
      MetaBox.php               Gutenberg meta box: renders the AI panel
      SettingsPage.php          Settings → WPEnhance AI
    REST/
      FeatureController.php     POST /wpenhance-ai/v1/feature/{key}/{post_id}
                                POST /wpenhance-ai/v1/footnotes/{post_id}
  assets/
    admin.js                    Meta box UI: fetch, render, Apply to Editor, Copy
    admin.css                   Panel styles
  templates/
    prompts/
      meta-description.txt      Prompt template for meta description generation
      translation.txt           Prompt template for content translation
      content-generator.txt     Prompt template for content drafting / rewriting
```

Each feature declares its own model and generation parameters via `get_worker_config()`, independent of the global provider setting. Adding a new feature requires only implementing `FeatureInterface` and registering it in `Registry.php` — no changes to providers or the factory.

The `WorkerConfig` value object is passed to `ProviderFactory::make()`, which instantiates the correct provider class for the active backend. Provider classes are interchangeable: all implement `AIProviderInterface` and return a plain string on success or `null` on any failure.

---

## Disclaimer

Developed with AI assistance, shaped by the constraints of a real production site. The code works for our specific needs — it may serve as a starting point for more complex or commercial plugins.

No attribution required. It's all yours.
