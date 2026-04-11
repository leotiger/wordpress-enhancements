<?php
/**
 * Accordion Scroll - improved UI behaviour for users
 * Author: Uli Hake
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
    if (!has_block('core/accordion')) return;

    wp_enqueue_script(
        'accordion-scroll',
        content_url('/mu-plugins/accordion-scroll/accordion-scroll.js'),
        [],
        '1.0',
        true
    );
});