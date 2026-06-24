#!/usr/bin/env php
<?php
/**
 * Восстановить bitrix/html_pages/.config.php после wipe html_pages.
 * Без Bitrix bootstrap (CLI short_open_tag=Off на prod).
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

$htmlPagesDir = $docRoot . '/bitrix/html_pages';
$configPath = $htmlPagesDir . '/.config.php';
$enabledPath = $htmlPagesDir . '/.enabled';

if (!is_dir($htmlPagesDir) && !mkdir($htmlPagesDir, 0755, true) && !is_dir($htmlPagesDir)) {
	fwrite(STDERR, "Cannot create $htmlPagesDir\n");
	exit(1);
}

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

$content = '<?' . "\n\$arHTMLPagesOptions = array(\n";
foreach ($options as $key => $value) {
	if (is_array($value)) {
		$content .= "\t\"{$key}\" => array(\n";
		foreach ($value as $k2 => $v2) {
			$content .= "\t\t\"{$k2}\" => \"{$v2}\",\n";
		}
		$content .= "\t),\n";
		continue;
	}
	$content .= "\t\"{$key}\" => \"{$value}\",\n";
}
$content .= ");\n?>";

$tmp = $configPath . '.' . bin2hex(random_bytes(4)) . '.tmp';
if (file_put_contents($tmp, $content) === false) {
	fwrite(STDERR, "Cannot write temp config\n");
	exit(1);
}
if (!rename($tmp, $configPath)) {
	@unlink($tmp);
	fwrite(STDERR, "Cannot rename config to $configPath\n");
	exit(1);
}
@chmod($configPath, 0664);

if (!is_file($enabledPath)) {
	if (file_put_contents($enabledPath, '') === false) {
		fwrite(STDERR, "Cannot create .enabled\n");
		exit(1);
	}
}
@chmod($enabledPath, 0664);

echo 'OK: .config.php ' . ($hadConfig ? 'updated' : 'created') . ' (' . filesize($configPath) . " bytes)\n";
echo "OK: .enabled present\n";
echo "Next: bash tools/perf/prod-warmup.sh https://vilmed.ru\n";
echo "Check: bash tools/perf/prod-composite-check.sh https://vilmed.ru\n";
