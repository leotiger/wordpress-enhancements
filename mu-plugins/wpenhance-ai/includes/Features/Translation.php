<?php

namespace WPEnhance\AI\Features;

use WPEnhance\AI\Features\Contracts\FeatureInterface;
use WPEnhance\AI\Providers\ProviderFactory;
use WPEnhance\AI\Providers\WorkerConfig;

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
            model:       'claude-sonnet-4-6',
            max_tokens:  8192,
            temperature: 0.2,
        );
    }

    public function get_ui_fields(): array {

        return [
            [
                'name'    => 'target_language',
                'type'    => 'select',
                'label'   => 'Target Language',
                'options' => self::LANGUAGES,
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

        $post = get_post($post_id);

        if (!$post) {

            return [
                'success' => false,
                'error'   => 'Post not found.',
            ];
        }

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

        $prompt = str_replace(
            ['{{language}}', '{{title}}', '{{content}}'],
            [
                $language_name,
                $post->post_title,
                mb_substr($post->post_content, 0, 20000),
            ],
            $prompt
        );

        // ── WordPress core footnotes (_footnotes post meta) ───────────────────
        // Footnotes are stored as a JSON array of {id, content} objects.
        // We append them to the prompt so they are translated in the same call,
        // keeping terminology consistent with the main content.
        $footnotes_raw = (string) get_post_meta($post_id, '_footnotes', true);
        $has_footnotes = false;

        if ($footnotes_raw !== '') {
            $decoded = json_decode($footnotes_raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                $has_footnotes = true;
                $prompt .= "\n\n" .
                    "The post also contains footnotes stored as a JSON array below.\n" .
                    "Translate only each \"content\" field value; leave every \"id\" value unchanged.\n" .
                    "After the translated page content output this exact separator on its own line:\n" .
                    "===FOOTNOTES===\n" .
                    "Then output the complete translated footnotes JSON array.\n\n" .
                    "Footnotes JSON:\n" . $footnotes_raw;
            }
        }

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
                    'Only translate the visible text content.',
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

        // ── Split content / footnotes from the model response ─────────────────
        $translated_content   = trim($result);
        $translated_footnotes = null;

        if ($has_footnotes && str_contains($result, '===FOOTNOTES===')) {

            [$content_part, $footnotes_part] = explode('===FOOTNOTES===', $result, 2);

            $translated_content = trim($content_part);
            $candidate          = trim($footnotes_part);

            // Only accept the footnotes section if it is valid JSON.
            if (json_decode($candidate) !== null) {
                $translated_footnotes = $candidate;
            } else {
                error_log('WPEnhance AI [Translation] footnotes section was not valid JSON — skipped.');
            }
        }

        $response = [
            'success'  => true,
            'output'   => $translated_content,
            'type'     => 'content',
            'language' => $language_name,
        ];

        if ($translated_footnotes !== null) {
            $response['footnotes'] = $translated_footnotes;
        }

        return $response;
    }
}
