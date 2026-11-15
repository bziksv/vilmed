<?php

namespace Titlo\Relevance;

/**
 * 301-редиректы из корневого .htaccess: Redirect / RedirectPermanent
 * и безусловные RewriteRule с флагом R (например «^(.*)\-r\/$ → /$1»).
 */
class RedirectMap
{
	/** @var array{alias:array<string,string>,rules:array<int,array{0:string,1:string}>}|null */
	protected static $parsed = null;

	public static function target(string $path): ?string
	{
		$path = '/' . ltrim($path, '/');
		$p = self::parse();
		if (isset($p['alias'][$path])) {
			return $p['alias'][$path];
		}
		$subject = ltrim($path, '/');
		foreach ($p['rules'] as [$regex, $sub]) {
			if (@preg_match($regex, $subject, $m) === 1) {
				return preg_replace_callback('/\$(\d)/', static function ($x) use ($m) {
					return $m[(int) $x[1]] ?? '';
				}, $sub);
			}
		}
		return null;
	}

	public static function normalizePath(string $url): string
	{
		$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
		return mb_strtolower('/' . trim($path, '/'));
	}

	/**
	 * @return array{alias:array<string,string>,rules:array<int,array{0:string,1:string}>}
	 */
	protected static function parse(): array
	{
		if (self::$parsed !== null) {
			return self::$parsed;
		}
		$out = ['alias' => [], 'rules' => []];
		$file = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/.htaccess';
		$lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
		$hasConds = false;
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#') {
				continue;
			}
			$parts = preg_split('/\s+/', $line) ?: [];
			$dir = strtolower($parts[0] ?? '');
			if ($dir === 'rewritecond') {
				$hasConds = true;
				continue;
			}
			if ($dir === 'redirect' || $dir === 'redirectpermanent') {
				$args = array_slice($parts, 1);
				if ($dir === 'redirect' && isset($args[0]) && strpos($args[0], '/') !== 0) {
					$code = strtolower(array_shift($args));
					if (!in_array($code, ['301', 'permanent', '302', 'temp'], true)) {
						continue;
					}
				}
				if (isset($args[0], $args[1])) {
					$out['alias'][$args[0]] = $args[1];
				}
				continue;
			}
			if ($dir === 'rewriterule') {
				$conds = $hasConds;
				$hasConds = false;
				$flags = strtoupper($parts[3] ?? '');
				if ($conds || !isset($parts[1], $parts[2]) || !preg_match('/\bR(=30[1-8])?\b/', $flags)) {
					continue;
				}
				$out['rules'][] = ['~' . str_replace('~', '\~', $parts[1]) . '~', $parts[2]];
			}
		}
		return self::$parsed = $out;
	}
}
