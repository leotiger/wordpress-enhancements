<?php

namespace WPEnhance\AI\REST;

use WPEnhance\AI\Features\Registry;

defined('ABSPATH') || exit;

class FeatureController {

    public static function init(): void {

        add_action(
            'rest_api_init',
            [self::class, 'register_routes']
        );
    }

    public static function register_routes(): void {

        register_rest_route(
            'wpenhance-ai/v1',
            '/feature/(?P<feature>[a-z0-9\-]+)/(?P<id>\d+)',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'run'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'wpenhance-ai/v1',
            '/footnotes/(?P<id>\d+)',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'save_footnotes'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    public static function run(
        \WP_REST_Request $request
    ) {
        $feature_key = sanitize_key($request['feature']);
        $post_id     = (int) $request['id'];

        // Collect extra parameters sent as a JSON body.
        $params = (array) ($request->get_json_params() ?? []);

        $feature = Registry::get($feature_key);

        if (!$feature) {

            return new \WP_Error(
                'invalid_feature',
                'Unknown feature.',
                ['status' => 404]
            );
        }

        if (!$feature->supports($post_id)) {

            return new \WP_Error(
                'forbidden',
                'Access denied.',
                ['status' => 403]
            );
        }

        return rest_ensure_response(
            $feature->run($post_id, $params)
        );
    }

    /**
     * Persist translated footnotes to the post's _footnotes meta.
     *
     * Expects a JSON body: { "footnotes": "[{\"id\":\"…\",\"content\":\"…\"}]" }
     *
     * The footnotes value must be a JSON-encoded array — matching the format
     * WordPress core uses for the native footnotes block.
     */
    public static function save_footnotes(
        \WP_REST_Request $request
    ) {
        $post_id = (int) $request['id'];

        if (!get_post($post_id)) {

            return new \WP_Error(
                'not_found',
                'Post not found.',
                ['status' => 404]
            );
        }

        if (!current_user_can('edit_post', $post_id)) {

            return new \WP_Error(
                'forbidden',
                'Access denied.',
                ['status' => 403]
            );
        }

        $params    = (array) ($request->get_json_params() ?? []);
        $footnotes = $params['footnotes'] ?? '';

        if (!is_string($footnotes) || json_decode($footnotes) === null) {

            return new \WP_Error(
                'invalid_footnotes',
                'Footnotes must be a JSON-encoded array.',
                ['status' => 400]
            );
        }

        update_post_meta($post_id, '_footnotes', $footnotes);

        return rest_ensure_response(['success' => true]);
    }
}
