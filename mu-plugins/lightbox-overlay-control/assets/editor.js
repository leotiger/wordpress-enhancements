wp.domReady(() => {
  const { addFilter } = wp.hooks;
  const { createHigherOrderComponent } = wp.compose;
  const { InspectorControls, ColorPalette } = wp.blockEditor;
  const { PanelBody, RangeControl } = wp.components;
  const { Fragment, createElement: el } = wp.element;
  const { select, dispatch, subscribe } = wp.data;

  /**
   * Default values
   */
  const DEFAULT_OVERLAY = {
    color: '#000000',
    opacity: 1,
    blur: 0,
  };

  /**
   * 1. Add attribute to Image + Gallery
   */
  addFilter(
    'blocks.registerBlockType',
    'loc/attribute',
    (settings, name) => {
      if (!['core/image', 'core/gallery'].includes(name)) {
        return settings;
      }

      settings.attributes = {
        ...settings.attributes,
        lightboxOverlay: {
          type: 'object',
          default: DEFAULT_OVERLAY,
        },
      };

      return settings;
    }
  );

  /**
   * 2. Inspector controls + default hydration
   */
  const withInspector = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
      if (!['core/image', 'core/gallery'].includes(props.name)) {
        return el(BlockEdit, props);
      }

      const { attributes, setAttributes } = props;

      let overlay = attributes.lightboxOverlay;

      // ✅ Hydrate defaults if missing
      if (!overlay) {
        overlay = DEFAULT_OVERLAY;

        setAttributes({
          lightboxOverlay: DEFAULT_OVERLAY,
        });
      }

      const update = (field, value) => {
        setAttributes({
          lightboxOverlay: {
            ...overlay,
            [field]: value,
          },
        });
      };

      return el(
        Fragment,
        {},
        el(BlockEdit, props),
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: 'Lightbox Overlay', initialOpen: true },

            el('p', {}, 'Overlay Color'),
            el(ColorPalette, {
              value: overlay.color,
              onChange: (color) => update('color', color),
            }),

            el(RangeControl, {
              label: 'Opacity',
              value: overlay.opacity,
              onChange: (value) => update('opacity', value),
              min: 0,
              max: 1,
              step: 0.05,
            }),

            el(RangeControl, {
              label: 'Blur (px)',
              value: overlay.blur,
              onChange: (value) => update('blur', value),
              min: 0,
              max: 30,
            })
          )
        )
      );
    };
  }, 'withInspector');

  addFilter('editor.BlockEdit', 'loc/inspector', withInspector);

  /**
   * 3. Save data attribute (for frontend JS)
   */
  addFilter(
    'blocks.getSaveContent.extraProps',
    'loc/data',
    (extraProps, blockType, attributes) => {
      if (!['core/image', 'core/gallery'].includes(blockType.name)) {
        return extraProps;
      }

      if (attributes.lightboxOverlay) {
        extraProps['data-lightbox-overlay'] = JSON.stringify(
          attributes.lightboxOverlay
        );
      }

      return extraProps;
    }
  );

  /**
   * 4. Gallery → Image inheritance (non-destructive)
   */
  let isSyncing = false;

  subscribe(() => {
    if (isSyncing) return;

    const selectedBlock = select('core/block-editor').getSelectedBlock();
    if (!selectedBlock) return;

    if (selectedBlock.name !== 'core/gallery') return;

    const overlay = selectedBlock.attributes.lightboxOverlay;
    if (!overlay) return;

    const innerBlocks = select('core/block-editor').getBlocks(
      selectedBlock.clientId
    );

    if (!innerBlocks || !innerBlocks.length) return;

    isSyncing = true;

    innerBlocks.forEach((block) => {
      if (block.name !== 'core/image') return;

      const hasOverlay = block.attributes.lightboxOverlay;

      // ✅ Only apply if image has no custom settings
      if (!hasOverlay) {
        dispatch('core/block-editor').updateBlockAttributes(
          block.clientId,
          {
            lightboxOverlay: overlay,
          }
        );
      }
    });

    isSyncing = false;
  });
});