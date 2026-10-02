#!/usr/bin/env php
<?php
/**
 * Decode repeatedly HTML-encoded product NAME + iblock iprop SEO values:
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

function vilmedFixTable(mysqli $m, string $sql, string $updateSql, array $bindTypes, bool $dry, int $limit): array
{
	if ($limit > 0) {
		$sql .= ' LIMIT ' . (int)$limit;
	}
	$res = $m->query($sql);
	if (!$res) {
		fwrite(STDERR, $m->error . "\n");
		exit(1);
	}
	$upd = $dry ? null : $m->prepare($updateSql);
	if (!$dry && !$upd) {
		fwrite(STDERR, $m->error . "\n");
		exit(1);
	}
	$checked = 0;
	$changed = 0;
	$failed = 0;
	while ($row = $res->fetch_assoc()) {
		$checked++;
		$from = (string)$row['VALUE'];
		$to = vilmedDecodeNameEntities($from);
		if ($to === $from) {
			continue;
		}
		$changed++;
		$idLabel = isset($row['ID']) ? ('#' . $row['ID']) : ('#' . $row['ELEMENT_ID'] . '/' . $row['IPROP_ID']);
		echo $idLabel . ' ' . mb_substr($from, 0, 100) . "\n  → " . mb_substr($to, 0, 100) . "\n";
		if ($dry) {
			continue;
		}
		if ($bindTypes === 'si') {
			$id = (int)$row['ID'];
			$upd->bind_param('si', $to, $id);
		} else {
			$eid = (int)$row['ELEMENT_ID'];
			$ipid = (int)$row['IPROP_ID'];
			$upd->bind_param('sii', $to, $eid, $ipid);
		}
		if (!$upd->execute()) {
			$failed++;
			fwrite(STDERR, "FAIL {$idLabel}: " . $upd->error . "\n");
		}
	}
	return compact('checked', 'changed', 'failed');
}

echo "== b_iblock_element.NAME ==\n";
$r1 = vilmedFixTable(
	$m,
	"SELECT ID, NAME AS VALUE FROM b_iblock_element WHERE NAME LIKE '%&%;%' OR NAME LIKE '%&#%' ORDER BY ID",
	'UPDATE b_iblock_element SET NAME = ? WHERE ID = ?',
	'si',
	$dry,
	$limit
);
echo "NAME checked={$r1['checked']} changed={$r1['changed']} failed={$r1['failed']}\n";

echo "== b_iblock_element_iprop.VALUE ==\n";
$r2 = vilmedFixTable(
	$m,
	"SELECT ELEMENT_ID, IPROP_ID, VALUE FROM b_iblock_element_iprop WHERE VALUE LIKE '%&%;%' OR VALUE LIKE '%&#%' ORDER BY ELEMENT_ID",
	'UPDATE b_iblock_element_iprop SET VALUE = ? WHERE ELEMENT_ID = ? AND IPROP_ID = ?',
	'sii',
	$dry,
	$limit
);
echo "IPROP checked={$r2['checked']} changed={$r2['changed']} failed={$r2['failed']}\n";
echo 'dry=' . ($dry ? 'Y' : 'N') . "\n";
