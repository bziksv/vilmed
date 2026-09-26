<?php
/**
 * Bulk-fix common DETAIL_TEXT W3C issues across catalog IB24.
 * php tools/perf/fix-w3c-product-snippets.php
 * php tools/perf/fix-w3c-product-snippets.php --all
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

$all = in_array('--all', $argv, true);

if ($all) {
	$sql = "SELECT ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element WHERE IBLOCK_ID=24 AND DETAIL_TEXT != '' AND (
		DETAIL_TEXT LIKE '%</ul></p>%' OR DETAIL_TEXT LIKE '%</ol></p>%'
		OR DETAIL_TEXT LIKE '%</p>%<%ol%' OR DETAIL_TEXT LIKE '%</p>%<%ul%'
		OR DETAIL_TEXT LIKE '%<p>%<%ol%' OR DETAIL_TEXT LIKE '%<p>%<%ul%'
		OR DETAIL_TEXT LIKE '%</li>%<%ul%' OR DETAIL_TEXT LIKE '%</li>%<%ol%'
		OR DETAIL_TEXT LIKE '%Преимущества</p>%'
		OR DETAIL_TEXT LIKE '%class=\"ic\"%'
		OR DETAIL_TEXT LIKE '%<summary%'
		OR DETAIL_TEXT LIKE '%<circle%'
		OR DETAIL_TEXT LIKE '%<details><details%'
		OR DETAIL_TEXT LIKE '%.м</ul>%'
		OR DETAIL_TEXT LIKE '%background-color: none%'
		OR DETAIL_TEXT LIKE '%background-color:none%'
		OR DETAIL_TEXT LIKE '%<ul>%<%p%'
		OR DETAIL_TEXT LIKE '%<ol>%<%p%'
		OR DETAIL_TEXT LIKE '%<h2>%<%ul%'
		OR DETAIL_TEXT LIKE '%<h2>%<%ol%'
		OR DETAIL_TEXT LIKE '%<div>%<%li%'
		OR DETAIL_TEXT LIKE '%<img %' AND DETAIL_TEXT NOT LIKE '% alt=%' AND DETAIL_TEXT NOT LIKE '% alt =%'
		OR DETAIL_TEXT REGEXP '<h[1-6][^>[:space:]/]'
	) ORDER BY ID";
} else {
	$codes = [
		'shchelevaya-lampa-lshch2-01-3-kh-pozitsionnaya-s-galogennym-istochnikom-sveta-orion-medik',
		'apparat-ivl-zisline-jv100-a-rossiya',
		'fetalnyy-monitor-serii-unicare-mcf-21-kitay',
		'spektrofotometr-yuniko-1201',
		'fotometr-plamennyy-pfa-378',
		'statsionarnaya-ultrazvukovaya-diagnosticheskaya-sistema-ekspertnogo-klassa-mindray-dc-8',
		'defibrillyator-monitor-beneheart-d6-mindray-kitay',
		'termoreaktor-laboratornyy-tyermion-khpk',
		'sistema-ochistki-dykhatelnykh-putey-the-vest-pnevmovibratsionnaya-sistema-zhilet',
	];
	$in = implode(',', array_map(static function ($c) use ($m) {
		return "'" . $m->real_escape_string($c) . "'";
	}, $codes));
	$sql = "SELECT ID, CODE, DETAIL_TEXT, PREVIEW_TEXT FROM b_iblock_element WHERE IBLOCK_ID=24 AND CODE IN ($in)";
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
	$old = (string)$row['DETAIL_TEXT'];
	$new = \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($old);
	$prev = (string)$row['PREVIEW_TEXT'];
	$newPrev = $prev !== '' ? \Titlo\Relevance\CatalogRepository::sanitizeCatalogHtml($prev) : $prev;

	if ($new === $old && $newPrev === $prev) {
		echo "NOCHANGE {$row['CODE']} id={$id}\n";
		continue;
	}
	$upd = $m->prepare('UPDATE b_iblock_element SET DETAIL_TEXT=?, PREVIEW_TEXT=? WHERE ID=?');
	$upd->bind_param('ssi', $new, $newPrev, $id);
	if ($upd->execute()) {
		$updated++;
		echo "OK {$row['CODE']} id={$id}\n";
	} else {
		echo "FAIL {$id} " . $m->error . "\n";
	}
}

echo "scanned={$scanned} updated={$updated}\n";
