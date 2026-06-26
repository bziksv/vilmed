<?php
/** Enable Bitrix cache.type=redis in .settings.php (prod-only, not in git). */
$f = dirname(__DIR__, 2) . '/bitrix/.settings.php';
if (!is_file($f)) {
	fwrite(STDERR, "ERROR: .settings.php not found\n");
	exit(1);
}

$c = file_get_contents($f);
if (strpos($c, "'type' => 'redis'") !== false || strpos($c, '"type" => "redis"') !== false) {
	echo "skip: redis cache already configured\n";
	exit(0);
}

$block = "  'cache' => \n"
	. "  array (\n"
	. "    'value' => \n"
	. "    array (\n"
	. "      'type' => 'redis',\n"
	. "      'sid' => 'vilmed_ru',\n"
	. "      'serializer' => 1,\n"
	. "      'redis' => \n"
	. "      array (\n"
	. "        'host' => '127.0.0.1',\n"
	. "        'port' => 6379,\n"
	. "      ),\n"
	. "    ),\n"
	. "    'readonly' => false,\n"
	. "  ),\n";

$needle = "  'cache_flags' =>";
if (strpos($c, $needle) === false) {
	fwrite(STDERR, "ERROR: cache_flags anchor not found\n");
	exit(1);
}

copy($f, $f . '.bak.redis');
file_put_contents($f, str_replace($needle, $block . $needle, $c, $count));
if ($count !== 1) {
	fwrite(STDERR, "ERROR: insert failed\n");
	exit(1);
}

echo "OK: redis cache block added\n";
