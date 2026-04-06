<?php
/**
 * MSLS Switcher – Object-based (no DOM, no parsing)
 */


/* -------------------------------------------------
 * DATA (MSLS as provider)
 * ------------------------------------------------- */
function my_msls_get_languages() {

    if (!function_exists('the_msls')) {
        return [];
    }

    // ----------------------------------------
    // 1. Get MSLS output (guaranteed to work)
    // ----------------------------------------
    ob_start();
    the_msls();
    $html = ob_get_clean();

    if (!$html) {
        return [];
    }

    // ----------------------------------------
    // 2. Parse links (robust)
    // ----------------------------------------
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    $links = $dom->getElementsByTagName('a');

    if ($links->length === 0) {
        return [];
    }

    // ----------------------------------------
    // 3. Normalize
    // ----------------------------------------
    $current_lang = strtolower(substr(get_bloginfo('language'), 0, 2));

    $map = [
        'en' => 'English',
        'es' => 'Español',
        'ca' => 'Català',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ];

    $reverse_map = array_flip($map);

    $langs = [];

    foreach ($links as $a) {

        $href = $a->getAttribute('href');
        if (!$href) continue;

        $text = trim($a->textContent);

        // Fix en_US → English
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $text)) {
            $locale_map = [
                'en_US' => 'English',
                'es_ES' => 'Español',
                'ca_ES' => 'Català',
                'fr_FR' => 'Français',
                'de_DE' => 'Deutsch',
            ];
            $text = $locale_map[$text] ?? $text;
        }

        $code = $reverse_map[$text] ?? null;

        $langs[] = [
            'code'    => $code,
            'url'     => $href,
            'label'   => $text,
            'current' => ($code === $current_lang),
        ];
    }

    // ----------------------------------------
    // 4. Ensure current exists
    // ----------------------------------------
    $has_current = false;

    foreach ($langs as $l) {
        if ($l['current']) {
            $has_current = true;
            break;
        }
    }

    if (!$has_current) {

        $langs[] = [
            'code'    => $current_lang,
            'url'     => home_url('/'),
            'label'   => $map[$current_lang] ?? strtoupper($current_lang),
            'current' => true,
        ];
    }

    return $langs;
}

/* -------------------------------------------------
 * RENDER
 * ------------------------------------------------- */
function my_msls_render_switcher($atts = []) {

    $atts = wp_parse_args($atts, [
        'direction'   => 'down',
        'show'        => 'label', // label | custom | icon | icon-label
        'customLabel' => 'Language',
		'iconHtml' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M351.9 280l-190.9 0c2.9 64.5 17.2 123.9 37.5 167.4 11.4 24.5 23.7 41.8 35.1 52.4 11.2 10.5 18.9 12.2 22.9 12.2s11.7-1.7 22.9-12.2c11.4-10.6 23.7-28 35.1-52.4 20.3-43.5 34.6-102.9 37.5-167.4zM160.9 232l190.9 0C349 167.5 334.7 108.1 314.4 64.6 303 40.2 290.7 22.8 279.3 12.2 268.1 1.7 260.4 0 256.4 0s-11.7 1.7-22.9 12.2c-11.4 10.6-23.7 28-35.1 52.4-20.3 43.5-34.6 102.9-37.5 167.4zm-48 0C116.4 146.4 138.5 66.9 170.8 14.7 78.7 47.3 10.9 131.2 1.5 232l111.4 0zM1.5 280c9.4 100.8 77.2 184.7 169.3 217.3-32.3-52.2-54.4-131.7-57.9-217.3L1.5 280zm398.4 0c-3.5 85.6-25.6 165.1-57.9 217.3 92.1-32.7 159.9-116.5 169.3-217.3l-111.4 0zm111.4-48C501.9 131.2 434.1 47.3 342 14.7 374.3 66.9 396.4 146.4 399.9 232l111.4 0z"/></svg>',		
    ]);

    $langs = my_msls_get_languages();

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
	
	
	/*
	$get_icon = function($html) {
        $allowed = [
            'svg' => [
                'xmlns'=>true,'viewBox'=>true,'fill'=>true
            ],
            'path'=>['d'=>true,'fill'=>true],
            'span'=>['class'=>true],
        ];
        return wp_kses($html, $allowed);
    };
	*/
	
    // Toggle
    if ($atts['show'] === 'custom') {
        $toggle = esc_html($atts['customLabel']);
    } elseif ($atts['show'] === 'icon') {
        $toggle = $get_icon($atts['iconHtml']);
    } elseif ($atts['show'] === 'icon-label') {
        $toggle =
            '<span class="msls-icon">'.$get_icon($atts['iconHtml']).'</span>'.
            '<span class="msls-label">'.esc_html($current['label']).'</span>';
    } else {
        $toggle = esc_html($current['label']);
    }

    $dir = ($atts['direction'] === 'up') ? 'msls-dropup' : 'msls-dropdown';

    ob_start(); ?>

    <ul class="msls-switcher <?php echo esc_attr($dir); ?>">
        <li class="msls-toggle" tabindex="0">

            <div class="msls-current"><?php echo $toggle; ?></div>

            <?php if ($others): ?>
            <ul class="msls-submenu">
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
        'msls-switcher-editor',
        'false',
        ['wp-blocks','wp-element','wp-components','wp-block-editor'],
        null,
        true
    );

    wp_add_inline_script('msls-switcher-editor', "
        (function(wp){
            const { registerBlockType } = wp.blocks;
            const { createElement: el } = wp.element;
            const { InspectorControls } = wp.blockEditor;
            const { PanelBody, SelectControl, TextControl } = wp.components;

            registerBlockType('custom/msls-switcher', {
				apiVersion: 3,
                title: 'MSLS Switcher',
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
                        }, 'MSLS Switcher')
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

						el('div', {}, 'MSLS Switcher')
					);
				},			
                save: function(){ return null; }
            });

        })(window.wp);
    ");
	
	wp_enqueue_script('msls-switcher-editor');
	
    register_block_type('custom/msls-switcher', [
        'editor_script'   => 'msls-switcher-editor',
        'render_callback' => 'my_msls_render_switcher',
    ]);
});

