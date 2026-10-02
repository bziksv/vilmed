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

$docRoot = dirname(__DIR__, 2);
$settings = include $docRoot . '/bitrix/.settings.php';
$db = $settings['connections']['value']['default'];
$m = new mysqli($db['host'], $db['login'], $db['password'], $db['database']);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . "\n");
	exit(1);
}
$m->set_charset('utf8');

$dry = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $a) {
	if (preg_match('/^--limit=(\d+)$/', $a, $mm)) {
		$limit = (int)$mm[1];
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

$sql = "SELECT ID, IBLOCK_ID, CODE, NAME FROM b_iblock_element WHERE NAME LIKE '%&amp;%' ORDER BY ID";
if ($limit > 0) {
	$sql .= ' LIMIT ' . (int)$limit;
}
$res = $m->query($sql);
if (!$res) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

$checked = 0;
$changed = 0;
$failed = 0;
$upd = $m->prepare('UPDATE b_iblock_element SET NAME = ? WHERE ID = ?');
if (!$upd) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

while ($row = $res->fetch_assoc()) {
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
	$upd->bind_param('si', $to, $id);
	if (!$upd->execute()) {
		$failed++;
		fwrite(STDERR, "FAIL {$id}: " . $upd->error . "\n");
	}
}

echo "checked={$checked} changed={$changed} failed={$failed} dry=" . ($dry ? 'Y' : 'N') . "\n";
