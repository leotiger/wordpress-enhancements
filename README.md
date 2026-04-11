# WordPress Minimal Integration & Enhancements

We are developing a WordPress integration based on minimal dependency on third-party plugins, to ensure speed, control, security, and autonomy.

Meanwhile, the WordPress ecosystem increasingly behaves like a business platform: basic plugins, limited functionality, and “professional” versions behind paywalls.

This project is a practical response to that situation.

You can see things in action on my test instance under [Cal Talaia Develop](https://wp.cal-talaia.cat).
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

You simply enable or disable the stuff you want or not want to use in this file. The basic Gutenberg enhancements are not included, only more complex stuff.

---

## Gutenberg Limitations (and fixes)

### Lightbox / Media Blocks

The Image, Gallery, and Carousel blocks have limitations:

- No proper control over the *lightbox* background (color and related parameters)  
- The Carousel block does not provide a natural slide progression within the same overlay for images included in the carousel block  
- The Gallery block does support this behavior natively

The image lightbox for carousel pictures could be improved to have a more native touch and feel via css.
Works on top of the [Carousel Slider Block Plugin](https://wordpress.org/plugins/carousel-block/) provided for Wordpress by Virgildia.

👉 We implemented a custom solution to unify behavior and improve visual control.

---

### Text Editing & Inline Content

Gutenberg currently does not allow:

- Assigning a different font to a text chunk within a paragraph  
- Adding inline images respecting WP block structure

This may seem minor, but becomes critical in cases like:

> Adding an image inside a footnote which breaks the footnote block

👉 Solution: 

A small extension that adds this capability without breaking the block system, using image as background and controlling display with style instrucions.
We added lightbox support for these images as well.

---

### Accordion Auto-Scroll (Gutenberg)

Automatically scrolls the opened accordion item into view when using the native Accordion block in WordPress (Gutenberg).

#### ✨ Features

- Smoothly scrolls the **opened item header** into view  
- Works with the **Interactivity API** (`data-wp-*`, `is-open`)  
- Handles `openByDefault` correctly (no jump on page load)  
- Skips scrolling if the header is already visible  
- Lightweight, dependency-free, and frontend-only
- Adjust scroll offset (for sticky headers) inside the js (could be improved)
- Designed for the new Accordion block, not legacy implementations
- Uses MutationObserver because the Interactivity API is state-driven (no DOM events)
- No editor-side behavior (frontend only)

---

# Multilingual Without Paid Services, Heavy Plugins and Dependencies

Building a multilingual website in WordPress is often an exercise in dependency:

- Paid plugins
- Complex multisite setups  

### Our approach

- Small website  
- Foster the own language skills  
- Manual translations  

Browsers already provide automatic translation for languages outside a user’s skill set, and with modern AI tools, generating translations is no longer the real challenge. What actually matters is having a clear and reliable way to switch between languages. For small websites, content is usually created gradually over time, often starting with no more than 20 or 30 pages and posts. In this context, using large multilingual plugins to manage a few hundred pages is often unnecessary, heavy, and restrictive.

Here we provide two mu-plugins. The first could be abstracted into classes and developed into a full-fledged plugin, but that is not our goal—feel free to use the code as a starting point and take the credit. The second is the result of an earlier architectural decision to use WordPress Multisite, which turned out to be overkill and is poorly supported by many plugins, especially those related to paid services. In our case, Vik Booking behaves more like a packaged Joomla application, even relying internally on Joomla-style translation mechanisms apart from not supporting WP Multisite by design. The WordPress ecosystem is, in that sense, quite an interesting one.

---

## 🌍 Multilingual Router + Language Switcher (LSFLR)

A lightweight, code-driven multilingual system for WordPress, designed for **small to medium websites** that need full control without relying on heavy plugins.

Our WPML for the poor...

This solution provides:

* language-based routing (`/de/`, `/fr/`, etc.)
* per-post translation linking (TRID system)
* early locale switching (compatible with Vik Booking which is our use case, to build a multi-lingual site for a small accommodation business we are running.)
* SEO-ready output (canonical + hreflang)
* a context-aware language switcher (no redirects, no JS hacks)
* Allows to run the instance with primary language set to en_US, for example, but serve content in Catalan and delegate en_US to a subfolder, e.g. /en
  (This seems absurd, but for plugins like Vik Booking which behaves better when it runs for admin in en_US that's vital, we use a .cat domain and it would
  be overly absurd to serve English as the first language, has and had be Catalan.)

### ✨ Features

#### 🌐 Language Routing

* Clean URLs with language prefix:

  ```
  /de/
  /fr/
  /es/
  ```
* Default language without prefix (e.g. `/`)
* Automatic language detection from URL

### 🔗 Translation System (TRID-based)

* Each post/page belongs to a translation group (`_trid`)
* Each translation stores its own language (`_lang`)
* No dependency on external translation plugins

### 🧠 Locale Handling (Critical)

* Forces WordPress locale early (`plugins_loaded`)
* Ensures compatibility with plugins that rely on locale at init time
* Required for correct translations in Vik Booking itself (our use case)

(Vik Booking offers to configure and use shortcodes but the language setting is not respected, at least in the current version of WP at writing. We may be wrong and feel invited to correct us on that.

### 🔎 SEO Ready

* Correct `<link rel="canonical">` per language
* Full `<link rel="alternate" hreflang="...">` support:

  * singular pages
  * archives
  * pagination
* Clean URL structure → no duplicate content

### 🔁 Context-Aware Language Switcher

* Preserves:

  * current page
  * pagination (`/page/2`)
  * archives (`/category/...`)
  * query parameters (e.g. booking data)

Example:

```
/de/category/news/page/3
→ /en/category/news/page/3
```

### ⚡ Performance

* Optional DB index on `_lang` for fast queries
* Compatible with object caching
* Lightweight (no runtime parsing, no DOM manipulation)

### 🧩 Components

#### 1. Language Router

Handles:

* language detection
* rewrite rules
* query filtering
* locale switching

### 2. Language Switcher (LSFLR)

* Gutenberg block + PHP render
* Object-based (no DOM parsing)
* Fully extensible
* Supports:

  * label / icon / custom display
  * dropdown / dropup

### 📦 Usage

#### Gutenberg

Use block:

```
LSFLR Switcher
```

#### PHP

```php
echo my_lsflr_render_switcher();
```

### ⚙️ Configuration

#### Define primary language

```php
add_filter('my_primary_language', function(){
    return 'ca';
});
```

### Supported languages

Derived automatically from:

* installed WordPress languages
* * primary language fallback


### 🧠 How it works

#### Routing

```
/de/page → lang = de
/page    → lang = default (ca)
```

#### Content filtering

Queries are automatically restricted to:

```
_lang = current language
```

#### Translation linking

Posts are connected via:

```
_trid = translation group
```

### ⚠️ Limitations

This system is intentionally designed to stay **simple and predictable**.

* Taxonomy slugs (categories, tags) are **not translated**
* Archive structures are identical across languages
* Missing translations fall back to source content (no automatic redirect)
* No automatic machine translation or sync


### 🎯 Intended Use Case

Best suited for:

* small to medium websites (≈ up to a few thousand posts)
* projects requiring **full control over URLs and logic**
* environments where heavy multilingual plugins are unnecessary

### 🚀 Why this approach

Instead of abstracting everything, this system:

* keeps logic transparent
* avoids hidden behavior
* integrates cleanly with custom workflows

It is especially useful when working with Vik Booking, where timing and locale handling are critical.

### 🧱 Philosophy

> Do less, but do it correctly.

* no overengineering
* no unnecessary abstraction
* predictable behavior over feature bloat

If needed, the system can be extended incrementally (SEO, caching, routing rules), but remains intentionally lightweight at its core.

### Known Issues and Workarounds

If you create a page, save it first and change language after first save. Once you've changed the language you will need to reload the page to be able to 
assign page equivalents in other languages correctly. I've spent already a lot of time on this, several days, and not sure if I will fix this. For my personal
usage, that's alright, I know how to proceed, but it's something to work on to make it clean and robust. Same goes for override from linked pages, you have to establish and save first, before you can override (import) your content from the original (desired) language.

### Implementation in your instances

The code is provided as a basis to adapt it to your needs. A reflection of this is the languages directory which contains language .mo files for Vik Booking.
Those won't be of any use for you, I kept them simply as an example. The code hasn't been tested in conjunction with other plugins apart from the ones I'm using.
This means that SEO plugins as well as many others might interfere and request solutions. If you test the language routing offered on a clean system, it should work.
For me it works with some 10, 12 additional plugins.

---

## MSLS

We use (not anymore):

**Multisite Language Switcher (MSLS)**  
(one of the few truly free plugins)

MSLS is for WP Multisite, finally the better approach for small multilingual sites is a single site with multilanguage support. 

(Please see above our Language Router and Language Switcher for Language Router implementation which offer a complete solution for single sites.)

But it is not enough.

👉 We added custom code to:
- increase flexibility  
- fix issues found in the current WordPress version  

Here’s the CSS part as an example. It is not included in the code — in a more structured setup, it would be loaded via the mu-plugin, but this repository is intended more as a brainstorming workspace.
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

We include a very simple Node.js script under Helpers with a current and free FontAwesome version:

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
