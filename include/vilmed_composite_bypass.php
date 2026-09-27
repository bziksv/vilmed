<?php
/**
 * Composite Responder runs in prolog.php before init.php and before auth.
 * Force bypass (ncc) so admin panel / public edit are not served from html_pages.
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
// «Смотреть сайт» из админки — иначе отдаётся композит без #bx-panel
if (!empty($_GET['back_url_admin'])) {
	$forceBypass = true;
}

$cookiePrefix = 'BITRIX_SM';
$ncc = $_COOKIE[$cookiePrefix . '_NCC'] ?? '';
$cc = $_COOKIE[$cookiePrefix . '_CC'] ?? '';
$login = $_COOKIE[$cookiePrefix . '_LOGIN'] ?? '';
$uidh = $_COOKIE[$cookiePrefix . '_UIDH'] ?? '';

if ($ncc === 'Y') {
	$forceBypass = true;
}
// Запомненный логин без CC → полная страница (как в Responder, до auth)
if ($login !== '' && $uidh !== '' && $cc !== 'Y') {
	$forceBypass = true;
}

if ($forceBypass && !isset($_GET['ncc'])) {
	$_GET['ncc'] = '1';
	$_REQUEST['ncc'] = '1';
}
