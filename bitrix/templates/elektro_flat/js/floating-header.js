/* VILMED floating (sticky) header — LOCALHOST ONLY (gated in header.php).
   - Builds a compact fixed bar that slides in on scroll.
   - Relocates the left red rail icons (account / compare / favorites / cart)
     into the top: a cluster is placed both in the static header (.header_4)
     and in the floating bar. The original rail (.foot_panel_all) is hidden
     via CSS; its blocks stay in the DOM so AJAX counts keep updating and we
     mirror them. */
(function () {
	"use strict";

	if (window.__vilmedFloatingHeader) {
		return;
	}
	window.__vilmedFloatingHeader = true;

	function ready(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn, { once: true });
		}
	}

	function text(el) {
		return el ? (el.textContent || "").trim() : "";
	}

	function readNum(el) {
		if (!el) { return 0; }
		var m = (el.textContent || "").match(/\d+/);
		return m ? parseInt(m[0], 10) : 0;
	}

	function escapeHtml(s) {
		return (s || "").replace(/[&<>"']/g, function (c) {
			return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
		});
	}

	// --- keyboard layout (ЙЦУКЕН ⇄ QWERTY) correction for quick search ---
	// e.g. brand "kaw" typed in RU layout comes out as "лфц"; we recover it.
	var VFH_LAT = "`qwertyuiop[]asdfghjkl;'zxcvbnm,./";
	var VFH_CYR = "ёйцукенгшщзхъфывапролджэячсмитьбю.";
	var VFH_EN2RU = {}, VFH_RU2EN = {};
	(function () {
		for (var i = 0; i < VFH_LAT.length; i++) {
			VFH_EN2RU[VFH_LAT[i]] = VFH_CYR[i];
			VFH_RU2EN[VFH_CYR[i]] = VFH_LAT[i];
		}
	})();

	function vfhMap(s, m) {
		var out = "";
		for (var i = 0; i < s.length; i++) {
			var ch = s[i], low = ch.toLowerCase(), rep = m[low];
			out += (rep != null) ? rep : ch;
		}
		return out;
	}

	// Toggle a single word to the opposite keyboard layout based on its script.
	function vfhToggleWord(w) {
		if (/[а-яё]/i.test(w)) { return vfhMap(w, VFH_RU2EN); }
		if (/[a-z]/i.test(w)) { return vfhMap(w, VFH_EN2RU); }
		return w;
	}

	// Build candidate queries: original, all words toggled, and each single
	// word toggled (handles mixed input like "отоскоп лфц" → "отоскоп kaw").
	function vfhQueryVariants(q) {
		var list = [q], seen = {};
		seen[q.toLowerCase()] = 1;
		var words = q.split(/\s+/).filter(Boolean);
		function push(s) {
			s = (s || "").trim();
			if (!s) { return; }
			var k = s.toLowerCase();
			if (seen[k]) { return; }
			seen[k] = 1;
			list.push(s);
		}
		if (words.length) {
			// wrong keyboard layout (whole phrase + each word individually)
			push(words.map(vfhToggleWord).join(" "));
			if (words.length > 1) {
				for (var i = 0; i < words.length; i++) {
					var copy = words.slice();
					copy[i] = vfhToggleWord(copy[i]);
					push(copy.join(" "));
				}
			}
			// typo correction against the site vocabulary (Levenshtein)
			push(words.map(function (w) { return vfhCorrectWord(w) || w; }).join(" "));
			// composed per-word fix (layout + typo + transliteration together)
			push(words.map(vfhResolveWord).join(" "));
		}
		return list.slice(0, 8);
	}

	// --- typo-tolerant search ---------------------------------------------
	// Vocabulary harvested from the catalog tree (ul.left-menu, ~500 links:
	// sections, subsections and brands), matched by bounded Levenshtein so
	// "отокоп" → "отоскопы", "дефибрилятор" → "дефибрилляторы", etc.
	var vfhVocab = null, vfhVocabSet = null, vfhVocabPromise = null;

	function vfhTokenize(s, bag) {
		(s || "").toLowerCase().split(/[^0-9a-zа-яё]+/i).forEach(function (w) {
			if (w.length >= 3) { bag[w] = 1; }
		});
	}

	function vfhHarvest(root, bag) {
		var links = root.querySelectorAll("ul.left-menu a");
		for (var i = 0; i < links.length; i++) { vfhTokenize(links[i].textContent, bag); }
	}

	function vfhBuildVocab() {
		if (vfhVocabPromise) { return vfhVocabPromise; }
		vfhVocabPromise = new Promise(function (resolve) {
			var bag = {};
			vfhHarvest(document, bag);
			if (Object.keys(bag).length >= 40) {
				vfhVocabSet = bag; vfhVocab = Object.keys(bag);
				return resolve();
			}
			fetch("/catalog/", { headers: { "X-Requested-With": "XMLHttpRequest" } })
				.then(function (r) { return r.text(); })
				.then(function (html) {
					var d = document.createElement("div");
					d.innerHTML = html;
					vfhHarvest(d, bag);
				})
				.catch(function () { /* ignore */ })
				.then(function () {
					vfhVocabSet = bag; vfhVocab = Object.keys(bag);
					resolve();
				});
		});
		return vfhVocabPromise;
	}

	// Bounded Levenshtein: bails out with max+1 once it provably exceeds max.
	function vfhLeven(a, b, max) {
		var la = a.length, lb = b.length;
		if (Math.abs(la - lb) > max) { return max + 1; }
		var prev = [], cur = [], i, j;
		for (j = 0; j <= lb; j++) { prev[j] = j; }
		for (i = 1; i <= la; i++) {
			cur[0] = i;
			var best = cur[0];
			for (j = 1; j <= lb; j++) {
				var cost = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
				var v = prev[j] + 1;
				if (cur[j - 1] + 1 < v) { v = cur[j - 1] + 1; }
				if (prev[j - 1] + cost < v) { v = prev[j - 1] + cost; }
				cur[j] = v;
				if (v < best) { best = v; }
			}
			if (best > max) { return max + 1; }
			var tmp = prev; prev = cur; cur = tmp;
		}
		return prev[lb];
	}

	// Phonetic RU → Latin transliteration (for brands typed in Cyrillic:
	// "каве" → "kave" ~ "kawe", "ризер" → "rizer" ~ "riester").
	var VFH_TR = {
		"а": "a", "б": "b", "в": "v", "г": "g", "д": "d", "е": "e", "ё": "e",
		"ж": "zh", "з": "z", "и": "i", "й": "y", "к": "k", "л": "l", "м": "m",
		"н": "n", "о": "o", "п": "p", "р": "r", "с": "s", "т": "t", "у": "u",
		"ф": "f", "х": "h", "ц": "c", "ч": "ch", "ш": "sh", "щ": "sch",
		"ъ": "", "ы": "y", "ь": "", "э": "e", "ю": "yu", "я": "ya"
	};
	function vfhTranslit(s) {
		var o = "";
		for (var i = 0; i < s.length; i++) {
			var ch = s[i].toLowerCase();
			o += (VFH_TR[ch] != null) ? VFH_TR[ch] : ch;
		}
		return o;
	}

	// Reduce a corrected word to a robust prefix-stem so the server's strict
	// prefix-AND matches both singular/plural ("отоскопы" → "отоскоп").
	function vfhStem(w) {
		w = (w || "").toLowerCase();
		if (w.length >= 6 && /[аеёиоуыэюя]$/.test(w)) { return w.slice(0, -1); }
		return w;
	}

	// Closest vocabulary spelling for a word, or null if none needed/found.
	// Handles plain typos (same alphabet) and brands typed in the other one.
	function vfhCorrectWord(w) {
		if (!vfhVocab) { return null; }
		w = (w || "").toLowerCase();
		if (w.length < 4) { return null; }
		if (vfhVocabSet[w]) { return null; }
		var i, cand, d;
		for (i = 0; i < vfhVocab.length; i++) {
			if (vfhVocab[i].indexOf(w) === 0) { return null; } // server already matches prefixes
		}
		var max = w.length <= 5 ? 1 : 2;
		var best = null, bestD = max + 1;
		for (i = 0; i < vfhVocab.length; i++) {
			cand = vfhVocab[i];
			if (Math.abs(cand.length - w.length) > max) { continue; }
			d = vfhLeven(w, cand, max);
			if (d <= max && d < bestD) {
				bestD = d; best = cand;
				if (d === 1) { break; }
			}
		}
		// Cross-script (phonetic) match for a Cyrillic-typed Latin brand.
		if (/[а-яё]/i.test(w)) {
			var tr = vfhTranslit(w);
			if (tr.length >= 3) {
				var tmax = tr.length <= 5 ? 1 : 2;
				for (i = 0; i < vfhVocab.length; i++) {
					cand = vfhVocab[i];
					if (!/[a-z]/.test(cand)) { continue; }
					if (Math.abs(cand.length - tr.length) > tmax) { continue; }
					d = vfhLeven(tr, cand, tmax);
					if (d < bestD) {
						bestD = d; best = cand;
						if (d === 0) { break; }
					}
				}
			}
		}
		return (best && best !== w) ? vfhStem(best) : null;
	}

	// A word is "known" if the server can already find it (exact or as a prefix).
	function vfhIsKnown(w) {
		if (!vfhVocab) { return false; }
		w = (w || "").toLowerCase();
		if (vfhVocabSet[w]) { return true; }
		for (var i = 0; i < vfhVocab.length; i++) {
			if (vfhVocab[i].indexOf(w) === 0) { return true; }
		}
		return false;
	}

	// Resolve one word to its best searchable form, composing layout + typo
	// fixes (so "лфц" → "kaw", "отокоп" → "отоскопы", together in one query).
	function vfhResolveWord(w) {
		if (vfhIsKnown(w)) { return w; }
		var t = vfhToggleWord(w);
		if (t !== w && vfhIsKnown(t)) { return t; }
		var c = vfhCorrectWord(w);
		if (c) { return c; }
		if (t !== w) {
			var c2 = vfhCorrectWord(t);
			if (c2) { return c2; }
		}
		return w;
	}

	// Sliding off-canvas catalog drawer, built from the site's left catalog
	// menu (ul.left-menu) as a clean accordion.
	function initCatalogDrawer(trigger) {
		if (!trigger) { return; }

		var drawer = document.createElement("div");
		drawer.className = "vilmed-drawer";
		drawer.setAttribute("aria-hidden", "true");
		drawer.innerHTML =
			'<div class="vilmed-drawer__overlay"></div>' +
			'<aside class="vilmed-drawer__panel" role="dialog" aria-label="Каталог товаров">' +
				'<div class="vilmed-drawer__head">' +
					'<span class="vilmed-drawer__title"><i class="fa fa-th-list"></i> Каталог товаров</span>' +
					'<button type="button" class="vilmed-drawer__close" aria-label="Закрыть"><i class="fa fa-times"></i></button>' +
				"</div>" +
				'<div class="vilmed-drawer__filter">' +
					'<i class="fa fa-search"></i>' +
					'<input type="text" class="vilmed-dm__filter" autocomplete="off" placeholder="Поиск по категориям">' +
					'<button type="button" class="vilmed-dm__filter-clear" aria-label="Очистить"><i class="fa fa-times"></i></button>' +
				"</div>" +
				'<div class="vilmed-drawer__body"></div>' +
			"</aside>";
		document.body.appendChild(drawer);

		var body = drawer.querySelector(".vilmed-drawer__body");
		var built = false;

		function buildList(ul) {
			var html = "", count = 0;
			for (var i = 0; i < ul.children.length; i++) {
				var li = ul.children[i];
				if (li.tagName !== "LI") { continue; }
				var a = null, sub = null;
				for (var c = 0; c < li.children.length; c++) {
					if (!a && li.children[c].tagName === "A") { a = li.children[c]; }
					if (!sub && li.children[c].tagName === "UL") { sub = li.children[c]; }
				}
				if (!a) { continue; }
				var ac = a.cloneNode(true);
				var arrow = ac.querySelector(".arrow");
				if (arrow) { arrow.parentNode.removeChild(arrow); }
				var name = (ac.textContent || "").trim();
				var href = a.getAttribute("href") || "#";
				var hasSub = !!(sub && sub.querySelector("li"));
				html += '<li class="vilmed-dm__item' + (hasSub ? " has-sub" : "") + '">' +
					'<div class="vilmed-dm__row">' +
						'<a class="vilmed-dm__link" href="' + href + '">' + escapeHtml(name) + "</a>" +
						(hasSub ? '<button type="button" class="vilmed-dm__toggle" aria-label="Подкатегории"><i class="fa fa-angle-down"></i></button>' : "") +
					"</div>" +
					(hasSub ? buildList(sub) : "") +
					"</li>";
				count++;
			}
			return count ? '<ul class="vilmed-dm__list">' + html + "</ul>" : "";
		}

		// Main site nav (Главная / Производители / Акции / Контакты …) harvested
		// from the existing mobile menu, shown atop the drawer on mobile so that
		// replacing the static mobile header doesn't lose top-level navigation.
		function buildSiteNav() {
			var anchors = document.querySelectorAll(
				".top_panel .panel_2 ul.submenu > li > a, " +
				".top_panel .panel_2 ul.submenu > li > span.text > a"
			);
			if (!anchors.length) { return ""; }
			var html = "", seen = {}, n = 0;
			for (var i = 0; i < anchors.length; i++) {
				var a = anchors[i];
				var href = a.getAttribute("href") || "";
				var name = (a.textContent || "").trim();
				if (!name || /^javascript:/i.test(href)) { continue; }
				var key = name.toLowerCase();
				if (seen[key]) { continue; }
				seen[key] = 1;
				html += '<a class="vilmed-dm__navlink" href="' + href + '">' + escapeHtml(name) + "</a>";
				n++;
			}
			return n ? '<nav class="vilmed-dm__nav">' + html + "</nav>" : "";
		}

		function renderFrom(ul) {
			var markup = ul ? buildList(ul) : "";
			var nav = buildSiteNav();
			// categories first, the site menu (Главная / Производители / …) below
			body.innerHTML = (markup ||
				'<div class="vilmed-dm__empty">Не удалось загрузить каталог. ' +
				'<a href="/catalog/">Открыть каталог</a></div>') + nav;
			built = true;
		}

		// Some pages (product cards) render ul.left-menu as a CSS-sprite menu
		// where category names live in background images, so textContent is
		// empty. In that case fall back to loading the homepage menu (text).
		function menuHasText(ul) {
			var as = ul.querySelectorAll("a");
			for (var i = 0; i < as.length && i < 40; i++) {
				var c = as[i].cloneNode(true);
				var ar = c.querySelector(".arrow");
				if (ar) { ar.parentNode.removeChild(ar); }
				if ((c.textContent || "").trim()) { return true; }
			}
			return false;
		}

		function ensureBuilt() {
			if (built) { return; }
			var ul = document.querySelector("ul.left-menu");
			if (ul && menuHasText(ul)) { renderFrom(ul); return; }
			body.innerHTML = '<div class="vilmed-dm__load"><i class="fa fa-spinner fa-pulse"></i></div>';
			fetch("/", { headers: { "X-Requested-With": "XMLHttpRequest" } })
				.then(function (r) { return r.text(); })
				.then(function (html) {
					var d = document.createElement("div");
					d.innerHTML = html;
					renderFrom(d.querySelector("ul.left-menu"));
				})
				.catch(function () { renderFrom(null); });
		}

		function openDrawer() {
			ensureBuilt();
			drawer.classList.add("is-open");
			drawer.setAttribute("aria-hidden", "false");
			document.documentElement.classList.add("vilmed-drawer-lock");
		}
		function closeDrawer() {
			drawer.classList.remove("is-open");
			drawer.setAttribute("aria-hidden", "true");
			document.documentElement.classList.remove("vilmed-drawer-lock");
		}

		trigger.addEventListener("click", function (e) {
			e.preventDefault();
			openDrawer();
		});
		drawer.querySelector(".vilmed-drawer__overlay").addEventListener("click", closeDrawer);
		drawer.querySelector(".vilmed-drawer__close").addEventListener("click", closeDrawer);
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape" && drawer.classList.contains("is-open")) { closeDrawer(); }
		});

		// accordion toggles (event delegation)
		body.addEventListener("click", function (e) {
			var btn = e.target.closest(".vilmed-dm__toggle");
			if (!btn) { return; }
			e.preventDefault();
			var item = btn.closest(".vilmed-dm__item");
			if (item) { item.classList.toggle("is-open"); }
		});

		// --- live filter over the category tree ---
		var filterInput = drawer.querySelector(".vilmed-dm__filter");
		var filterClear = drawer.querySelector(".vilmed-dm__filter-clear");
		var noRes = null;

		function applyFilter(raw) {
			var q = (raw || "").trim().toLowerCase();
			var items = body.querySelectorAll(".vilmed-dm__item");
			var nav = body.querySelector(".vilmed-dm__nav");
			var i, li, p;
			if (filterClear) { filterClear.style.display = q ? "flex" : "none"; }
			if (!q) {
				body.classList.remove("vilmed-dm--filtering");
				for (i = 0; i < items.length; i++) { items[i].style.display = ""; }
				if (nav) { nav.style.display = ""; }
				if (noRes) { noRes.style.display = "none"; }
				return;
			}
			body.classList.add("vilmed-dm--filtering");
			if (nav) { nav.style.display = "none"; }
			for (i = 0; i < items.length; i++) { items[i].style.display = "none"; }
			var links = body.querySelectorAll(".vilmed-dm__link");
			var any = false;
			for (i = 0; i < links.length; i++) {
				if ((links[i].textContent || "").toLowerCase().indexOf(q) > -1) {
					any = true;
					li = links[i].closest(".vilmed-dm__item");
					while (li) {
						li.style.display = "";
						p = li.parentElement;
						li = p ? p.closest(".vilmed-dm__item") : null;
					}
				}
			}
			if (!noRes) {
				noRes = document.createElement("div");
				noRes.className = "vilmed-dm__nores";
				noRes.textContent = "Ничего не найдено";
				body.appendChild(noRes);
			}
			noRes.style.display = any ? "none" : "block";
		}

		if (filterInput) {
			filterInput.addEventListener("input", function () { applyFilter(this.value); });
			filterInput.addEventListener("keydown", function (e) {
				if (e.key === "Escape" || e.keyCode === 27) {
					if (this.value) { e.stopPropagation(); this.value = ""; applyFilter(""); }
				}
			});
		}
		if (filterClear) {
			filterClear.style.display = "none";
			filterClear.addEventListener("click", function () {
				if (filterInput) { filterInput.value = ""; filterInput.focus(); }
				applyFilter("");
			});
		}
		// reset filter each time the drawer opens
		trigger.addEventListener("click", function () {
			if (filterInput && filterInput.value) { filterInput.value = ""; applyFilter(""); }
		});
	}

	// Quick search: posts to the page's altop:search.title ajax handler
	// (INPUT_ID=title-search-input) and renders a clean styled dropdown.
	function initQuickSearch(box) {
		if (!box) { return; }
		var input = box.querySelector('input[name="q"]');
		if (!input) { return; }
		var MIN = 2;

		box.style.position = "relative";
		input.setAttribute("autocomplete", "off");

		var pop = document.createElement("div");
		pop.className = "vilmed-fh__sresult";
		box.appendChild(pop);

		var active = -1;
		var controller = null;
		var lastQ = "";

		function close() {
			box.classList.remove("vilmed-fh--open");
			active = -1;
		}
		function open() {
			if (pop.innerHTML) { box.classList.add("vilmed-fh--open"); }
		}
		function items() {
			return pop.querySelectorAll(".vilmed-fh__sitem");
		}
		function setActive(idx) {
			var list = items();
			if (!list.length) { return; }
			if (idx < 0) { idx = list.length - 1; }
			if (idx >= list.length) { idx = 0; }
			for (var i = 0; i < list.length; i++) {
				list[i].classList.toggle("is-active", i === idx);
			}
			active = idx;
			list[idx].scrollIntoView({ block: "nearest" });
		}

		function parseItems(html) {
			var tmp = document.createElement("div");
			tmp.innerHTML = html;
			var found = tmp.querySelectorAll("#catalog_search .tvr_search, .tvr_search");
			var arr = [];
			for (var i = 0; i < found.length; i++) {
				var titleLink = found[i].querySelector(".cat_title a") || found[i].querySelector("a:not(.image)");
				var href = titleLink ? titleLink.getAttribute("href") : null;
				if (!href) {
					var im = found[i].querySelector("a.image");
					href = im ? im.getAttribute("href") : null;
				}
				var name = titleLink ? (titleLink.textContent || "").trim() : "";
				var img = found[i].querySelector("img");
				var src = img ? (img.getAttribute("src") || img.getAttribute("data-src") || "") : "";
				if (!href || !name) { continue; }
				arr.push({ href: href, name: name, src: src });
			}
			return arr;
		}

		function renderItems(list, effQ, typedQ) {
			var allHref = "/catalog/?q=" + encodeURIComponent(effQ);
			var out = "", shown = 0;
			if (typedQ && effQ && list.length && effQ.toLowerCase() !== typedQ.toLowerCase()) {
				out += '<div class="vilmed-fh__sfix">Показаны результаты для <b>«' +
					escapeHtml(effQ) + '»</b></div>';
			}
			for (var i = 0; i < list.length && shown < 8; i++) {
				var it = list[i];
				out +=
					'<a class="vilmed-fh__sitem" href="' + it.href + '">' +
						'<span class="vilmed-fh__sitem-pic">' +
							(it.src ? '<img src="' + it.src + '" alt="" loading="lazy">' : "") +
						"</span>" +
						'<span class="vilmed-fh__sitem-name">' + it.name + "</span>" +
					"</a>";
				shown++;
			}
			if (shown > 0) {
				out += '<a class="vilmed-fh__sall" href="' + allHref + '">' +
					"Показать все результаты</a>";
			} else {
				out = '<div class="vilmed-fh__sempty">По запросу ничего не найдено</div>' +
					'<a class="vilmed-fh__sall" href="' + allHref + '">Искать в каталоге</a>';
			}
			pop.innerHTML = out;
			active = -1;
			open();
		}

		function fetchOne(q, signal) {
			return fetch(location.pathname, {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
					"X-Requested-With": "XMLHttpRequest"
				},
				body: "ajax_call=y&INPUT_ID=title-search-input&q=" + encodeURIComponent(q),
				signal: signal
			}).then(function (r) {
				return r.text();
			}).then(function (t) {
				return { q: q, items: parseItems(t) };
			}).catch(function () {
				return { q: q, items: [] };
			});
		}

		function fetchResults(q) {
			if (controller) { controller.abort(); }
			controller = ("AbortController" in window) ? new AbortController() : null;
			var signal = controller ? controller.signal : undefined;
			pop.innerHTML = '<div class="vilmed-fh__sload"><i class="fa fa-spinner fa-pulse"></i></div>';
			open();
			vfhBuildVocab().then(function () {
				if (input.value.trim() !== q) { return; }
				var variants = vfhQueryVariants(q);
				return Promise.all(variants.map(function (v) { return fetchOne(v, signal); }))
					.then(function (results) {
						if (input.value.trim() !== q) { return; }
						var seen = {}, merged = [], effQ = q;
						results.forEach(function (res) {
							res.items.forEach(function (it) {
								if (seen[it.href]) { return; }
								seen[it.href] = 1;
								merged.push(it);
							});
						});
						for (var i = 0; i < results.length; i++) {
							if (results[i].items.length) { effQ = results[i].q; break; }
						}
						renderItems(merged, effQ, q);
					});
			}).catch(function () { /* aborted / network */ });
		}

		var timer = null;
		input.addEventListener("input", function () {
			var q = input.value.trim();
			if (timer) { clearTimeout(timer); }
			if (q.length < MIN) { close(); pop.innerHTML = ""; lastQ = ""; return; }
			if (q === lastQ) { open(); return; }
			lastQ = q;
			timer = setTimeout(function () { fetchResults(q); }, 220);
		});

		input.addEventListener("focus", function () {
			if (input.value.trim().length >= MIN && pop.innerHTML) { open(); }
		});

		input.addEventListener("keydown", function (e) {
			if (!box.classList.contains("vilmed-fh--open")) { return; }
			if (e.key === "ArrowDown") { e.preventDefault(); setActive(active + 1); }
			else if (e.key === "ArrowUp") { e.preventDefault(); setActive(active - 1); }
			else if (e.key === "Enter") {
				var list = items();
				if (active > -1 && list[active]) { e.preventDefault(); window.location = list[active].getAttribute("href"); }
			} else if (e.key === "Escape") { close(); }
		});

		document.addEventListener("click", function (e) {
			if (!box.contains(e.target)) { close(); }
		});
	}

	ready(function () {
		var header = document.querySelector("header");
		if (!header) {
			return;
		}

		// --- sources from existing markup ---
		var logoLink = document.querySelector("header .logo a");
		var logoImg = logoLink ? logoLink.querySelector("img") : null;
		var cityLink = document.getElementById("geolocationChangeCity");
		var phoneEl = document.querySelector("header .telephone");

		// rail count sources (kept in DOM, just hidden)
		var srcCart = document.querySelector("#cart_line1 a.cart, #cart_line1 a");
		var srcCartBox = document.getElementById("cart_line1");
		var srcCompare = document.querySelector(".compare_line");
		var srcDelay = document.querySelector(".delay_line");

		var logoHref = logoLink ? logoLink.getAttribute("href") : "/";
		var logoSrc = logoImg ? logoImg.getAttribute("src") : "";
		var cityName = text(cityLink) || "";
		var phoneText = phoneEl ? text(phoneEl).replace(/\s+/g, " ") : "";
		var phoneHref = phoneText ? "tel:" + phoneText.replace(/[^\d+]/g, "") : "";

		// Залогинен ли пользователь — по виджету входа .kabinet (рендерится per-user
		// даже на композитных страницах): гость → .login_anch, авторизован → .personal/.exit.
		function isAuthed() {
			return !!document.querySelector(".kabinet .personal, .kabinet a.exit, .foot_panel_all a.exit");
		}
		// Иконка кабинета: гостя ведём сразу на авторизацию, авторизованного — в кабинет.
		function accountHref() { return isAuthed() ? "/personal/" : "/personal/private/"; }
		function accountTitle() { return isAuthed() ? "Личный кабинет" : "Войти"; }

		// --- reusable icon cluster (account / compare / favorites / cart) ---
		function clusterHTML(ns) {
			function ico(kind, href, icon, title) {
				return (
					'<a class="' + ns + '" data-vfh="' + kind + '" href="' + href + '" title="' + title + '">' +
						'<i class="fa ' + icon + '"></i>' +
						'<span class="' + ns + '-cnt" data-vfh-cnt="' + kind + '"></span>' +
					"</a>"
				);
			}
			return (
				ico("account", accountHref(), "fa-user", accountTitle()) +
				ico("compare", "/catalog/compare/", "fa-bar-chart", "Сравнение") +
				ico("delay", "/personal/cart/?delay=Y", "fa-heart", "Отложенные") +
				ico("cart", "/personal/cart/", "fa-shopping-cart", "Корзина")
			);
		}

		// --- build the floating bar ---
		var bar = document.createElement("div");
		bar.className = "vilmed-fh";
		bar.setAttribute("role", "navigation");
		bar.setAttribute("aria-label", "Плавающая шапка");

		bar.innerHTML =
			'<div class="vilmed-fh__in">' +
				'<a class="vilmed-fh__logo" href="' + logoHref + '">' +
					(logoSrc ? '<img src="' + logoSrc + '" alt="Vilmed">' : "Vilmed") +
				"</a>" +
				'<a class="vilmed-fh__catalog" href="/catalog/">' +
					'<span class="vilmed-fh__burger"><span></span><span></span><span></span></span>' +
					"<span>Каталог</span>" +
				"</a>" +
				'<form class="vilmed-fh__search" action="/catalog/" method="get">' +
					'<i class="fa fa-search"></i>' +
					'<input type="text" name="q" maxlength="50" autocomplete="off" ' +
						'placeholder="Поиск по товарам, брендам, категориям">' +
					"<button type=\"submit\">Найти</button>" +
					'<button type="button" class="vilmed-fh__search-hide" aria-label="Закрыть поиск"><i class="fa fa-times"></i></button>' +
				"</form>" +
				'<button type="button" class="vilmed-fh__search-toggle" aria-label="Поиск" aria-expanded="false"><i class="fa fa-search"></i></button>' +
				'<div class="vilmed-fh__tools">' +
					(cityName
						? '<a class="vilmed-fh__city" href="javascript:void(0)">' +
							'<i class="fa fa-map-marker"></i><span>' + cityName + "</span></a>"
						: "") +
					(phoneText
						? '<div class="vilmed-fh__phone">' +
							'<button type="button" class="vilmed-fh__phone-toggle" ' +
								'aria-label="Показать телефон" aria-expanded="false">' +
								'<i class="fa fa-phone"></i></button>' +
							'<div class="vilmed-fh__phone-pop">' +
								'<a href="' + phoneHref + '">' + phoneText + "</a>" +
							"</div>" +
						"</div>"
						: "") +
					'<div class="vilmed-fh__icons">' + clusterHTML("vilmed-fh__ico") + "</div>" +
				"</div>" +
			"</div>";

		document.body.appendChild(bar);

		// --- city click → delegate to original geolocation popup ---
		var cityBtn = bar.querySelector(".vilmed-fh__city");
		if (cityBtn && cityLink) {
			cityBtn.addEventListener("click", function (e) {
				e.preventDefault();
				cityLink.click();
			});
		}

		// --- phone handset → reveal number (tel:) on click ---
		var phoneBox = bar.querySelector(".vilmed-fh__phone");
		var phoneToggle = bar.querySelector(".vilmed-fh__phone-toggle");
		if (phoneBox && phoneToggle) {
			var closePhone = function () {
				phoneBox.classList.remove("is-open");
				phoneToggle.setAttribute("aria-expanded", "false");
			};
			phoneToggle.addEventListener("click", function (e) {
				e.preventDefault();
				e.stopPropagation();
				var open = phoneBox.classList.toggle("is-open");
				phoneToggle.setAttribute("aria-expanded", open ? "true" : "false");
			});
			document.addEventListener("click", function (e) {
				if (!phoneBox.contains(e.target)) { closePhone(); }
			});
			document.addEventListener("keydown", function (e) {
				if (e.key === "Escape") { closePhone(); }
			});
		}

		// --- quick search (autocomplete) reusing the server altop:search.title ---
		initQuickSearch(bar.querySelector(".vilmed-fh__search"));

		// --- mobile: collapse search to a loupe; tap opens a full-width bar ---
		(function () {
			var toggle = bar.querySelector(".vilmed-fh__search-toggle");
			var searchForm = bar.querySelector(".vilmed-fh__search");
			var hideBtn = bar.querySelector(".vilmed-fh__search-hide");
			if (!toggle || !searchForm) { return; }
			var searchInput = searchForm.querySelector('input[name="q"]');

			function openSearch() {
				bar.classList.add("vilmed-fh--search");
				toggle.setAttribute("aria-expanded", "true");
				if (searchInput) { setTimeout(function () { searchInput.focus(); }, 30); }
			}
			function closeSearch() {
				bar.classList.remove("vilmed-fh--search");
				toggle.setAttribute("aria-expanded", "false");
				if (searchInput) { searchInput.blur(); }
			}
			toggle.addEventListener("click", function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (bar.classList.contains("vilmed-fh--search")) { closeSearch(); }
				else { openSearch(); }
			});
			if (hideBtn) {
				hideBtn.addEventListener("click", function (e) {
					e.preventDefault();
					closeSearch();
				});
			}
			document.addEventListener("click", function (e) {
				if (!bar.classList.contains("vilmed-fh--search")) { return; }
				if (!searchForm.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
					closeSearch();
				}
			});
			document.addEventListener("keydown", function (e) {
				if ((e.key === "Escape" || e.keyCode === 27) && bar.classList.contains("vilmed-fh--search")) {
					closeSearch();
				}
			});
		})();

		// --- replace the native static-header search with the same super-search ---
		var nativeInput = document.getElementById("title-search-input");
		if (nativeInput) {
			var nativeForm = nativeInput.closest("form") || nativeInput.parentElement;
			// cloning drops the native JCTitleSearch listeners (keeps id/name)
			var fresh = nativeInput.cloneNode(true);
			nativeInput.parentNode.replaceChild(fresh, nativeInput);
			initQuickSearch(nativeForm);
		}

		// --- catalog button → sliding off-canvas category drawer ---
		initCatalogDrawer(bar.querySelector(".vilmed-fh__catalog"));

		// --- static-header layout: search | Время работы | account icons | contacts
		//     The working-hours block and the icon cluster are physically swapped:
		//     the schedule moves into the (shortened) search cell, and the icons
		//     take the schedule cell — so the icons sit to the RIGHT of the hours
		//     and never overlap them. ----------------------------------------------
		function mkIcons(extraNs) {
			var d = document.createElement("div");
			d.className = "vilmed-hdr-icons" + (extraNs ? " " + extraNs : "");
			d.innerHTML = clusterHTML("vilmed-hdr-ico");
			return d;
		}

		// --- live working-hours line (ПН–ПТ 09:00–19:00, выходные СБ/ВС) ---------
		//     Replaces the static two-liner with an automatic, day-aware text:
		//       open now    → "Сегодня до 19"
		//       before open → "Сегодня с 09 до 19"
		//       day off/closed → "Сегодня выходной" + "В <след. рабочий день> с 09 до 19"
		function vfhUpdateSchedule(schedule) {
			var p = schedule && schedule.querySelector("p:not(.time)");
			if (!p) { return; }
			var OPEN = 9, CLOSE = 19, OPEN_STR = "09:00", CLOSE_STR = "19:00";
			var NAMES = ["ВС", "ПН", "ВТ", "СР", "ЧТ", "ПТ", "СБ"];
			var isWork = function (x) { return x >= 1 && x <= 5; };
			var now = new Date();
			var d = now.getDay(), h = now.getHours();
			var html;
			if (isWork(d) && h >= OPEN && h < CLOSE) {
				html = "Сегодня до " + CLOSE_STR;
			} else if (isWork(d) && h < OPEN) {
				html = "Сегодня с " + OPEN_STR + " до " + CLOSE_STR;
			} else {
				var nd = d, i = 0;
				do { nd = (nd + 1) % 7; i++; } while (!isWork(nd) && i < 8);
				html = (isWork(d) ? "Сегодня уже закрыто" : "Сегодня выходной") +
					"<br>В " + NAMES[nd] + " с " + OPEN_STR + " до " + CLOSE_STR;
			}
			p.innerHTML = html;
		}

		function placeStaticIcons() {
			var searchCell = document.querySelector("header .header_2");
			var box = document.getElementById("altop_search");
			var schedCell = document.querySelector("header .header_3");
			var schedule = schedCell && schedCell.querySelector(".schedule");

			if (schedule) { vfhUpdateSchedule(schedule); }

			if (searchCell && box && schedCell && schedule &&
				!document.querySelector(".vilmed-hdr-icons--sched")) {
				var contacts = document.querySelector("header .header_4 .contacts");

				// shorten the search field to free room beside it
				searchCell.classList.add("vilmed-hdr-search-host");
				// move the working-hours block to the right of the search field
				schedule.classList.add("vilmed-hdr-sched-moved");
				searchCell.appendChild(schedule);
				// collapse the now-empty schedule cell
				schedCell.classList.add("vilmed-hdr-sched-host", "vilmed-hdr-empty");

				// icons + city/phone share one flex row in the contacts cell
				if (contacts) {
					var col = document.createElement("div");
					col.className = "vilmed-hdr-contact-col";
					while (contacts.firstChild) {
						col.appendChild(contacts.firstChild);
					}
					contacts.appendChild(mkIcons("vilmed-hdr-icons--sched"));
					contacts.appendChild(col);
				}
				return;
			}

			// fallback if the expected cells are missing: icons on the contacts line
			var contacts = document.querySelector("header .header_4 .contacts");
			if (contacts && !contacts.querySelector(".vilmed-hdr-icons")) {
				var geo = contacts.querySelector("#geolocation");
				var topRow = document.createElement("div");
				topRow.className = "vilmed-hdr-toprow";
				if (geo) {
					contacts.insertBefore(topRow, geo);
					topRow.appendChild(geo);
				} else {
					contacts.insertBefore(topRow, contacts.firstChild);
				}
				topRow.appendChild(mkIcons());
			}
		}

		placeStaticIcons();

		// --- mobile homepage: relocate the sidebar promo banner into the
		//     "Акции и скидки" block (on desktop it stays in the left sidebar).
		//     On phones the left-column floats to the very top and the lone
		//     banner looks out of place above the hero slider. ---------------
		(function relocateHomeBanner() {
			var banner = document.querySelector(".left-column .banners_left");
			var promo = document.querySelector(".promotions-block");
			if (!banner || !promo) { return; }
			var origParent = banner.parentNode;
			var origNext = banner.nextSibling;
			var title = promo.querySelector(".promotions-block__title");
			var anchor = title || promo.firstChild;
			var mq = window.matchMedia("(max-width: 787px)");
			function apply() {
				if (mq.matches) {
					if (banner.parentNode !== promo) {
						banner.classList.add("vilmed-promo-banner");
						promo.insertBefore(banner, anchor ? anchor.nextSibling : promo.firstChild);
					}
				} else if (banner.parentNode !== origParent) {
					banner.classList.remove("vilmed-promo-banner");
					origParent.insertBefore(banner, origNext);
				}
			}
			apply();
			if (mq.addEventListener) { mq.addEventListener("change", apply); }
			else if (mq.addListener) { mq.addListener(apply); }
		})();

		// --- product card: pull the article number into the price block ----
		//     (rating stars are hidden via CSS; the lonely "Артикул: …" line
		//     reads better integrated right above the price.) ----------------
		(function placeArticleInPrice() {
			var article = document.querySelector(".catalog-detail .catalog-detail-article");
			var price = document.querySelector(".catalog-detail .catalog-detail-price");
			if (!article || !price || article.closest(".catalog-detail-price")) { return; }
			var wrap = article.closest(".article_rating");
			article.classList.add("vilmed-article-inprice");
			price.insertBefore(article, price.firstChild);
			if (wrap) { wrap.style.display = "none"; }
		})();

		// --- mirror live counts (cart / compare / favorites) to both clusters ---
		function syncCounts() {
			var vals = {
				cart: readNum(document.querySelector("#cart_line1 a.cart, #cart_line1 a") || srcCart),
				compare: readNum(srcCompare),
				delay: readNum(srcDelay)
			};
			Object.keys(vals).forEach(function (kind) {
				var badges = document.querySelectorAll('[data-vfh-cnt="' + kind + '"]');
				for (var i = 0; i < badges.length; i++) {
					var n = vals[kind];
					if (n > 0) {
						badges[i].textContent = n;
						badges[i].classList.add("is-on");
					} else {
						badges[i].classList.remove("is-on");
					}
				}
			});
		}
		syncCounts();
		if (window.MutationObserver) {
			[srcCartBox, srcCompare, srcDelay].forEach(function (box) {
				if (box) {
					new MutationObserver(syncCounts).observe(box, { childList: true, subtree: true, characterData: true });
				}
			});
		}

		// --- scroll behaviour: slide in once past the header ---
		var threshold = Math.max(140, header.getBoundingClientRect().height + 10);
		var ticking = false;

		function update() {
			ticking = false;
			var y = window.pageYOffset || document.documentElement.scrollTop || 0;
			bar.classList.toggle("is-visible", y > threshold);
		}

		function onScroll() {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		}

		window.addEventListener("scroll", onScroll, { passive: true });
		window.addEventListener("resize", function () {
			threshold = Math.max(140, header.getBoundingClientRect().height + 10);
			update();
		});
		update();
	});
})();

/* VILMED — каталог: список подкатегорий «первые 10 + Показать ещё»
   + мобильный фильтр-поиск по категориям и подкатегориям. */
(function () {
	"use strict";
	function ready(fn) {
		if (document.readyState !== "loading") { fn(); }
		else { document.addEventListener("DOMContentLoaded", fn, { once: true }); }
	}
	var LIMIT = 12;

	ready(function () {
		var list = document.getElementById("catalog-section-list");
		if (!list) { return; }

		function norm(s) { return (s || "").toLowerCase().replace(/ё/g, "е").replace(/\s+/g, " ").trim(); }
		function directChildren(wrap) {
			var out = [], n = wrap.children;
			for (var i = 0; i < n.length; i++) {
				if (n[i].classList && n[i].classList.contains("catalog-section-child")) { out.push(n[i]); }
			}
			return out;
		}

		// --- свёртка каждой группы до 10 карточек + кнопка «Показать ещё» ---
		var collapses = [];
		var wraps = list.querySelectorAll(".catalog-section-childs");
		for (var w = 0; w < wraps.length; w++) {
			(function (wrap) {
				if (directChildren(wrap).length <= LIMIT) { return; }
				wrap.classList.add("vilmed-cats-collapsed");
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "vilmed-cats-more";
				btn.innerHTML = "Показать ещё <span>(" + (directChildren(wrap).length - LIMIT) + ")</span>";
				wrap.parentNode.insertBefore(btn, wrap.nextSibling);
				var entry = { wrap: wrap, btn: btn, expanded: false };
				btn.addEventListener("click", function () {
					entry.expanded = true;
					wrap.classList.remove("vilmed-cats-collapsed");
					btn.style.display = "none";
				});
				collapses.push(entry);
			})(wraps[w]);
		}

		// --- поле-фильтр (на десктопе скрыто через CSS, активно на мобиле) ---
		var filterWrap = document.createElement("div");
		filterWrap.className = "vilmed-cats-filter";
		filterWrap.innerHTML =
			'<i class="fa fa-search"></i>' +
			'<input type="text" autocomplete="off" placeholder="Поиск по категориям">' +
			'<button type="button" class="vilmed-cats-filter__clear" aria-label="Очистить"><i class="fa fa-times"></i></button>';
		list.parentNode.insertBefore(filterWrap, list);
		var input = filterWrap.querySelector("input");
		var clearBtn = filterWrap.querySelector(".vilmed-cats-filter__clear");

		var sections = list.querySelectorAll(".catalog-section");
		var allKids = list.querySelectorAll(".catalog-section-child");

		function apply(raw) {
			var q = norm(raw);
			var filtering = q.length > 0;
			filterWrap.classList.toggle("is-filled", filtering);

			var i;
			if (!filtering) {
				for (i = 0; i < allKids.length; i++) { allKids[i].style.display = ""; }
				for (i = 0; i < sections.length; i++) { sections[i].style.display = ""; }
				for (i = 0; i < collapses.length; i++) {
					if (collapses[i].expanded) {
						collapses[i].wrap.classList.remove("vilmed-cats-collapsed");
						collapses[i].btn.style.display = "none";
					} else {
						collapses[i].wrap.classList.add("vilmed-cats-collapsed");
						collapses[i].btn.style.display = "";
					}
				}
				return;
			}

			for (i = 0; i < collapses.length; i++) {
				collapses[i].wrap.classList.remove("vilmed-cats-collapsed");
				collapses[i].btn.style.display = "none";
			}
			for (i = 0; i < allKids.length; i++) {
				allKids[i].style.display = norm(allKids[i].textContent).indexOf(q) !== -1 ? "" : "none";
			}
			for (i = 0; i < sections.length; i++) {
				var title = sections[i].querySelector(".catalog-section-title");
				var kids = sections[i].querySelectorAll(".catalog-section-child");
				var k, any = false;
				if (title && norm(title.textContent).indexOf(q) !== -1) {
					any = true;
					for (k = 0; k < kids.length; k++) { kids[k].style.display = ""; }
				} else {
					for (k = 0; k < kids.length; k++) { if (kids[k].style.display !== "none") { any = true; break; } }
				}
				sections[i].style.display = any ? "" : "none";
			}
		}

		input.addEventListener("input", function () { apply(this.value); });
		clearBtn.addEventListener("click", function () { input.value = ""; apply(""); input.focus(); });
	});
})();
