<?php
/**
 * Find broken /catalog/ links inside vendor DETAIL_TEXT and resolve section CODEs.
 * php tools/perf/check-vendor-404-links.php
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

$vendorIds = [324, 415]; // monitor, medtekhnika
$brokenWanted = [
	'reanimatologiya',
	'koltsa-skleroderzhateli-trabekulotomy-fiksatory',
];

foreach ($vendorIds as $id) {
	$r = $m->query('SELECT ID,CODE,NAME,DETAIL_TEXT,PREVIEW_TEXT FROM b_iblock_element WHERE ID=' . (int)$id);
	$row = $r->fetch_assoc();
	if (!$row) {
		echo "missing element $id\n";
		continue;
	}
	echo '===== ' . $row['CODE'] . " (ID {$row['ID']}) =====\n";
	$text = (string)($row['DETAIL_TEXT'] ?? '') . "\n" . (string)($row['PREVIEW_TEXT'] ?? '');
	if (!preg_match_all('#/catalog/([a-z0-9_-]+)/#u', $text, $mm)) {
		echo "(no catalog links)\n";
		continue;
	}
	$codes = array_unique($mm[1]);
	sort($codes);
	foreach ($codes as $code) {
		$sr = $m->query(
			"SELECT ID,ACTIVE,CODE,NAME FROM b_iblock_section WHERE IBLOCK_ID=24 AND CODE='"
			. $m->real_escape_string($code) . "' LIMIT 1"
		);
		$sec = $sr ? $sr->fetch_assoc() : null;
		$status = $sec ? ('OK id=' . $sec['ID'] . ' A=' . $sec['ACTIVE']) : 'MISSING';
		$mark = in_array($code, $brokenWanted, true) || !$sec ? ' ***' : '';
		echo "/catalog/{$code}/ => {$status}{$mark}\n";
	}
	foreach ($brokenWanted as $frag) {
		$pos = mb_stripos($text, $frag);
		if ($pos !== false) {
			echo 'CTX: ' . mb_substr($text, max(0, $pos - 100), 280) . "\n\n";
		}
	}
}

echo "=== similar sections ===\n";
$likes = [
	'%reanim%',
	'%anestez%',
	'%intensiv%',
	'%sklero%',
	'%trabek%',
	'%koltsa%',
	'%skleroder%',
];
foreach ($likes as $like) {
	$likeEsc = $m->real_escape_string($like);
	$r = $m->query(
		"SELECT ID,ACTIVE,CODE,NAME FROM b_iblock_section
		 WHERE IBLOCK_ID=24 AND (CODE LIKE '{$likeEsc}' OR NAME LIKE '{$likeEsc}')
		 ORDER BY ID LIMIT 40"
	);
	echo "-- {$like} --\n";
	while ($row = $r->fetch_assoc()) {
		echo $row['ID'] . ' | ' . $row['ACTIVE'] . ' | ' . $row['CODE'] . ' | ' . $row['NAME'] . "\n";
	}
}
