<?php
/**
 * Plugin Name: Lightbox Overlay Carousel for Carousel Slider Block (MU Module)
 * Description: Adds overlay carousel for images included in Carousel Slider Block.
 * Author: Uli Hake
 * Version: 1.1.3
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
        LOC_URL . 'assets/carousel-lightbox.js',
        [],
        filemtime(LOC_PATH . '/assets/carousel-lightbox.js'),
        true
    );

    wp_enqueue_style(
        'loc-carousel-lightbox',
        LOC_URL . 'assets/carousel-lightbox.css',
        [],
        filemtime(LOC_PATH . '/assets/carousel-lightbox.css')
    );
	
    wp_enqueue_style(
        'loc-style',
        LOC_URL . 'assets/style.css',
        [],
        filemtime(LOC_PATH . '/assets/style.css')
    );
});

add_action('wp_footer', function () {
?>
<script>
(function () {

	document.addEventListener('pointerenter', function(e) {

		const container = e.target.closest('.wp-lightbox-container');

		if (!container) {
			return;
		}

		const gallery = container.closest('.swiper');

		if (!gallery) {
			return;
		}

		gallery.querySelectorAll('.swiper-slide figure img').forEach(img => {

			if (img.dataset.src) {
				img.src = img.dataset.src;
			}

			if (img.dataset.srcset) {
				img.srcset = img.dataset.srcset;
			}

			const picture = img.closest('picture');

			if (picture) {

				picture.querySelectorAll('source').forEach(source => {

					if (source.dataset.srcset) {
						source.srcset = source.dataset.srcset;
					}

				});

			}

		});

		container.querySelectorAll('img.lazyload, img.lazyloading').forEach(img => {

			if (img.dataset.src) {
				img.src = img.dataset.src;
			}

			if (img.dataset.srcset) {
				img.srcset = img.dataset.srcset;
			}

			img.classList.remove('lazyload');
			img.classList.remove('lazyloading');
			img.classList.add('lazyloaded');

		});

	}, {
		capture: true,
		passive: true
	});

})();	
</script>
<?php
}, 100);
