<?php

namespace WPEnhance\AI\Features;

use WPEnhance\AI\Features\Contracts\FeatureInterface;
use WPEnhance\AI\Providers\ProviderFactory;
use WPEnhance\AI\Providers\WorkerConfig;
use WPEnhance\AI\Core\BlockTextExtractor;
use WPEnhance\AI\Core\CacheStore;
use WPEnhance\AI\Core\Config;

defined('ABSPATH') || exit;

/**
 * Translates the full content of a post or page into a target language
 * while preserving all WordPress block markup and HTML structure.
 *
 * Uses Claude Sonnet for higher quality on longer texts.
 */
class Translation implements FeatureInterface {

    /** @var array<string, string> Supported target languages. */
    private const LANGUAGES = [
        'en' => 'English',
        'es' => 'Spanish',
        'de' => 'German',
        'fr' => 'French',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'ca' => 'Catalan',
        'pl' => 'Polish',
        'ru' => 'Russian',
        'zh' => 'Chinese (Simplified)',
        'ja' => 'Japanese',
        'ar' => 'Arabic',
    ];

    public function get_key(): string {

        return 'translation';
    }

    public function get_label(): string {

        return 'Translate Content';
    }

    /**
     * Sonnet offers stronger multilingual quality and can handle the
     * larger token budgets required by full-page content.
     */
    public function get_worker_config(): WorkerConfig {

        return new WorkerConfig(
            model:       Config::model('quality'),
            max_tokens:  8192,
            temperature: 0.2,
        );
    }

    public function get_ui_fields(): array {

        return [
            [
                'name'    => 'translate_mode',
                'type'    => 'select',
                'label'   => 'Mode',
                'options' => [
                    'full'  => 'Full post',
                    'chunk' => 'Translate chunk',
                ],
            ],
            [
                'name'    => 'target_language',
                'type'    => 'select',
                'label'   => 'Target Language',
                'options' => self::LANGUAGES,
            ],
            [
                'name'        => 'chunk_text',
                'type'        => 'textarea',
                'label'       => 'Text to translate',
                'placeholder' => 'Paste a footnote, sentence, or any snippet here…',
                'rows'        => 6,
                'condition'   => ['translate_mode' => 'chunk'],
            ],
        ];
    }

    /**
     * Pre-select the target language from the post's _lang meta so that,
     * e.g., a French page (‹_lang = fr›) has French already selected even
     * when its current content was imported in another language.
     */
    public function get_field_defaults(int $post_id): array {

        $lang = (string) get_post_meta($post_id, '_lang', true);

        if ($lang !== '' && array_key_exists($lang, self::LANGUAGES)) {
            return ['target_language' => $lang];
        }

        return [];
    }

    public function supports(int $post_id): bool {

        return current_user_can('edit_post', $post_id);
    }

    public function run(int $post_id, array $params = []): array {

        $target_language = sanitize_text_field(
            $params['target_language'] ?? 'en'
        );

        if (!array_key_exists($target_language, self::LANGUAGES)) {

            return [
                'success' => false,
                'error'   => 'Invalid target language.',
            ];
        }

        $language_name = self::LANGUAGES[$target_language];

        // ── Chunk mode ────────────────────────────────────────────────────────
        // Translate an arbitrary text snippet instead of the full post.
        // Useful as a manual workaround for footnotes or any passage that the
        // full-post path struggles with (long content, complex footnotes, etc.).
        $translate_mode = sanitize_text_field($params['translate_mode'] ?? 'full');

        if ($translate_mode === 'chunk') {
            return $this->run_chunk($language_name, $params);
        }

        // ── Full-post mode ────────────────────────────────────────────────────
        $post = get_post($post_id);

        if (!$post) {

            return [
                'success' => false,
                'error'   => 'Post not found.',
            ];
        }

        // ── Cache check ───────────────────────────────────────────────────────
        // Cache key is per-language so multiple translations of the same post
        // can coexist (e.g. _wpenhance_cache_translation_fr, …_ca, …_de).
        //
        // Prefer the footnotes value forwarded by the JS client from the live
        // Gutenberg meta store — this captures unsaved footnotes that have not
        // yet been written to the database.  Fall back to get_post_meta() for
        // classic-editor requests or when the param is absent / invalid JSON.
        $param_footnotes = isset($params['footnotes_meta']) && is_string($params['footnotes_meta'])
            ? wp_unslash($params['footnotes_meta'])
            : '';

        $footnotes_raw = ($param_footnotes !== '' && json_decode($param_footnotes) !== null)
            ? $param_footnotes
            : (string) get_post_meta($post_id, 'footnotes', true);
        $cache_key     = $this->get_key() . '_' . $target_language;
        $hash          = CacheStore::hash([$post->post_title, $post->post_content, $footnotes_raw, $target_language]);
        $cached = empty($params['force_refresh'])
            ? CacheStore::get($post_id, $cache_key, $hash)
            : null;

        if ($cached !== null) {
            return array_merge(['success' => true, 'cached' => true], $cached);
        }

        // ── Block attribute extraction ────────────────────────────────────────
        // Pull translatable strings out of block comment JSON attributes
        // (e.g. the "summary" field of wp:details) and replace them with
        // __WPAI_N__ placeholders.  The placeholders survive translation
        // unchanged; the translated values are reinserted afterwards.
        // When no translatable attrs are found this is a cheap no-op.
        [$placeholder_content, $attr_map] = BlockTextExtractor::extract(
            $post->post_content
        );

        // ── Build prompt ──────────────────────────────────────────────────────
        $prompt = file_get_contents(
            WPENHANCE_AI_PATH .
            '/templates/prompts/translation.txt'
        );

        if ($prompt === false) {
            return [
                'success' => false,
                'error'   => 'Prompt template not found.',
            ];
        }

        // ── Build optional extra-output sections ─────────────────────────────
        // All additional output sections (footnotes, block attrs) are assembled
        // first so they can be injected into the {{extra_output}} placeholder
        // in the template.  Putting them inside the template — rather than
        // appending after it — eliminates the prompt conflict that previously
        // caused the model to skip these sections: the template used to say
        // "nothing else before or after the content body" and then we appended
        // instructions telling the model to output ===FOOTNOTES=== afterward,
        // which it silently ignored.

        $has_footnotes  = false;
        $extra_output   = '';
        $extra_sections = [];

        // ── WordPress core footnotes (footnotes post meta) ────────────────────
        if ($footnotes_raw !== '') {
            $decoded = json_decode($footnotes_raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                $has_footnotes    = true;
                $extra_sections[] =
                    "After the translated content, output this exact separator on its own line:\n" .
                    "===FOOTNOTES===\n" .
                    "Then output the complete translated footnotes JSON array.\n" .
                    "Translate only each \"content\" field value; leave every \"id\" value unchanged.\n\n" .
                    "Footnotes JSON:\n" . $footnotes_raw;
            }
        }

        // ── Block attribute strings ───────────────────────────────────────────
        if (!empty($attr_map)) {
            $extra_sections[] =
                "After the translated content (and after the ===FOOTNOTES=== section if present), " .
                "output this exact separator on its own line:\n" .
                "===ATTRS===\n" .
                "Then output a JSON object mapping each placeholder key to its translation. " .
                "Translate only the values — every key must remain exactly as shown.\n\n" .
                "Block attribute strings:\n" .
                wp_json_encode($attr_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!empty($extra_sections)) {
            $extra_output = "\n\n" . implode("\n\n", $extra_sections);
        }

        $prompt = str_replace(
            ['{{language}}', '{{title}}', '{{content}}', '{{extra_output}}'],
            [
                $language_name,
                $post->post_title,
                mb_substr($placeholder_content, 0, 20000),
                $extra_output,
            ],
            $prompt
        );

        $provider = ProviderFactory::make(
            $this->get_worker_config()
        );

        $result = $provider->chat([
            [
                'role'    => 'system',
                'content' =>
                    'You are a professional translator. ' .
                    'Preserve all WordPress block comments ' .
                    '(<!-- wp:... /-->), HTML tags, shortcodes, ' .
                    'and attributes exactly as they appear. ' .
                    'Only translate the visible text content. ' .
                    'Do NOT add any new HTML tags — especially not <br> or <br/> — ' .
                    'that are not already present in the source.',
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ]);

        if (empty($result)) {

            return [
                'success' => false,
                'error'   => 'Translation failed. Please try again.',
            ];
        }

        // ── Split response sections ───────────────────────────────────────────
        // Response structure:
        //   ===TITLE===          (always first)
        //   [translated title]
        //   [translated content body]
        //   ===FOOTNOTES===      (optional)
        //   [footnotes JSON]
        //   ===ATTRS===          (optional, always last)
        //   [block attribute translations JSON]
        //
        // Parse outermost sections first so each step works on clean input.
        $translated_content   = trim($result);
        $translated_footnotes = null;
        $translated_title     = null;

        // ── ===TITLE=== (first line of response) ──────────────────────────────
        if (str_starts_with($translated_content, '===TITLE===')) {

            $after = ltrim(substr($translated_content, strlen('===TITLE===')));
            $nl    = strpos($after, "\n");

            if ($nl !== false) {
                $translated_title   = trim(substr($after, 0, $nl));
                $translated_content = trim(substr($after, $nl + 1));
            } else {
                // Edge case: model returned only a title with no body.
                $translated_title   = trim($after);
                $translated_content = '';
            }
        }

        // Strip ===ATTRS=== section before handling footnotes.
        $translated_attrs_json = null;
        if (!empty($attr_map) && str_contains($translated_content, '===ATTRS===')) {

            $parts                 = explode('===ATTRS===', $translated_content, 2);
            $translated_content    = trim($parts[0]);
            $translated_attrs_json = trim($parts[1]);
        }

        if ($has_footnotes && str_contains($translated_content, '===FOOTNOTES===')) {

            [$content_part, $footnotes_part] = explode('===FOOTNOTES===', $translated_content, 2);

            $translated_content = trim($content_part);
            $candidate          = trim($footnotes_part);

            // Only accept the footnotes section if it is valid JSON.
            if (json_decode($candidate) !== null) {
                $translated_footnotes = $candidate;
            } else {
                error_log('WPEnhance AI [Translation] footnotes section was not valid JSON — skipped.');
            }
        }

        // Reinsert translated block attribute strings.
        if ($translated_attrs_json !== null) {

            $translated_attrs = json_decode($translated_attrs_json, true);

            if (is_array($translated_attrs)) {
                $translated_content = BlockTextExtractor::reinsert(
                    $translated_content,
                    $translated_attrs
                );
            } else {
                error_log('WPEnhance AI [Translation] block attribute translations were not valid JSON — skipped.');
            }
        }

        // ── Strip stray <br> tags ─────────────────────────────────────────────
        // Models reliably hallucinate <br> tags between blocks to "preserve"
        // newlines, breaking the Gutenberg block parser.  The prompt already
        // instructs the model to preserve all HTML exactly as-is, so any <br>
        // tag it outputs that was legitimately in the original should survive
        // that instruction without our help.  Stripping all <br> tags here
        // acts as an unconditional safety net: the worst outcome is losing a
        // soft line break (Shift+Enter) inside a paragraph — a trivial manual
        // fix — whereas leaving a stray <br> breaks block structure entirely.
        $translated_content = preg_replace('/<br[\s\/]*>/i', '', $translated_content);

        $payload = [
            'output'   => $translated_content,
            'type'     => 'content',
            'language' => $language_name,
        ];

        if ($translated_title !== null && $translated_title !== '') {
            $payload['translated_title'] = $translated_title;
        }

        if ($translated_footnotes !== null) {
            $payload['footnotes'] = $translated_footnotes;
        }

        CacheStore::set($post_id, $cache_key, $hash, $payload);

        return array_merge(['success' => true], $payload);
    }

    // ── Chunk translation ─────────────────────────────────────────────────────

    /**
     * Translate a free-form text snippet rather than the full post.
     *
     * Chunk mode is a manual workaround for cases where the full-post path
     * fails — most commonly long footnotes or complex HTML passages.  The user
     * pastes the snippet, clicks Translate, gets back the translated text, and
     * copies it wherever it is needed.
     *
     * No block-comment preservation, no ===FOOTNOTES=== parsing, no cache.
     * The result is intentionally kept plain so it is easy to copy-paste.
     *
     * @param  string $language_name  Human-readable language name (e.g. "French").
     * @param  array  $params         Request parameters; chunk_text is required.
     * @return array
     */
    private function run_chunk(string $language_name, array $params): array {

        $chunk_text = trim(wp_unslash($params['chunk_text'] ?? ''));

        if ($chunk_text === '') {
            return [
                'success' => false,
                'error'   => 'No text provided. Paste a snippet into the "Text to translate" field.',
            ];
        }

        $prompt_template = file_get_contents(
            WPENHANCE_AI_PATH . '/templates/prompts/translation_chunk.txt'
        );

        if ($prompt_template === false) {
            return [
                'success' => false,
                'error'   => 'Chunk prompt template not found.',
            ];
        }

        $prompt = str_replace(
            ['{{language}}', '{{chunk_text}}'],
            [$language_name, mb_substr($chunk_text, 0, 8000)],
            $prompt_template
        );

        $provider = ProviderFactory::make($this->get_worker_config());

        $result = $provider->chat([
            [
                'role'    => 'system',
                'content' =>
                    'You are a professional translator. ' .
                    'Output only the translated text — no commentary, no preamble.',
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ]);

        if (empty($result)) {
            return [
                'success' => false,
                'error'   => 'Translation failed. Please try again.',
            ];
        }

        return [
            'success'  => true,
            'output'   => trim($result),
            'type'     => 'chunk',
            'language' => $language_name,
        ];
    }
}
