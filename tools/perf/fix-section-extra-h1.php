<?php
/**
 * Section DESCRIPTION: H1 → H2 (page H1 is #pagetitle).
 * php tools/perf/fix-section-extra-h1.php [--dry]
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
	"SELECT ID, CODE, DESCRIPTION FROM b_iblock_section
	 WHERE DESCRIPTION LIKE '%<h1%' OR DESCRIPTION LIKE '%<H1%'
	    OR DESCRIPTION LIKE '%<h3%'
	    OR DESCRIPTION LIKE '%margin-%'
	    OR DESCRIPTION LIKE '%7px%'
	    OR DESCRIPTION LIKE '%<li%'
	 ORDER BY ID"
);
if (!$res) {
	fwrite(STDERR, $m->error . "\n");
	exit(1);
}

$updated = 0;
while ($row = $res->fetch_assoc()) {
	$raw = (string)$row['DESCRIPTION'];
	// sanitize demotes H1→H2 via normalizeHeadingHierarchy; light path if allowlist too aggressive
	$new = \Titlo\Relevance\CatalogRepository::normalizeHeadingHierarchy(
		\Titlo\Relevance\CatalogRepository::fixW3cMarkupGlitches($raw)
	);
	// ensure any remaining h1 gone
	$new = preg_replace('#<h1(\b[^>]*)>#i', '<h2$1>', $new) ?? $new;
	$new = preg_replace('#</h1>#i', '</h2>', $new) ?? $new;
	if ($new === $raw) {
		continue;
	}
	if ($dry) {
		echo "DRY {$row['ID']} {$row['CODE']}\n";
		$updated++;
		continue;
	}
	$id = (int)$row['ID'];
	$upd = $m->prepare('UPDATE b_iblock_section SET DESCRIPTION = ? WHERE ID = ?');
	$upd->bind_param('si', $new, $id);
	if ($upd->execute()) {
		$updated++;
		echo "OK {$row['ID']} {$row['CODE']}\n";
	} else {
		echo "FAIL {$row['ID']} " . $m->error . "\n";
	}
}
echo 'updated=' . $updated . ($dry ? " (dry)\n" : "\n");
