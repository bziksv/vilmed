<?php
/**
 * Re-sanitize DETAIL_TEXT / section DESCRIPTION for reported W3C pages.
 * php tools/perf/fix-w3c-reported-batch.php [--dry]
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

$dry = in_array('--dry', $argv ?? [], true);

$elementCodes = [
	'myshtsy-gortani-plakat',
	'nemetskiy-alfavit-plakat-a1a2',
	'termoreaktor-laboratornyy-tyermion-khpk',
	'mindray-m5-uzi-skaner',
	'ultrazvukovaya-sistema-mindray-dc-40-kitay',
	'apparat-radius-01-inter-sm-rossiya',
	'oftalmologicheskiy-stol-s-elektroprivodom-tagler-so-1',
	'shchelevaya-lampa-dixion-s280-02l-rossiya',
	'uzi-apparat-venue-50-ge-healthcare-ssha',
	'apparatnyy-massazh-slim-project-plus',
	'avtorefraktometr-vzor-9000-dixion-kitay',
	'rostomer-seca-417-germaniya',
	'igloderzhatel-po-barrakeru-pryamoy-115-mm-h-2105-pto-medtekhnika-kazan-rossiya',
	'oufd-01-solnyshko-obluchatel-ultrafioletovyy-lampa-tipa-dkbu-5',
	'vekorasshiritel-po-barrakeru-temporalnyy-r-1260',
];

$sectionCodes = [
	'ekrany-rentgenovskie',
	'tsifrovye-detektory',
	'dmv-terapiya',
];

$updated = 0;

// ortorent — длинный CODE
$r = $m->query(
	"SELECT ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element
	 WHERE IBLOCK_ID=24 AND CODE LIKE 'ortorent-moto-aktiv%'"
);
while ($row = $r->fetch_assoc()) {
	$elementCodes[] = $row['CODE'];
}
$elementCodes = array_values(array_unique($elementCodes));

foreach ($elementCodes as $code) {
	$esc = $m->real_escape_string($code);
	$res = $m->query(
		"SELECT ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element
		 WHERE IBLOCK_ID=24 AND CODE='{$esc}' LIMIT 1"
	);
	$row = $res ? $res->fetch_assoc() : null;
	if (!$row) {
		echo "MISS EL {$code}\n";
		continue;
	}
	$detail = (string)$row['DETAIL_TEXT'];
	$preview = (string)$row['PREVIEW_TEXT'];
	$newDetail = $detail !== '' ? \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($detail) : $detail;
	$newPreview = $preview;
	if ($preview !== '' && (stripos($preview, '<') !== false)) {
		$newPreview = \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($preview);
	}
	if ($newDetail === $detail && $newPreview === $preview) {
		echo "SKIP EL {$row['ID']} {$code}\n";
		continue;
	}
	echo ($dry ? 'DRY ' : '') . "OK EL {$row['ID']} {$code} " . strlen($detail) . '→' . strlen($newDetail) . "\n";
	if (!$dry) {
		$id = (int)$row['ID'];
		$stmt = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT=?, PREVIEW_TEXT=? WHERE ID=?');
		$stmt->bind_param('ssi', $newDetail, $newPreview, $id);
		$stmt->execute();
		$stmt->close();
		$updated++;
	}
}

foreach ($sectionCodes as $code) {
	$esc = $m->real_escape_string($code);
	$res = $m->query(
		"SELECT ID, CODE, DESCRIPTION FROM b_iblock_section
		 WHERE IBLOCK_ID=24 AND CODE='{$esc}' LIMIT 1"
	);
	$row = $res ? $res->fetch_assoc() : null;
	if (!$row) {
		echo "MISS SEC {$code}\n";
		continue;
	}
	$desc = (string)$row['DESCRIPTION'];
	$newDesc = $desc !== '' ? \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($desc) : $desc;
	if ($newDesc === $desc) {
		echo "SKIP SEC {$row['ID']} {$code}\n";
		continue;
	}
	echo ($dry ? 'DRY ' : '') . "OK SEC {$row['ID']} {$code} " . strlen($desc) . '→' . strlen($newDesc) . "\n";
	if (!$dry) {
		$id = (int)$row['ID'];
		$stmt = $m->prepare('UPDATE b_iblock_section SET DESCRIPTION=? WHERE ID=?');
		$stmt->bind_param('si', $newDesc, $id);
		$stmt->execute();
		$stmt->close();
		$updated++;
	}
}

// HTML в свойствах CML2_COMPLECT* (tabs / комплектация)
$idList = [];
foreach ($elementCodes as $code) {
	$esc = $m->real_escape_string($code);
	$res = $m->query("SELECT ID FROM b_iblock_element WHERE IBLOCK_ID=24 AND CODE='{$esc}' LIMIT 1");
	if ($row = $res->fetch_assoc()) {
		$idList[] = (int)$row['ID'];
	}
}
if ($idList) {
	$in = implode(',', $idList);
	$res = $m->query(
		"SELECT ep.ID, ep.IBLOCK_ELEMENT_ID, p.CODE, ep.VALUE
		 FROM b_iblock_element_property ep
		 JOIN b_iblock_property p ON p.ID=ep.IBLOCK_PROPERTY_ID
		 WHERE ep.IBLOCK_ELEMENT_ID IN ({$in}) AND p.CODE LIKE 'CML2_COMPLECT%'"
	);
	while ($row = $res->fetch_assoc()) {
		$val = (string)$row['VALUE'];
		$arr = @unserialize($val, ['allowed_classes' => false]);
		if (!is_array($arr) || empty($arr['TEXT']) || !is_string($arr['TEXT'])) {
			continue;
		}
		$old = $arr['TEXT'];
		$new = \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($old);
		if ($new === $old) {
			continue;
		}
		$arr['TEXT'] = $new;
		$ser = serialize($arr);
		echo ($dry ? 'DRY ' : '') . "OK PROP {$row['CODE']} el={$row['IBLOCK_ELEMENT_ID']} "
			. strlen($old) . '→' . strlen($new) . "\n";
		if (!$dry) {
			$id = (int)$row['ID'];
			$stmt = $m->prepare('UPDATE b_iblock_element_property SET VALUE=? WHERE ID=?');
			$stmt->bind_param('si', $ser, $id);
			$stmt->execute();
			$stmt->close();
			$updated++;
		}
	}
}

echo "updated={$updated}\n";
