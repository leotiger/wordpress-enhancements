<?php

namespace WPEnhance\AI\Admin;

use WPEnhance\AI\Features\Registry;

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
            '0.3.0',
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
            '0.3.0'
        );
    }

    public static function register(): void {

        add_meta_box(
            'wpenhance-ai',
            'WPEnhance AI',
            [self::class, 'render'],
            ['post', 'page'],
            'side',
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
                            <?php foreach ($ui_fields as $field): ?>

                                <?php if ($field['type'] === 'textarea'): ?>
                                    <label class="wpenhance-ai-label">
                                        <?php echo esc_html($field['label']); ?>
                                        <textarea
                                            class="wpenhance-ai-input-textarea"
                                            data-field="<?php echo esc_attr($field['name']); ?>"
                                            data-feature-ref="<?php echo esc_attr($feature->get_key()); ?>"
                                            rows="4"
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

                </div>

            <?php endforeach; ?>

            <div class="wpenhance-ai-result"></div>

        </div>
        <?php
    }
}
