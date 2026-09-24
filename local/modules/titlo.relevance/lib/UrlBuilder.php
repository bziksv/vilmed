<?php

namespace Titlo\Relevance;

class UrlBuilder
{
	/**
	 * URL для краула кабинетом: публичный site_url + path.
	 * 127.0.0.1 кабинет скачать не может (это localhost сервера Titlo).
	 *
	 * @return array{0:string,1:?string} [crawlUrl, error]
	 */
	public static function forCabinetCrawl(string $browseUrl): array
	{
		$browseUrl = trim($browseUrl);
		if ($browseUrl === '') {
			return ['', 'url required'];
		}
		$host = Config::hostOf($browseUrl);
		$isLoopback = in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
			|| strpos($host, '192.168.') === 0
			|| strpos($host, '10.') === 0
			|| (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host);

		$prod = self::toProdUrl($browseUrl);
		$site = rtrim(Config::siteUrl(), '/');
		$siteHost = Config::hostOf($site);

		if ($isLoopback) {
			if ($site === '' || $siteHost === '' || in_array($siteHost, ['127.0.0.1', 'localhost', '::1'], true)) {
				return ['', 'Кабинет не может открыть 127.0.0.1. Укажите публичный «URL сайта» в настройках модуля Titlo (прод), туда уйдёт краул.'];
			}
			if ($prod === '') {
				return ['', 'Не удалось собрать публичный URL для краула — проверьте «URL сайта» в настройках'];
			}
			return [$prod, null];
		}

		// Уже публичный URL — краулим как есть (или через toProdUrl если path с админки)
		if ($prod !== '') {
			return [$prod, null];
		}
		return [$browseUrl, null];
	}

	public static function product(string $code, int $id = 0): string
	{
		$tpl = Config::productUrlTemplate();
		if ($tpl !== '') {
			return self::applyTemplate($tpl, $code, $id);
		}
		$code = trim($code, '/');
		$base = self::origin();
		if ($base === '' || $code === '') {
			return $code !== '' ? '/' . rawurlencode($code) . '/' : '';
		}
		// нейтральный fallback без привязки к структуре конкретного магазина
		return $base . '/' . rawurlencode($code) . '/';
	}

	public static function category(string $code, int $id = 0): string
	{
		$tpl = Config::sectionUrlTemplate();
		if ($tpl !== '') {
			return self::applyTemplate($tpl, $code, $id);
		}
		$code = trim($code, '/');
		$base = self::origin();
		if ($base === '' || $code === '') {
			return $code !== '' ? '/' . rawurlencode($code) . '/' : '';
		}
		return $base . '/' . rawurlencode($code) . '/';
	}

	/**
	 * Prefer DETAIL_PAGE_URL / SECTION_PAGE_URL from Bitrix (работает на любом сайте).
	 */
	public static function fromDetailPageUrl(?string $detailPageUrl, string $fallbackCode, string $entityType, int $id = 0): string
	{
		$detailPageUrl = trim((string) $detailPageUrl);
		if ($detailPageUrl !== '') {
			if (preg_match('#^https?://#i', $detailPageUrl)) {
				return self::toBrowseUrl($detailPageUrl);
			}
			$base = self::origin();
			$path = '/' . ltrim($detailPageUrl, '/');
			return $base !== '' ? $base . $path : $path;
		}

		return $entityType === 'S'
			? self::category($fallbackCode, $id)
			: self::product($fallbackCode, $id);
	}

	/**
	 * Карта elementId => публичный URL через DETAIL_PAGE_URL инфоблока (сайт-агностик).
	 *
	 * @param int[] $ids
	 * @return array<int, string>
	 */
	public static function mapElementUrls(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return [];
		}
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$map = [];
		foreach (array_chunk($ids, 400) as $chunk) {
			$res = \CIBlockElement::GetList(
				[],
				['IBLOCK_ID' => $iblockId, 'ID' => $chunk, 'CHECK_PERMISSIONS' => 'N', 'SHOW_NEW' => 'Y'],
				false,
				false,
				['ID', 'CODE', 'DETAIL_PAGE_URL']
			);
			while ($row = $res->GetNext()) {
				$id = (int) $row['ID'];
				$map[$id] = self::fromDetailPageUrl(
					(string) ($row['DETAIL_PAGE_URL'] ?? ''),
					(string) ($row['CODE'] ?? ''),
					'E',
					$id
				);
			}
		}
		return $map;
	}

	/**
	 * Карта sectionId => публичный URL через SECTION_PAGE_URL.
	 *
	 * @param int[] $ids
	 * @return array<int, string>
	 */
	public static function mapSectionUrls(array $ids): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return [];
		}
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$map = [];
		foreach (array_chunk($ids, 400) as $chunk) {
			$res = \CIBlockSection::GetList(
				[],
				['IBLOCK_ID' => $iblockId, 'ID' => $chunk, 'CHECK_PERMISSIONS' => 'N'],
				false,
				['ID', 'CODE', 'SECTION_PAGE_URL']
			);
			while ($row = $res->GetNext()) {
				$id = (int) $row['ID'];
				$map[$id] = self::fromDetailPageUrl(
					(string) ($row['SECTION_PAGE_URL'] ?? ''),
					(string) ($row['CODE'] ?? ''),
					'S',
					$id
				);
			}
		}
		return $map;
	}

	/**
	 * Origin для публичных ссылок в UI: текущий хост админки (локал ≠ прод).
	 */
	protected static function origin(): string
	{
		return Config::browseOrigin();
	}

	/**
	 * Абсолютный URL с прода → тот же path на текущем хосте.
	 * Относительный path оставляем / дописываем browse origin.
	 */
	public static function toBrowseUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '') {
			return '';
		}
		$browse = Config::browseOrigin();
		if ($browse === '') {
			return $url;
		}

		if (!preg_match('#^https?://#i', $url)) {
			return $browse . '/' . ltrim($url, '/');
		}

		$parts = parse_url($url);
		if (!is_array($parts) || empty($parts['host'])) {
			return $url;
		}

		$path = (string) ($parts['path'] ?? '/');
		$query = isset($parts['query']) ? ('?' . $parts['query']) : '';
		$fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

		$configuredHost = Config::hostOf(Config::siteUrl());
		$urlHost = mb_strtolower((string) $parts['host']);
		$browseHost = Config::hostOf($browse);

		// Уже текущий хост — не трогаем
		if ($browseHost !== '' && $urlHost === $browseHost) {
			return $url;
		}

		// Переписываем: настроенный site_url ИЛИ любой чужой хост при работе с локал/админки
		if ($configuredHost === '' || $urlHost === $configuredHost || self::isDevHost($browseHost)) {
			return $browse . $path . $query . $fragment;
		}

		return $url;
	}

	/**
	 * Тот же path на каноническом site_url (прод) — для ссылки «открыть карточку на проде».
	 */
	public static function toProdUrl(string $url): string
	{
		$url = trim($url);
		$prod = rtrim(Config::siteUrl(), '/');
		if ($url === '' || $prod === '') {
			return '';
		}

		if (!preg_match('#^https?://#i', $url)) {
			return $prod . '/' . ltrim($url, '/');
		}

		$parts = parse_url($url);
		if (!is_array($parts)) {
			return '';
		}

		$path = (string) ($parts['path'] ?? '/');
		$query = isset($parts['query']) ? ('?' . $parts['query']) : '';
		$fragment = isset($parts['fragment']) ? ('#' . $parts['fragment']) : '';

		return $prod . $path . $query . $fragment;
	}

	protected static function isDevHost(string $host): bool
	{
		$host = mb_strtolower($host);
		if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
			return true;
		}
		if (preg_match('/\.(local|localhost|test|dev)$/', $host)) {
			return true;
		}
		// *.loc, vilmed.local и т.п.
		if (preg_match('/\.(loc|lan|internal)$/', $host)) {
			return true;
		}
		return false;
	}

	protected static function applyTemplate(string $tpl, string $code, int $id): string
	{
		$code = trim($code, '/');
		$path = str_replace(
			['{code}', '{CODE}', '{id}', '{ID}'],
			[rawurlencode($code), rawurlencode($code), (string) $id, (string) $id],
			$tpl
		);
		if (preg_match('#^https?://#i', $path)) {
			return self::toBrowseUrl($path);
		}
		$base = self::origin();
		$path = '/' . ltrim($path, '/');
		return $base !== '' ? $base . $path : $path;
	}

	/**
	 * Похоже на URL/path каталога (для поиска в «Проверке названий»).
	 */
	public static function looksLikeUrlInput(string $q): bool
	{
		$q = trim($q);
		if ($q === '') {
			return false;
		}
		if (preg_match('#^https?://#i', $q)) {
			return true;
		}
		if (isset($q[0]) && $q[0] === '/' && strpos($q, ' ') === false) {
			return true;
		}
		if (preg_match('#^(product|catalog|category|section)/#i', $q) && strpos($q, ' ') === false) {
			return true;
		}
		return false;
	}

	/**
	 * Из URL/path → ID товара или раздела каталога модуля.
	 * null — это не URL (искать как текст); [] — URL, но сущность не найдена.
	 *
	 * @return int[]|null
	 */
	public static function resolveCatalogIdsFromUrl(string $input, string $entityType): ?array
	{
		$input = trim($input);
		if ($input === '' || !self::looksLikeUrlInput($input)) {
			return null;
		}

		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$raw = $input;
		if (!preg_match('#^https?://#i', $raw)) {
			$raw = 'https://local.test/' . ltrim($raw, '/');
		}
		$parts = parse_url($raw);
		if (!is_array($parts)) {
			return [];
		}

		$query = [];
		if (!empty($parts['query'])) {
			parse_str((string) $parts['query'], $query);
		}
		$idKeys = $entityType === 'S'
			? ['SECTION_ID', 'SID', 'ID', 'id']
			: ['ELEMENT_ID', 'ID', 'id'];
		foreach ($idKeys as $key) {
			if (!isset($query[$key])) {
				continue;
			}
			$id = (int) $query[$key];
			if ($id <= 0) {
				continue;
			}
			$ok = $entityType === 'S'
				? Config::sectionBelongsToCatalog($id)
				: Config::elementBelongsToCatalog($id);
			if ($ok) {
				return [$id];
			}
		}

		$path = (string) ($parts['path'] ?? '');
		$segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
		$noise = [
			'bitrix' => true, 'admin' => true, 'local' => true, 'index.php' => true,
			'index.html' => true, 'ru' => true, 'en' => true,
		];
		$segments = array_values(array_filter($segments, static function ($s) use ($noise) {
			return !isset($noise[mb_strtolower($s)]);
		}));
		if ($segments === []) {
			return [];
		}

		foreach (array_reverse($segments) as $seg) {
			$seg = rawurldecode((string) $seg);
			$seg = trim($seg);
			if ($seg === '') {
				continue;
			}
			if (ctype_digit($seg)) {
				$id = (int) $seg;
				$ok = $entityType === 'S'
					? Config::sectionBelongsToCatalog($id)
					: Config::elementBelongsToCatalog($id);
				if ($ok) {
					return [$id];
				}
				continue;
			}
			$id = self::findCatalogIdByCode($seg, $entityType);
			if ($id > 0) {
				return [$id];
			}
		}

		return [];
	}

	protected static function findCatalogIdByCode(string $code, string $entityType): int
	{
		$iblockId = Config::iblockId();
		if ($iblockId <= 0 || $code === '') {
			return 0;
		}
		if ($entityType === 'S') {
			$res = \CIBlockSection::GetList(
				[],
				['IBLOCK_ID' => $iblockId, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'],
				false,
				['ID'],
				['nTopCount' => 1]
			);
			$row = $res->Fetch();
			return $row ? (int) $row['ID'] : 0;
		}
		$res = \CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => $iblockId, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N', 'SHOW_NEW' => 'Y'],
			false,
			['nTopCount' => 1],
			['ID']
		);
		$row = $res->Fetch();
		return $row ? (int) $row['ID'] : 0;
	}
}
