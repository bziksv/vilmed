<?php
/**
 * Fix broken DETAIL_TEXT HTML (W3C: bad attributes, stray </p>).
 * Run on prod: php tools/perf/fix-w3c-content.php
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

function fixRow(mysqli $m, string $code, callable $fixer): void
{
	$stmt = $m->prepare('SELECT ID, IBLOCK_ID, DETAIL_TEXT FROM b_iblock_element WHERE CODE = ?');
	$stmt->bind_param('s', $code);
	$stmt->execute();
	$res = $stmt->get_result();
	$found = false;
	while ($row = $res->fetch_assoc()) {
		$found = true;
		$raw = (string)$row['DETAIL_TEXT'];
		$new = $fixer($raw);
		if ($new === $raw) {
			echo "SKIP {$row['IBLOCK_ID']}:{$row['ID']} $code\n";
			continue;
		}
		$upd = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT = ? WHERE ID = ?');
		$id = (int)$row['ID'];
		$upd->bind_param('si', $new, $id);
		$ok = $upd->execute();
		echo ($ok ? 'OK' : 'FAIL') . " {$row['IBLOCK_ID']}:{$row['ID']} $code " . strlen($raw) . '→' . strlen($new) . "\n";
	}
	if (!$found) {
		echo "MISS $code\n";
	}
}

fixRow($m, 'vitalograph', static function (string $t): string {
	// Broken opening: <span p="" акцент="" ...>
	$t = preg_replace('/<span\s+p=""[^>]*>/u', '', $t, 1);
	// Drop orphan closing span at end if we removed the opener
	$t = preg_replace('/<\/span>(\s*)$/u', '$1', $t, 1);
	return $t;
});

fixRow($m, 'sensitec', static function (string $t): string {
	return preg_replace('/(?:<\/p>\s*){2,}\s*$/u', '</p>', $t) ?? $t;
});

foreach ([
	'otkashlivatel-cough-assist-e70',
	'shchelevaya-lampa-lshch2-01-3-kh-pozitsionnaya-s-galogennym-istochnikom-sveta-orion-medik',
	'apparat-ivl-zisline-jv100-a-rossiya',
] as $code) {
	fixRow($m, $code, static function (string $t): string {
		$t = preg_replace('/(<br\s*\/?>\s*)+<\/p>/iu', '$1', $t) ?? $t;
		$t = preg_replace('/(?:<\/p>\s*){2,}\s*$/u', '</p>', $t) ?? $t;
		return $t;
	});
}

echo "done\n";
