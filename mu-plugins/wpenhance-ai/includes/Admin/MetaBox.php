<?php

namespace WPEnhance\AI\Admin;

use WPEnhance\AI\Features\Registry;
use WPEnhance\AI\Features\Translation;

defined('ABSPATH') || exit;

class MetaBox {

    public static function init(): void {

        add_action(
            'add_meta_boxes',
            [self::class, 'register']
        );

        add_action(
            'admin_enqueue_scripts',
            [self::class, 'enqueue']
        );

        // Editor top-toolbar button — loaded via two independent hooks so the
        // script is guaranteed to run in BOTH the post/page editor and the
        // FSE site/template editor regardless of which hook fires first.
        //
        // enqueue_block_editor_assets  → fires when the block editor boots
        //                                (post editor + usually site editor).
        // admin_enqueue_scripts        → fires for every admin page; we limit
        //                                it to site-editor.php as a hard
        //                                guarantee for the FSE context.
        add_action(
            'enqueue_block_editor_assets',
            [self::class, 'enqueue_editor_plugin']
        );
        add_action(
            'admin_enqueue_scripts',
            [self::class, 'enqueue_editor_plugin_for_site_editor']
        );
    }

    public static function enqueue(string $hook): void {

        // Only load on post create / edit screens.
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        wp_enqueue_script('wp-api');

        wp_enqueue_script(
            'wpenhance-ai-admin',
            WPENHANCE_AI_URL . '/assets/admin.js',
            ['wp-api'],
            '1.0.7',
            true
        );

        wp_localize_script(
            'wpenhance-ai-admin',
            'WPEnhanceAI',
            [
                'restUrl' => rest_url('wpenhance-ai/v1'),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]
        );

        wp_enqueue_style(
            'wpenhance-ai-admin',
            WPENHANCE_AI_URL . '/assets/admin.css',
            [],
            '1.0.7'
        );
    }

    /**
     * Enqueue the Gutenberg editor plugin (Quick Translate modal).
     *
     * Uses enqueue_block_editor_assets so it loads in both:
     *   - the classic post/page block editor  (post.php, post-new.php)
     *   - the full site / template editor     (site-editor.php)
     *
     * The wp-edit-post and wp-edit-site handles are listed as dependencies
     * so WordPress loads whichever is relevant for the current editor
     * context before our script runs.
     */
    public static function enqueue_editor_plugin(): void {

        if (!current_user_can('edit_posts')) {
            return;
        }

        wp_enqueue_style(
            'wpenhance-ai-editor',
            WPENHANCE_AI_URL . '/assets/editor-translate.css',
            ['wp-components'],
            '1.0.8'
        );

        // Pure vanilla JS — no WP React/component packages needed.
        $editor_deps = [];

        wp_enqueue_script(
            'wpenhance-ai-editor',
            WPENHANCE_AI_URL . '/assets/editor-translate.js',
            $editor_deps,
            '1.0.8',
            true
        );

        wp_localize_script(
            'wpenhance-ai-editor',
            'WPEnhanceAIEditor',
            [
                'restUrl'   => rest_url('wpenhance-ai/v1'),
                'nonce'     => wp_create_nonce('wp_rest'),
                'languages' => Translation::get_languages(),
            ]
        );
    }

    /**
     * Hard-guarantee that the editor translate script loads on the site editor
     * page via admin_enqueue_scripts, which always fires for every admin page.
     *
     * Covers WP installations where enqueue_block_editor_assets does not fire
     * reliably during site-editor.php initialisation.  wp_script_is() prevents
     * double-enqueuing when both hooks fire on the same page load.
     */
    public static function enqueue_editor_plugin_for_site_editor( string $hook ): void {

        // site-editor.php is the top-level FSE page slug in WP 6.x core.
        // The Gutenberg plugin may use a different slug — add variants here if needed.
        if ( $hook !== 'site-editor.php' ) {
            return;
        }

        // Avoid double-enqueue if enqueue_block_editor_assets already ran.
        if ( wp_script_is( 'wpenhance-ai-editor', 'enqueued' ) ) {
            return;
        }

        self::enqueue_editor_plugin();
    }

    public static function register(): void {

        add_meta_box(
            'wpenhance-ai',
            'WPEnhance AI',
            [self::class, 'render'],
            ['post', 'page'],
            'normal',
            'high'
        );
    }

    public static function render(
        \WP_Post $post
    ): void {

        $features = Registry::all();

        ?>
        <div class="wpenhance-ai-panel">

            <?php foreach ($features as $feature): ?>

                <div class="wpenhance-ai-feature-group">

                    <?php
                    $ui_fields = $feature->get_ui_fields();
                    $defaults  = $feature->get_field_defaults($post->ID);
                    ?>

                    <?php if (!empty($ui_fields)): ?>
                        <div class="wpenhance-ai-feature-fields">
                            <?php foreach ($ui_fields as $field):

                                // Conditional visibility: wrap in a div with data-condition-* attrs
                                // so JS can show/hide based on another field's current value.
                                $has_condition   = !empty($field['condition']) && is_array($field['condition']);
                                $condition_field = $has_condition ? (string) array_key_first($field['condition']) : '';
                                $condition_value = $has_condition ? (string) $field['condition'][$condition_field] : '';
                            ?>

                                <div
                                    class="wpenhance-ai-field-wrapper"
                                    <?php if ($has_condition): ?>
                                        data-condition-field="<?php echo esc_attr($condition_field); ?>"
                                        data-condition-value="<?php echo esc_attr($condition_value); ?>"
                                    <?php endif; ?>
                                >

                                <?php if ($field['type'] === 'textarea'): ?>
                                    <label class="wpenhance-ai-label">
                                        <?php echo esc_html($field['label']); ?>
                                        <textarea
                                            class="wpenhance-ai-input-textarea"
                                            data-field="<?php echo esc_attr($field['name']); ?>"
                                            data-feature-ref="<?php echo esc_attr($feature->get_key()); ?>"
                                            rows="<?php echo esc_attr($field['rows'] ?? 4); ?>"
                                            placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                                        ><?php echo esc_textarea($defaults[$field['name']] ?? ''); ?></textarea>
                                    </label>
                                <?php endif; ?>

                                <?php if ($field['type'] === 'select'): ?>
                                    <label class="wpenhance-ai-label">
                                        <?php echo esc_html($field['label']); ?>
                                        <select
                                            class="wpenhance-ai-select"
                                            data-field="<?php echo esc_attr($field['name']); ?>"
                                            data-feature-ref="<?php echo esc_attr($feature->get_key()); ?>"
                                        >
                                            <?php
                                            $selected_val = $defaults[$field['name']] ?? '';
                                            foreach ($field['options'] as $val => $label):
                                            ?>
                                                <option
                                                    value="<?php echo esc_attr($val); ?>"
                                                    <?php selected($selected_val, $val); ?>
                                                >
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endif; ?>

                                </div><!-- .wpenhance-ai-field-wrapper -->

                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <button
                        type="button"
                        class="button button-secondary wpenhance-ai-action"
                        data-feature="<?php echo esc_attr($feature->get_key()); ?>"
                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                    >
                        <?php echo esc_html($feature->get_label()); ?>
                    </button>

                    <div class="wpenhance-ai-result"></div>

                </div>

            <?php endforeach; ?>

        </div>
        <?php
    }
}
