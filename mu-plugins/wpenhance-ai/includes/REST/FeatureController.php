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

        // Strip any <br> tags that survive — or are re-introduced by
        // wpautop (which some plugins/themes apply to REST responses via
        // the_content filter).  Priority 999 ensures this runs after every
        // other filter, including wpautop, so it is truly the last step
        // before the response is echoed to the browser.
        add_filter(
            'rest_pre_echo_response',
            [self::class, 'strip_br_from_output'],
            999,
            3
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

    /**
     * Remove every <br> variant from the 'output' field of our feature
     * responses right before WordPress echoes the REST reply.
     *
     * Running at priority 999 guarantees this executes after wpautop
     * (priority 10) and any other plugin that hooks into rest_pre_echo_response
     * to apply the_content filters.  wpautop converts newlines between
     * Gutenberg block comments and their inner HTML to <br /> tags, which
     * breaks the block parser.  Stripping unconditionally is safe: the worst
     * outcome is losing a soft line-break (Shift+Enter) inside a paragraph —
     * a trivial manual fix — whereas a stray <br> breaks block structure.
     *
     * @param mixed            $result  Raw response data (array or scalar).
     * @param \WP_REST_Server  $server  REST server instance (unused).
     * @param \WP_REST_Request $request Current request.
     * @return mixed
     */
    public static function strip_br_from_output($result, $server, $request) {

        if (!is_array($result)) {
            return $result;
        }

        // Target only our feature endpoint so we do not touch other routes.
        if (strpos($request->get_route(), '/wpenhance-ai/v1/feature/') === false) {
            return $result;
        }

        if (isset($result['output']) && is_string($result['output'])) {
            $result['output'] = preg_replace('/<br[^>]*>/i', '', $result['output']);
        }

        return $result;
    }
}
