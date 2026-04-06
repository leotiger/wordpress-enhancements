# WordPress Minimal Integration & Enhancements

We are developing a WordPress integration based on minimal dependency on third-party plugins, to ensure control, security, and autonomy.

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
- The Carousel block does not provide a natural slide progression within the same overlay  
- The Gallery block does support this behavior natively  

👉 We implemented a custom solution to unify behavior and improve visual control.

---

### Text Editing & Inline Content

Gutenberg currently does not allow:

- Assigning a different font to a word within a paragraph  
- Adding inline images  

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
