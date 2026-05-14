/**
 * Accordion Scroll - improved UI behaviour for users
 * Author: Uli Hake
 * Version: 1.1
 */

(function () {
	const cfg                = window.accordionScrollConfig || {};
	const HEADER_SELECTOR    = cfg.headerSelector      || '.site-header';
	const FALLBACK_OFFSET    = cfg.fallbackOffset      ?? 110;
	const VISIBILITY_THRESHOLD = cfg.visibilityThreshold ?? 0.3;

	document.addEventListener('DOMContentLoaded', () => {
		const header = document.querySelector(HEADER_SELECTOR);
		const OFFSET = header ? header.offsetHeight : FALLBACK_OFFSET;

		const items = document.querySelectorAll('.wp-block-accordion-item');
		if (!items.length) return;

		// Track items open on load (openByDefault) to skip the initial scroll
		const initiallyOpen = new WeakSet();
		items.forEach((item) => {
			if (item.classList.contains('is-open')) {
				initiallyOpen.add(item);
			}
		});

		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if (
					mutation.type !== 'attributes' ||
					mutation.attributeName !== 'class'
				) return;

				const item = mutation.target;

				// Only react when the item is opening
				if (!item.classList.contains('is-open')) return;

				// Skip items that were open on page load (openByDefault)
				if (initiallyOpen.has(item)) {
					initiallyOpen.delete(item);
					return;
				}

				const button = item.querySelector(
					'.wp-block-accordion-heading__toggle'
				);
				if (!button) return;

				requestAnimationFrame(() => {
					const rect = button.getBoundingClientRect();

					// Already near the top of the viewport — no scroll needed
					const isNearTop =
						rect.top >= 0 &&
						rect.top <= window.innerHeight * VISIBILITY_THRESHOLD;

					if (isNearTop) return;

					window.scrollTo({
						top: rect.top + window.scrollY - OFFSET,
						behavior: 'smooth',
					});
				});
			});
		});

		items.forEach((item) => {
			observer.observe(item, {
				attributes: true,
				attributeFilter: ['class'],
			});
		});
	});
})();