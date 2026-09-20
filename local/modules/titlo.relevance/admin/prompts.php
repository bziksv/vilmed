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
		$message = !empty($res['ok'])
			? 'Назначен по умолчанию для своего режима (полная или повторная).'
			: ($res['error'] ?? 'Ошибка');
		$messageType = !empty($res['ok']) ? 'OK' : 'ERROR';
	} elseif ($action === 'activate') {
		if ($id > 0) {
			$row = Prompts::find($id);
			if ($row) {
				Prompts::setActiveId($row['type'], $id);
				$modeLabel = Prompts::runModeLabel((string) ($row['run_mode'] ?? Prompts::RUN_MODE_FULL));
				$message = 'Выбран для режима «' . $modeLabel . '»: «' . $row['name'] . '».';
			}
		}
	}
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$catalog = Prompts::catalog();

/**
 * @param array $it
 * @param string $type
 * @param int $activeId
 * @param int $typeTotal
 * @param string $self
 * @param bool $lockMode
 * @param string $fixedMode
 */
$renderPromptCard = static function (
	array $it,
	string $type,
	int $activeId,
	int $typeTotal,
	string $self,
	bool $lockMode = false,
	string $fixedMode = ''
): void {
	$mode = Prompts::normalizePromptRunMode((string) ($it['run_mode'] ?? Prompts::RUN_MODE_FULL));
	?>
	<form method="post" action="<?= htmlspecialcharsbx($self) ?>" class="card<?= $it['id'] === $activeId ? ' is-active' : '' ?>">
		<?= bitrix_sessid_post() ?>
		<input type="hidden" name="titlo_action" value="save">
		<input type="hidden" name="type" value="<?= htmlspecialcharsbx($type) ?>">
		<input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
		<input type="hidden" name="focus_type" value="<?= htmlspecialcharsbx($type) ?>">
		<?php if ($lockMode): ?>
			<input type="hidden" name="run_mode" value="<?= htmlspecialcharsbx($fixedMode !== '' ? $fixedMode : $mode) ?>">
		<?php endif; ?>
		<div class="card-h">
			<input type="text" name="name" class="adm-input" value="<?= htmlspecialcharsbx($it['name']) ?>" placeholder="Название варианта">
			<?php if (!$lockMode): ?>
				<label class="titlo-prompt-mode">
					Режим
					<select name="run_mode" class="adm-input">
						<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_FULL) ?>" <?= $mode === Prompts::RUN_MODE_FULL ? 'selected' : '' ?>>полная проработка</option>
						<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_REFINE) ?>" <?= $mode === Prompts::RUN_MODE_REFINE ? 'selected' : '' ?>>повторная доработка</option>
						<option value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_ANY) ?>" <?= $mode === Prompts::RUN_MODE_ANY ? 'selected' : '' ?>>любой режим</option>
					</select>
				</label>
			<?php endif; ?>
			<?php if ($it['is_default']): ?>
				<span class="badge badge-def">по умолчанию в режиме</span>
			<?php endif; ?>
			<?php if ($it['id'] === $activeId): ?>
				<span class="badge badge-active">выбран для режима</span>
			<?php endif; ?>
			<?php if (!$lockMode): ?>
				<span class="badge badge-mode"><?= htmlspecialcharsbx($it['run_mode_label'] ?? Prompts::runModeLabel($mode)) ?></span>
			<?php endif; ?>
			<span class="badge badge-used"><?= htmlspecialcharsbx($it['last_used_label']) ?></span>
		</div>
		<textarea name="body" rows="9"><?= htmlspecialcharsbx($it['body']) ?></textarea>
		<div class="card-actions">
			<input type="submit" class="adm-btn-save" value="Сохранить"
				onclick="this.form.titlo_action.value='save'">
			<input type="submit" class="adm-btn" value="Выбрать для режима"
				onclick="this.form.titlo_action.value='activate'">
			<?php if (!$it['is_default']): ?>
				<input type="submit" class="adm-btn" value="По умолчанию в режиме"
					onclick="this.form.titlo_action.value='default'">
			<?php endif; ?>
			<?php if ($typeTotal > 1): ?>
				<input type="submit" class="adm-btn" value="Удалить"
					data-confirm-name="<?= htmlspecialcharsbx($it['name'], ENT_QUOTES) ?>"
					onclick="this.form.titlo_action.value='delete'; return confirm('Удалить «' + (this.getAttribute('data-confirm-name') || '') + '»?');">
			<?php endif; ?>
		</div>
	</form>
	<?php
};

/**
 * @param string $type
 * @param string $mode
 * @param array $items
 * @param string $self
 * @param callable $renderPromptCard
 */
$renderModeSection = static function (
	string $type,
	string $mode,
	array $items,
	string $self,
	callable $renderPromptCard,
	bool $isActive = false
): void {
	$meta = Prompts::runModeSectionMeta($mode);
	$activeId = Prompts::getActiveId($type, $mode);
	$typeTotal = count(Prompts::listByType($type));
	$count = count($items);
	?>
	<div class="mode-block mode-block--<?= htmlspecialcharsbx($mode) ?><?= $isActive ? ' is-open' : '' ?>"
		data-mode-panel="<?= htmlspecialcharsbx($mode) ?>"
		<?= $isActive ? '' : 'hidden' ?>>
		<div class="mode-block__h">
			<h4><?= htmlspecialcharsbx($meta['title']) ?> <span class="mode-count">(<?= (int) $count ?>)</span></h4>
			<p><?= htmlspecialcharsbx($meta['hint']) ?></p>
		</div>
		<?php if (!$items): ?>
			<p class="meta">В этом режиме пока нет промптов.</p>
		<?php endif; ?>
		<?php foreach ($items as $it): ?>
			<?php $renderPromptCard($it, $type, $activeId, $typeTotal, $self, true, $mode); ?>
		<?php endforeach; ?>
		<form method="post" action="<?= htmlspecialcharsbx($self) ?>" class="add-row">
			<?= bitrix_sessid_post() ?>
			<input type="hidden" name="titlo_action" value="add">
			<input type="hidden" name="type" value="<?= htmlspecialcharsbx($type) ?>">
			<input type="hidden" name="focus_type" value="<?= htmlspecialcharsbx($type) ?>">
			<input type="hidden" name="run_mode" value="<?= htmlspecialcharsbx($mode) ?>">
			<input type="submit" class="adm-btn" value="+ Добавить в «<?= htmlspecialcharsbx($meta['title']) ?>»">
		</form>
	</div>
	<?php
};
?>

<?php AdminUi::renderCss(); ?>

<div class="titlo-prompts">
	<?php if ($message): ?>
		<div class="adm-info-message-wrap adm-info-message-<?= $messageType === 'ERROR' ? 'red' : 'green' ?>">
			<div class="adm-info-message"><?= htmlspecialcharsbx($message) ?></div>
		</div>
	<?php endif; ?>

	<div class="hint-box">
		Разделы: <b>товары</b> и <b>категории</b>. Внутри детального текста / текста категории —
		два режима:
		<br>
		• <b>полная проработка</b> — пишем описание с нуля (первый проход);
		<br>
		• <b>повторная доработка</b> — уже есть текст, дописываем под TLP без урезания стилей.
		<br><br>
		«По умолчанию» и «Выбрать» работают <b>отдельно для каждого режима</b>: дефолт полной
		не затирает дефолт доработки. В автопроработке в селекте видны только промпты текущего режима.
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
				$usesModes = Prompts::typeUsesRunModes($type);
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
							(режим: <?= htmlspecialcharsbx($last['run_mode_label'] ?? '') ?>,
							запусков варианта: <?= (int) $last['use_count'] ?>)</p>
					<?php else: ?>
						<p class="meta">По этому типу генераций ещё не было.</p>
					<?php endif; ?>

					<?php if ($usesModes): ?>
						<?php
						$parts = Prompts::partitionByRunMode($items);
						$tabModes = [Prompts::RUN_MODE_FULL, Prompts::RUN_MODE_REFINE];
						if ($parts[Prompts::RUN_MODE_ANY]) {
							$tabModes[] = Prompts::RUN_MODE_ANY;
						}
						$openMode = Prompts::RUN_MODE_FULL;
						if ($message && $openType === $type) {
							$postedMode = (string) ($_POST['run_mode'] ?? '');
							if (in_array($postedMode, $tabModes, true)) {
								$openMode = $postedMode;
							}
						}
						?>
						<div class="mode-tabs" data-mode-tabs>
							<?php foreach ($tabModes as $modeKey): ?>
								<?php
								$sec = Prompts::runModeSectionMeta($modeKey);
								$n = count($parts[$modeKey] ?? []);
								?>
								<button type="button"
									class="mode-tab<?= $modeKey === $openMode ? ' is-active' : '' ?>"
									data-mode-tab="<?= htmlspecialcharsbx($modeKey) ?>">
									<?= htmlspecialcharsbx($sec['title']) ?>
									<span class="mode-tab__n"><?= (int) $n ?></span>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="mode-panels">
							<?php foreach ($tabModes as $modeKey): ?>
								<?php $renderModeSection($type, $modeKey, $parts[$modeKey] ?? [], $self, $renderPromptCard, $modeKey === $openMode); ?>
							<?php endforeach; ?>
						</div>
					<?php else: ?>
						<?php
						$activeId = Prompts::getActiveId($type);
						$typeTotal = count($items);
						foreach ($items as $it) {
							// анонс / фразы — без свалки режимов
							$renderPromptCard($it, $type, $activeId, $typeTotal, $self, true, Prompts::RUN_MODE_FULL);
						}
						?>
						<form method="post" action="<?= htmlspecialcharsbx($self) ?>" class="add-row">
							<?= bitrix_sessid_post() ?>
							<input type="hidden" name="titlo_action" value="add">
							<input type="hidden" name="type" value="<?= htmlspecialcharsbx($type) ?>">
							<input type="hidden" name="focus_type" value="<?= htmlspecialcharsbx($type) ?>">
							<input type="hidden" name="run_mode" value="<?= htmlspecialcharsbx(Prompts::RUN_MODE_FULL) ?>">
							<input type="submit" class="adm-btn" value="+ Добавить вариант промпта">
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>
(function () {
	var storageKey = 'titlo_prompts_groups_v2';
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
				if (ev.key === 'Enter' || ev.key === ' ') {
					toggle(ev);
				}
			});
		}
	});
	Array.prototype.forEach.call(document.querySelectorAll('.titlo-prompts [data-mode-tabs]'), function (tabs) {
		var host = tabs.closest('.type-block');
		if (!host) return;
		Array.prototype.forEach.call(tabs.querySelectorAll('[data-mode-tab]'), function (btn) {
			btn.addEventListener('click', function () {
				var mode = btn.getAttribute('data-mode-tab') || '';
				Array.prototype.forEach.call(tabs.querySelectorAll('[data-mode-tab]'), function (b) {
					b.classList.toggle('is-active', b === btn);
				});
				Array.prototype.forEach.call(host.querySelectorAll('[data-mode-panel]'), function (panel) {
					var on = panel.getAttribute('data-mode-panel') === mode;
					panel.hidden = !on;
					panel.classList.toggle('is-open', on);
				});
			});
		});
	});
})();
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
