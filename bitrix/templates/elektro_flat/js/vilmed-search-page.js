(function () {
	"use strict";

	function ready(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn, { once: true });
		}
	}

	function norm(s) {
		return (s || "").toLowerCase().replace(/ё/g, "е").trim();
	}

	ready(function () {
		var root = document.querySelector("[data-vilmed-search-cats]");
		if (!root) {
			return;
		}
		var input = root.querySelector(".vilmed-search-cats__filter");
		var chips = root.querySelectorAll(".vilmed-search-cats__chip");
		if (!input || !chips.length) {
			return;
		}

		input.addEventListener("input", function () {
			var q = norm(input.value);
			for (var i = 0; i < chips.length; i++) {
				var name = norm(chips[i].getAttribute("data-name") || chips[i].textContent);
				chips[i].style.display = !q || name.indexOf(q) !== -1 ? "" : "none";
			}
		});
	});
})();
