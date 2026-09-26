<?php
/**
 * Vendors: drop leading Hn matching ELEMENT_PAGE_TITLE / «Оборудование компании {NAME}».
 * php tools/perf/fix-vendor-dup-h2.php [--dry]
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

$dry = in_array('--dry', $argv, true);

$res = $m->query(
	'SELECT e.ID, e.CODE, e.NAME, e.PREVIEW_TEXT, e.DETAIL_TEXT,
		(SELECT VALUE FROM b_iblock_element_iprop WHERE ELEMENT_ID=e.ID AND CODE="ELEMENT_PAGE_TITLE" LIMIT 1) AS PAGE_TITLE
	 FROM b_iblock_element e
	 WHERE e.IBLOCK_ID=13 AND e.ACTIVE="Y"'
);
if (!$res) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

$updated = 0;
while ($row = $res->fetch_assoc()) {
	$title = trim((string)($row['PAGE_TITLE'] ?? ''));
	if ($title === '') {
		$title = 'Оборудование компании ' . $row['NAME'];
	}
	$id = (int)$row['ID'];
	$fields = [];
	foreach (['PREVIEW_TEXT', 'DETAIL_TEXT'] as $field) {
		$raw = (string)$row[$field];
		if ($raw === '') {
			continue;
		}
		$new = \Titlo\Relevance\CatalogRepository::stripLeadingHeadingIfEquals($raw, $title);
		if ($new !== $raw) {
			$fields[$field] = $new;
		}
	}
	if ($fields === []) {
		continue;
	}
	if ($dry) {
		echo "DRY {$row['CODE']} " . implode(',', array_keys($fields)) . "\n";
		$updated++;
		continue;
	}
	$set = [];
	$types = '';
	$vals = [];
	foreach ($fields as $f => $v) {
		$set[] = "$f = ?";
		$types .= 's';
		$vals[] = $v;
	}
	$types .= 'i';
	$vals[] = $id;
	$sql = 'UPDATE b_iblock_element SET ' . implode(', ', $set) . ' WHERE ID = ?';
	$upd = $m->prepare($sql);
	$upd->bind_param($types, ...$vals);
	if ($upd->execute()) {
		$updated++;
		echo "OK {$row['CODE']} " . implode(',', array_keys($fields)) . "\n";
	} else {
		echo "FAIL {$row['CODE']} " . $m->error . "\n";
	}
}
echo 'updated=' . $updated . ($dry ? " (dry)\n" : "\n");
