<?php

/**
 * Перебор каталога: в одном адресе три и больше section_id.
 * Такой запрос не собирает раздел. Обычный поиск, сортировка и номер страницы
 * открываются сразу, в том числе в инкогнито без куки.
 */

function vmCatalogGateIsSearchBot(string $ua): bool
{
	return (bool)preg_match(
		'/Googlebot|Google-InspectionTool|Storebot-Google|AdsBot-Google|YandexBot|YandexImages|YandexMobileBot|YandexWebmaster|YandexMarket|bingbot|Mail\.RU_Bot|Applebot|vilmed-warmup|vilmed-psi/i',
		$ua
	);
}

function vmCatalogGateSectionCount(): int
{
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	$path = (string)parse_url($uri, PHP_URL_PATH);
	if (!preg_match('#^/catalog(?:/|$)#', $path)) {
		return 0;
	}

	$query = (string)parse_url($uri, PHP_URL_QUERY);
	if ($query === '') {
		return 0;
	}

	$params = [];
	parse_str($query, $params);
	if (!isset($params['section_id'])) {
		return 0;
	}
	if (is_array($params['section_id'])) {
		return count($params['section_id']);
	}

	return 1;
}

function vmCatalogGateRun(): void
{
	if (PHP_SAPI === 'cli') {
		return;
	}
	$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
	if (
		strpos($host, 'localhost') !== false
		|| strpos($host, '127.0.0.1') !== false
		|| strpos($host, '8082') !== false
	) {
		return;
	}
	$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
	if ($method !== 'GET' && $method !== 'HEAD' && $method !== 'POST') {
		return;
	}
	if (defined('ADMIN_SECTION') && ADMIN_SECTION) {
		return;
	}
	if (vmCatalogGateSectionCount() < 3) {
		return;
	}
	if (vmCatalogGateIsSearchBot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
		return;
	}
	if (!headers_sent()) {
		header('Cache-Control: no-store');
		header('X-Robots-Tag: noindex, nofollow');
		http_response_code(403);
	}
	die();
}

vmCatalogGateRun();
