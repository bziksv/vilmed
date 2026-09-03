/* VILMED — reachGoal Яндекс.Метрики на карточке товара (вкладки, фото). */
(function () {
	"use strict";

	var METRIKA_ID = 55225453;
	var IMG_RE = /\.(jpe?g|png|gif|webp|bmp)(\?|$)/i;
	var photoGoalSent = false;

	function reachGoal(goal) {
		if (!goal || typeof window.ym !== "function") {
			return;
		}
		try {
			ym(METRIKA_ID, "reachGoal", goal);
		} catch (e) {}
	}

	function initTabs() {
		if (!document.querySelector(".tabs-catalog-detail")) {
			return;
		}

		document.body.addEventListener("click", function (e) {
			var tab = e.target.closest
				? e.target.closest(".tabs-catalog-detail .tabs__tab:not(.current)")
				: null;
			if (!tab) {
				return;
			}
			var goal = tab.getAttribute("data-metrika-goal");
			if (goal) {
				reachGoal(goal);
			}
		});
	}

	function initPhoto() {
		var gallery = document.querySelector(".catalog-detail-pictures");
		if (!gallery) {
			return;
		}

		gallery.addEventListener("click", function (e) {
			if (photoGoalSent) {
				return;
			}

			var a = e.target.closest
				? e.target.closest("a.catalog-detail-images, a.fancybox")
				: null;
			if (!a || !gallery.contains(a)) {
				return;
			}

			var href = a.getAttribute("href") || "";
			if (href.charAt(0) === "#" || !IMG_RE.test(href)) {
				return;
			}

			var inMore = a.closest && a.closest(".more_photo");
			var inMain = a.closest && (a.closest(".detail_picture") || a.closest(".catalog-detail-picture"));
			if (inMore && !inMain) {
				return;
			}

			photoGoalSent = true;
			reachGoal("nazhatienafototovara310826");
		}, true);
	}

	function init() {
		initTabs();
		initPhoto();
	}

	if (document.readyState !== "loading") {
		init();
	} else {
		document.addEventListener("DOMContentLoaded", init);
	}
})();
