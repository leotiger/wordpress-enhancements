<?php
/**
 * Accordion Scroll - improved UI behaviour for users
 * Author: Uli Hake
 * Version: 1.1
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    if (!has_block('core/accordion')) return;

    $js_path = __DIR__ . '/accordion-scroll.js';
    $version = file_exists($js_path) ? filemtime($js_path) : '1.1';

    wp_enqueue_script(
        'accordion-scroll',
        content_url('/mu-plugins/accordion-scroll/accordion-scroll.js'),
        [],
        $version,
        true
    );

    // Pass configurable settings to JS. Themes can override these via a filter
    // or by writing to window.accordionScrollConfig before this script runs.
    wp_add_inline_script(
        'accordion-scroll',
        'window.accordionScrollConfig=' . wp_json_encode([
            'headerSelector'      => '.site-header',
            'fallbackOffset'      => 110,
            'visibilityThreshold' => 0.3,
        ]) . ';',
        'before'
    );
});