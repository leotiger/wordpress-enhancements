<?php

namespace WPEnhance\AI\Features;

use WPEnhance\AI\Features\Contracts\FeatureInterface;
use WPEnhance\AI\Providers\ProviderFactory;
use WPEnhance\AI\Providers\WorkerConfig;
use WPEnhance\AI\Core\CacheStore;

defined('ABSPATH') || exit;

class MetaDescription implements FeatureInterface {

    public function get_key(): string {

        return 'meta-description';
    }

    public function get_label(): string {

        return 'Generate Meta Description';
    }

    /**
     * Haiku is sufficient for short, structured outputs like meta
     * descriptions — fast and cost-effective.
     */
    public function get_worker_config(): WorkerConfig {

        return new WorkerConfig(
            model:       'claude-haiku-4-5-20251001',
            max_tokens:  256,
            temperature: 0.4,
        );
    }

    public function get_ui_fields(): array {

        return [];
    }

    public function get_field_defaults(int $post_id): array {

        return [];
    }

    public function supports(int $post_id): bool {

        return current_user_can('edit_post', $post_id);
    }

    public function run(int $post_id, array $params = []): array {

        $post = get_post($post_id);

        if (!$post) {

            return ['success' => false];
        }

        $content = wp_strip_all_tags($post->post_content);

        $locale = get_post_meta($post_id, '_lang', true)
            ?: determine_locale();

        // ── Cache check ───────────────────────────────────────────────────────
        $hash   = CacheStore::hash([$post->post_content, $post->post_title, $locale]);
        $cached = empty($params['force_refresh'])
            ? CacheStore::get($post_id, $this->get_key(), $hash)
            : null;

        if ($cached !== null) {
            return array_merge(['success' => true, 'cached' => true], $cached);
        }

        // ── API call ──────────────────────────────────────────────────────────
        $prompt = file_get_contents(
            WPENHANCE_AI_PATH .
            '/templates/prompts/meta-description.txt'
        );

        if ($prompt === false) {
            return [
                'success' => false,
                'error'   => 'Prompt template not found.',
            ];
        }

        $prompt = str_replace(
            ['{{locale}}', '{{title}}', '{{content}}'],
            [
                $locale,
                $post->post_title,
                mb_substr($content, 0, 5000),
            ],
            $prompt
        );

        $provider = ProviderFactory::make(
            $this->get_worker_config()
        );

        $result = $provider->chat([
            [
                'role'    => 'system',
                'content' => 'You are an SEO copywriter.',
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ]);

        if ($result === null || $result === '') {
            return [
                'success' => false,
                'error'   => 'No response from AI provider. Check your API key and try again.',
            ];
        }

        $payload = ['output' => $result, 'type' => 'text'];

        CacheStore::set($post_id, $this->get_key(), $hash, $payload);

        return array_merge(['success' => true], $payload);
    }
}
