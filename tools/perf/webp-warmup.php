#!/usr/bin/env php
<?php
/**
 * Batch WebP — run on server (cron weekly or after deploy).
 *
 *   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
 *   php tools/perf/webp-warmup.php
 *   php tools/perf/webp-warmup.php --limit=500
 *   nohup php tools/perf/webp-warmup.php >> /var/log/vilmed-webp.log 2>&1 &
 */
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$docRoot = realpath(__DIR__ . '/../..');
if ($docRoot === false) {
	fwrite(STDERR, "Cannot find document root\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('B_PROLOG_INCLUDED', true);

require_once $docRoot . '/include/vilmed_perf.php';

$limit = 0;
foreach ($argv as $arg) {
	if (strpos($arg, '--limit=') === 0) {
		$limit = (int)substr($arg, 8);
	}
}

$scanRoots = [
	$docRoot . '/logo',
	$docRoot . '/upload/iblock',
	$docRoot . '/upload/resize_cache',
	$docRoot . '/upload/medialibrary',
	$docRoot . '/upload/uf',
	$docRoot . '/bitrix/templates/elektro_flat/images',
];

$skipDirNames = [
	'.git',
	'webp',
];

$created = 0;
$skipped = 0;
$errors = 0;
$started = microtime(true);

$processFile = static function (string $path) use ($docRoot, &$created, &$skipped, &$errors, $limit): bool {
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
	if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
		return true;
	}

	$relative = substr($path, strlen($docRoot));
	$webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

	if (is_file($webpPath) && filemtime($webpPath) >= filemtime($path)) {
		$skipped++;

		return true;
	}

	$result = vilmedGenerateWebpSrc($relative);
	if ($result !== null) {
		$created++;
		if ($created <= 20 || $created % 200 === 0) {
			echo "+ $result\n";
		}
	} else {
		$errors++;
	}

	if ($limit > 0 && ($created + $errors) >= $limit) {
		return false;
	}

	return true;
};

foreach ($scanRoots as $root) {
	if (!is_dir($root)) {
		continue;
	}

	echo '== scan: ' . str_replace($docRoot, '', $root) . " ==\n";

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if (!$file->isFile()) {
			continue;
		}

		foreach ($skipDirNames as $skipName) {
			if (strpos($file->getPathname(), '/' . $skipName . '/') !== false) {
				continue 2;
			}
		}

		if (!$processFile($file->getPathname())) {
			break 2;
		}
	}
}

$elapsed = round(microtime(true) - $started, 1);
echo "Done in {$elapsed}s. created=$created skipped=$skipped errors=$errors\n";
