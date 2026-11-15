/**
 * Панель кеша и фонового пересчёта групп дублей.
 * TitloDupBuild({entity, post, minScore, mount, stages, onDone}) → {start, watch, meta}
 */
window.TitloDupBuild = function (o) {
	var mount = o.mount;
	var timer = null;
	var tick = null;
	var running = false;

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c];
		});
	}
	function mmss(sec) {
		sec = Math.max(0, Math.round(sec));
		var m = Math.floor(sec / 60), s = sec % 60;
		return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
	}
	function ago(ts) {
		var d = Math.max(0, Math.round(Date.now() / 1000 - ts));
		if (d < 60) return 'только что';
		if (d < 3600) return Math.round(d / 60) + ' мин назад';
		if (d < 86400) return Math.round(d / 3600) + ' ч назад';
		return Math.round(d / 86400) + ' дн назад';
	}
	function fmt(ts) {
		var d = new Date(ts * 1000);
		var p = function (n) { return (n < 10 ? '0' : '') + n; };
		return p(d.getDate()) + '.' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
	}

	function renderProgress(p) {
		var idx = -1;
		o.stages.forEach(function (st, i) { if (st.key === p.stage) idx = i; });
		var list = o.stages.map(function (st, i) {
			var cls = i < idx ? 'is-done' : (i === idx ? 'is-cur' : '');
			return '<li class="' + cls + '"><span class="titlo-build__dot"></span>' + esc(st.label) + '</li>';
		}).join('');
		var pct = Math.max(0, Math.min(100, p.pct || 0));
		mount.innerHTML =
			'<div class="titlo-build">' +
			'<div class="titlo-build__head"><b>Пересчёт групп дублей</b>' +
			'<span class="titlo-build__time" data-started="' + (p.started || 0) + '">' + mmss(Date.now() / 1000 - (p.started || Date.now() / 1000)) + '</span></div>' +
			'<div class="titlo-build__bar"><div class="titlo-build__fill" style="width:' + pct + '%"></div><span>' + pct + '%</span></div>' +
			'<div class="titlo-build__text">' + esc(p.text || '') + '</div>' +
			'<ul class="titlo-build__stages">' + list + '</ul>' +
			'<div class="titlo-build__note">Можно уйти со страницы — пересчёт идёт на сервере, результат сохранится.</div>' +
			'</div>';
	}

	function renderError(p) {
		mount.innerHTML = '<div class="titlo-build titlo-build--err"><b>Пересчёт не удался:</b> ' + esc(p.error || 'ошибка') +
			' <input type="button" class="adm-btn titlo-build-retry" value="Повторить"></div>';
		mount.querySelector('.titlo-build-retry').onclick = function () { start(false); };
	}

	function stopTimers() {
		if (timer) clearTimeout(timer);
		if (tick) clearInterval(tick);
		timer = tick = null;
		running = false;
	}

	function poll() {
		o.post('dup_build_status', {entity: o.entity}).then(function (res) {
			var p = (res && res.progress) || {};
			if (p.state === 'running') {
				renderProgress(p);
				timer = setTimeout(poll, 800);
				return;
			}
			stopTimers();
			if (p.state === 'error') {
				renderError(p);
				return;
			}
			mount.innerHTML = '';
			if (o.onDone) o.onDone(p);
		}).catch(function () {
			timer = setTimeout(poll, 2000);
		});
	}

	function watch(p) {
		if (running) return;
		running = true;
		renderProgress(p || {pct: 0, text: 'Запуск…', started: Date.now() / 1000});
		tick = setInterval(function () {
			var t = mount.querySelector('.titlo-build__time');
			if (t) t.textContent = mmss(Date.now() / 1000 - (+t.getAttribute('data-started') || Date.now() / 1000));
		}, 1000);
		timer = setTimeout(poll, 600);
	}

	function start(probeReset) {
		if (running) return;
		watch({pct: 0, text: 'Запуск…', started: Date.now() / 1000});
		o.post('dup_build_start', {entity: o.entity, min_score: o.minScore(), probe_reset: probeReset ? 1 : ''}).catch(function () {});
	}

	function meta(cache, el) {
		if (!el) return;
		if (!cache || !cache.built_at) {
			el.innerHTML = '';
			return;
		}
		el.innerHTML = 'Данные от <b>' + fmt(cache.built_at) + '</b> (' + ago(cache.built_at) + '). ' +
			'Решения и noindex — актуальные; новые товары/разделы и переименования — после пересчёта. ' +
			'<input type="button" class="adm-btn titlo-build-run" value="Пересчитать">';
		el.querySelector('.titlo-build-run').onclick = function () { start(false); };
		if (cache.running) watch(null);
	}

	return {start: start, watch: watch, meta: meta, isRunning: function () { return running; }};
};
