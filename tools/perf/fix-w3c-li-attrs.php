<?php
/**
 * W3C: double <li>, p="", #ссс, <a name>, hN>div, bare "< ", no-space attrs.
 * Run on prod: php tools/perf/fix-w3c-li-attrs.php [--limit=N] [--dry]
 */
$docRoot = dirname(__DIR__, 2);
$settings = include $docRoot . '/bitrix/.settings.php';
$db = $settings['connections']['value']['default'];
$m = new mysqli($db['host'], $db['login'], $db['password'], $db['database']);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . "\n");
	exit(1);
}
$m->set_charset('utf8');

require_once $docRoot . '/local/modules/titlo.relevance/lib/CatalogRepository.php';

$limit = 0;
$dry = false;
foreach ($argv as $arg) {
	if (strpos($arg, '--limit=') === 0) {
		$limit = (int)substr($arg, 8);
	}
	if ($arg === '--dry') {
		$dry = true;
	}
}

// Cyrillic «с» in hex colors; double-li; junk attrs; named anchors; heading>div; bare "< "
$sql = "SELECT ID, IBLOCK_ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element
	WHERE DETAIL_TEXT LIKE '%<li>%<li>%'
	   OR DETAIL_TEXT LIKE '%</li>%</li>%'
	   OR DETAIL_TEXT LIKE '% p=\"\"%'
	   OR DETAIL_TEXT LIKE '% p=\"%'
	   OR DETAIL_TEXT LIKE '%name=\"%'
	   OR DETAIL_TEXT LIKE '%<a name%'
	   OR DETAIL_TEXT LIKE '%#ссс%'
	   OR DETAIL_TEXT LIKE '%#сссссс%'
	   OR DETAIL_TEXT LIKE '%\"title=%'
	   OR DETAIL_TEXT LIKE '%\"alt=%'
	   OR DETAIL_TEXT LIKE '%\"src=%'
	   OR DETAIL_TEXT LIKE '%<h3>%<div%'
	   OR DETAIL_TEXT LIKE '%<h2>%<div%'
	   OR DETAIL_TEXT LIKE '%< %'
	   OR DETAIL_TEXT LIKE '%; title=%'
	   OR PREVIEW_TEXT LIKE '%<li>%<li>%'
	ORDER BY ID";
if ($limit > 0) {
	$sql .= ' LIMIT ' . $limit;
}

$res = $m->query($sql);
if (!$res) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

$updated = 0;
$scanned = 0;
while ($row = $res->fetch_assoc()) {
	$scanned++;
	$id = (int)$row['ID'];
	$detail = (string)$row['DETAIL_TEXT'];
	$preview = (string)$row['PREVIEW_TEXT'];
	$newDetail = $detail !== '' ? \Titlo\Relevance\CatalogRepository::fixW3cMarkupGlitches($detail) : $detail;
	$newPreview = $preview !== '' ? \Titlo\Relevance\CatalogRepository::fixW3cMarkupGlitches($preview) : $preview;

	if ($newDetail === $detail && $newPreview === $preview) {
		continue;
	}

	if ($dry) {
		echo "DRY {$row['IBLOCK_ID']}:{$id} {$row['CODE']} " . strlen($detail) . '→' . strlen($newDetail) . "\n";
		$updated++;
		continue;
	}

	$upd = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT = ?, PREVIEW_TEXT = ? WHERE ID = ?');
	$upd->bind_param('ssi', $newDetail, $newPreview, $id);
	if ($upd->execute()) {
		$updated++;
		echo "OK {$row['IBLOCK_ID']}:{$id} {$row['CODE']}\n";
	} else {
		echo "FAIL {$id} " . $m->error . "\n";
	}
}

echo "scanned={$scanned} updated={$updated}" . ($dry ? " (dry)\n" : "\n");
