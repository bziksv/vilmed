<?php

namespace Titlo\Relevance;

use Bitrix\Main\Config\Option;

class Config
{
	public const MODULE_ID = 'titlo.relevance';

	public const API_BASE_PROD = 'https://cabinet.titlo.ru/api/v1';

	/**
	 * @return array<string, string>
	 */
	public static function apiBasePresets(): array
	{
		return [
			self::API_BASE_PROD => 'Рабочий (cabinet.titlo.ru)',
			'http://host.docker.internal:3002/api/v1' => 'Локально из Docker (host.docker.internal:3002)',
			'http://127.0.0.1:3002/api/v1' => 'Локально (127.0.0.1:3002)',
			'http://localhost:3002/api/v1' => 'Локально (localhost:3002)',
		];
	}

	public static function get(string $name, string $default = ''): string
	{
		return (string) Option::get(self::MODULE_ID, $name, $default);
	}

	public static function apiBaseUrl(): string
	{
		return rtrim(self::get('api_base_url', self::API_BASE_PROD), '/');
	}

	/**
	 * Публичный origin ЛК для ссылок из Bitrix-админки.
	 * Не из api_base: локальный API (127.0.0.1:3002) не должен уводить «Полезные сервисы».
	 */
	public static function cabinetPublicOrigin(): string
	{
		$fromApi = self::cabinetOrigin();
		$host = self::hostOf($fromApi);
		// Прод-кабинет или явный https cabinet.titlo.ru
		if ($host === 'cabinet.titlo.ru') {
			return 'https://cabinet.titlo.ru';
		}
		// Local/docker API — всё равно открываем боевой кабинет (разделы UI)
		return 'https://cabinet.titlo.ru';
	}

	/**
	 * Origin из api_base_url (для API-клиентов / отладки).
	 */
	public static function cabinetOrigin(): string
	{
		$api = self::apiBaseUrl();
		$origin = preg_replace('#/api(?:/v\d+)?/?$#i', '', $api);
		$origin = rtrim((string) $origin, '/');
		if ($origin === '' || !preg_match('#^https?://#i', $origin)) {
			return 'https://cabinet.titlo.ru';
		}
		return $origin;
	}

	/**
	 * Полезные сервисы ЛК: code => absolute URL раздела (не морда /).
	 *
	 * @return array<string, array{title:string,url:string,path:string,hint:string,icon?:string}>
	 */
	public static function cabinetUsefulServices(): array
	{
		$base = self::cabinetPublicOrigin();
		$iconBase = '/local/modules/titlo.relevance/admin/img/menu';
		return [
			'site-audit' => [
				'title' => 'Аудит сайта',
				'path' => '/site-audit',
				'url' => $base . '/site-audit',
				'hint' => 'Обходит страницы сайта и показывает технические проблемы: битые ссылки, дубли title/description, ошибки индексации и сотни других отчётов.',
				'icon' => $iconBase . '/useful_audit.png',
			],
			'seo-checklist' => [
				'title' => 'SEO-чек лист',
				'path' => '/checklist',
				'url' => $base . '/checklist',
				'hint' => 'Планы работ по SEO: задачи команде, статусы, сроки и контроль, что сделано по проекту.',
				'icon' => $iconBase . '/useful_checklist.png',
			],
			'site-monitoring' => [
				'title' => 'Мониторинг сайта',
				'path' => '/site-monitoring',
				'url' => $base . '/site-monitoring',
				'hint' => 'Следит, что сайт открывается: доступность, ответы сервера, сбои и инциденты по доменам.',
				'icon' => $iconBase . '/useful_site_mon.png',
			],
			'monitoring-positions' => [
				'title' => 'Мониторинг позиций',
				'path' => '/monitoring-v2',
				'url' => $base . '/monitoring-v2',
				'hint' => 'Снимает позиции запросов в поиске по проектам: динамика, просадки и рост в выдаче.',
				'icon' => $iconBase . '/useful_pos_mon.png',
			],
		];
	}

	public static function apiKey(): string
	{
		return trim(self::get('api_key', ''));
	}

	public static function iblockId(): int
	{
		return (int) self::get('iblock_id', '0');
	}

	/**
	 * Хосты, на которые разрешено слать Bearer (presets + prod + local dev).
	 *
	 * @return string[]
	 */
	public static function allowedApiBaseHosts(): array
	{
		return [
			'cabinet.titlo.ru',
			'localhost',
			'127.0.0.1',
			'host.docker.internal',
		];
	}

	/**
	 * Разрешённые API base: presets всегда OK.
	 * Custom — только при confirm и только https (или http на local hosts) + host allowlist.
	 */
	public static function isAllowedApiBaseUrl(string $url, bool $allowCustomConfirm = false): bool
	{
		$url = rtrim(trim($url), '/');
		if ($url === '') {
			return false;
		}
		if (isset(self::apiBasePresets()[$url])) {
			return true;
		}
		if (!$allowCustomConfirm) {
			return false;
		}
		return self::isSafeCustomApiBaseUrl($url);
	}

	/**
	 * Custom API base: схема + host allowlist; private/link-local IP — нет.
	 */
	public static function isSafeCustomApiBaseUrl(string $url): bool
	{
		$url = rtrim(trim($url), '/');
		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return false;
		}
		$parts = parse_url($url);
		if (!is_array($parts)) {
			return false;
		}
		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host = strtolower((string) ($parts['host'] ?? ''));
		if ($host === '' || !in_array($host, self::allowedApiBaseHosts(), true)) {
			return false;
		}
		$isLocal = in_array($host, ['localhost', '127.0.0.1', 'host.docker.internal'], true);
		if ($scheme === 'http' && !$isLocal) {
			return false;
		}
		if ($scheme !== 'http' && $scheme !== 'https') {
			return false;
		}
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			if (!in_array($host, ['127.0.0.1'], true)) {
				return false;
			}
		}
		$path = (string) ($parts['path'] ?? '');
		if ($path !== '' && !preg_match('#^/api(?:/v\d+)?/?$#i', $path)) {
			return false;
		}
		return true;
	}

	/**
	 * Публичный origin магазина для allowlist crawl/generate (без HTTP_HOST).
	 */
	public static function isAllowedSiteUrl(string $url): bool
	{
		$url = rtrim(trim($url), '/');
		if ($url === '') {
			return true; // пустое = сброс, fallback на SITE_SERVER_NAME
		}
		if (!preg_match('#^https?://#i', $url)) {
			return false;
		}
		$parts = parse_url($url);
		if (!is_array($parts)) {
			return false;
		}
		$host = strtolower((string) ($parts['host'] ?? ''));
		if ($host === '') {
			return false;
		}
		$isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
			|| substr($host, -6) === '.local';
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			if (!$isLocal && !self::isPublicIp($host)) {
				return false;
			}
		} elseif (!$isLocal) {
			// hostname: только буквы/цифры/точки/дефис
			if (!preg_match('#^[a-z0-9.-]+$#i', $host) || strpos($host, '..') !== false) {
				return false;
			}
		}
		return true;
	}

	protected static function isPublicIp(string $ip): bool
	{
		return filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false;
	}

	/**
	 * URL анализа/генерации: только site_url / SITE_SERVER_NAME (не HTTP_HOST).
	 */
	public static function isAllowedAnalysisUrl(string $url): bool
	{
		$url = trim($url);
		if ($url === '' || !preg_match('#^https?://#i', $url)) {
			return false;
		}
		$host = self::hostOf($url);
		if ($host === '') {
			return false;
		}
		$allowed = [];
		foreach ([self::configuredSiteOrigin(), self::bitrixSiteOrigin()] as $origin) {
			$h = self::hostOf($origin);
			if ($h !== '') {
				$allowed[$h] = true;
				if (strpos($h, 'www.') === 0) {
					$allowed[substr($h, 4)] = true;
				} else {
					$allowed['www.' . $h] = true;
				}
			}
		}
		if (isset($allowed['127.0.0.1']) || isset($allowed['localhost'])) {
			$allowed['127.0.0.1'] = true;
			$allowed['localhost'] = true;
		}
		return isset($allowed[$host]);
	}

	/**
	 * Только Option site_url (без HTTP_HOST fallback).
	 */
	public static function configuredSiteOrigin(): string
	{
		return rtrim(self::get('site_url', ''), '/');
	}

	/**
	 * Ключ Option авто-панели с разделением E/S: auto_e_run_mode / auto_s_gen_preview.
	 */
	public static function autoOptionKey(string $name, string $entityType): string
	{
		$suffix = strtoupper($entityType) === 'S' ? 's' : 'e';
		$name = preg_replace('#^auto_#', '', $name);
		return 'auto_' . $suffix . '_' . $name;
	}

	public static function getAutoOption(string $name, string $entityType, string $default = ''): string
	{
		$key = self::autoOptionKey($name, $entityType);
		$v = self::get($key, '');
		if ($v !== '') {
			return $v;
		}
		// legacy shared keys
		$legacy = self::get('auto_' . preg_replace('#^auto_#', '', $name), '');
		return $legacy !== '' ? $legacy : $default;
	}

	public static function setAutoOption(string $name, string $entityType, string $value): void
	{
		Option::set(self::MODULE_ID, self::autoOptionKey($name, $entityType), $value);
	}

	/**
	 * Старый дефолт «нет на сайте» был 200 — один раз поднимаем сохранённые 200 → 300.
	 */
	public static function migrateTlpMissingDefault300(): void
	{
		if (Option::get(self::MODULE_ID, 'tlp_missing_300_v1', '') === 'Y') {
			return;
		}
		foreach (['E', 'S'] as $entityType) {
			$cur = self::getAutoOption('tlp_missing', $entityType, '');
			if ($cur === '' || $cur === '200') {
				self::setAutoOption('tlp_missing', $entityType, '300');
			}
		}
		$legacy = self::get('auto_tlp_missing', '');
		if ($legacy === '200') {
			Option::set(self::MODULE_ID, 'auto_tlp_missing', '300');
		}
		Option::set(self::MODULE_ID, 'tlp_missing_300_v1', 'Y');
	}

	/**
	 * Безопасный публичный URL для href в админке (только http/https или относительный path).
	 */
	public static function safeHrefUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		if (isset($url[0]) && $url[0] === '/') {
			return $url;
		}
		if (preg_match('#^https?://#i', $url)) {
			return $url;
		}
		return '';
	}

	/**
	 * Элемент принадлежит настроенному каталогу.
	 */
	public static function elementBelongsToCatalog(int $id): bool
	{
		$id = (int) $id;
		$iblockId = self::iblockId();
		if ($id <= 0 || $iblockId <= 0) {
			return false;
		}
		if (!\Bitrix\Main\Loader::includeModule('iblock')) {
			return false;
		}
		$res = \CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N'],
			false,
			['nTopCount' => 1],
			['ID']
		);
		return (bool) $res->Fetch();
	}

	/**
	 * Секция принадлежит настроенному каталогу.
	 */
	public static function sectionBelongsToCatalog(int $id): bool
	{
		$id = (int) $id;
		$iblockId = self::iblockId();
		if ($id <= 0 || $iblockId <= 0) {
			return false;
		}
		if (!\Bitrix\Main\Loader::includeModule('iblock')) {
			return false;
		}
		$res = \CIBlockSection::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N'],
			false,
			['ID'],
			['nTopCount' => 1]
		);
		return (bool) $res->Fetch();
	}

	/**
	 * @param int[] $ids
	 * @return int[]
	 */
	public static function filterCatalogElementIds(array $ids, int $max = 200): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === [] || self::iblockId() <= 0) {
			return [];
		}
		if (count($ids) > $max) {
			$ids = array_slice($ids, 0, $max);
		}
		global $DB;
		$idList = implode(',', $ids);
		$res = $DB->Query(
			'SELECT ID FROM b_iblock_element WHERE IBLOCK_ID = ' . (int) self::iblockId()
			. ' AND ID IN (' . $idList . ')'
		);
		$ok = [];
		while ($row = $res->Fetch()) {
			$ok[] = (int) $row['ID'];
		}
		return $ok;
	}

	/**
	 * @param int[] $ids
	 * @return int[]
	 */
	public static function filterCatalogSectionIds(array $ids, int $max = 200): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === [] || self::iblockId() <= 0) {
			return [];
		}
		if (count($ids) > $max) {
			$ids = array_slice($ids, 0, $max);
		}
		global $DB;
		$idList = implode(',', $ids);
		$res = $DB->Query(
			'SELECT ID FROM b_iblock_section WHERE IBLOCK_ID = ' . (int) self::iblockId()
			. ' AND ID IN (' . $idList . ')'
		);
		$ok = [];
		while ($row = $res->Fetch()) {
			$ok[] = (int) $row['ID'];
		}
		return $ok;
	}

	/**
	 * Публичный origin сайта из настроек (для API/краула).
	 * Пусто → SITE_SERVER_NAME (без HTTP_HOST).
	 */
	public static function siteUrl(): string
	{
		$url = self::configuredSiteOrigin();
		if ($url !== '') {
			return $url;
		}
		return self::bitrixSiteOrigin();
	}

	/**
	 * Origin для ссылок «на сайте» в админке: текущий хост запроса.
	 * Не использовать для security allowlist crawl/generate.
	 */
	public static function browseOrigin(): string
	{
		$current = self::requestOrigin();
		if ($current !== '') {
			return $current;
		}
		return self::siteUrl();
	}

	/**
	 * Host из URL без порта-схемы для сравнения.
	 */
	public static function hostOf(string $url): string
	{
		$host = parse_url($url, PHP_URL_HOST);
		return $host ? mb_strtolower((string) $host) : '';
	}

	protected static function requestOrigin(): string
	{
		$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
		if ($host === '') {
			return '';
		}
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
			|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
		if (function_exists('CMain::IsHTTPS') && class_exists('CMain', false)) {
			try {
				$https = \CMain::IsHTTPS();
			} catch (\Throwable $e) {
				// leave as detected
			}
		}
		return ($https ? 'https' : 'http') . '://' . $host;
	}

	/**
	 * SITE_SERVER_NAME only — без fallback на HTTP_HOST (security allowlist).
	 */
	protected static function bitrixSiteOrigin(): string
	{
		if (defined('SITE_SERVER_NAME') && SITE_SERVER_NAME) {
			$scheme = 'http';
			if (function_exists('CMain::IsHTTPS') && class_exists('CMain', false)) {
				$scheme = \CMain::IsHTTPS() ? 'https' : 'http';
			} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
				$scheme = 'https';
			}
			return $scheme . '://' . SITE_SERVER_NAME;
		}
		return '';
	}

	/** Шаблон URL товара — пусто = DETAIL_PAGE_URL инфоблока. */
	public static function productUrlTemplate(): string
	{
		return trim(self::get('product_url_tpl', ''));
	}

	public static function sectionUrlTemplate(): string
	{
		return trim(self::get('section_url_tpl', ''));
	}

	/**
	 * CODE свойства noindex на ЭТОМ магазине.
	 * Пусто = свойство НЕ трогаем (только UF + SEO) — так модуль не завязан на чужой каталог.
	 */
	public static function noindexProperty(): string
	{
		return trim(self::get('noindex_property', ''));
	}

	public static function noindexUseSeo(): bool
	{
		return self::get('noindex_use_seo', 'Y') !== 'N';
	}

	/** Дефолты запуска анализа (если UI не передал). */
	public static function analysisEngineDefault(): string
	{
		$v = strtolower(self::get('analysis_engine', 'yandex'));
		return in_array($v, ['yandex', 'google'], true) ? $v : 'yandex';
	}

	public static function analysisRegionDefault(?string $engine = null): string
	{
		$engine = $engine !== null ? strtolower($engine) : self::analysisEngineDefault();
		if ($engine === 'google') {
			$v = trim(self::get('analysis_region_google', '1011969'));
			return $v !== '' ? $v : '1011969'; // Москва (Google geo)
		}
		$v = trim(self::get('analysis_region', '213'));
		return $v !== '' ? $v : '213';
	}

	public static function analysisTopDefault(): int
	{
		$top = (int) self::get('analysis_top', '20');
		if ($top < 10) {
			$top = 10;
		}
		if ($top > 50) {
			$top = 50;
		}
		return $top;
	}

	/**
	 * Популярные города Яндекс (lr) — подсказки при пустом поле.
	 *
	 * @return array<string, string> id => label
	 */
	public static function analysisRegions(): array
	{
		return [
			'213' => 'Москва',
			'2' => 'Санкт-Петербург',
			'193' => 'Воронеж',
			'54' => 'Екатеринбург',
			'65' => 'Новосибирск',
			'43' => 'Казань',
			'47' => 'Нижний Новгород',
			'39' => 'Ростов-на-Дону',
			'35' => 'Краснодар',
			'172' => 'Уфа',
			'56' => 'Челябинск',
			'51' => 'Самара',
			'62' => 'Красноярск',
			'75' => 'Владивосток',
		];
	}

	/**
	 * Популярные города Google (geo id).
	 *
	 * @return array<string, string>
	 */
	public static function analysisGoogleRegions(): array
	{
		return [
			'1011969' => 'Москва',
			'1012040' => 'Санкт-Петербург',
			'1012077' => 'Воронеж',
			'1012052' => 'Екатеринбург',
			'1011984' => 'Новосибирск',
			'1012054' => 'Казань',
			'1011981' => 'Нижний Новгород',
			'1012013' => 'Ростов-на-Дону',
			'1011905' => 'Краснодар',
			'1011867' => 'Уфа',
			'1011874' => 'Челябинск',
			'1012029' => 'Самара',
			'1011941' => 'Красноярск',
			'1012008' => 'Владивосток',
		];
	}

	/**
	 * @return array<int, array{id:string,name:string}>
	 */
	public static function analysisRegionsList(?string $engine = null): array
	{
		$engine = $engine !== null ? strtolower($engine) : 'yandex';
		if ($engine === 'google') {
			return self::analysisGoogleRegionsList();
		}
		return self::analysisYandexRegionsList();
	}

	/**
	 * Полный список регионов Яндекса (lr).
	 *
	 * @return array<int, array{id:string,name:string}>
	 */
	public static function analysisYandexRegionsList(): array
	{
		static $list = null;
		if ($list !== null) {
			return $list;
		}
		$path = dirname(__DIR__) . '/data/yandex_lr_regions.json';
		if (!is_readable($path)) {
			$list = [];
			foreach (self::analysisRegions() as $id => $name) {
				$list[] = ['id' => (string) $id, 'name' => (string) $name];
			}
			return $list;
		}
		$decoded = json_decode((string) file_get_contents($path), true);
		$list = is_array($decoded) ? $decoded : [];
		return $list;
	}

	/**
	 * Города РФ для Google (geo).
	 *
	 * @return array<int, array{id:string,name:string}>
	 */
	public static function analysisGoogleRegionsList(): array
	{
		static $list = null;
		if ($list !== null) {
			return $list;
		}
		$path = dirname(__DIR__) . '/data/google_ru_cities.json';
		if (!is_readable($path)) {
			$list = [];
			foreach (self::analysisGoogleRegions() as $id => $name) {
				$list[] = ['id' => (string) $id, 'name' => (string) $name];
			}
			return $list;
		}
		$decoded = json_decode((string) file_get_contents($path), true);
		$list = is_array($decoded) ? $decoded : [];
		return $list;
	}

	public static function analysisRegionLabel(string $id, ?string $engine = null): string
	{
		$id = trim($id);
		$engine = $engine !== null ? strtolower($engine) : null;
		$lists = $engine === 'google'
			? [self::analysisGoogleRegionsList()]
			: ($engine === 'yandex'
				? [self::analysisYandexRegionsList()]
				: [self::analysisYandexRegionsList(), self::analysisGoogleRegionsList()]);
		foreach ($lists as $rows) {
			foreach ($rows as $row) {
				if ((string) ($row['id'] ?? '') === $id) {
					$name = (string) ($row['name'] ?? '');
					return $name !== '' ? ($name . ' [' . $id . ']') : $id;
				}
			}
		}
		$popular = self::analysisRegions() + self::analysisGoogleRegions();
		if (isset($popular[$id])) {
			return $popular[$id] . ' [' . $id . ']';
		}
		return $id;
	}

	/** @return int[] */
	public static function analysisTopOptions(): array
	{
		return [10, 20, 30, 50];
	}
}
