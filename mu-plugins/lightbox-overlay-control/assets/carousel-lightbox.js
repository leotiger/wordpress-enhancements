(function () {

  if (window.__locCarouselLightbox) return;
  window.__locCarouselLightbox = true;

  var images = [];
  var current = 0;
  var overlayData = null;

	function getImages(container) {
	  //var slides = container.children;
	  var slides = container.querySelector('.swiper-wrapper, .splide__list')?.children || container.children;	
	  var result = [];
	  var seen = {};
	  for (var i = 0; i < slides.length; i++) {
		var slide = slides[i];

		// skip hidden slides (very common in sliders)
		if (slide.offsetParent === null) continue;

		var img = slide.querySelector('img');
		if (!img) continue;
		var src = img.currentSrc || img.src;
		if (!src || seen[src]) continue;

		seen[src] = true;

		result.push({
		  src: src,
		  el: img
		});
	  }

	  return result;
	}
	
  function getOverlayData(img) {
    var block = img.closest('.wp-block-image');
    if (!block) return null;

    var data = block.getAttribute('data-lightbox-overlay');
    if (!data) return null;

    try {
      return JSON.parse(data);
    } catch (e) {
      return null;
    }
  }

  function applyOverlayStyle(overlay) {
    if (!overlayData) return;

    var backdrop = overlay.querySelector('.loc-carousel-backdrop');

    var color = overlayData.color || '#000000';
    var opacity = overlayData.opacity != null ? overlayData.opacity : 1;
    var blur = overlayData.blur != null ? overlayData.blur : 0;

    backdrop.style.backgroundColor =
      'rgba(' +
      parseInt(color.substr(1,2),16) + ',' +
      parseInt(color.substr(3,2),16) + ',' +
      parseInt(color.substr(5,2),16) + ',' +
      opacity +
      ')';

    overlay.style.backdropFilter = 'blur(' + blur + 'px)';
  }

	function createLightbox() {
		var overlay = document.createElement('div');
		overlay.className = 'loc-carousel-overlay';

		overlay.innerHTML =
			'<button class="loc-close" aria-label="Close">x</button>' +
			'<button class="loc-prev">‹</button>' +
			'<button class="loc-next">›</button>' +
			'<div class="loc-carousel-backdrop"></div>' +
			'<div class="loc-carousel-ui">' +
			'<img class="loc-img" />' +
			'</div>';

		document.body.appendChild(overlay);

		var img = overlay.querySelector('.loc-img');

		function close() {
			overlay.remove();
		}

		function render() {
			img.src = images[current];
		}

		overlay.querySelector('.loc-close').onclick = close;

		overlay.querySelector('.loc-next').onclick = function () {
			current = (current + 1) % images.length;
			render();
		};

		overlay.querySelector('.loc-prev').onclick = function () {
			current = (current - 1 + images.length) % images.length;
			render();
		};

		// 🔥 NEW: close when clicking the image
		img.onclick = function () {
			e.stopPropagation();
			close();
		};

		// close when clicking outside UI
		overlay.onclick = function (e) {
			if (e.target === overlay) close();
		};
		
		var startX = 0;
		var startY = 0;
		var isSwiping = false;

		overlay.addEventListener('touchstart', function (e) {
		  var touch = e.touches[0];
		  startX = touch.clientX;
		  startY = touch.clientY;
		  isSwiping = true;
		}, { passive: true });

		overlay.addEventListener('touchmove', function (e) {
		  if (!isSwiping) return;

		  var touch = e.touches[0];
		  var dx = touch.clientX - startX;
		  var dy = touch.clientY - startY;

		  // prevent vertical scroll interference
		  if (Math.abs(dx) > Math.abs(dy)) {
			e.preventDefault();
		  }
		}, { passive: false });

		overlay.addEventListener('touchend', function (e) {
		  if (!isSwiping) return;
		  isSwiping = false;

		  var touch = e.changedTouches[0];
		  var dx = touch.clientX - startX;
		  var dy = touch.clientY - startY;

		  var threshold = 50;

		  // horizontal swipe wins
		  if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > threshold) {
			if (dx < 0) {
			  // swipe left → next
			  current = (current + 1) % images.length;
			} else {
			  // swipe right → prev
			  current = (current - 1 + images.length) % images.length;
			}
			render();
			return;
		  }

		  // tap (small movement) → close
		  if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {
			close();
		  }
		});		

		function keyHandler(e) {
			if (!document.body.contains(overlay)) {
				document.removeEventListener('keydown', keyHandler);
				return;
			}

			if (e.key === 'ArrowRight') {
				current = (current + 1) % images.length;
				render();
			}

			if (e.key === 'ArrowLeft') {
				current = (current - 1 + images.length) % images.length;
				render();
			}

			if (e.key === 'Escape') {
				close();
			}
		}

		document.removeEventListener('keydown', keyHandler);
		document.addEventListener('keydown', keyHandler);

		applyOverlayStyle(overlay);
		render();
	}
  document.addEventListener('click', function (e) {

    var img = e.target;

    if (!img || img.tagName !== 'IMG') return;

    var container = img.closest('.swiper-wrapper');
    if (!container) return;

    // 🔥 CRITICAL: stop WP lightbox
    e.preventDefault();
    e.stopPropagation();

    var items = getImages(container);
    if (items.length < 2) return;

    images = [];
    for (var i = 0; i < items.length; i++) {
      images.push(items[i].src);
    }

    current = 0;
    for (var j = 0; j < items.length; j++) {
      if (items[j].el === img) {
        current = j;
        break;
      }
    }

    overlayData = getOverlayData(img);

    createLightbox();

  }, true);

})();