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
}
