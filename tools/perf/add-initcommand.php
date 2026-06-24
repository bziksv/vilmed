<?php
/** One-time: SET NAMES utf8 for Bitrix site_checker (MySQL 8 utf8mb3 alias). */
$f = dirname(__DIR__, 2) . '/bitrix/.settings.php';
if (!is_file($f)) {
	fwrite(STDERR, "ERROR: .settings.php not found\n");
	exit(1);
}

$c = file_get_contents($f);
if (strpos($c, 'initCommand') !== false) {
	echo "skip: initCommand already set\n";
	exit(0);
}

$needle = "'options' => 2,";
$insert = "'options' => 2,\n        'initCommand' => \"SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'\",";

if (strpos($c, $needle) === false) {
	fwrite(STDERR, "ERROR: cannot find options => 2\n");
	exit(1);
}

copy($f, $f . '.bak.initcmd');
$count = 1;
file_put_contents($f, str_replace($needle, $insert, $c, $count));
echo "OK: initCommand added\n";
