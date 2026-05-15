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
            '1.0.6',
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
            '1.0.6'
        );
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
