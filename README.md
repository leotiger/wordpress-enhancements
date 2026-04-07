# WordPress Minimal Integration & Enhancements

We are developing a WordPress integration based on minimal dependency on third-party plugins, to ensure speed, control, security, and autonomy.

Meanwhile, the WordPress ecosystem increasingly behaves like a business platform: basic plugins, limited functionality, and “professional” versions behind paywalls.

This project is a practical response to that situation.

---

## Philosophy

- Minimal third-party dependencies  
- Full control over the code  
- Simple solutions to real problems  
- Respect for WordPress architecture  
- No unnecessary feature bloat  

---

## Plugin Loader

The **Plugin Loader** allows integrating more complex functionality — organized across multiple files — within a clear and maintainable structure.

This avoids:
- proliferation of small scattered plugins  
- unnecessary external dependencies  
- loss of system control  

---

## Gutenberg Limitations (and fixes)

### Lightbox / Media Blocks

The Image, Gallery, and Carousel blocks have limitations:

- No proper control over the *lightbox* background (color and related parameters)  
- The Carousel block does not provide a natural slide progression within the same overlay for images included in the carousel block  
- The Gallery block does support this behavior natively

👉 We implemented a custom solution to unify behavior and improve visual control.

---

### Text Editing & Inline Content

Gutenberg currently does not allow:

- Assigning a different font to a text chunk within a paragraph  
- Adding inline images respecting WP block structure

This may seem minor, but becomes critical in cases like:

> Adding an image inside a footer line

👉 Solution: a small extension that adds this capability without breaking the block system.

---

## Multilingual Without Dependencies

Building a multilingual website in WordPress is often an exercise in dependency:

- Paid plugins  
- Complex multisite setups  

### Our approach

- Small website  
- Direct knowledge of the languages  
- Manual translations  

Browsers already provide automatic translation; what is really needed is a clear way to switch languages.

### MSLS

We use:

**Multisite Language Switcher (MSLS)**  
(one of the few truly free plugins)

But it is not enough.

👉 We added custom code to:
- increase flexibility  
- fix issues found in the current WordPress version  

Here's the css part as an example not included within the code, sorry, would have been better to load it with the mu-plugin code:

```
/* Caltalaia custom language switcher for MSLS */
/* =========================================
   Variables (theme-friendly)
========================================= */
.msls-switcher {
    --msls-bg: #fff;
    --msls-color: currentColor;
    --msls-border: rgba(0,0,0,0.08);
    --msls-hover: rgba(0,0,0,0.05);
    --msls-shadow: 0 10px 26px rgba(0,0,0,0.14);
    --msls-radius: 8px;
    --msls-speed: 0.18s;
    --msls-delay: 0.04s;	
}

/* Dark mode */
/*
@media (prefers-color-scheme: dark) {
    .msls-switcher {
        --msls-bg: #1e1e1e;
        --msls-color: #eee;
        --msls-border: rgba(255,255,255,0.1);
        --msls-hover: rgba(255,255,255,0.08);
        --msls-shadow: 0 10px 26px rgba(0,0,0,0.5);
    }
}
*/
/* =========================================
   Container
========================================= */
.msls-switcher {
    position: relative;
    display: inline-block;
    font-size: 1.3rem;
	font-family: var(--wp--preset--font-family--base-hand);	
    color: var(--msls-color);
	color-scheme: light;
	margin-block-start: 0;
}

/* =========================================
   Toggle
========================================= */
.msls-toggle {
    list-style: none;
    cursor: pointer;
    outline: none;	
}

.msls-current {
    display: inline-flex;
    align-items: center;
	vertical-align: center;
    gap: 0.35em;
}

/* Arrow */
.msls-current::after {
    content: "▾";
    font-size: 1.7em;
    opacity: 0.6;
    transition: transform var(--msls-speed) ease;
}

.msls-toggle .msls-current img, .msls-toggle .msls-current svg {
	height: calc(var(--wp--custom--fixed-bottom-bar--icon-size) - 1rem);
	width: calc(var(--wp--custom--fixed-bottom-bar--icon-size) - 1rem);	
}

.msls-toggle .msls-current svg {
	fill: currentColor;
	display: inline-block;
}

.msls-toggle .msls-current svg path {
	fill: currentColor;
}

.msls-toggle:hover .msls-current::after,
.msls-toggle:focus-within .msls-current::after {
    transform: rotate(180deg);
}
```

---

## Icons Without Overhead

This may not seem critical, but we strongly dislike the overkill and overhead associated with icon systems like Font Awesome — especially CSS-based integration and unnecessary payload.

We implemented a small module that allows loading a custom icon file. Icons can be extracted from Font Awesome (respecting licensing — attribution should remain within the SVG code).

This approach provides:
- lightweight icon usage  
- full control over assets  
- direct integration (icons can be used as links without additional wrappers)  

---

## Icon Build Process

Preparing the icon set requires a small preprocessing step.

We include a very simple Node.js script under Helpers with a current free FontAwesome verion:

**`build-icons.js`**

This script generates a usable icon set (e.g. from Font Awesome) for integration with the **SVG Icon Button block**.

It is intentionally minimal and can be adapted as needed. You can extend the script to include other fonts or svg icons. The list of icons to include in your icons.svg is handled via the icons-list.json file.

---

## Distribution

Fetch whatever you need from the sources. There's a lot of space for improvements or simply fetch the idea and do better.

---

## Disclaimer

Everything was developed with the support of artificial intelligence, but required extensive review, correction, and time to reach a minimally acceptable and reliable result.

The code can be significantly improved. It currently works for our specific needs, but we cannot guarantee its behavior in other contexts.

It may serve as a base for more complex plugins, including commercial ones.

**No attribution required.**  
We are not interested in additional work or responsibility related to this code.

**It’s all yours.**
