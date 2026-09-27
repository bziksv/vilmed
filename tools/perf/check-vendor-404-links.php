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

// Scan all vendor DETAIL_TEXT for /catalog/{code}/ that have no section
$r = $m->query('SELECT ID,CODE,NAME,DETAIL_TEXT FROM b_iblock_element WHERE IBLOCK_ID=13 AND DETAIL_TEXT LIKE "%/catalog/%"');
$missing = [];
while ($row = $r->fetch_assoc()) {
	$text = (string)$row['DETAIL_TEXT'];
	if (!preg_match_all('#/catalog/([a-z0-9_-]+)/#u', $text, $mm)) {
		continue;
	}
	foreach (array_unique($mm[1]) as $code) {
		$sr = $m->query(
			"SELECT ID,ACTIVE FROM b_iblock_section WHERE IBLOCK_ID=24 AND CODE='"
			. $m->real_escape_string($code) . "' LIMIT 1"
		);
		$sec = $sr ? $sr->fetch_assoc() : null;
		if (!$sec || $sec['ACTIVE'] !== 'Y') {
			$key = $code;
			if (!isset($missing[$key])) {
				$missing[$key] = [];
			}
			$missing[$key][] = $row['CODE'] . '#' . $row['ID'];
		}
	}
}
echo "=== missing catalog links in vendors DETAIL_TEXT ===\n";
ksort($missing);
foreach ($missing as $code => $vendors) {
	echo "/catalog/{$code}/ <= " . implode(', ', array_unique($vendors)) . "\n";
}
echo 'total_missing_codes=' . count($missing) . "\n";
