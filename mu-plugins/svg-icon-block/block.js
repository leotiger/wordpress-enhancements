const { registerBlockType } = wp.blocks;
const { useState, useEffect } = wp.element;
// const { link, linkOff, rotateRight } = wp.icons;

const {
  useBlockProps,
  InspectorControls,
  BlockControls,
  AlignmentToolbar,
  LinkControl
} = wp.blockEditor;

const {
  TextControl,
  PanelBody,
  RangeControl,
  ColorPalette,
  ToolbarGroup,
  ToolbarButton,
  ToolbarDropdownMenu,
  ToolbarItem,
  Popover,
  SelectControl,
  Icon
} = wp.components;

const el = wp.element.createElement;

/* ---------------- DATA ---------------- */

const DATA = window.SIB_DATA || {};
const ICONS = Object.values(DATA.icons || {}).flat();
const META = DATA.meta || {};
const I18N = DATA.i18n || {};

/* ---------------- HELPERS ---------------- */

function useDebounce(value, delay = 150) {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value]);

  return debounced;
}

function levenshtein(a, b) {
  const matrix = Array.from({ length: b.length + 1 }, () => []);
  for (let i = 0; i <= b.length; i++) matrix[i][0] = i;
  for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

  for (let i = 1; i <= b.length; i++) {
    for (let j = 1; j <= a.length; j++) {
      matrix[i][j] = Math.min(
        matrix[i - 1][j] + 1,
        matrix[i][j - 1] + 1,
        matrix[i - 1][j - 1] + (b[i - 1] === a[j - 1] ? 0 : 1)
      );
    }
  }
  return matrix[b.length][a.length];
}

function getWords(name) {
  const data = I18N[name];
  if (!data) return [];

  return [
    ...(data.en || []),
    ...(data.es || []),
    ...(data.ca || []),
    ...(data.tags || [])
  ];
}

function scoreIcon(name, query) {
  let score = 0;

  if (name === query) score += 100;
  if (name.startsWith(query)) score += 60;
  if (name.includes(query)) score += 40;

  const dist = levenshtein(name, query);
  if (dist <= 2) score += 30;

  (META[name] || []).forEach(tag => {
    if (tag.includes(query)) score += 20;
  });

  getWords(name).forEach(word => {
    if (word.includes(query)) score += 40;
  });

  return score;
}

function searchIcons(query) {
  query = query.toLowerCase();

  return ICONS
    .map(name => ({
      name,
      score: scoreIcon(name, query)
    }))
    .filter(i => i.score > 0)
    .sort((a, b) => b.score - a.score)
    .map(i => i.name);
}

/* ---------------- COMPONENTS ---------------- */

function IconSVG({ name, size, iconColor, rotation, flipH, flipV }) {
  if (!name) return null;

  return el(
    'svg',
    {
      style: {
        width: size,
        height: size,
		...(iconColor ? {
		  fill: iconColor,
		  color: iconColor,
		  stroke: iconColor
		} : {}),		 
		transform: `
		  rotate(${rotation}deg)
  		  scale(${flipH ? -1 : 1}, ${flipV ? -1 : 1})`		  
		//transform: `rotate(${rotation}deg)`
      }
    },
    el('use', {
      href: `${DATA.sprite}#${name}`,
      xlinkHref: `${DATA.sprite}#${name}`
    })
  );
}

function IconPicker({ icons, onSelect, search, setSearch }) {
  return el(
    'div',
    { className: 'sib-popover-inner' },

    el(TextControl, {
      placeholder: 'Search icon...',
      value: search,
      onChange: setSearch
    }),

    el(
      'div',
      { className: 'sib-grid' },

      icons.map(name =>
        el(
          'button',
          {
            key: name,
            className: 'icon-button',
            onClick: () => onSelect(name)
          },
          el(IconSVG, { name, size: 20 })
        )
      )
    )
  );
}

/* ---------------- BLOCK ---------------- */

registerBlockType('custom/svg-icon', {

  edit({ attributes, setAttributes }) {

    const {
      icon,
      size = 24,
      color = '',
      rotation = 0,
	  justifyContent = 'flex-start',
	  alignItems = 'center',		
      url = '',
      opensInNewTab = false,
      label = '',
	  variant = 'primary',
	  iconColor = '',
	  textColor = '',
	  iconPosition = 'left',
	  flipH = false,
	  flipV = false		
    } = attributes;

    const [search, setSearch] = useState('');
    const [isIconOpen, setIconOpen] = useState(false);
    const [isLinkOpen, setLinkOpen] = useState(false);

    const debounced = useDebounce(search);
    const results = debounced ? searchIcons(debounced) : ICONS;
	  
	// const isVertical = iconPosition === 'top';
	  
	const flexDirection =
	  iconPosition === 'right' ? 'row-reverse' :
	  iconPosition === 'top' ? 'column' :
	  iconPosition === 'bottom' ? 'column-reverse' :
	  'row';
	  
	const blockProps = useBlockProps({
	  className: 'sib-block'
	});	  

    function selectIcon(name) {
      setAttributes({ icon: name });
      setIconOpen(false);
    }

    return el(
      'div',
      blockProps,

      /* -------- TOOLBAR -------- */
      el(
        BlockControls,
        {},

		el(
		  ToolbarGroup,
		  {},

		  el(ToolbarDropdownMenu, {
			  icon:
				justifyContent === 'center' ? 'editor-aligncenter' :
				justifyContent === 'flex-end' ? 'editor-alignright' :
				'editor-alignleft',

			  label: 'Horizontal alignment',

			  controls: [
				{
				  title: 'Align left',
				  icon: 'editor-alignleft',
				  isActive: justifyContent === 'flex-start',
				  onClick: () => setAttributes({ justifyContent: 'flex-start' })
				},
				{
				  title: 'Align center',
				  icon: 'editor-aligncenter',
				  isActive: justifyContent === 'center',
				  onClick: () => setAttributes({ justifyContent: 'center' })
				},
				{
				  title: 'Align right',
				  icon: 'editor-alignright',
				  isActive: justifyContent === 'flex-end',
				  onClick: () => setAttributes({ justifyContent: 'flex-end' })
				}
			  ]
		  }),
		  el(ToolbarDropdownMenu, {
			  icon:
				alignItems === 'flex-start' ? 'arrow-up-alt' :
				alignItems === 'flex-end' ? 'arrow-down-alt' :
				'align-center',

			  label: 'Vertical alignment',

			  controls: [
				{
				  title: 'Align top',
				  icon: 'arrow-up-alt',
				  isActive: alignItems === 'flex-start',
				  onClick: () => setAttributes({ alignItems: 'flex-start' })
				},
				{
				  title: 'Align middle',
				  icon: 'minus',
				  isActive: alignItems === 'center',
				  onClick: () => setAttributes({ alignItems: 'center' })
				},
				{
				  title: 'Align bottom',
				  icon: 'arrow-down-alt',
				  isActive: alignItems === 'flex-end',
				  onClick: () => setAttributes({ alignItems: 'flex-end' })
				}
			  ]
		  })			
		),	  
        el(
          ToolbarGroup,
          {},

          el(ToolbarButton, {
            label: icon ? 'Change icon' : 'Select icon',
            onClick: () => {
              setIconOpen(!isIconOpen);
              setLinkOpen(false);
            }
          }, 'Icon'),

  		  el(ToolbarButton, {
		  	icon: 'admin-links',
		  	label: 'Add link',
		  	isPressed: !!url,
		  	onClick: () => {
				setLinkOpen(!isLinkOpen);
				setIconOpen(false);
		  	}
		  }),			
          url && el(ToolbarButton, {
            label: 'Remove link',
            icon: 'editor-unlink',
            onClick: () => {
              setAttributes({
                url: '',
                opensInNewTab: false
              });
            }
          }),
		  el(ToolbarButton, {
		  	icon: 'image-rotate',
		  	label: 'Rotate 90°',
		  	onClick: () => {
				setAttributes({ rotation: (rotation + 90) % 360 });
		  	}
		  }),			
		  el(ToolbarButton, {
		  	icon: 'image-flip-horizontal',
		  	label: 'Flip horizontal',
		  	isPressed: flipH,
		  	onClick: () => setAttributes({ flipH: !flipH })
		  }),
		  el(ToolbarButton, {
		  	icon: 'image-flip-vertical',
		  	label: 'Flip vertical',
		  	isPressed: flipV,
		  	onClick: () => setAttributes({ flipV: !flipV })
		  })			
        )
      ),

      /* -------- SIDEBAR -------- */
      el(
        InspectorControls,
        {},

        el(
          PanelBody,
          { title: 'Button Settings', initialOpen: true },
		  el(SelectControl, {
		  	label: 'Variant',
		  	value: variant,
		  	options: [
				{ label: 'Primary', value: 'primary' },
				{ label: 'Outline', value: 'outline' },
				{ label: 'Ghost', value: 'ghost' }
		  	],
		  	onChange: (val) => setAttributes({ variant: val })
		  }),
          el(TextControl, {
            label: 'Label',
            value: label,
            onChange: (val) => setAttributes({ label: val })
          }),
          el(RangeControl, {
            label: 'Size',
            value: size,
            min: 12,
            max: 128,
            onChange: (v) => setAttributes({ size: v })
          }),
		  el(SelectControl, {
		  	label: 'Icon position',
		  	value: iconPosition,
		  	options: [
				{ label: 'Left', value: 'left' },
				{ label: 'Right', value: 'right' },
				{ label: 'Above', value: 'top' },
				{ label: 'Below', value: 'bottom' }
		  	],
		    onChange: (val) => setAttributes({ iconPosition: val })
		  }),			
		  /*
          el(ColorPalette, {
            value: color,
            onChange: (c) => setAttributes({ color: c })
          }),
		  */
		  el(
			  PanelBody,
			  { title: 'Icon', initialOpen: true },

			  el(ColorPalette, {
				value: iconColor,
				onChange: (c) => setAttributes({ iconColor: c })
			  })
		  ),
		  el(
			  PanelBody,
			  { title: 'Text', initialOpen: false },

			  el(ColorPalette, {
				value: textColor,
				onChange: (c) => setAttributes({ textColor: c })
			  })
		  ),			
          el(RangeControl, {
            label: 'Rotation',
            value: rotation,
            min: 0,
            max: 360,
            step: 1,
            onChange: (val) => setAttributes({ rotation: val })
          })
        )
      ),

		/* -------- DISPLAY -------- */

		el(
			'div',
			{
				className: 'sib-wrapper',
				style: {
					justifyContent: justifyContent,
					alignItems: alignItems,
				}
			},

			icon
			? el(
				'div',
				{
					className: `sib-button sib-${variant} ${label ? 'has-label' : 'icon-only'}`,
					style: {
					  ...(textColor ? { color: textColor } : {}),
					  display: 'inline-flex',
					  flexDirection: flexDirection,
					  alignItems: 'center',
					  gap: '8px'
					}				
				},
				el(IconSVG, { name: icon, size, iconColor, rotation, flipH, flipV }),

				label &&
				el('span', { className: 'sib-label' }, label)
			)

			: el(
				'button',
				{
					className: 'sib-empty',
					onClick: () => setIconOpen(true)
				},
				'Select icon'
			)
		),
      /* -------- ICON POPOVER -------- */
      isIconOpen &&
        el(
          Popover,
          {
            position: 'bottom center',
            onClose: () => setIconOpen(false),
            className: 'sib-popover'
          },
          el(IconPicker, {
            icons: results,
            onSelect: selectIcon,
            search,
            setSearch
          })
        ),

      /* -------- LINK POPOVER -------- */
      isLinkOpen &&
        el(
          Popover,
          {
            position: 'bottom center',
            onClose: () => setLinkOpen(false)
          },
          el(LinkControl, {
            value: {
              url,
              opensInNewTab
            },
            onChange: (val) => {
              setAttributes({
                url: val.url,
                opensInNewTab: val.opensInNewTab
              });
            }
          })
        )
    );
  },

  save() {
    return null;
  }
});