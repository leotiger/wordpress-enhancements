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

## Enhancements Plugin Loader

The **Plugin Loader** allows integrating more complex functionality — organized across multiple files — within a clear and maintainable structure.

This avoids:
- proliferation of small scattered plugins  
- unnecessary external dependencies  
- loss of system control

You simply enable or disable the stuff you want or not want to use in this file, simply place two slashes to comment out what's not desired. The language switcher add-on for MSLS is not included as we don't use it anymore.

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

- Plugins with pay walls and constant advertising for Single Instance
- Complex multisite setups and complex maintainence

### Our approach

- Small website  
- Foster the own language skills  
- Manual translations  

Browsers already provide automatic translation for languages outside a user’s skill set, and with modern AI tools, generating translations is no longer the real challenge. What actually matters is having a clear and reliable way to switch between languages. For small websites, content is usually created gradually over time, often starting with no more than 20 or 30 pages and posts. In this context, using large multilingual plugins to manage a few hundred pages is often unnecessary, heavy, and restrictive.

Here we provide two mu-plugins. The first could be abstracted into classes and developed into a full-fledged plugin, but that is not our goal—feel free to use the code as a starting point and take the credit. The second is the result of an earlier architectural decision to use WordPress Multisite, which turned out to be overkill and is poorly supported by many plugins, especially those related to paid services. In our case, Vik Booking behaves more like a packaged Joomla application, even relying internally on Joomla-style translation mechanisms apart from not supporting WP Multisite by design. The WordPress ecosystem is, in that sense, quite an interesting one.

---

### 🌍 Language Router + Language Switcher (LSFLR)

A lightweight, code-driven multilingual system for WordPress, designed for **small to medium websites** that need full control without relying on heavy plugins.

Our WPML for the poor...

This solution provides:

* language-based routing (`/de/`, `/fr/`, etc.)
* per-post translation linking (TRID system)
* early locale switching (compatible with Vik Booking which is our use case, to build a multi-lingual site for a small accommodation business we are running.)
* SEO-ready output (canonical + hreflang)
* a context-aware language switcher (no redirects, no JS hacks)
* Allows to run the instance with primary language set to en_US, for example, but serve content in Catalan and delegate en_US to a virtual subfolder (slug), e.g. /en
  (This seems absurd, but for plugins like Vik Booking which behaves better when it runs for admin in en_US that's vital, we use a .cat domain and it would
  be overly absurd to serve English as the first language, has and had to serve Catalan first.)
* Language Router uses the template system to provide the language environment that encapsulates content in a given language.

## 🧩 Integration with WordPress FSE (Full Site Editing)

This language router is fully compatible with WordPress Full Site Editing (FSE) and relies on the native block template system (`wp_template`, `wp_template_part`, patterns) rather than classic PHP templates.

Instead of duplicating themes or using separate template hierarchies per language, the router dynamically selects the appropriate templates and content based on the active language for WP intrincic workflows based on  (`MY_LANG`). This is implemented right now for WP search, one of the intrinsic workflows of WP. For post types like pages or posts you have to assign language specific templates prepared by you to guarantee that your content is encapsuñated with the language specific container.

---

## 🧠 Core Concept

WordPress FSE stores templates and template parts as **database entities**:

- Templates → `wp_template`
- Template parts (header, footer, etc.) → `wp_template_part`
- Patterns → reusable block structures

This router **hooks into the template resolution layer** and swaps templates dynamically per language.

Example:

- `search` → default language
- `search-en` → English
- `search-de` → German

The router detects the current language and loads the corresponding template if it exists.

---

## 📄 Language-Specific Templates

In WP for FSE you have to consider two tyes of templates: intrinsic templates tied to WP intrinsic workflows like the Search block and search results 
and content templates for posts and pages for which the editor for Themes under Appearance allows to create Customn templates.
For intrincis templates Language Router supports right now Search templates that have to comply with the following equivalances:

| Template Type | Default | English | German |
|---------------|--------|--------|--------|
| Search        | `search` | `search-en` | `search-de` |

The mentioned theme templates are created via:

> **Appearance → Editor → Templates** for post types like posts, pages using custom templates and
> via **Appearance -> Theme Options** for WP intrinsic workflows like Search

and stored in the database (not as files).

Once created, you can edit and adapt the templates to your language specific needs. Assigning templates to posts and pages is easy via the Gutenberg Editor.
Templates for WP intrinsic workflows are loades automatically if created, don't forget to edit and adapt.

Adapting the templatess to roll down all and include all their necessary language specific parts and patterns is a bit ardous... and requires work. Once you
are familiar with the workflow after a learning curve, you dispose of a WP instance with multi-language support.

At the time of writing there's only one suport for "instrinsic" Wordpress templates, e.g. Search. Let's repeat, for content types like postsn and pages you simply create
your templates within the editor available under Appearance and you assing your template to the content in the page and post editor.

---

## 🧱 Template Parts (Header, Footer, etc.)

FSE relies heavily on reusable template parts:

- Header
- Footer
- Navigation
- Sidebar (if used)

To achieve full multilingual control, you should provide **language-specific template parts**, for example:

- `header-en`
- `footer-en`
- `navigation-en`

Then reference them inside your language-specific templates.

---

## 🧩 Patterns (Recommended)

Patterns are used for:

- Navigation structures
- Footer layouts
- Reusable sections

For multilingual setups:

- Create language-specific patterns
- Or ensure patterns are language-neutral where possible

---

## 🔍 Search Templates (Special Case)

Search is handled explicitly by the router:

- The router intercepts template resolution using `get_block_templates`
- It attempts to load `search-{lang}` dynamically
- Falls back to default `search` if no language-specific template exists

This allows full control over:

- Layout
- Labels
- Language-specific UI

---

## ⚠️ Important Notes

### 1. No Automatic Duplication
Templates and template parts are **not automatically translated or duplicated**.

You must create language variants manually where needed.

---

### 2. Fallback Behavior
If a language-specific template does not exist:

- WordPress falls back to the default template
- This ensures graceful degradation

---

### 3. Separation of Concerns

- **Routing layer (this plugin)** → decides *which language*
- **FSE templates** → decide *how it looks*

---

### 4. Database-Based System

All templates are stored in:

- `wp_posts` (post type: `wp_template`, `wp_template_part`)

There are **no physical template files required**.

---

## ✅ Recommended Setup

For each language:

- Provide at least:
  - `search-{lang}`
  - `header-{lang}`
  - `footer-{lang}`
  - `navigation-{lang}`

- Optionally:
  - `page-{lang}`
  - `archive-{lang}`

---

## 💡 Strategy

You don’t need to duplicate everything.

A common approach:

- Keep structure identical
- Only localize:
  - text
  - labels
  - navigation

---

## 🚀 Summary

This language router:

- Uses WordPress FSE as-is (no custom rendering engine)
- Extends template resolution instead of replacing it
- Keeps templates in the database
- Enables clean, scalable multilingual setups

👉 The more your templates follow FSE conventions, the smoother the integration.

---

**MSLS is abandoned. It's in here because it may serve as an intersting code for Wordpress sites using Multisite configuration for Wordpress, which is not our case anymore.
That said, don't try to use this with our Language Router, the two approaches to deliver a webiste for several languages are completly different.**

### MSLS

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

## Environment

The code was built and tested on a fresh WordPress instance (v6.9.4) running on PHP v8.3.30. We used ChatGPT to assist, which definitely reduced development time — but believe me, sometimes it drives you mad…
