/* VILMED — reachGoal Яндекс.Метрики на карточке товара (вкладки, фото). */
(function () {
	"use strict";

	var METRIKA_ID = 55225453;
	var IMG_RE = /\.(jpe?g|png|gif|webp|bmp)(\?|$)/i;
	var photoGoalSent = false;

	/**
	 * Метрика на сайте грузится отложенно (requestIdleCallback в counter_2.php),
	 * поэтому к моменту клика window.ym может ещё не быть. Ставим официальный stub —
	 * вызовы копятся в ym.a и уйдут, когда подтянется tag.js.
	 */
	function ensureYm() {
		if (typeof window.ym === "function") {
			return;
		}
		window.ym = function () {
			(window.ym.a = window.ym.a || []).push(arguments);
		};
		window.ym.l = 1 * new Date();
	}

	function reachGoal(goal) {
		if (!goal) {
			return;
		}
		ensureYm();
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
				? e.target.closest(".tabs-catalog-detail .tabs__tab")
				: null;
			if (!tab || tab.classList.contains("current")) {
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
