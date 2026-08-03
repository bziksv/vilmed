/* VILMED — лайтбокс галереи товара + переключение главного фото миниатюрами.
   Миниатюры справа на карточке меняют главное фото.
   Попап: крупное фото + лента превью снизу + стрелки/счётчик.
   Fancybox 1.3.1 перехватывается в capture. */
(function () {
	"use strict";

	function ready(fn) {
		if (document.readyState !== "loading") { fn(); }
		else { document.addEventListener("DOMContentLoaded", fn); }
	}

	var IMG_RE = /\.(jpe?g|png|gif|webp|bmp)(\?|$)/i;

	function normKey(href) {
		return href.replace(/\/resize_cache\/(.*?)\/\d+_\d+(?:_\d+)?\//, "/$1/");
	}

	ready(function () {
		var gallery = document.querySelector(".catalog-detail-pictures");
		if (!gallery) { return; }

		var ov, stage, imgEl, thumbsEl, counterEl, prevBtn, nextBtn;
		var items = [], idx = 0;

		if (window.jQuery) {
			try { jQuery(gallery).find("a.catalog-detail-images").unbind("click").removeData("fancybox"); } catch (e) {}
		}

		function collect() {
			var anchors = gallery.querySelectorAll("a.catalog-detail-images");
			var out = [], seen = {};
			for (var i = 0; i < anchors.length; i++) {
				var a = anchors[i];
				if (a.offsetParent === null) { continue; }
				var href = a.getAttribute("href") || "";
				if (!IMG_RE.test(href)) { continue; }
				var key = normKey(href);
				if (seen[key]) { continue; }
				seen[key] = 1;
				var thumbImg = a.querySelector("img");
				out.push({
					src: href,
					key: key,
					thumb: thumbImg ? (thumbImg.getAttribute("src") || href) : href,
					alt: (thumbImg && thumbImg.getAttribute("alt")) || ""
				});
			}
			return out;
		}

		function setMainFromHref(href) {
			var mainA = gallery.querySelector(".detail_picture a.catalog-detail-images, .catalog-detail-picture a.catalog-detail-images");
			if (!mainA) { return; }
			mainA.setAttribute("href", href);
			var pic = mainA.querySelector("picture");
			var img = mainA.querySelector("img");
			if (pic) {
				var source = pic.querySelector("source");
				if (source) { source.removeAttribute("srcset"); }
			}
			if (img) {
				img.setAttribute("src", href);
				img.removeAttribute("srcset");
			}
			var thumbs = gallery.querySelectorAll(".more_photo li:not(.catalog-detail-video)");
			for (var i = 0; i < thumbs.length; i++) {
				var ta = thumbs[i].querySelector("a.catalog-detail-images");
				if (!ta) { continue; }
				if (normKey(ta.getAttribute("href") || "") === normKey(href)) {
					thumbs[i].classList.add("is-active");
				} else {
					thumbs[i].classList.remove("is-active");
				}
			}
		}

		function build() {
			ov = document.createElement("div");
			ov.className = "vlb";
			ov.innerHTML =
				'<div class="vlb__backdrop"></div>' +
				'<button type="button" class="vlb__close" aria-label="Закрыть">&times;</button>' +
				'<div class="vlb__counter"></div>' +
				'<button type="button" class="vlb__nav vlb__prev" aria-label="Предыдущее фото">&#10094;</button>' +
				'<button type="button" class="vlb__nav vlb__next" aria-label="Следующее фото">&#10095;</button>' +
				'<div class="vlb__stage"><img class="vlb__img" alt=""></div>' +
				'<div class="vlb__thumbs"></div>';
			document.body.appendChild(ov);

			stage = ov.querySelector(".vlb__stage");
			imgEl = ov.querySelector(".vlb__img");
			thumbsEl = ov.querySelector(".vlb__thumbs");
			counterEl = ov.querySelector(".vlb__counter");
			prevBtn = ov.querySelector(".vlb__prev");
			nextBtn = ov.querySelector(".vlb__next");

			ov.querySelector(".vlb__close").addEventListener("click", close);
			ov.querySelector(".vlb__backdrop").addEventListener("click", close);
			prevBtn.addEventListener("click", function () { go(idx - 1); });
			nextBtn.addEventListener("click", function () { go(idx + 1); });

			var sx = 0, sy = 0, dx = 0, dragging = false, horizontal = false;
			stage.addEventListener("touchstart", function (e) {
				if (e.touches.length !== 1) { return; }
				sx = e.touches[0].clientX; sy = e.touches[0].clientY;
				dx = 0; dragging = true; horizontal = false;
				imgEl.style.transition = "";
			}, { passive: true });
			stage.addEventListener("touchmove", function (e) {
				if (!dragging || e.touches.length !== 1) { return; }
				dx = e.touches[0].clientX - sx;
				var dy = e.touches[0].clientY - sy;
				if (!horizontal && Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) {
					horizontal = true;
				}
				if (horizontal) {
					if (e.cancelable) { e.preventDefault(); }
					imgEl.style.transform = "translateX(" + dx + "px)";
				}
			}, { passive: false });
			function endSwipe() {
				if (!dragging) { return; }
				dragging = false;
				imgEl.style.transition = "transform .2s";
				if (dx < -45) { go(idx + 1); }
				else if (dx > 45) { go(idx - 1); }
				else { imgEl.style.transform = ""; }
				setTimeout(function () { imgEl.style.transition = ""; }, 220);
			}
			stage.addEventListener("touchend", endSwipe);
			stage.addEventListener("touchcancel", endSwipe);
		}

		function renderThumbs() {
			var single = items.length <= 1;
			thumbsEl.style.display = single ? "none" : "";
			prevBtn.style.display = single ? "none" : "";
			nextBtn.style.display = single ? "none" : "";
			if (single) { thumbsEl.innerHTML = ""; return; }
			var h = "";
			for (var i = 0; i < items.length; i++) {
				h += '<button type="button" class="vlb__thumb" data-i="' + i + '">' +
					'<img src="' + items[i].thumb + '" alt=""></button>';
			}
			thumbsEl.innerHTML = h;
			var btns = thumbsEl.querySelectorAll(".vlb__thumb");
			for (var j = 0; j < btns.length; j++) {
				btns[j].addEventListener("click", function () {
					go(parseInt(this.getAttribute("data-i"), 10));
				});
			}
		}

		function go(n) {
			if (!items.length) { return; }
			idx = (n + items.length) % items.length;
			imgEl.style.transition = "";
			imgEl.style.transform = "";
			imgEl.src = items[idx].src;
			imgEl.alt = items[idx].alt || "";
			counterEl.textContent = (idx + 1) + " / " + items.length;
			var single = items.length <= 1;
			prevBtn.style.display = single ? "none" : "";
			nextBtn.style.display = single ? "none" : "";
			var btns = thumbsEl.querySelectorAll(".vlb__thumb");
			for (var i = 0; i < btns.length; i++) {
				if (i === idx) { btns[i].classList.add("is-active"); }
				else { btns[i].classList.remove("is-active"); }
			}
			var act = btns[idx];
			if (act && act.scrollIntoView) { act.scrollIntoView({ inline: "center", block: "nearest" }); }
		}

		function open(startSrc) {
			if (!ov) { build(); }
			items = collect();
			if (!items.length) { return; }
			renderThumbs();
			var start = 0;
			var startKey = normKey(startSrc);
			for (var i = 0; i < items.length; i++) {
				if (items[i].key === startKey) { start = i; break; }
			}
			go(start);
			document.documentElement.classList.add("vlb-lock");
			ov.classList.add("is-open");
		}

		function close() {
			if (!ov) { return; }
			ov.classList.remove("is-open");
			document.documentElement.classList.remove("vlb-lock");
		}

		document.addEventListener("keydown", function (e) {
			if (!ov || !ov.classList.contains("is-open")) { return; }
			if (e.key === "Escape" || e.keyCode === 27) { close(); }
			else if (e.key === "ArrowLeft" || e.keyCode === 37) { go(idx - 1); }
			else if (e.key === "ArrowRight" || e.keyCode === 39) { go(idx + 1); }
		});

		gallery.addEventListener("click", function (e) {
			var a = e.target.closest ? e.target.closest("a.catalog-detail-images, a.fancybox") : null;
			if (!a || !gallery.contains(a)) { return; }
			var href = a.getAttribute("href") || "";
			if (href.charAt(0) === "#") { return; }
			if (!IMG_RE.test(href)) { return; }
			e.preventDefault();
			e.stopImmediatePropagation();

			var inMore = a.closest && a.closest(".more_photo");
			var inMain = a.closest && (a.closest(".detail_picture") || a.closest(".catalog-detail-picture"));
			if (inMore && !inMain) {
				setMainFromHref(href);
				return;
			}
			open(href);
		}, true);

		var mainA = gallery.querySelector(".detail_picture a.catalog-detail-images, .catalog-detail-picture a.catalog-detail-images");
		if (mainA) { setMainFromHref(mainA.getAttribute("href") || ""); }
	});
})();
