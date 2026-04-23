/**
 * Plugin Name: Lightbox Overlay Control (MU Module)
 * Description: Adds overlay styling controls to Gutenberg Image & Gallery lightbox.
 * Author: Uli Hake
 * Version: 1.1
 */

(function () {

	var lastOverlay = null;

	function hexToRgba(hex, opacity) {
		if (!hex) return 'rgba(0,0,0,' + opacity + ')';

		var bigint = parseInt(hex.replace('#', ''), 16);
		var r = (bigint >> 16) & 255;
		var g = (bigint >> 8) & 255;
		var b = bigint & 255;

		return 'rgba(' + r + ',' + g + ',' + b + ',' + opacity + ')';
	}

	function getOverlayData(target) {
		var imageBlock = target.closest('.wp-block-image');
		if (!imageBlock) return null;

		var container = imageBlock.closest('.wp-block-gallery');

		var data = imageBlock.getAttribute('data-lightbox-overlay');

		if (!data && container) {
			data = container.getAttribute('data-lightbox-overlay');
		}

		if (!data) return null;

		try {
			return JSON.parse(data);
		} catch (e) {
			return null;
		}
	}

	function applyOverlay() {
		var overlay = document.querySelector('.wp-lightbox-overlay');
		if (!overlay || !lastOverlay) return;

		var scrim = overlay.querySelector('.scrim');
		if (!scrim) return;

		var color = lastOverlay.color || '#000000';
		var opacity = lastOverlay.opacity != null ? lastOverlay.opacity : 0.8;
		var blur = lastOverlay.blur != null ? lastOverlay.blur : 0;

		var rgba = hexToRgba(color, opacity);

		// override WP scrim color
		scrim.style.setProperty('background-color', rgba, 'important');

		// apply blur
		overlay.style.setProperty('--loc-blur', blur + 'px');

		// marker class
		overlay.classList.add('loc-custom-overlay');
	}

  document.addEventListener(
    'click',
    function (e) {
      var img = e.target.closest('img');
      if (!img) return;

      var overlayData = getOverlayData(img);
      if (!overlayData) return;

      lastOverlay = overlayData;

      // wait for WP lightbox to render
      requestAnimationFrame(applyOverlay);
    },
    true
  );

	document.addEventListener('keydown', function (e) {
		// Only act if lightbox is open
		const lightbox = document.querySelector('.loc-lightbox.active');
		if (!lightbox) return;

		switch (e.key) {
			case 'ArrowRight':
				e.preventDefault();
				showNext(); // 👉 your existing function
				break;

			case 'ArrowLeft':
				e.preventDefault();
				showPrev(); // 👉 your existing function
				break;

			case 'Escape':
				e.preventDefault();
				closeLightbox(); // optional but expected UX
				break;
		}
	});	
	
	var observer = new MutationObserver(function () {
		applyOverlay();
	});

	observer.observe(document.body, {
		childList: true,
		subtree: true
	});

})();
