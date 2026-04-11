<?php
/**
 * LSFLR: Language Switcher for Language Routing  – Object-based (no DOM, no parsing)
 */


/* -------------------------------------------------
 * DATA (LSFLR as provider)
 * ------------------------------------------------- */
function my_lsflr_get_languages() {

    $post_id = get_the_ID();

    if (!$post_id) return [];

    $translations = my_get_translations($post_id);

    if (empty($translations)) return [];

    $langs = [];

    foreach ($translations as $lang => $id) {

        $langs[] = [
            'code'    => $lang,
			// test
			'url' => my_lsflr_translate_current_url($lang, $id),
            //'url'     => get_permalink($id),
            //'label'   => strtoupper($lang),
			'label' => my_language_label($lang),
            'current' => ($lang === MY_LANG),
        ];
    }

    return $langs;
}

function my_lsflr_translate_current_url($target_lang, $post_id = null){

    $current_url = home_url($_SERVER['REQUEST_URI']);
    $langs = my_languages();
    $source = my_source_language();

    // Parse URL
    $parsed = parse_url($current_url);
    $path = trim($parsed['path'] ?? '', '/');
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

    $segments = explode('/', $path);

    // Detect current lang in URL
    if (!empty($segments[0]) && in_array($segments[0], $langs)) {
        array_shift($segments);
    }

    $new_path = implode('/', $segments);

    // =============================
    // 🔹 SINGULAR (use translation mapping)
    // =============================
    if (is_singular() && $post_id) {

        $url = get_permalink($post_id);

        // Preserve query string (important for Vik Booking)
        return $url . $query;
    }

    // =============================
    // 🔹 NON-SINGULAR (preserve path)
    // =============================
    if ($target_lang === $source) {
		// test
		return home_url('/' . trim($new_path, '/') . '/') . $query;
        // return home_url('/' . $new_path . '/') . $query;
    }

    return home_url('/' . $target_lang . '/' . $new_path . '/') . $query;
}

/**
 * Frontend assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'lsflr-css',
        plugin_dir_url(__FILE__) . '/assets/lsflr.css'
    );	
});


function my_is_valid_lang($lang){
    return in_array($lang, my_languages());
	// ====================================
	// Use this line to implement checks in other functions: 
	// if(!my_is_valid_lang($lang)) return;
	// ====================================
}

/* -------------------------------------------------
 * RENDER
 * ------------------------------------------------- */
function my_lsflr_render_switcher($atts = []) {

    $atts = wp_parse_args($atts, [
        'direction'   => 'down',
        'show'        => 'label', // label | custom | icon | icon-label
        'customLabel' => 'Language',
		'iconHtml' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M351.9 280l-190.9 0c2.9 64.5 17.2 123.9 37.5 167.4 11.4 24.5 23.7 41.8 35.1 52.4 11.2 10.5 18.9 12.2 22.9 12.2s11.7-1.7 22.9-12.2c11.4-10.6 23.7-28 35.1-52.4 20.3-43.5 34.6-102.9 37.5-167.4zM160.9 232l190.9 0C349 167.5 334.7 108.1 314.4 64.6 303 40.2 290.7 22.8 279.3 12.2 268.1 1.7 260.4 0 256.4 0s-11.7 1.7-22.9 12.2c-11.4 10.6-23.7 28-35.1 52.4-20.3 43.5-34.6 102.9-37.5 167.4zm-48 0C116.4 146.4 138.5 66.9 170.8 14.7 78.7 47.3 10.9 131.2 1.5 232l111.4 0zM1.5 280c9.4 100.8 77.2 184.7 169.3 217.3-32.3-52.2-54.4-131.7-57.9-217.3L1.5 280zm398.4 0c-3.5 85.6-25.6 165.1-57.9 217.3 92.1-32.7 159.9-116.5 169.3-217.3l-111.4 0zm111.4-48C501.9 131.2 434.1 47.3 342 14.7 374.3 66.9 396.4 146.4 399.9 232l111.4 0z"/></svg>',		
    ]);

    $langs = my_lsflr_get_languages();

    if (!$langs) return '';

    $current = null;
    $others  = [];

    foreach ($langs as $lang) {
        if ($lang['current']) $current = $lang;
        else $others[] = $lang;
    }

    if (!$current) {
        $current = $langs[0];
    }

    // Safe icon
	$get_icon = function($html) {

		// Remove inline width/height → let CSS control size
		$html = preg_replace('/(width|height)="[^"]*"/i', '', $html);

		$allowed = [
			'svg' => [
				'xmlns' => true,
				'viewbox' => true,
			],
			'path' => [
				'd' => true,
				'fill' => true,
			],
		];
		return wp_kses($html, $allowed);
	};
		
    // Toggle
    if ($atts['show'] === 'custom') {
        $toggle = esc_html($atts['customLabel']);
    } elseif ($atts['show'] === 'icon') {
        $toggle = $get_icon($atts['iconHtml']);
    } elseif ($atts['show'] === 'icon-label') {
        $toggle =
            '<span class="lsflr-icon">'.$get_icon($atts['iconHtml']).'</span>'.
            '<span class="lsflr-label">'.esc_html($current['label']).'</span>';
    } else {
        $toggle = esc_html($current['label']);
    }

    $dir = ($atts['direction'] === 'up') ? 'lsflr-dropup' : 'lsflr-dropdown';

    ob_start(); ?>

    <ul class="lsflr-switcher <?php echo esc_attr($dir); ?>">
        <li class="lsflr-toggle" tabindex="0">

            <div class="lsflr-current"><?php echo $toggle; ?></div>

            <?php if ($others): ?>
            <ul class="lsflr-submenu">
                <?php foreach ($others as $lang): ?>
                    <li>
                        <a href="<?php echo esc_url($lang['url']); ?>">
                            <?php echo esc_html($lang['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

        </li>
    </ul>

    <?php
    return ob_get_clean();
}


/* -------------------------------------------------
 * BLOCK
 * ------------------------------------------------- */
add_action('init', function () {

    wp_register_script(
        'lsflr-switcher-editor',
        'false',
        ['wp-blocks','wp-element','wp-components','wp-block-editor'],
        null,
        true
    );

    wp_add_inline_script('lsflr-switcher-editor', "
        (function(wp){
            const { registerBlockType } = wp.blocks;
            const { createElement: el } = wp.element;
            const { InspectorControls } = wp.blockEditor;
            const { PanelBody, SelectControl, TextControl } = wp.components;

            registerBlockType('custom/lsflr-switcher', {
				apiVersion: 3,
                title: 'LSFLR Switcher',
                icon: 'translation',
                category: 'widgets',

                attributes: {
                    direction: { type: 'string', default: 'down' },
                    show: { type: 'string', default: 'label' },
                    customLabel: { type: 'string', default: 'Language' },
                    iconHtml: { type: 'string', default: '🌐' }
                },
				/*
                edit: function(props) {
                    const { attributes, setAttributes } = props;

                    return el('div', {},

                        el(InspectorControls, {},
                            el(PanelBody, { title: 'Settings' },

                                el(SelectControl, {
                                    label: 'Direction',
                                    value: attributes.direction,
                                    options: [
                                        { label: 'Dropdown', value: 'down' },
                                        { label: 'Dropup', value: 'up' }
                                    ],
                                    onChange: function(v){ setAttributes({ direction: v }); }
                                }),

                                el(SelectControl, {
                                    label: 'Toggle Display',
                                    value: attributes.show,
                                    options: [
                                        { label: 'Current language', value: 'label' },
                                        { label: 'Custom label', value: 'custom' },
                                        { label: 'Icon only', value: 'icon' },
                                        { label: 'Icon + language', value: 'icon-label' }
                                    ],
                                    onChange: function(v){ setAttributes({ show: v }); }
                                }),

                                attributes.show === 'custom' &&
                                el(TextControl, {
                                    label: 'Custom label',
                                    value: attributes.customLabel,
                                    onChange: function(v){ setAttributes({ customLabel: v }); }
                                }),

                                (attributes.show === 'icon' || attributes.show === 'icon-label') &&
                                el(TextControl, {
                                    label: 'Icon (emoji or SVG)',
                                    value: attributes.iconHtml,
                                    onChange: function(v){ setAttributes({ iconHtml: v }); }
                                })

                            )
                        ),

                        el('div', {
                            style:{padding:'10px',border:'1px dashed #ccc',background:'#f9f9f9'}
                        }, 'LSFLR Switcher')
                    );
                },
				*/
				edit: function(props) {

					const { attributes, setAttributes } = props;

					const blockProps = wp.blockEditor.useBlockProps({
						style:{
							padding:'10px',
							border:'1px dashed #ccc',
							background:'#f9f9f9',
							cursor:'pointer'
						}
					});

					return el('div', blockProps,

						el(InspectorControls, {},
							el(PanelBody, { title: 'Settings' },

								el(SelectControl, {
									label: 'Direction',
									value: attributes.direction,
									options: [
										{ label: 'Dropdown', value: 'down' },
										{ label: 'Dropup', value: 'up' }
									],
									onChange: function(v){ setAttributes({ direction: v }); }
								}),

								el(SelectControl, {
									label: 'Toggle Display',
									value: attributes.show,
									options: [
										{ label: 'Current language', value: 'label' },
										{ label: 'Custom label', value: 'custom' },
										{ label: 'Icon only', value: 'icon' },
										{ label: 'Icon + language', value: 'icon-label' }
									],
									onChange: function(v){ setAttributes({ show: v }); }
								}),

								attributes.show === 'custom' &&
								el(TextControl, {
									label: 'Custom label',
									value: attributes.customLabel,
									onChange: function(v){ setAttributes({ customLabel: v }); }
								}),

								(attributes.show === 'icon' || attributes.show === 'icon-label') &&
								el(TextControl, {
									label: 'Icon (emoji or SVG)',
									value: attributes.iconHtml,
									onChange: function(v){ setAttributes({ iconHtml: v }); }
								})

							)
						),

						el('div', {}, 'LSFLR Switcher')
					);
				},			
                save: function(){ return null; }
            });

        })(window.wp);
    ");
	
	wp_enqueue_script('lsflr-switcher-editor');
	
    register_block_type('custom/lsflr-switcher', [
        'editor_script'   => 'lsflr-switcher-editor',
        'render_callback' => 'my_lsflr_render_switcher',
    ]);
});