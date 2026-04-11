<?php
/**
 * Plugin Name: Cal Talaia Gutenberg Enhancements
 * Author: Uli Hake
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

add_action('enqueue_block_editor_assets', function () {

    wp_enqueue_media();
	
	add_theme_support('editor-styles');
	add_editor_style('./gutenberg-editor-enhancements.css');	

	
    wp_register_script(
        'gb-enhance-inline-tools',
        false,
        [
            'wp-rich-text',
            'wp-block-editor',
            'wp-element',
            'wp-components',
            'wp-data'
        ],
        null,
        true
    );

    wp_add_inline_script('gb-enhance-inline-tools', <<<'JS'
(function(wp){

    const { registerFormatType, toggleFormat, insert, create } = wp.richText;
    const { BlockFormatControls } = wp.blockEditor;
    const { createElement: el, Fragment, useState, useEffect } = wp.element;
    const { DropdownMenu, Button, TextControl, Popover } = wp.components;

    // =========================================
    // FONT SELECTOR (WORKING)
    // =========================================

    let fontOptions = [{ name: 'Default', slug: '' }];

    const unsubscribe = wp.data.subscribe(() => {

        const settings = wp.data.select('core/block-editor')?.getSettings();
        const features = settings?.__experimentalFeatures;

        if (!features?.typography?.fontFamilies) return;

        const fonts = [];

        Object.values(features.typography.fontFamilies).forEach(group => {
            group.forEach(font => {
                fonts.push({ name: font.name, slug: font.slug });
            });
        });

        if (fonts.length) {
            fontOptions = [{ name: 'Default', slug: '' }, ...fonts];
        }

        unsubscribe();
    });

    registerFormatType('caltalaia/font', {
        title: 'Font',
        tagName: 'span',
        className: 'has-inline-font-family',
        attributes: { class: 'class' },

        edit: function(props){

            const { value, onChange } = props;

            const controls = fontOptions.map(font => ({
                title: font.name,
                onClick: () => {

                    let cls = 'has-inline-font-family';

                    if (font.slug) {
                        cls += ' has-' + font.slug + '-font-family';
                    }

                    onChange(
                        toggleFormat(value, {
                            type: 'caltalaia/font',
                            attributes: { class: cls }
                        })
                    );
                }
            }));

            return el(BlockFormatControls, {},
                el(DropdownMenu, {
                    icon: 'editor-textcolor',
                    label: 'Font family',
                    controls: controls
                })
            );
        }
    });

    // =========================================
    // INLINE IMAGE TOOL (FULLY FIXED)
    // =========================================
	
	registerFormatType('caltalaia/inline-image', {
		title: 'Inline Image',
		tagName: 'span',
		className: 'inline-image-wrapper',

		edit: function(props){

			const { value, onChange } = props;

			const { createElement: el, Fragment, useState, useEffect } = wp.element;
			const { Button, TextControl, Popover } = wp.components;
			const { insert, create, remove } = wp.richText;

			const [open, setOpen] = useState(false);
			const [width, setWidth] = useState(80);
			const [height, setHeight] = useState(80);
			const [align, setAlign] = useState('left');
			const [lightbox, setLightbox] = useState(false);
			const [selectedId, setSelectedId] = useState(null);

			// =============================
			// BUILDERS
			// =============================
			const buildStyle = (url) => {
				return 'background-image:url(' + url + ');width:' + width + 'px;height:' + height + 'px;';
			};

			const buildClasses = () => {
				let wrapper = 'inline-image-wrapper is-block';

				let img = 'inline-image is-fixed';

				if (align === 'center') img += ' is-center';
				else if (align === 'right') img += ' is-right';
				else img += ' is-left';

				if (lightbox) wrapper += ' has-lightbox';

				return { wrapper, img };
			};
			
			const buildHTML = (url, id = null) => {

				const cls = buildClasses();
				const imageId = id || ('img-' + Date.now());

				return '<span class="' + cls.wrapper + '" ' +
					   'data-inline-id="' + imageId + '" ' +
					   'data-width="' + width + '" ' +
					   'data-height="' + height + '" ' +
					   'data-align="' + align + '" ' +
					   'data-lightbox="' + (lightbox ? 'true' : 'false') + '" ' +
					   'contenteditable="false">' +
							'<span class="' + cls.img + '" style="' + buildStyle(url) + '"></span>' +
					   '</span>';
			};
			

			// =============================
			// INSERT
			// =============================
			const insertImage = (url) => {
				onChange(insert(value, create({ html: buildHTML(url) })));
			};

			// =============================
			// FIND NODE
			// =============================
			const findNodeById = () => {
				const iframe = document.querySelector('iframe[name="editor-canvas"]');
				if (!iframe || !iframe.contentDocument) return null;

				return iframe.contentDocument.querySelector(
					'[data-inline-id="' + selectedId + '"]'
				);
			};

			// =============================
			// UPDATE
			// =============================
			const updateCurrentImage = () => {

				if (!selectedId) return;

				const node = findNodeById();
				if (!node) return;

				const img = node.querySelector('.inline-image');
				if (!img) return;

				const style = img.getAttribute('style') || '';
				const urlMatch = style.match(/url\((.*?)\)/);
				if (!urlMatch) return;

				const url = urlMatch[1];

				const doc = node.ownerDocument;
				const sel = doc.getSelection();
				const range = doc.createRange();

				range.selectNode(node);
				sel.removeAllRanges();
				sel.addRange(range);

				const start = value.start;
				const end = value.end;

				const newHTML = buildHTML(url, selectedId);

				const newValue = insert(
					remove(value, start, end),
					create({ html: newHTML }),
					start
				);

				onChange(newValue);
			};

			// =============================
			// REPLACE IMAGE
			// =============================
			const replaceImage = (url) => {

				if (!selectedId) {
					insertImage(url);
					return;
				}

				const node = findNodeById();
				if (!node) return;

				const doc = node.ownerDocument;
				const sel = doc.getSelection();
				const range = doc.createRange();

				range.selectNode(node);
				sel.removeAllRanges();
				sel.addRange(range);

				const start = value.start;
				const end = value.end;

				const newHTML = buildHTML(url, selectedId);

				const newValue = insert(
					remove(value, start, end),
					create({ html: newHTML }),
					start
				);

				onChange(newValue);
			};

			// =============================
			// MEDIA
			// =============================
			const openMedia = (callback) => {

				const frame = wp.media({
					title: 'Select image',
					button: { text: 'Use image' },
					multiple: false
				});

				frame.on('select', () => {
					const media = frame.state().get('selection').first().toJSON();
					callback(media.url);
				});

				frame.open();
			};

			// =============================
			// CLICK HANDLER
			// =============================
			useEffect(() => {

				let cleanup = null;

				const attach = () => {

					const iframe = document.querySelector('iframe[name="editor-canvas"]');
					if (!iframe || !iframe.contentDocument) return false;

					const doc = iframe.contentDocument;
					const body = doc.body;

					if (!body || body.__inlineBound) return true;
					body.__inlineBound = true;

					const handleClick = (e) => {

						const wrapper = e.target.closest('.inline-image-wrapper');
						if (!wrapper) return;

						e.preventDefault();
						e.stopPropagation();

						// SELECT VISUAL
						doc.querySelectorAll('.inline-image-wrapper')
							.forEach(el => el.classList.remove('is-selected'));

						wrapper.classList.add('is-selected');

						// STORE ID
						const id = wrapper.getAttribute('data-inline-id');
						if (id) setSelectedId(id);

						// 🔥 PREFILL FROM DATA ATTRIBUTES
						const w = parseInt(wrapper.getAttribute('data-width'));
						const h = parseInt(wrapper.getAttribute('data-height'));
						const a = wrapper.getAttribute('data-align');
						const l = wrapper.getAttribute('data-lightbox');

						if (!isNaN(w)) setWidth(w);
						if (!isNaN(h)) setHeight(h);
						if (a) setAlign(a);
						setLightbox(l);

						// AUTO OPEN
						setOpen(true);
					};

					body.addEventListener('click', handleClick);

					cleanup = () => {
						body.removeEventListener('click', handleClick);
						body.__inlineBound = false;
					};

					return true;
				};

				const interval = setInterval(() => {
					if (attach()) clearInterval(interval);
				}, 200);

				return () => {
					clearInterval(interval);
					if (cleanup) cleanup();
				};

			}, [selectedId]);

			// =============================
			// UI
			// =============================
			return el(wp.blockEditor.BlockFormatControls, {},
				el(Fragment, {},

					el(Button, {
						icon: 'format-image',
						onClick: () => setOpen(true)
					}),

					open && el(Popover, {
						position: 'bottom center',
						onClose: () => setOpen(false)
					},

						el('div', { style:{ padding:'10px', width:'240px' } },

							el(TextControl, {
								label: 'Width (px)',
								value: width,
								onChange: v => setWidth(parseInt(v) || 0)
							}),

							el(TextControl, {
								label: 'Height (px)',
								value: height,
								onChange: v => setHeight(parseInt(v) || 0)
							}),

							el('label', {
								style: { display: 'flex', alignItems: 'center', marginBottom: '10px', gap: '6px' }
							},
								el('input', {
									type: 'checkbox',
									checked: lightbox,
									onChange: (e) => setLightbox(e.target.checked)
								}),
								'Enable Lightbox'
							),								   
						   
							el('select', {
								value: align,
								onChange: e => setAlign(e.target.value),
								style:{ width:'100%', marginBottom:'10px' }
							},
								el('option', { value:'left' }, 'Left'),
								el('option', { value:'center' }, 'Center'),
								el('option', { value:'right' }, 'Right')
							),

							el(Button, {
								variant: 'primary',
								onClick: () => {
									openMedia(insertImage);
									setOpen(false);
								}
							}, 'Insert Image'),

							el(Button, {
								variant: 'secondary',
								onClick: updateCurrentImage,
								style:{ marginTop:'5px' }
							}, 'Update Current'),

							el(Button, {
								variant: 'secondary',
								onClick: () => openMedia(replaceImage),
								style:{ marginTop:'5px' }
							}, 'Replace Image')
						)
					)
				)
			);
		}
	});	

})(window.wp);
JS
);
wp_enqueue_script('gb-enhance-inline-tools');
});


add_action('wp_enqueue_scripts', function () {

    wp_register_script(
        'gb-background-lightbox',
        false,
        [],
        null,
        true
    );

    wp_add_inline_script('gb-background-lightbox', <<<'JS'
document.addEventListener('click', function (e) {

    const el = e.target.closest('[data-lightbox="true"]');
    if (!el) return;

    const img = el.querySelector('.inline-image');
    if (!img) return;

    const style = img.getAttribute('style') || '';
    const match = style.match(/url\((.*?)\)/);

    if (!match) return;

    const src = match[1];

    // -----------------------------
    // CREATE OVERLAY
    // -----------------------------
    const overlay = document.createElement('div');
    overlay.className = 'caltalaia-lightbox';

    overlay.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        cursor: zoom-out;
    `;

    const image = document.createElement('img');
    image.src = src;
    image.style.cssText = `
        max-width: 90%;
        max-height: 90%;
    `;


	overlay.appendChild(image);

	overlay.classList.add('is-opening');
	requestAnimationFrame(() => {
		overlay.classList.add('is-visible');
	});

    // -----------------------------
    // CLOSE FUNCTION (shared)
    // -----------------------------
	const closeOverlay = () => {

		overlay.classList.add('is-closing');

		document.removeEventListener('keydown', onKeyDown);

		// wait for animation
		setTimeout(() => {
			overlay.remove();
		}, 250);
	};	

    // -----------------------------
    // ESC KEY HANDLER
    // -----------------------------
    const onKeyDown = (ev) => {
        if (ev.key === 'Escape') {
            closeOverlay();
        }
    };

    document.addEventListener('keydown', onKeyDown);

    // -----------------------------
    // CLOSE ON CLICK
    // -----------------------------
    overlay.addEventListener('click', closeOverlay);

    document.body.appendChild(overlay);
});
JS
    );

    wp_enqueue_script('gb-background-lightbox');
});
