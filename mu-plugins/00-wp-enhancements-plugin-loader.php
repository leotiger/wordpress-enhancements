<?php
/**
 * MU Plugins Loader
 * Loads modular MU plugins from subfolders
 */

$mu_plugins = [
    'lightbox-overlay-control/lightbox-overlay-control.php',
	'svg-icon-block/svg-icon-block.php',
	'accordion-scroll/accordion-scroll.php',
	'language-router/language-router.php',
	'gutenberg-enhancements/gutenberg-enhancements.php',
];

foreach ($mu_plugins as $plugin) {
    $path = __DIR__ . '/' . $plugin;

    if (file_exists($path)) {
        require_once $path;
    }
}
