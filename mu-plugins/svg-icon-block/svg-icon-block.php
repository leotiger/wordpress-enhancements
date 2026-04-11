<?php
/**
 * SVG Icon Block
 * Author: Uli Hake
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {

    $sprite = sib_get_sprite_url();

    // Preload (high priority)
    echo '<link rel="preload" as="image" href="' . esc_url($sprite) . '" type="image/svg+xml">';

    // Prefetch (low priority fallback)
    echo '<link rel="prefetch" href="' . esc_url($sprite) . '">';

}, 1);

add_action('init', function () {
    if (isset($_GET['flush_icons_cache'])) {
        delete_transient('sib_icon_data_v1');
    }
});


/**
 * Sprite URL (filterable)
 */
function sib_get_sprite_url() {
	
    return apply_filters(
        'sib_sprite_url',
        set_url_scheme(
            get_stylesheet_directory_uri() . '/assets/icons/icons.svg',
            'https'
        )
    );
	
	//return 'https://' . $_SERVER['HTTP_HOST'] . '/wp-content/themes/cal-talaia/assets/icons/icons.svg';	
}

/**
 * Load + cache JSON data
 */
function sib_get_icon_data() {

    $cache_key = 'sib_icon_data_v1';

    // Try cache first
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    // File paths
    $base = __DIR__;
    $icons_path = $base . '/icons.json';
    $meta_path  = $base . '/icons-meta.json';
    $i18n_path  = $base . '/icons-i18n.json';

    // Load safely
    $data = [
        //'sprite' => sib_get_sprite_url(),
        'icons'  => file_exists($icons_path) ? json_decode(file_get_contents($icons_path), true) : [],
        'meta'   => file_exists($meta_path)  ? json_decode(file_get_contents($meta_path), true)  : [],
        'i18n'   => file_exists($i18n_path)  ? json_decode(file_get_contents($i18n_path), true)  : [],
        'version' => [
            filemtime($icons_path) ?: 0,
            filemtime($meta_path) ?: 0,
            filemtime($i18n_path) ?: 0
        ]
    ];

    // Store cache (12 hours)
    set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);

    return $data;
}



/**
 * Register block
 */
add_action('init', function () {

    // Register script
    wp_register_script(
        'sib-icon-block-editor',
        plugins_url('block.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
        filemtime(__DIR__ . '/block.js'),
        true
    );

    // Get cached data
    $data = sib_get_icon_data();
	$data['sprite'] = sib_get_sprite_url(); // always fresh	

    // Inject into JS
    wp_add_inline_script(
        'sib-icon-block-editor',
        'window.SIB_DATA = ' . wp_json_encode($data) . ';',
        'before'
    );

    // Register block
    register_block_type(__DIR__, [
        'editor_script' => 'sib-icon-block-editor',

		'render_callback' => function ($attributes) {

			$icon = $attributes['icon'] ?? '';
			if (!$icon) return '';

			$sprite = sib_get_sprite_url();

			$size  = intval($attributes['size'] ?? 24);
			$rotation = intval($attributes['rotation'] ?? 0);

			$variant = $attributes['variant'] ?? 'primary';
			$label = $attributes['label'] ?? '';

			$iconColor = $attributes['iconColor'] ?? '';
			$textColor = $attributes['textColor'] ?? '';

			$iconPosition = $attributes['iconPosition'] ?? 'left';
			
			$flipH = !empty($attributes['flipH']);
			$flipV = !empty($attributes['flipV']);			

			$justify = $attributes['justifyContent'] ?? 'flex-start';
			$align   = $attributes['alignItems'] ?? 'center';

			$url   = $attributes['url'] ?? '';
			$target = !empty($attributes['opensInNewTab']) ? ' target="_blank"' : '';

			$rel   = $attributes['rel'] ?? '';
			if (!empty($attributes['opensInNewTab']) && empty($rel)) {
				$rel = 'noopener noreferrer';
			}
			$rel_attr = $rel ? ' rel="' . esc_attr($rel) . '"' : '';

			/* -------- WRAPPER -------- */

			$wrapper_style = 'display:flex;';
			$wrapper_style .= 'justify-content:' . esc_attr($justify) . ';';
			$wrapper_style .= 'align-items:' . esc_attr($align) . ';';
			$wrapper_style .= 'width:auto;';

			/* -------- FLEX DIRECTION -------- */

			$flex_direction =
				$iconPosition === 'right'  ? 'row-reverse' :
				($iconPosition === 'top'   ? 'column' :
				($iconPosition === 'bottom'? 'column-reverse' :
											'row'));

			/* -------- SVG -------- */

			$svg_style = 'width:' . $size . 'px;height:' . $size . 'px;';
			if ($iconColor) {
				$svg_style .= 'fill:' . esc_attr($iconColor) . ';';
				$svg_style .= 'color:' . esc_attr($iconColor) . ';';
			}
			/*
			if ($rotation) {
				$svg_style .= 'transform:rotate(' . $rotation . 'deg);';
			}
			*/
			$transform = '';

			if ($rotation) {
				$transform .= 'rotate(' . $rotation . 'deg) ';
			}

			if ($flipH || $flipV) {
				$scaleX = $flipH ? -1 : 1;
				$scaleY = $flipV ? -1 : 1;
				$transform .= 'scale(' . $scaleX . ',' . $scaleY . ')';
			}

			if ($transform) {
				$svg_style .= 'transform:' . trim($transform) . ';';
			}			
			
			$svg = '<svg class="sib-icon" style="' . esc_attr($svg_style) . '">
				<use href="' . esc_url($sprite) . '#' . esc_attr($icon) . '"></use>
			</svg>';

			/* -------- BUTTON -------- */

			$button_class = 'sib-button sib-' . esc_attr($variant) . ' ' . ($label ? 'has-label' : 'icon-only');

			$button_style = 'display:inline-flex;';
			$button_style .= 'flex-direction:' . esc_attr($flex_direction) . ';';
			$button_style .= 'align-items:flex-start;';
			$button_style .= 'vertical-align:middle;';
			$button_style .= 'gap:8px;';

			if ($textColor) {
				$button_style .= 'color:' . esc_attr($textColor) . ';';
			}

			$button = '<div class="' . $button_class . '" style="' . esc_attr($button_style) . '">';

			/* -------- CONTENT ORDER (same as JS) -------- */

			$button .= $svg;

			if ($label) {
				$button .= '<span class="sib-label">' . esc_html($label) . '</span>';
			}

			$button .= '</div>';

			/* -------- LINK WRAP -------- */

			if ($url) {
				$button = '<a href="' . esc_url($url) . '"' . $target . $rel_attr . ' class="sib-link">' . $button . '</a>';
			}

			/* -------- FINAL -------- */

			return '<div class="sib-wrapper" style="' . esc_attr($wrapper_style) . '">' . $button . '</div>';
		}
		
    ]);
});

add_action('enqueue_block_editor_assets', function () {

    $sprite = sib_get_sprite_url();

    wp_add_inline_script(
        'sib-icon-block-editor',
        '(function(){
            var link = document.createElement("link");
            link.rel = "preload";
            link.as = "image";
            link.href = "' . esc_url($sprite) . '";
            link.type = "image/svg+xml";
            document.head.appendChild(link);
        })();',
        'before'
    );
});
