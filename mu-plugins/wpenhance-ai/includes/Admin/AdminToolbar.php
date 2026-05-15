<?php

namespace WPEnhance\AI\Admin;

use WPEnhance\AI\Features\Translation;

defined('ABSPATH') || exit;

/**
 * Admin Toolbar — Quick Translate popover
 *
 * Registers a "Translate" node in the WordPress Admin Bar that opens an
 * inline popover for translating any text snippet into a chosen language.
 * The feature is completely independent from the editor meta-box translation
 * workflow: it uses a dedicated REST endpoint and its own JS/CSS bundle, so
 * it works on every admin screen and on the front-end admin bar alike.
 */
class AdminToolbar {

    public static function init(): void {

        add_action('admin_bar_menu',        [self::class, 'register_node'], 100);
        add_action('wp_enqueue_scripts',    [self::class, 'enqueue']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    // ── Admin Bar node ────────────────────────────────────────────────────────

    public static function register_node(\WP_Admin_Bar $bar): void {

        if (!current_user_can('edit_posts')) {
            return;
        }

        $bar->add_node([
            'id'    => 'wpenhance-ai-translate',
            'title' =>
                '<span class="ab-icon dashicons dashicons-translation" aria-hidden="true"></span>' .
                '<span class="ab-label">Translate</span>',
            'href'  => '#',
            'meta'  => [
                'class' => 'wpenhance-ai-toolbar-item',
                'title' => 'WPEnhance AI — Quick Translate',
            ],
        ]);
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────

    public static function enqueue(): void {

        if (!is_admin_bar_showing() || !current_user_can('edit_posts')) {
            return;
        }

        wp_enqueue_style(
            'wpenhance-ai-toolbar',
            WPENHANCE_AI_URL . '/assets/toolbar-translate.css',
            [],
            '1.1.0'
        );

        wp_enqueue_script(
            'wpenhance-ai-toolbar',
            WPENHANCE_AI_URL . '/assets/toolbar-translate.js',
            [],
            '1.1.0',
            true   // load in footer
        );

        wp_localize_script(
            'wpenhance-ai-toolbar',
            'WPEnhanceAIToolbar',
            [
                'restUrl'   => rest_url('wpenhance-ai/v1'),
                'nonce'     => wp_create_nonce('wp_rest'),
                'languages' => Translation::get_languages(),
            ]
        );
    }
}
