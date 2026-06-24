#!/usr/bin/env php
<?php
/**
 * Ограничить inline CSS (arturgolubev.cssinliner) — уменьшает HTML с ~840 KB.
 *
 *   cd /var/www/vilmed_ru_usr/data/www/vilmed.ru
 *   php tools/perf/prod-cssinliner-fix.php
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

require $docRoot . '/bitrix/modules/main/include/prolog_before.php';

$moduleId = 'arturgolubev.cssinliner';
if (!CModule::IncludeModule($moduleId)) {
	fwrite(STDERR, "Module $moduleId not installed\n");
	exit(1);
}

$exceptions = implode("\n", [
	'colors.css',
	'template_styles.css',
	'template_styles.catalog',
	'template_styles.personal',
	'template_styles.compare',
	'font-awesome',
	'slider.css',
	'fancybox',
	'slick.css',
	'custom-forms.css',
	'ui.font',
	'opensans',
]);

$prevWeight = COption::GetOptionString($moduleId, 'inline_max_weight', '512');
$prevExceptions = COption::GetOptionString($moduleId, 'exceptions', '');

COption::SetOptionString($moduleId, 'inline_max_weight', '48');
COption::SetOptionString($moduleId, 'exceptions', $exceptions);

$cacheRoot = $docRoot . '/bitrix/cache';
$cleared = 0;
if (is_dir($cacheRoot)) {
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($cacheRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $file) {
		if ($file->isDir() && strpos($file->getPathname(), 'arturgolubev.cssinliner') !== false) {
			$cleared++;
		}
	}
	// Fast path: site cache folders named after module
	foreach (glob($cacheRoot . '/*', GLOB_ONLYDIR) ?: [] as $siteDir) {
		$modDir = $siteDir . '/arturgolubev.cssinliner';
		if (is_dir($modDir)) {
			$rii = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($modDir, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ($rii as $f) {
				$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
			}
			@rmdir($modDir);
			$cleared++;
		}
	}
}

echo "OK: cssinliner inline_max_weight: {$prevWeight} → 48 KB\n";
echo "OK: exceptions set (" . substr_count($exceptions, "\n") + 1 . " patterns)\n";
if ($prevExceptions !== $exceptions) {
	echo "  was: " . str_replace("\n", ', ', trim($prevExceptions ?: '(empty)')) . "\n";
}
echo "OK: cssinliner cache dirs cleared: {$cleared}\n";
echo "Next: rm -f bitrix/html_pages/vilmed.ru/index@.html && bash tools/perf/prod-warmup.sh https://vilmed.ru\n";
