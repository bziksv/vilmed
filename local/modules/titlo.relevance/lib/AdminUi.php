<?php

namespace Titlo\Relevance;

/**
 * Общие ассеты админки модуля (единый CSS).
 */
class AdminUi
{
	/** @var bool */
	protected static $cssDone = false;

	public static function renderCss(): void
	{
		if (self::$cssDone) {
			return;
		}
		self::$cssDone = true;

		$rel = '/local/modules/titlo.relevance/admin/css/titlo-relevance.css';
		$abs = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $rel;
		$v = is_file($abs) ? (string) filemtime($abs) : '1';
		echo '<link rel="stylesheet" href="' . htmlspecialcharsbx($rel) . '?v=' . htmlspecialcharsbx($v) . '">' . "\n";
	}

	/**
	 * Фильтр «ветка каталога»: поиск по имени + select с деревом разделов.
	 *
	 * @param int $selectedId выбранный раздел (0 = все)
	 * @param string $entityHint подсказка: «товары» | «категории»
	 */
	public static function renderSectionBranchFilter(int $selectedId = 0, string $entityHint = 'товары'): void
	{
		$selectedId = (int) $selectedId;
		$tree = CatalogRepository::listSectionTreeForSelect();
		$hint = $entityHint === 'категории'
			? 'Выберите раздел: покажем его и все вложенные категории.'
			: 'Выберите раздел: покажем товары из него и всех вложенных подразделов.';
		?>
		<label class="titlo-section-branch">Ветка каталога
			<span class="titlo-help" tabindex="0" aria-label="Ветка каталога">?
				<span class="titlo-help__tip">
					<?= htmlspecialcharsbx($hint) ?><br>
					Поиск сверху сужает список разделов (например, наберите «офтальмоскоп»).
					«Все разделы» — без ограничения по дереву.
				</span>
			</span>:
			<span class="titlo-section-branch__controls">
				<input type="text" id="titlo-auto-section-q" class="adm-input titlo-section-branch__search"
					placeholder="Поиск раздела…" autocomplete="off" spellcheck="false">
				<select id="titlo-auto-section" class="adm-input titlo-section-branch__select">
					<option value="0" <?= $selectedId <= 0 ? 'selected' : '' ?>>Все разделы</option>
					<?php foreach ($tree as $sec): ?>
						<option
							value="<?= (int) $sec['id'] ?>"
							data-name="<?= htmlspecialcharsbx($sec['name']) ?>"
							<?= $selectedId === (int) $sec['id'] ? 'selected' : '' ?>
						><?= htmlspecialcharsbx($sec['label']) ?></option>
					<?php endforeach; ?>
				</select>
			</span>
		</label>
		<?php
	}
}
