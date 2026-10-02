#!/usr/bin/env php
<?php
/**
 * Decode repeatedly HTML-encoded product NAME values:
 *   &amp;amp;…quot;Гулливер → "Гулливер"
 * Usage (prod):
 *   php tools/perf/fix-amp-encoded-names.php [--dry-run] [--limit=N]
 */
if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$root = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $root;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
require $root . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');

$dry = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $a) {
	if (preg_match('/^--limit=(\d+)$/', $a, $m)) {
		$limit = (int)$m[1];
	}
}

function vilmedDecodeNameEntities(string $name): string
{
	$prev = null;
	$cur = $name;
	for ($i = 0; $i < 32 && $cur !== $prev; $i++) {
		$prev = $cur;
		$cur = html_entity_decode($cur, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return $cur;
}

$filter = [
	'NAME' => '%&amp;%',
	'CHECK_PERMISSIONS' => 'N',
];
$select = ['ID', 'IBLOCK_ID', 'NAME', 'CODE'];
$rs = CIBlockElement::GetList(['ID' => 'ASC'], $filter, false, $limit > 0 ? ['nTopCount' => $limit] : false, $select);

$el = new CIBlockElement();
$checked = 0;
$changed = 0;
$failed = 0;

while ($row = $rs->Fetch()) {
	$checked++;
	$id = (int)$row['ID'];
	$from = (string)$row['NAME'];
	$to = vilmedDecodeNameEntities($from);
	if ($to === $from) {
		continue;
	}
	$changed++;
	echo sprintf(
		"#%d [%s] %s\n  → %s\n",
		$id,
		$row['CODE'] ?: '-',
		mb_substr($from, 0, 120),
		mb_substr($to, 0, 120)
	);
	if ($dry) {
		continue;
	}
	$ok = $el->Update($id, ['NAME' => $to, 'TIMESTAMP_X' => false]);
	if (!$ok) {
		$failed++;
		fwrite(STDERR, "FAIL {$id}: " . $el->LAST_ERROR . "\n");
	}
}

echo "checked={$checked} changed={$changed} failed={$failed} dry=" . ($dry ? 'Y' : 'N') . "\n";
