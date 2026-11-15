<?php

namespace Titlo\Relevance;

/**
 * Фактический HTTP-ответ страниц сайта (без следования редиректам): 3xx + Location,
 * <link rel="canonical">. Ловит редиректы любого источника — nginx, Apache, PHP, SEO-модули.
 * Кеш — таблица titlo_relevance_url_probe.
 */
class UrlProbe
{
	public const TYPE_OK = 'ok';
	public const TYPE_REDIRECT = 'redirect';
	public const TYPE_CANONICAL = 'canonical';
	public const TYPE_BLOCKED = 'blocked';
	public const TYPE_ERROR = 'error';
	public const TYPE_PENDING = 'pending';

	protected const TTL_DAYS = 7;
	protected const CONCURRENCY = 6;
	protected const TIMEOUT = 15;
	protected const BODY_LIMIT = 262144;
	protected const DEFAULT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

	/** @var bool */
	protected static $schemaReady = false;

	public static function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		global $DB;
		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_relevance_url_probe (
				PATH_HASH char(32) NOT NULL,
				PATH varchar(1024) NOT NULL DEFAULT '',
				TYPE varchar(16) NOT NULL DEFAULT 'ok',
				HTTP_CODE int(11) NOT NULL DEFAULT 0,
				TARGET varchar(1024) NOT NULL DEFAULT '',
				CHECKED_AT datetime DEFAULT NULL,
				PRIMARY KEY (PATH_HASH)
			)
		");
		self::$schemaReady = true;
	}

	public static function enabled(): bool
	{
		return Config::get('url_probe', 'Y') !== 'N' && function_exists('curl_multi_init');
	}

	/**
	 * @param array<int, string> $paths key => path (/catalog/x/)
	 * @return array<int|string, array{type:string,code:int,target:string}>
	 */
	public static function check(array $paths, float $budgetSec = 15.0, ?callable $onProgress = null): array
	{
		self::ensureSchema();
		global $DB;
		$out = [];
		$byHash = [];
		foreach ($paths as $key => $path) {
			$path = '/' . ltrim((string) $path, '/');
			$byHash[md5($path)][] = [$key, $path];
		}
		if ($byHash === []) {
			return [];
		}

		$cached = [];
		foreach (array_chunk(array_keys($byHash), 500) as $chunk) {
			$in = "'" . implode("','", $chunk) . "'";
			$res = $DB->Query("
				SELECT PATH_HASH, TYPE, HTTP_CODE, TARGET FROM titlo_relevance_url_probe
				WHERE PATH_HASH IN ({$in}) AND CHECKED_AT > DATE_SUB(NOW(), INTERVAL " . self::TTL_DAYS . " DAY)
			");
			while ($row = $res->Fetch()) {
				$cached[$row['PATH_HASH']] = [
					'type' => (string) $row['TYPE'],
					'code' => (int) $row['HTTP_CODE'],
					'target' => (string) $row['TARGET'],
				];
			}
		}

		$todo = [];
		foreach ($byHash as $hash => $list) {
			if (isset($cached[$hash])) {
				foreach ($list as [$key]) {
					$out[$key] = $cached[$hash];
				}
			} else {
				$todo[$hash] = $list[0][1];
			}
		}

		$fetched = $todo !== [] ? self::fetch($todo, $budgetSec, $onProgress) : [];
		foreach ($fetched as $hash => $r) {
			$DB->Query("
				REPLACE INTO titlo_relevance_url_probe (PATH_HASH, PATH, TYPE, HTTP_CODE, TARGET, CHECKED_AT)
				VALUES ('" . $DB->ForSql($hash) . "', '" . $DB->ForSql(mb_substr($todo[$hash], 0, 1024)) . "',
					'" . $DB->ForSql($r['type']) . "', " . (int) $r['code'] . ",
					'" . $DB->ForSql(mb_substr($r['target'], 0, 1024)) . "', NOW())
			");
		}
		foreach ($todo as $hash => $_) {
			$r = $fetched[$hash] ?? ['type' => self::TYPE_PENDING, 'code' => 0, 'target' => ''];
			foreach ($byHash[$hash] as [$key]) {
				$out[$key] = $r;
			}
		}
		return $out;
	}

	public static function clearCache(): void
	{
		self::ensureSchema();
		global $DB;
		$DB->Query('TRUNCATE TABLE titlo_relevance_url_probe');
	}

	/**
	 * @param array<string, string> $todo hash => path
	 * @return array<string, array{type:string,code:int,target:string}>
	 */
	protected static function fetch(array $todo, float $budgetSec, ?callable $onProgress = null): array
	{
		$origin = rtrim(Config::siteUrl(), '/');
		if ($origin === '') {
			return [];
		}
		$ua = trim(Config::get('url_probe_ua', '')) ?: self::DEFAULT_UA;
		$cookie = trim(Config::get('url_probe_cookie', ''));
		$deadline = microtime(true) + $budgetSec;

		$mh = curl_multi_init();
		$queue = $todo;
		$active = [];
		$bodies = [];
		$out = [];

		$add = static function () use (&$queue, &$active, &$bodies, $mh, $origin, $ua, $cookie) {
			$hash = key($queue);
			$path = array_shift($queue);
			$bodies[$hash] = '';
			$ch = curl_init($origin . $path);
			curl_setopt_array($ch, [
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_HEADER => false,
				CURLOPT_TIMEOUT => self::TIMEOUT,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_USERAGENT => $ua,
				CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: ru'],
				CURLOPT_ENCODING => '',
				CURLOPT_WRITEFUNCTION => static function ($c, $chunk) use (&$bodies, $hash) {
					$bodies[$hash] .= $chunk;
					return strlen($bodies[$hash]) > self::BODY_LIMIT ? 0 : strlen($chunk);
				},
			]);
			if ($cookie !== '') {
				curl_setopt($ch, CURLOPT_COOKIE, $cookie);
			}
			curl_multi_add_handle($mh, $ch);
			$active[(int) $ch] = [$ch, $hash, $path];
		};

		while ($queue !== [] && count($active) < self::CONCURRENCY) {
			$add();
		}
		while ($active !== []) {
			curl_multi_exec($mh, $running);
			curl_multi_select($mh, 0.5);
			while ($info = curl_multi_info_read($mh)) {
				$ch = $info['handle'];
				[, $hash, $path] = $active[(int) $ch];
				unset($active[(int) $ch]);
				$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$location = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
				curl_multi_remove_handle($mh, $ch);
				curl_close($ch);
				$out[$hash] = self::classify($path, $code, $location, $bodies[$hash] ?? '');
				if ($onProgress !== null) {
					$onProgress(count($out), count($todo));
				}
				unset($bodies[$hash]);
				if ($queue !== [] && microtime(true) < $deadline) {
					$add();
				}
			}
			if (microtime(true) > $deadline + self::TIMEOUT) {
				break;
			}
		}
		foreach ($active as [$ch]) {
			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
		}
		curl_multi_close($mh);
		return $out;
	}

	/**
	 * @return array{type:string,code:int,target:string}
	 */
	protected static function classify(string $path, int $code, string $location, string $body): array
	{
		$self = RedirectMap::normalizePath($path);
		if ($code >= 300 && $code < 400 && $location !== '') {
			if (RedirectMap::normalizePath($location) !== $self || self::hostDiffers($location)) {
				return ['type' => self::TYPE_REDIRECT, 'code' => $code, 'target' => self::shortUrl($location)];
			}
			// только слэш/регистр: считаем той же страницей
			return ['type' => self::TYPE_OK, 'code' => $code, 'target' => ''];
		}
		if ($code === 200) {
			if (preg_match('~<link[^>]+rel=["\']?canonical["\']?[^>]*>~i', $body, $m)
				&& preg_match('~href=["\']([^"\']+)["\']~i', $m[0], $h)) {
				$canon = html_entity_decode($h[1], ENT_QUOTES);
				if (RedirectMap::normalizePath($canon) !== $self) {
					return ['type' => self::TYPE_CANONICAL, 'code' => $code, 'target' => self::shortUrl($canon)];
				}
				return ['type' => self::TYPE_OK, 'code' => $code, 'target' => ''];
			}
			if (strlen($body) < 4096 && stripos($body, '<title') !== false && !preg_match('~<(a|nav|main|h1)\b~i', $body)) {
				return ['type' => self::TYPE_BLOCKED, 'code' => $code, 'target' => ''];
			}
			return ['type' => self::TYPE_OK, 'code' => $code, 'target' => ''];
		}
		return ['type' => self::TYPE_ERROR, 'code' => $code, 'target' => ''];
	}

	protected static function hostDiffers(string $url): bool
	{
		$host = Config::hostOf($url);
		return $host !== '' && $host !== Config::hostOf(Config::siteUrl());
	}

	protected static function shortUrl(string $url): string
	{
		if (!self::hostDiffers($url)) {
			$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
			$q = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
			return ($path !== '' ? $path : '/') . ($q !== '' ? '?' . $q : '');
		}
		return $url;
	}
}
