<?php

use Bitrix\Main\Loader;
use Titlo\Relevance\Prompts;
use Titlo\Relevance\AdminUi;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */
/** @global CUser $USER */

if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm('');
}

Loader::includeModule('titlo.relevance');
Prompts::ensureTables();

$APPLICATION->SetTitle('Titlo: промпты');

$message = null;
$messageType = 'OK';
$self = 'titlo_relevance_prompts.php?lang=' . LANGUAGE_ID;
$openType = (string) ($_GET['type'] ?? $_POST['focus_type'] ?? 'preview');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
	$action = (string) ($_POST['titlo_action'] ?? '');
	$type = (string) ($_POST['type'] ?? '');
	$id = (int) ($_POST['id'] ?? 0);
	$openType = $type !== '' ? $type : $openType;

	if ($action === 'save') {
		$name = (string) ($_POST['name'] ?? '');
		$body = (string) ($_POST['body'] ?? '');
		$runMode = (string) ($_POST['run_mode'] ?? Prompts::RUN_MODE_FULL);
		if ($id > 0) {
			$res = Prompts::update($id, $name, $body, $runMode);
			$message = !empty($res['ok']) ? 'Промпт сохранён.' : ($res['error'] ?? 'Ошибка');
			$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
		} else {
			$res = Prompts::create($type, $name !== '' ? $name : 'Новый вариант', $body, false, $runMode);
			$message = !empty($res['ok']) ? 'Добавлен промпт #' . (int) ($res['id'] ?? 0) : ($res['error'] ?? 'Ошибка');
			$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
		}
	} elseif ($action === 'add') {
		$meta = Prompts::catalog()[$type] ?? null;
		if ($meta) {
			$n = count(Prompts::listByType($type)) + 1;
			$runMode = (string) ($_POST['run_mode'] ?? Prompts::RUN_MODE_FULL);
			$res = Prompts::create($type, 'Вариант ' . $n, $meta['default'], false, $runMode);
			$message = !empty($res['ok']) ? 'Добавлен «Вариант ' . $n . '». Отредактируйте и сохраните.' : ($res['error'] ?? 'Ошибка');
			$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
		}
	} elseif ($action === 'delete') {
		$res = Prompts::delete($id);
		$message = !empty($res['ok']) ? 'Промпт удалён.' : ($res['error'] ?? 'Ошибка');
		$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
	} elseif ($action === 'default') {
		$res = Prompts::setDefault($id);
		$message = !empty($res['ok']) ? 'Назначен промптом по умолчанию (будет подставляться при генерации).' : ($res['error'] ?? 'Ошибка');
		$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
	} elseif ($action === 'activate') {
		if ($id > 0) {
			$row = Prompts::find($id);
			if ($row) {
				Prompts::setActiveId($row['type'], $id);
				$message = 'Выбран для генерации: «' . $row['name'] . '».';
			}
		}
	}
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$catalog = Prompts::catalog();
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-prompts">
	<?php if ($message): ?>
		<div class="adm-info-message-wrap adm-info-message-<?= $messageType === 'ERROR' ? 'red' : 'green' ?>">
			<div class="adm-info-message"><?= htmlspecialcharsbx($message) ?></div>
		</div>
	<?php endif; ?>

	<div class="hint-box">
		Можно завести <b>несколько вариантов</b> промпта (например «короткий», «продающий», «технический») и на генерации выбирать нужный.
		У каждого видно, <b>когда использовали последний раз</b>. Плейсхолдеры <code>{link}</code>, <code>{name}</code>, <code>{list}</code> не удаляйте.
		<br><br>
		У промпта есть <b>режим</b>: <i>полная проработка</i>, <i>повторная доработка</i> или <i>любой</i> —
		в автопроработке в селекте остаются только подходящие варианты.
	</div>

	<?php foreach (Prompts::groups() as $groupKey => $group): ?>
		<div class="group" data-group="<?= htmlspecialcharsbx($groupKey) ?>">
			<div class="group-h" role="button" tabindex="0" aria-expanded="true">
				<div class="group-h__text">
					<h2><?= htmlspecialcharsbx($group['title']) ?></h2>
					<p><?= htmlspecialcharsbx($group['hint']) ?></p>
				</div>
				<button type="button" class="group-h__toggle" aria-label="Свернуть или развернуть">Свернуть</button>
			</div>
			<div class="group-body">
			<?php foreach ($group['types'] as $type): ?>
				<?php
				$meta = $catalog[$type];
				$items = Prompts::listByType($type);
				$activeId = Prompts::getActiveId($type);
				$last = null;
				$lastTs = null;
				foreach ($items as $it) {
					if ($it['last_used_at'] && ($lastTs === null || $it['last_used_at'] > $lastTs)) {
						$lastTs = $it['last_used_at'];
						$last = $it;
					}
				}
				?>
				<div class="type-block" id="type-<?= htmlspecialcharsbx($type) ?>">
					<h3><?= htmlspecialcharsbx($meta['title']) ?></h3>
					<p class="meta"><?= htmlspecialcharsbx($meta['for']) ?></p>
					<p class="ph">Плейсхолдеры: <?= htmlspecialcharsbx($meta['placeholders']) ?></p>
					<?php if ($last): ?>
						<p class="meta">Последний запуск: <b><?= htmlspecialcharsbx($last['name']) ?></b> — <?= htmlspecialcharsbx($last['last_used_label']) ?>
							(всего запусков этого варианта: <?= (int) $last['use_count'] ?>)</p>
					<?php else: ?>
						<p class="meta">По этому типу генераций ещё не было.</p>
					<?php endif; ?>

					<?php foreach ($items as $it): ?>
						<form method="post" action="<?= htmlspecialcharsbx($self) ?>" class="card<?= $it['id'] === $activeId ? ' is-active' : '' ?>">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="titlo_action" value="save">
							<input type="hidden" name="type" value="<?= htmlspecialcharsbx($type) ?>">
							<input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
							<input type="hidden" name="focus_type" value="<?= htmlspecialcharsbx($type) ?>">
							<div class="card-h">
								<input type="text" name="name" class="adm-input" value="<?= htmlspecialcharsbx($it['name']) ?>" placeholder="Название варианта">
								<label class="titlo-prompt-mode">
									Режим
									<select name="run_mode" class="adm-input">
										<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_FULL) ?>" <?= ($it['run_mode'] ?? '') === Prompts::RUN_MODE_FULL ? 'selected' : '' ?>>полная проработка</option>
										<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_REFINE) ?>" <?= ($it['run_mode'] ?? '') === Prompts::RUN_MODE_REFINE ? 'selected' : '' ?>>повторная доработка</option>
										<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_ANY) ?>" <?= ($it['run_mode'] ?? '') === Prompts::RUN_MODE_ANY ? 'selected' : '' ?>>любой режим</option>
									</select>
								</label>
								<?php if ($it['is_default']): ?>
									<span class="badge badge-def">по умолчанию</span>
								<?php endif; ?>
								<?php if ($it['id'] === $activeId): ?>
									<span class="badge badge-active">выбран для генерации</span>
								<?php endif; ?>
								<span class="badge badge-mode"><?= htmlspecialcharsbx($it['run_mode_label'] ?? 'полная проработка') ?></span>
								<span class="badge badge-used"><?= htmlspecialcharsbx($it['last_used_label']) ?></span>
							</div>
							<textarea name="body" rows="9"><?= htmlspecialcharsbx($it['body']) ?></textarea>
							<div class="card-actions">
								<input type="submit" class="adm-btn-save" value="Сохранить"
									onclick="this.form.titlo_action.value='save'">
								<input type="submit" class="adm-btn" value="Выбрать для генерации"
									onclick="this.form.titlo_action.value='activate'">
								<?php if (!$it['is_default']): ?>
									<input type="submit" class="adm-btn" value="Сделать по умолчанию"
										onclick="this.form.titlo_action.value='default'">
								<?php endif; ?>
								<?php if (count($items) > 1): ?>
									<input type="submit" class="adm-btn" value="Удалить"
										onclick="this.form.titlo_action.value='delete'; return confirm('Удалить «<?= htmlspecialcharsbx($it['name']) ?>»?');">
								<?php endif; ?>
							</div>
						</form>
					<?php endforeach; ?>

					<form method="post" action="<?= htmlspecialcharsbx($self) ?>" class="add-row">
						<?= bitrix_sessid_post() ?>
						<input type="hidden" name="titlo_action" value="add">
						<input type="hidden" name="type" value="<?= htmlspecialcharsbx($type) ?>">
						<input type="hidden" name="focus_type" value="<?= htmlspecialcharsbx($type) ?>">
						<label class="titlo-prompt-mode">
							Режим нового
							<select name="run_mode" class="adm-input">
								<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_FULL) ?>">полная проработка</option>
								<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_REFINE) ?>">повторная доработка</option>
								<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_ANY) ?>">любой режим</option>
							</select>
						</label>
						<input type="submit" class="adm-btn" value="+ Добавить вариант промпта">
					</form>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>
(function () {
	var storageKey = 'titlo_prompts_groups_v1';
	// Только после POST кратко раскрываем группу с сохранённым типом (в память не пишем)
	var forceOpenType = <?= json_encode($message ? $openType : '') ?>;

	function loadState() {
		try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; }
		catch (e) { return {}; }
	}
	function saveState(state) {
		try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (e) {}
	}
	function setCollapsed(group, collapsed) {
		group.classList.toggle('is-collapsed', !!collapsed);
		var btn = group.querySelector('.group-h__toggle');
		var head = group.querySelector('.group-h');
		if (btn) btn.textContent = collapsed ? 'Развернуть' : 'Свернуть';
		if (head) head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
	}

	var state = loadState();
	Array.prototype.forEach.call(document.querySelectorAll('.titlo-prompts .group'), function (group) {
		var key = group.getAttribute('data-group') || '';
		var collapsed = Object.prototype.hasOwnProperty.call(state, key) ? !!state[key] : false;
		setCollapsed(group, collapsed);

		// Временный фокус после сохранения — без перезаписи localStorage
		if (forceOpenType && group.querySelector('#type-' + forceOpenType)) {
			setCollapsed(group, false);
		}

		function toggle(ev) {
			if (ev) {
				ev.preventDefault();
				ev.stopPropagation();
			}
			var next = !group.classList.contains('is-collapsed');
			setCollapsed(group, next);
			state[key] = next;
			saveState(state);
		}
		var head = group.querySelector('.group-h');
		var btn = group.querySelector('.group-h__toggle');
		if (btn) {
			btn.addEventListener('click', toggle);
		}
		if (head) {
			head.addEventListener('click', function (ev) {
				if (ev.target && ev.target.closest && ev.target.closest('.group-h__toggle')) {
					return;
				}
				toggle(ev);
			});
			head.addEventListener('keydown', function (ev) {
				if (ev.key === 'Enter' || ev.key === ' ') toggle(ev);
			});
		}
	});

	if (forceOpenType) {
		var el = document.getElementById('type-' + forceOpenType);
		if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
	}
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
