<?php
// VILMED: восстановление bitrix/html_pages/.config.php после CLEAR_HTML_PAGES.
// Делает то же, что админка /bitrix/admin/composite.php → «Сохранить»:
// перезаписывает .config.php из текущих опций композита (Helper::setOptions).
// Запуск: php tools/perf/composite-restore-config.php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
if (empty($_SERVER["DOCUMENT_ROOT"])) {
	$_SERVER["DOCUMENT_ROOT"] = dirname(dirname(__DIR__));
}
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Composite\Helper;

if (!class_exists(Helper::class)) {
	fwrite(STDERR, "Composite Helper not available\n");
	exit(1);
}

Helper::setOptions();

$path = Helper::getConfigFilePath();
echo (is_file($path) ? "OK: .config.php restored -> ".$path : "FAILED: .config.php missing")."\n";
echo "composite enabled: ".(Helper::isOn() ? "Y" : "N")."\n";
