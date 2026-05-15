<?php

namespace WPEnhance\AI\REST;

use WPEnhance\AI\Features\Registry;
use WPEnhance\AI\Features\Translation;

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

        // ── Toolbar chunk-translation endpoint ────────────────────────────────
        // Translates a free-form text snippet without requiring a post ID.
        // Used by the Admin Toolbar translate popover — completely independent
        // from the editor meta box translation feature.
        register_rest_route(
            'wpenhance-ai/v1',
            '/translate-chunk',
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'run_translate_chunk'],
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
     * Translate a free-form text chunk via the Admin Toolbar popover.
     *
     * Accepts:
     *   - target_language  (string)  ISO language code, e.g. "es"
     *   - chunk_text       (string)  The text to translate
     *
     * @param  \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function run_translate_chunk(\WP_REST_Request $request) {

        $params          = (array) ($request->get_json_params() ?? []);
        $target_language = sanitize_text_field($params['target_language'] ?? 'en');
        $languages       = Translation::get_languages();

        if (!array_key_exists($target_language, $languages)) {
            return new \WP_Error(
                'invalid_language',
                'Invalid target language.',
                ['status' => 400]
            );
        }

        $language_name = $languages[$target_language];

        /** @var Translation $translation */
        $translation = Registry::get('translation');

        if (!$translation) {
            return new \WP_Error(
                'feature_unavailable',
                'Translation feature is not registered.',
                ['status' => 500]
            );
        }

        return rest_ensure_response(
            $translation->run_chunk($language_name, $params)
        );
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
