<?php
/**
 * Escape bare "< " in catalog property values (W3C: Bad character after <).
 * php tools/perf/fix-w3c-prop-lt.php [elementId]
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

$elementId = isset($argv[1]) ? (int)$argv[1] : 0;
$sql = "SELECT ep.ID, ep.IBLOCK_ELEMENT_ID, p.CODE, ep.VALUE
	FROM b_iblock_element_property ep
	JOIN b_iblock_property p ON p.ID = ep.IBLOCK_PROPERTY_ID
	WHERE ep.VALUE LIKE '%< %'";
if ($elementId > 0) {
	$sql .= ' AND ep.IBLOCK_ELEMENT_ID = ' . $elementId;
}

$res = $m->query($sql);
if (!$res) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

$updated = 0;
while ($row = $res->fetch_assoc()) {
	$raw = (string)$row['VALUE'];
	$new = preg_replace('/<(?=\s)/u', '&lt;', $raw);
	if ($new === null || $new === $raw) {
		continue;
	}
	$id = (int)$row['ID'];
	$upd = $m->prepare('UPDATE b_iblock_element_property SET VALUE = ? WHERE ID = ?');
	$upd->bind_param('si', $new, $id);
	if ($upd->execute()) {
		$updated++;
		echo "OK el={$row['IBLOCK_ELEMENT_ID']} prop={$row['CODE']} id={$id}\n";
	}
}
echo "updated={$updated}\n";
