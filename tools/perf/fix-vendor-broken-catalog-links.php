<?php
/**
 * Fix broken /catalog/ links in vendor DETAIL_TEXT (iblock 13).
 * - known map
 * - CODE ending with -r → same CODE without -r if section exists
 *
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

function sectionActive(mysqli $m, string $code): bool
{
	$esc = $m->real_escape_string($code);
	$r = $m->query("SELECT ID FROM b_iblock_section WHERE IBLOCK_ID=24 AND ACTIVE='Y' AND CODE='{$esc}' LIMIT 1");
	return $r && (bool)$r->fetch_assoc();
}

/** @return array<string,string> broken => replacement */
function buildReplaceMap(mysqli $m): array
{
	$map = [
		'reanimatologiya' => 'anesteziologiya',
		'koltsa-skleroderzhateli-trabekulotomy-fiksatory' => 'khirurgiya',
		'telezhki-meditsinskie' => 'telezhki-dlya-perevozki-bolnykh',
		'kushetki-massazhnye' => 'mebel-meditsinskaya',
		'kushetki-massazhnye-r' => 'mebel-meditsinskaya',
	];

	// discover missing codes from vendor texts
	$r = $m->query('SELECT DETAIL_TEXT FROM b_iblock_element WHERE IBLOCK_ID=13 AND DETAIL_TEXT LIKE "%/catalog/%"');
	$codes = [];
	while ($row = $r->fetch_assoc()) {
		if (preg_match_all('#/catalog/([a-z0-9_-]+)/#u', (string)$row['DETAIL_TEXT'], $mm)) {
			foreach ($mm[1] as $c) {
				$codes[$c] = true;
			}
		}
	}

	foreach (array_keys($codes) as $code) {
		if (isset($map[$code])) {
			continue;
		}
		if (sectionActive($m, $code)) {
			continue;
		}
		// strip trailing -r
		if (preg_match('/^(.+)-r$/', $code, $mm2) && sectionActive($m, $mm2[1])) {
			$map[$code] = $mm2[1];
			continue;
		}
	}

	foreach ($map as $from => $to) {
		if (!sectionActive($m, $to)) {
			fwrite(STDERR, "bad target for {$from}: {$to}\n");
			unset($map[$from]);
		}
	}

	return $map;
}

$map = buildReplaceMap($m);
echo "map:\n";
foreach ($map as $from => $to) {
	echo "  /catalog/{$from}/ => /catalog/{$to}/\n";
}

$r = $m->query('SELECT ID,CODE,NAME,DETAIL_TEXT FROM b_iblock_element WHERE IBLOCK_ID=13 AND DETAIL_TEXT LIKE "%/catalog/%"');
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
		continue;
	}
	echo ($dry ? 'DRY ' : '') . "update {$row['CODE']}#{$row['ID']}\n";
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
