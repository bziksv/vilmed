<?php
/**
 * Заменяет «руб.» на знак рубля (₽) в формате валюты RUB (ru).
 * Запуск: php tools/perf/set-ruble-symbol.php
 * После — сбросить managed_cache / redis (prod-deploy.sh).
 */
$root = dirname(__DIR__, 2);
$settings = include $root.'/bitrix/.settings.php';
$c = $settings['connections']['value']['default'];
$host = $c['host'];
$port = 3306;
if (strpos($host, ':') !== false) {
	[$host, $port] = explode(':', $host, 2);
}
$mysqli = new mysqli($host, $c['login'], $c['password'], $c['database'], (int)$port);
if ($mysqli->connect_error) {
	fwrite(STDERR, $mysqli->connect_error."\n");
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$format = '# &#8381;'; // «1 374 464 ₽»
$stmt = $mysqli->prepare('UPDATE b_catalog_currency_lang SET FORMAT_STRING=? WHERE CURRENCY=? AND LID=?');
$currency = 'RUB';
$lid = 'ru';
$stmt->bind_param('sss', $format, $currency, $lid);
$stmt->execute();
echo 'affected='.$stmt->affected_rows."\n";

$res = $mysqli->query("SELECT LID, FORMAT_STRING FROM b_catalog_currency_lang WHERE CURRENCY='RUB'");
while ($row = $res->fetch_assoc()) {
	echo $row['LID'].' => '.$row['FORMAT_STRING']."\n";
}
echo "DONE\n";
