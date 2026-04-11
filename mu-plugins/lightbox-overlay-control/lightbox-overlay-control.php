<?php
/**
 * Plugin Name: Lightbox Overlay Control (MU Module)
 * Description: Adds overlay styling controls to Gutenberg Image & Gallery lightbox.
 * Author: Uli Hake
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

define('LOC_PATH', __DIR__);
define('LOC_URL', plugin_dir_url(__FILE__));

/**
 * Editor assets
 */
add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'loc-editor',
        LOC_URL . 'assets/editor.js',
		['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-hooks'],
        filemtime(LOC_PATH . '/assets/editor.js')
    );
});

/**
 * Frontend assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'loc-frontend',
        LOC_URL . 'assets/frontend.js',
        [],
        filemtime(LOC_PATH . '/assets/frontend.js'),
        true
    );
	
    wp_enqueue_script(
        'loc-carousel-lightbox',
        plugin_dir_url(__FILE__) . 'assets/carousel-lightbox.js',
        [],
        '1.0',
        true
    );	

    wp_enqueue_style(
        'loc-carousel-lightbox',
        plugin_dir_url(__FILE__) . 'assets/carousel-lightbox.css'
    );
	
    wp_enqueue_style(
        'loc-style',
        LOC_URL . 'assets/style.css',
        [],
        filemtime(LOC_PATH . '/assets/style.css')
    );
});

/*
add_filter('render_block', function ($block_content, $block) {

    // 🎯 Only target blocks that act as carousels
    // Adjust these conditions depending on your setup

    if (
        strpos($block_content, 'swiper') === false &&
        strpos($block_content, 'splide') === false &&
        strpos($block_content, 'flickity') === false &&
        strpos($block_content, 'data-carousel') === false
    ) {
        return $block_content;
    }

    // ❌ Skip real galleries
    if (strpos($block_content, 'wp-block-gallery') !== false) {
        return $block_content;
    }

    // 🔥 Generate stable galleryId
    static $gallery_index = 0;
    $gallery_index++;
    $gallery_id = 'loc-' . $gallery_index;

    //**
    // * ✅ Inject lightbox context into container
    // *
    $block_content = preg_replace(
        '/(<div[^>]*class="[^"]*(swiper|splide|flickity|carousel)[^"]*"[^>]*)>/',
        '$1 data-wp-interactive="core/lightbox" data-wp-context=\'{"galleryId":"' . $gallery_id . '"}\'>',
        $block_content,
        1
    );

    // **
    // * ✅ Ensure images are wrapped in links
    // *
    $block_content = preg_replace_callback(
        '/<img([^>]+?)src="([^"]+)"([^>]*)>/',
        function ($matches) {

            $img = $matches[0];
            $src = $matches[2];

            // already wrapped → skip
            if (strpos($matches[0], '<a') !== false) {
                return $img;
            }

            return '<a href="' . esc_url($src) . '">' . $img . '</a>';
        },
        $block_content
    );

    return $block_content;

}, 20, 2);
*/