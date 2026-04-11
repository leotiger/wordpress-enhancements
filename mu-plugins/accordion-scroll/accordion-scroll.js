/**
 * Accordion Scroll - improved UI behaviour for users
 * Author: Uli Hake
 * Version: 1.0
 */

(function () {
	document.addEventListener('DOMContentLoaded', () => {
		const OFFSET = document.querySelector('.site-header')?.offsetHeight || 110;
		//const OFFSET = 80;

		const items = document.querySelectorAll('.wp-block-accordion-item');
		if (!items.length) return;

		// Track items open on load (openByDefault)
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

				// Only react when opening
				if (!item.classList.contains('is-open')) return;

				// Ignore initial open state
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

					const isVisible =
						  rect.top >= 0 &&
						  rect.top <= window.innerHeight * 0.3;

					if (isVisible) return;

					const targetY =
						  rect.top + window.scrollY - OFFSET;

					window.scrollTo({
						top: targetY,
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