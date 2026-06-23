#!/usr/bin/env php
<?php
/**
 * Batch WebP for /upload/resize_cache — run on server after deploy.
 *
 *   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
 *   php tools/perf/webp-warmup.php
 *   php tools/perf/webp-warmup.php --limit=500
 */
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only\n");
	exit(1);
}

$docRoot = realpath(__DIR__ . '/../..');
if ($docRoot === false || !is_dir($docRoot . '/upload')) {
	fwrite(STDERR, "Cannot find document root\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require $docRoot . '/bitrix/modules/main/include/prolog_before.php';
require_once $docRoot . '/include/vilmed_perf.php';

$limit = 0;
foreach ($argv as $arg) {
	if (strpos($arg, '--limit=') === 0) {
		$limit = (int)substr($arg, 8);
	}
}

$resizeRoot = $docRoot . '/upload/resize_cache';
if (!is_dir($resizeRoot)) {
	echo "No resize_cache dir\n";
	exit(0);
}

$created = 0;
$skipped = 0;
$errors = 0;

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($resizeRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	if (!$file->isFile()) {
		continue;
	}

	$path = $file->getPathname();
	$ext = strtolower($file->getExtension());
	if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
		continue;
	}

	$relative = substr($path, strlen($docRoot));
	$webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

	if (is_file($webpPath) && filemtime($webpPath) >= filemtime($path)) {
		$skipped++;
		continue;
	}

	if (!function_exists('vilmedGenerateWebpSrc')) {
		fwrite(STDERR, "vilmedGenerateWebpSrc missing — deploy include/vilmed_perf.php first\n");
		exit(1);
	}

	$result = vilmedGenerateWebpSrc($relative);
	if ($result !== null) {
		$created++;
		if ($created <= 20 || $created % 100 === 0) {
			echo "+ $result\n";
		}
	} else {
		$errors++;
	}

	if ($limit > 0 && ($created + $errors) >= $limit) {
		break;
	}
}

echo "Done. created=$created skipped=$skipped errors=$errors\n";
