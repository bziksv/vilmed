<?php

namespace Titlo\Relevance;

/**
 * Кеш групп дублей (товары E / разделы S) и фоновый пересчёт с прогрессом.
 * Файлы — local/var/titlo_dups/ (bitrix/cache чистится при деплое).
 */
class DupCache
{
	/** Прогресс не обновлялся дольше — считаем процесс упавшим. */
	public const STALE_SEC = 300;

	protected const GUARD = "<?php die(); ?>\n";

	public static function dir(): string
	{
		$dir = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') . '/local/var/titlo_dups';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		return $dir;
	}

	public static function entity(string $e): string
	{
		return $e === 'S' ? 'S' : 'E';
	}

	protected static function dataFile(string $e, int $minScore): string
	{
		return self::dir() . '/' . self::entity($e) . '_' . $minScore . '.php';
	}

	protected static function progressFile(string $e): string
	{
		return self::dir() . '/' . self::entity($e) . '_progress.php';
	}

	protected static function read(string $file)
	{
		$raw = @file_get_contents($file);
		if ($raw === false || strpos($raw, self::GUARD) !== 0) {
			return null;
		}
		$data = @unserialize(substr($raw, strlen(self::GUARD)), ['allowed_classes' => false]);
		return is_array($data) ? $data : null;
	}

	protected static function write(string $file, array $data): void
	{
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (@file_put_contents($tmp, self::GUARD . serialize($data)) !== false) {
			@rename($tmp, $file);
		}
	}

	public static function load(string $e, int $minScore): ?array
	{
		return self::read(self::dataFile($e, $minScore));
	}

	public static function save(string $e, int $minScore, array $data): void
	{
		$data['built_at'] = time();
		$data['min_score'] = $minScore;
		self::write(self::dataFile($e, $minScore), $data);
	}

	public static function progress(string $e): array
	{
		$p = self::read(self::progressFile($e)) ?? ['state' => 'idle'];
		if (($p['state'] ?? '') === 'running' && time() - (int) ($p['updated'] ?? 0) > self::STALE_SEC) {
			$p['state'] = 'error';
			$p['error'] = 'пересчёт прервался (нет ответа ' . self::STALE_SEC . ' с) — запустите ещё раз';
		}
		return $p;
	}

	public static function isRunning(string $e): bool
	{
		return (self::progress($e)['state'] ?? '') === 'running';
	}

	protected static function setProgress(string $e, array $p): void
	{
		$p['updated'] = time();
		self::write(self::progressFile($e), $p);
	}

	/**
	 * Запустить пересчёт в фоне (CLI-процесс). Если exec недоступен — считаем в текущем запросе,
	 * отпустив сессию, чтобы опрос прогресса не ждал.
	 *
	 * @return array{ok:bool,started:bool,progress:array}
	 */
	public static function start(string $e, int $minScore): array
	{
		$e = self::entity($e);
		if (self::isRunning($e)) {
			return ['ok' => true, 'started' => false, 'progress' => self::progress($e)];
		}
		self::setProgress($e, [
			'state' => 'running',
			'min_score' => $minScore,
			'started' => time(),
			'pct' => 0,
			'stage' => 'start',
			'text' => 'Запуск…',
		]);

		$script = dirname(__DIR__) . '/cli/dup-build.php';
		$php = self::phpCli();
		if ($php !== '' && function_exists('exec')) {
			$cmd = 'nohup ' . escapeshellarg($php) . ' -d short_open_tag=1 -d memory_limit=2048M '
				. escapeshellarg($script) . ' ' . escapeshellarg((string) $_SERVER['DOCUMENT_ROOT']) . ' '
				. $e . ' ' . $minScore . ' > /dev/null 2>&1 &';
			@exec($cmd, $out, $code);
			if ($code === 0) {
				return ['ok' => true, 'started' => true, 'progress' => self::progress($e)];
			}
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
		ignore_user_abort(true);
		self::run($e, $minScore);
		return ['ok' => true, 'started' => true, 'progress' => self::progress($e)];
	}

	protected static function phpCli(): string
	{
		foreach ([PHP_BINDIR . '/php', '/usr/bin/php', '/usr/local/bin/php'] as $bin) {
			if (@is_executable($bin)) {
				return $bin;
			}
		}
		return '';
	}

	/**
	 * Сам пересчёт: вызывается из CLI-воркера (или в запросе как fallback).
	 */
	public static function run(string $e, int $minScore): void
	{
		$e = self::entity($e);
		@set_time_limit(0);
		$t0 = microtime(true);
		$started = (int) (self::progress($e)['started'] ?? time());
		$last = 0.0;
		$lastStage = '';
		$report = static function (int $pct, string $stage, string $text) use ($e, $minScore, $started, &$last, &$lastStage) {
			$now = microtime(true);
			if ($stage === $lastStage && $now - $last < 0.4) {
				return;
			}
			$last = $now;
			$lastStage = $stage;
			self::setProgress($e, [
				'state' => 'running',
				'min_score' => $minScore,
				'started' => $started,
				'pct' => max(0, min(99, $pct)),
				'stage' => $stage,
				'text' => $text,
			]);
		};

		try {
			$data = $e === 'S'
				? SectionDuplicates::buildCache($minScore, $report)
				: ProductDuplicates::buildCache($minScore, $report);
			$report(99, 'save', 'Сохраняю результат…');
			self::save($e, $minScore, $data);
			self::setProgress($e, [
				'state' => 'done',
				'min_score' => $minScore,
				'started' => $started,
				'pct' => 100,
				'stage' => 'done',
				'text' => 'Готово',
				'duration' => (int) round(microtime(true) - $t0),
				'clusters' => count($data['clusters'] ?? []),
			]);
		} catch (\Throwable $ex) {
			self::setProgress($e, [
				'state' => 'error',
				'min_score' => $minScore,
				'started' => $started,
				'pct' => 0,
				'stage' => 'error',
				'error' => $ex->getMessage(),
			]);
		}
	}
}
