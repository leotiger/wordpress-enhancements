<?php

namespace WPEnhance\AI\Features;

use WPEnhance\AI\Features\Contracts\FeatureInterface;
use WPEnhance\AI\Providers\ProviderFactory;
use WPEnhance\AI\Providers\WorkerConfig;
use WPEnhance\AI\Core\CacheStore;

defined('ABSPATH') || exit;

/**
 * Generates or rewrites post content using AI.
 *
 * Supports multiple content types (full article, introduction, outline)
 * and tones (informative, persuasive, storytelling, technical).
 * Returns a 'content' type result so the existing JS renderer handles
 * the "Apply to Editor" / "Copy" flow out of the box.
 */
class ContentGenerator implements FeatureInterface {

    /** @var array<string, string> Available writing tones. */
    private const TONES = [
        'informative'  => 'Informative',
        'persuasive'   => 'Persuasive',
        'storytelling' => 'Storytelling',
        'technical'    => 'Technical',
        'conversational' => 'Conversational',
    ];

    /** @var array<string, string> Output types the model can produce. */
    private const CONTENT_TYPES = [
        'full_article' => 'Full Article',
        'introduction' => 'Introduction only',
        'outline'      => 'Structured Outline',
    ];

    public function get_key(): string {

        return 'content-generator';
    }

    public function get_label(): string {

        return 'Generate Content';
    }

    /**
     * Sonnet for quality long-form generation.
     */
    public function get_worker_config(): WorkerConfig {

        return new WorkerConfig(
            model:       'claude-sonnet-4-6',
            max_tokens:  8192,
            temperature: 0.6,
        );
    }

    public function get_ui_fields(): array {

        return [
            [
                'name'        => 'hints',
                'type'        => 'textarea',
                'label'       => 'Hints',
                'placeholder' => 'Key points, ideas, or structure to build from…',
            ],
            [
                'name'    => 'tone',
                'type'    => 'select',
                'label'   => 'Tone',
                'options' => self::TONES,
            ],
            [
                'name'    => 'content_type',
                'type'    => 'select',
                'label'   => 'Output',
                'options' => self::CONTENT_TYPES,
            ],
        ];
    }

    public function get_field_defaults(int $post_id): array {

        return [
            'hints'        => '',
            'tone'         => 'informative',
            'content_type' => 'full_article',
        ];
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

        // ── Validate params ───────────────────────────────────────────────────
        $tone = sanitize_key($params['tone'] ?? 'informative');
        if (!array_key_exists($tone, self::TONES)) {
            $tone = 'informative';
        }

        $content_type = sanitize_key($params['content_type'] ?? 'full_article');
        if (!array_key_exists($content_type, self::CONTENT_TYPES)) {
            $content_type = 'full_article';
        }

        $tone_label         = self::TONES[$tone];
        $content_type_label = self::CONTENT_TYPES[$content_type];

        // ── Hints ─────────────────────────────────────────────────────────────
        // When hints are provided they take priority over the post body.
        // If no hints, fall back to existing post content as context.
        $hints = mb_substr(trim(sanitize_textarea_field($params['hints'] ?? '')), 0, 2000);

        // ── Cache check ───────────────────────────────────────────────────────
        // Cache is keyed per tone + content_type combination.
        $cache_key = $this->get_key() . '_' . $tone . '_' . $content_type;
        $hash      = CacheStore::hash([
            $post->post_title,
            $post->post_content,
            $tone,
            $content_type,
            $hints,
        ]);

        $cached = empty($params['force_refresh'])
            ? CacheStore::get($post_id, $cache_key, $hash)
            : null;

        if ($cached !== null) {
            return array_merge(['success' => true, 'cached' => true], $cached);
        }

        // ── Build prompt ──────────────────────────────────────────────────────
        $prompt_tpl = file_get_contents(
            WPENHANCE_AI_PATH . '/templates/prompts/content-generator.txt'
        );

        if ($prompt_tpl === false) {
            return [
                'success' => false,
                'error'   => 'Prompt template not found.',
            ];
        }

        // Use hints as the seed when provided; otherwise fall back to the
        // existing post body so the model can rewrite / extend it.
        if ($hints !== '') {
            $seed_section = "\n\nHints and key points to build from:\n" . $hints;
        } else {
            $existing_content = trim(wp_strip_all_tags($post->post_content));
            $seed_section     = $existing_content !== ''
                ? "\n\nExisting content (use as context or rewrite as needed):\n" .
                  mb_substr($existing_content, 0, 6000)
                : '';
        }

        $prompt = str_replace(
            ['{{title}}', '{{tone}}', '{{content_type}}', '{{existing_content}}'],
            [
                $post->post_title,
                $tone_label,
                $content_type_label,
                $seed_section,
            ],
            $prompt_tpl
        );

        // ── API call ──────────────────────────────────────────────────────────
        $provider = ProviderFactory::make($this->get_worker_config());

        $result = $provider->chat([
            [
                'role'    => 'system',
                'content' =>
                    'You are an expert WordPress content writer. ' .
                    'Output clean WordPress block-editor (Gutenberg) markup. ' .
                    'Use <!-- wp:paragraph -->, <!-- wp:heading -->, ' .
                    '<!-- wp:list --> and similar block comments where appropriate. ' .
                    'Do not include front-matter, meta-commentary, or explanations — ' .
                    'output only the post body markup.',
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ]);

        if (empty($result)) {
            return [
                'success' => false,
                'error'   => 'Content generation failed. Please try again.',
            ];
        }

        $payload = [
            'output'       => trim($result),
            'type'         => 'content',
            'tone'         => $tone_label,
            'content_type' => $content_type_label,
        ];

        CacheStore::set($post_id, $cache_key, $hash, $payload);

        return array_merge(['success' => true], $payload);
    }
}
