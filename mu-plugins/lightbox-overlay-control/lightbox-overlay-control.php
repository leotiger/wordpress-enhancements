<?php
/**
 * Plugin Name: Lightbox Overlay Carousel for Carousel Slider Block (MU Module)
 * Description: Adds overlay carousel for images included in Carousel Slider Block.
 * Author: Uli Hake
 * Version: 1.1
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

add_action('wp_footer', function () {
?>
<script>
	(function () {
		document.addEventListener('pointerdown', function(e) {
			document.querySelectorAll('.swiper-slide figure img').forEach(img => {

			if (img.dataset.src && !img.src.includes(img.dataset.src)) {
			  img.src = img.dataset.src;
			}

			if (img.dataset.srcset && !img.srcset) {
			  img.srcset = img.dataset.srcset;
			}

			const picture = img.closest('picture');

			if (picture) {
			  picture.querySelectorAll('source').forEach(source => {

				if (source.dataset.srcset && !source.srcset) {
				  source.srcset = source.dataset.srcset;
				}

			  });
			}

		  });

		}, true);
		
		document.addEventListener('click', async function(e) {

			const trigger = e.target.closest('.wp-lightbox-container');
			if (!trigger) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();

			const images = document.querySelectorAll('.swiper-slide figure img');

			await Promise.all([...images].map(img => {

				return new Promise(resolve => {

					if (img.dataset.src) {
						img.src = img.dataset.src;
					}

					if (img.dataset.srcset) {
						img.srcset = img.dataset.srcset;
					}

					if (img.dataset.sizes) {
						img.sizes = img.dataset.sizes;
					}

					const picture = img.closest('picture');

					if (picture) {
						picture.querySelectorAll('source').forEach(source => {

							if (source.dataset.srcset) {
								source.srcset = source.dataset.srcset;
							}

						});
					}

					if (img.complete) {
						resolve();
						return;
					}

					img.onload = resolve;
					img.onerror = resolve;

				});

			}));
			// allow browser to commit layout/source selection
			await new Promise(requestAnimationFrame);

			replayingLightboxClick = true;
			await new Promise(resolve => setTimeout(resolve, 50));
			trigger.click();

		}, true);	
	})();	
</script>
<?php
}, 100);
