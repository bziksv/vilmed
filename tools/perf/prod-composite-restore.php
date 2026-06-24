#!/usr/bin/env php
<?php
/**
 * Восстановить bitrix/html_pages/.config.php после wipe html_pages.
 * Эквивалент «Сохранить» в /bitrix/admin/composite.php (режим Авто).
 *
 *   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
 *   php tools/perf/prod-composite-restore.php
 */
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$docRoot = realpath(__DIR__ . '/../..');
if ($docRoot === false) {
	fwrite(STDERR, "Cannot find document root\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);
define('BX_CRONTAB', true);

require $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('main')) {
	fwrite(STDERR, "Cannot load main module\n");
	exit(1);
}

use Bitrix\Main\Composite\Helper;

$configPath = Helper::getConfigFilePath();
$hadConfig = is_file($configPath);

$options = [
	'AUTO_COMPOSITE' => 'Y',
	'FRAME_MODE' => 'Y',
	'FRAME_TYPE' => 'DYNAMIC_WITH_STUB',
	'AUTO_UPDATE' => 'Y',
	'AUTO_UPDATE_TTL' => '120',
	'STORAGE' => 'files',
	'COMPOSITE' => 'Y',
	'INCLUDE_MASK' => '/*',
	'EXCLUDE_MASK' => '/bitrix/*; /404.php; /personal/*; /personal/order/make/*;',
	'EXCLUDE_PARAMS' => 'ncc; ',
	'DOMAINS' => [
		'vilmed.ru' => 'vilmed.ru',
		'www.vilmed.ru' => 'www.vilmed.ru',
	],
	'IGNORED_PARAMETERS' =>
		'utm_source; utm_medium; utm_campaign; utm_content; fb_action_ids; '
		. 'utm_term; yclid; gclid; _openstat; from; '
		. 'referrer1; r1; referrer2; r2; referrer3; r3; ',
	'ONLY_PARAMETERS' => 'id; ELEMENT_ID; SECTION_ID; PAGEN_1; ',
	'FILE_QUOTA' => '500',
	'WRITE_STATISTIC' => 'Y',
];

Helper::setEnabled(true);
Helper::setOptions($options);

if (!is_file($configPath)) {
	fwrite(STDERR, "FAIL: .config.php not created at $configPath\n");
	exit(1);
}

$enabledPath = Helper::getEnabledFilePath();
echo 'OK: .config.php ' . ($hadConfig ? 'updated' : 'created') . ' (' . filesize($configPath) . " bytes)\n";
echo 'OK: .enabled ' . (is_file($enabledPath) ? 'present' : 'MISSING') . "\n";
echo "Next: bash tools/perf/prod-warmup.sh https://vilmed.ru\n";
echo "Check: bash tools/perf/prod-composite-check.sh https://vilmed.ru\n";
