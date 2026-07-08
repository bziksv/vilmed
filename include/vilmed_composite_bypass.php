<?php
/**
 * Composite Responder runs in prolog.php before init.php and before auth.
 * Force bypass (ncc) when public edit mode or cache reset is in the URL.
 */
if (PHP_SAPI === 'cli') {
	return;
}

$forceBypass = false;

if (isset($_GET['bitrix_include_areas']) && $_GET['bitrix_include_areas'] === 'Y') {
	$forceBypass = true;
}
if (isset($_GET['clear_cache']) || isset($_GET['clear_cache_session'])) {
	$forceBypass = true;
}

if ($forceBypass && !isset($_GET['ncc'])) {
	$_GET['ncc'] = '1';
	$_REQUEST['ncc'] = '1';
}
