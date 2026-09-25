<?php
/**
 * Re-sanitize DETAIL_TEXT / PREVIEW_TEXT with VMD markup (SVG/.ic, FAQ summary, mark).
 * Run on prod: php tools/perf/fix-w3c-vmd-html.php [--limit=N]
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
require_once $docRoot . '/local/modules/titlo.relevance/lib/Config.php';

$limit = 0;
foreach ($argv as $arg) {
	if (strpos($arg, '--limit=') === 0) {
		$limit = (int)substr($arg, 8);
	}
}

$sql = "SELECT ID, IBLOCK_ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element
	WHERE (DETAIL_TEXT LIKE '%class=\"ic\"%' OR DETAIL_TEXT LIKE '%<summary%' OR DETAIL_TEXT LIKE '%<mark%'
		OR DETAIL_TEXT LIKE '%<circle%' OR DETAIL_TEXT LIKE '%vmd-faq%'
		OR DETAIL_TEXT LIKE '%<details><details%'
		OR PREVIEW_TEXT LIKE '%<li>%' OR PREVIEW_TEXT LIKE '%</li%')
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
	$newDetail = $detail !== '' ? \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($detail) : $detail;
	$newPreview = $preview;
	if ($preview !== '' && (stripos($preview, '<li') !== false || stripos($preview, '<div') !== false || stripos($preview, '<p') !== false)) {
		// Preview for cards: plain text is safer; keep short HTML sanitized if AI wrote lists
		$newPreview = \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($preview);
		// Wrap orphan <li>…</li> sequences in <ul>
		if (preg_match('#<li\b#i', $newPreview) && !preg_match('#<(?:ul|ol)\b#i', $newPreview)) {
			$newPreview = preg_replace('#((?:\s*<li\b.*?</li>)+)#is', '<ul>$1</ul>', $newPreview) ?? $newPreview;
		}
	}

	if ($newDetail === $detail && $newPreview === $preview) {
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

echo "scanned={$scanned} updated={$updated}\n";
