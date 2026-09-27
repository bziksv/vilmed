<?php
/**
 * Fix broken catalog links in vendor DETAIL_TEXT + suggest redirects.
 * php tools/perf/fix-vendor-broken-catalog-links.php [--dry]
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
$dry = in_array('--dry', $argv ?? [], true);

// broken CODE => replacement CODE (must exist in iblock 24)
$map = [
	'reanimatologiya' => 'anesteziologiya',
	'koltsa-skleroderzhateli-trabekulotomy-fiksatory' => 'khirurgiya',
];

foreach ($map as $from => $to) {
	$r = $m->query(
		"SELECT ID,ACTIVE,CODE FROM b_iblock_section WHERE IBLOCK_ID=24 AND CODE='"
		. $m->real_escape_string($to) . "' LIMIT 1"
	);
	$sec = $r ? $r->fetch_assoc() : null;
	if (!$sec || $sec['ACTIVE'] !== 'Y') {
		fwrite(STDERR, "replacement missing/inactive: {$to}\n");
		exit(1);
	}
	echo "OK target /catalog/{$to}/ id={$sec['ID']}\n";
}

// find all vendor DETAIL_TEXT containing broken paths
$patterns = array_keys($map);
$where = [];
foreach ($patterns as $p) {
	$esc = $m->real_escape_string($p);
	$where[] = "DETAIL_TEXT LIKE '%{$esc}%'";
}
$sql = 'SELECT ID,CODE,NAME,DETAIL_TEXT FROM b_iblock_element WHERE IBLOCK_ID=13 AND ('
	. implode(' OR ', $where) . ')';
$r = $m->query($sql);
$updated = 0;
while ($row = $r->fetch_assoc()) {
	$text = (string)$row['DETAIL_TEXT'];
	$new = $text;
	foreach ($map as $from => $to) {
		$new = str_replace(
			[
				'https://vilmed.ru/catalog/' . $from . '/',
				'http://vilmed.ru/catalog/' . $from . '/',
				'/catalog/' . $from . '/',
			],
			[
				'https://vilmed.ru/catalog/' . $to . '/',
				'https://vilmed.ru/catalog/' . $to . '/',
				'/catalog/' . $to . '/',
			],
			$new
		);
	}
	if ($new === $text) {
		echo "skip {$row['CODE']} (ID {$row['ID']}) — no replace\n";
		continue;
	}
	echo ($dry ? 'DRY ' : '') . "update vendor {$row['CODE']} (ID {$row['ID']})\n";
	if (!$dry) {
		$stmt = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT=? WHERE ID=?');
		$id = (int)$row['ID'];
		$stmt->bind_param('si', $new, $id);
		$stmt->execute();
		$stmt->close();
		$updated++;
	}
}

echo "updated={$updated}\n";

// also list similar oftalmo instrument sections for manual review
echo "=== oftalmo/khirurg sections (info) ===\n";
$r = $m->query(
	"SELECT ID,ACTIVE,CODE,NAME FROM b_iblock_section
	 WHERE IBLOCK_ID=24 AND (
	   CODE IN ('khirurgiya','oftalmologiya','igly-khirurgicheskie','instrumenty-travmatologiya','shovnyy-material')
	   OR NAME LIKE '%склеро%'
	   OR NAME LIKE '%трабекул%'
	   OR NAME LIKE '%офтальмохирург%'
	 )
	 ORDER BY ID LIMIT 40"
);
while ($row = $r->fetch_assoc()) {
	echo $row['ID'] . ' | ' . $row['ACTIVE'] . ' | ' . $row['CODE'] . ' | ' . $row['NAME'] . "\n";
}
