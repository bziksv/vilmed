<?php

/**
 * Прямой заход в каталог с sort/view/PAGEN/q/section_id не собирает раздел:
 * гостю показывается капча Битрикса, после верного кода — кука vm_cg на 14 дней.
 * Переход со страницы vilmed.ru пропускается. Порт с kosmamed km_catalog_gate.php.
 */

function vmCatalogGateSecret(): string
{
	static $secret = null;
	if ($secret !== null) {
		return $secret;
	}

	$secret = (string)\Bitrix\Main\Config\Option::get('main', 'captcha_password', '');
	if ($secret === '') {
		$secret = hash('sha256', 'vm-catalog-gate|' . (string)\Bitrix\Main\Config\Option::get('main', 'server_name', 'vilmed.ru'));
	}

	return $secret;
}

function vmCatalogGateCookieValid(): bool
{
	$raw = (string)($_COOKIE['vm_cg'] ?? '');
	$parts = explode('.', $raw, 2);
	if (count($parts) !== 2 || !ctype_digit($parts[0])) {
		return false;
	}
	if ((int)$parts[0] < time()) {
		return false;
	}

	$expected = hash_hmac('sha256', $parts[0], vmCatalogGateSecret());

	return hash_equals($expected, $parts[1]);
}

function vmCatalogGateSetCookie(): void
{
	$expires = time() + 14 * 86400;
	$value = $expires . '.' . hash_hmac('sha256', (string)$expires, vmCatalogGateSecret());
	$secure = false;
	try {
		$secure = \Bitrix\Main\Context::getCurrent()->getRequest()->isHttps();
	} catch (\Throwable $e) {
		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
	}

	setcookie('vm_cg', $value, [
		'expires' => $expires,
		'path' => '/',
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
	$_COOKIE['vm_cg'] = $value;
}

function vmCatalogGateIsSearchBot(string $ua): bool
{
	return (bool)preg_match(
		'/Googlebot|Google-InspectionTool|Storebot-Google|AdsBot-Google|YandexBot|YandexImages|YandexMobileBot|YandexWebmaster|YandexMarket|bingbot|Mail\.RU_Bot|Applebot|vilmed-warmup|vilmed-psi/i',
		$ua
	);
}

function vmCatalogGateSameSiteReferer(): bool
{
	$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
	if ($ref === '') {
		return false;
	}
	$host = strtolower((string)parse_url($ref, PHP_URL_HOST));

	return in_array($host, [
		'vilmed.ru',
		'www.vilmed.ru',
		'localhost',
		'127.0.0.1',
	], true);
}

function vmCatalogGateIsTailedCatalog(): bool
{
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	$path = (string)parse_url($uri, PHP_URL_PATH);
	if (!preg_match('#^/catalog(?:/|$)#', $path)) {
		return false;
	}

	$query = (string)parse_url($uri, PHP_URL_QUERY);
	if ($query === '') {
		return false;
	}

	$params = [];
	parse_str($query, $params);
	// как на kosmamed + поиск/фильтры (шторм q=heine&section_id[])
	if (
		isset($params['sort'])
		|| isset($params['view'])
		|| isset($params['q'])
		|| isset($params['section_id'])
	) {
		return true;
	}
	foreach ($params as $name => $value) {
		if (stripos((string)$name, 'PAGEN') === 0) {
			return true;
		}
	}

	return false;
}

function vmCatalogGatePass(string $uri): void
{
	$safe = htmlspecialchars($uri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	if (!headers_sent()) {
		header('Content-Type: text/html; charset=UTF-8');
		header('Cache-Control: no-store');
		http_response_code(200);
	}
	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
		. '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
		. '<title>Открываем каталог</title></head><body>'
		. '<p>Код принят. <a href="' . $safe . '">Перейти в каталог</a></p>'
		. '<script>location.replace(' . json_encode($uri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script>'
		. '</body></html>';
	die();
}

function vmCatalogGateShow(string $error = ''): void
{
	global $APPLICATION;

	$sid = $APPLICATION->CaptchaGetCode();
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '/catalog/');
	$action = htmlspecialchars($uri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$sidEsc = htmlspecialchars((string)$sid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$errorHtml = $error !== ''
		? '<p class="vm-gate-error">' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
		: '';

	if (!headers_sent()) {
		header('Content-Type: text/html; charset=UTF-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('X-Robots-Tag: noindex, nofollow');
		http_response_code(200);
	}

	echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<meta name="robots" content="noindex, nofollow">'
		. '<title>Подтвердите, что вы не робот</title>'
		. '<style>'
		. 'html,body{margin:0;height:100%;background:#f1f5f9;color:#1e293b;font:16px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
		. 'body{display:flex;align-items:center;justify-content:center;padding:24px;}'
		. '.vm-gate{width:100%;max-width:420px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 12px 40px rgba(15,23,42,.08);padding:28px 24px 24px;}'
		. '.vm-gate h1{margin:0 0 8px;font-size:22px;line-height:1.3;}'
		. '.vm-gate p{margin:0 0 16px;color:#475569;}'
		. '.vm-gate-error{color:#b91c1c;font-weight:600;}'
		. '.vm-gate img{display:block;margin:0 0 12px;border:1px solid #e2e8f0;border-radius:8px;}'
		. '.vm-gate label{display:block;margin:0 0 6px;font-size:14px;color:#475569;}'
		. '.vm-gate input[type=text]{width:100%;box-sizing:border-box;height:44px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:18px;letter-spacing:.08em;}'
		. '.vm-gate button{margin-top:14px;width:100%;height:46px;border:0;border-radius:8px;background:#0b3961;color:#fff;font-size:16px;font-weight:600;cursor:pointer;}'
		. '.vm-gate button:hover{background:#082a47;}'
		. '</style></head><body><div class="vm-gate">'
		. '<h1>Подтвердите, что вы не робот</h1>'
		. '<p>Страница каталога открыта сразу с поиском, фильтром, сортировкой или номером страницы. Введите код с картинки, и мы покажем товары.</p>'
		. $errorHtml
		. '<form method="post" action="' . $action . '">'
		. '<input type="hidden" name="vm_catalog_gate" value="1">'
		. '<input type="hidden" name="captcha_sid" value="' . $sidEsc . '">'
		. '<img src="/bitrix/tools/captcha.php?captcha_sid=' . $sidEsc . '" width="180" height="40" alt="Код">'
		. '<label for="vm-captcha-word">Код с картинки</label>'
		. '<input id="vm-captcha-word" type="text" name="captcha_word" autocomplete="off" required autofocus>'
		. '<button type="submit">Продолжить</button>'
		. '</form></div></body></html>';
	die();
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
	if (!vmCatalogGateIsTailedCatalog()) {
		return;
	}
	if (vmCatalogGateIsSearchBot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
		return;
	}
	if (vmCatalogGateCookieValid()) {
		return;
	}
	if (vmCatalogGateSameSiteReferer()) {
		vmCatalogGateSetCookie();
		return;
	}

	if ($method === 'POST' && (string)($_POST['vm_catalog_gate'] ?? '') === '1') {
		global $APPLICATION;
		$word = (string)($_POST['captcha_word'] ?? '');
		$sid = (string)($_POST['captcha_sid'] ?? '');
		if ($word !== '' && $APPLICATION->CaptchaCheckCode($word, $sid)) {
			vmCatalogGateSetCookie();
			vmCatalogGatePass((string)($_SERVER['REQUEST_URI'] ?? '/catalog/'));
		}
		vmCatalogGateShow('Неверный код. Введите новый.');
	}

	if ($method === 'HEAD') {
		if (!headers_sent()) {
			header('Cache-Control: no-store');
			http_response_code(200);
		}
		die();
	}

	vmCatalogGateShow();
}

vmCatalogGateRun();
