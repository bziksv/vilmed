<?php

namespace Titlo\Relevance;

/**
 * Массовая генерация коротких фраз по текущему фильтру names-check.
 * UI крутит tick(); Bitrix-агент подхватывает, если вкладка закрыта.
 */
class PhraseBulk
{
	public const BATCH_QUEUED = 'queued';
	public const BATCH_RUNNING = 'running';
	public const BATCH_STOPPED = 'stopped';
	public const BATCH_DONE = 'done';

	public const JOB_QUEUED = 'queued';
	public const JOB_RUNNING = 'running';
	public const JOB_DONE = 'done';
	public const JOB_FAILED = 'failed';

	/** Максимум товаров в одну пачку (защита от случайных 20k без лимита). */
	public const MAX_LIMIT = 500;
	/** Дефолт UI / без явного limit */
	public const DEFAULT_LIMIT = 200;

	/** Сколько товаров в одном запросе к Titlo API (экономия токенов). */
	public const API_CHUNK = 30;

	public static function ensureTables(): void
	{
		global $DB;
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;

		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_phrase_batches (
				ID int(11) NOT NULL AUTO_INCREMENT,
				FILTER varchar(64) NOT NULL DEFAULT 'todo',
				Q varchar(255) NOT NULL DEFAULT '',
				TOTAL int(11) NOT NULL DEFAULT 0,
				DONE_CNT int(11) NOT NULL DEFAULT 0,
				FAIL_CNT int(11) NOT NULL DEFAULT 0,
				STATUS varchar(32) NOT NULL DEFAULT 'running',
				CREATED_AT datetime DEFAULT NULL,
				UPDATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_status (STATUS)
			)
		");

		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_phrase_jobs (
				ID int(11) NOT NULL AUTO_INCREMENT,
				BATCH_ID int(11) NOT NULL DEFAULT 0,
				ENTITY_ID int(11) NOT NULL DEFAULT 0,
				NAME varchar(1024) NOT NULL DEFAULT '',
				URL varchar(2048) NOT NULL DEFAULT '',
				STATUS varchar(32) NOT NULL DEFAULT 'queued',
				RECORD_ID int(11) DEFAULT NULL,
				RESULT varchar(255) DEFAULT NULL,
				ERROR text,
				POLL_TRIES int(11) NOT NULL DEFAULT 0,
				CREATED_AT datetime DEFAULT NULL,
				UPDATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_batch_status (BATCH_ID, STATUS),
				KEY ix_entity (ENTITY_ID)
			)
		");
		self::ensurePromptColumn();
		self::ensureEntityTypeColumn();
	}

	protected static function ensurePromptColumn(): void
	{
		global $DB;
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;
		$col = $DB->Query("SHOW COLUMNS FROM titlo_phrase_batches LIKE 'PROMPT_ID'")->Fetch();
		if (!$col) {
			$DB->Query('ALTER TABLE titlo_phrase_batches ADD COLUMN PROMPT_ID int(11) NOT NULL DEFAULT 0 AFTER STATUS');
		}
	}

	protected static function ensureEntityTypeColumn(): void
	{
		global $DB;
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;
		$col = $DB->Query("SHOW COLUMNS FROM titlo_phrase_batches LIKE 'ENTITY_TYPE'")->Fetch();
		if (!$col) {
			$DB->Query("ALTER TABLE titlo_phrase_batches ADD COLUMN ENTITY_TYPE char(1) NOT NULL DEFAULT 'E' AFTER ID");
			$DB->Query('ALTER TABLE titlo_phrase_batches ADD KEY ix_entity_type (ENTITY_TYPE)');
		}
	}

	/**
	 * @return array{ok:bool,batch_id?:int,total?:int,error?:string,capped?:bool}
	 */
	public static function start(string $filter, string $q, int $limit, int $promptId = 0, string $entityType = 'E'): array
	{
		self::ensureTables();
		global $DB;

		self::ensurePromptColumn();
		self::ensureEntityTypeColumn();
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		if ($promptId <= 0) {
			$promptId = Prompts::getActiveId(Prompts::phraseTypeForEntity($entityType, true));
		}

		$active = self::activeBatch();
		if ($active) {
			return [
				'ok' => false,
				'error' => 'Уже идёт пачка #' . (int) $active['ID'] . ' — сначала остановите или дождитесь конца',
				'batch_id' => (int) $active['ID'],
			];
		}

		$limit = $limit <= 0 ? self::DEFAULT_LIMIT : min(self::MAX_LIMIT, $limit);
		$items = $entityType === 'S'
			? CatalogRepository::listSectionsForPhraseBulk([
				'filter' => $filter,
				'q' => $q,
				'limit' => $limit,
			])
			: CatalogRepository::listElementsForPhraseBulk([
				'filter' => $filter,
				'q' => $q,
				'limit' => $limit,
			]);

		if ($items === []) {
			return ['ok' => false, 'error' => 'По фильтру нечего генерировать'];
		}

		AuditLog::write('phrase_bulk_start', [
			'entity_type' => $entityType,
			'filter' => $filter,
			'q' => $q,
			'limit' => $limit,
			'prompt_id' => $promptId,
			'total' => count($items),
		]);

		$now = date('Y-m-d H:i:s');
		$total = count($items);
		$DB->Query("
			INSERT INTO titlo_phrase_batches
			(ENTITY_TYPE, FILTER, Q, TOTAL, DONE_CNT, FAIL_CNT, STATUS, PROMPT_ID, CREATED_AT, UPDATED_AT)
			VALUES (
				'" . $DB->ForSql($entityType) . "',
				'" . $DB->ForSql($filter) . "',
				'" . $DB->ForSql(mb_substr($q, 0, 255)) . "',
				" . (int) $total . ",
				0, 0,
				'" . self::BATCH_RUNNING . "',
				" . (int) $promptId . ",
				'" . $now . "',
				'" . $now . "'
			)
		");
		$batchId = (int) $DB->LastID();
		if ($promptId > 0) {
			Prompts::markUsed($promptId);
		}

		$chunk = [];
		foreach ($items as $item) {
			$name = mb_substr((string) $item['name'], 0, 1000);
			$url = mb_substr((string) $item['url'], 0, 2000);
			$chunk[] = '(' .
				(int) $batchId . ',' .
				(int) $item['id'] . ',' .
				"'" . $DB->ForSql($name) . "'," .
				"'" . $DB->ForSql($url) . "'," .
				"'" . self::JOB_QUEUED . "'," .
				"'" . $now . "'," .
				"'" . $now . "'" .
			')';
			if (count($chunk) >= 200) {
				$DB->Query('
					INSERT INTO titlo_phrase_jobs
					(BATCH_ID, ENTITY_ID, NAME, URL, STATUS, CREATED_AT, UPDATED_AT)
					VALUES ' . implode(',', $chunk)
				);
				$chunk = [];
			}
		}
		if ($chunk !== []) {
			$DB->Query('
				INSERT INTO titlo_phrase_jobs
				(BATCH_ID, ENTITY_ID, NAME, URL, STATUS, CREATED_AT, UPDATED_AT)
				VALUES ' . implode(',', $chunk)
			);
		}

		return [
			'ok' => true,
			'batch_id' => $batchId,
			'total' => $total,
			'capped' => $total >= $limit,
			'limit' => $limit,
			'entity_type' => $entityType,
		];
	}

	/**
	 * Один шаг: довести running job или стартовать следующий queued.
	 *
	 * @return array{ok:bool,batch:?array,processed:bool,message:?string}
	 */
	public static function tick(): array
	{
		self::ensureTables();
		$batch = self::activeBatch();
		if (!$batch) {
			$last = self::latestBatch();
			return [
				'ok' => true,
				'batch' => $last ? self::publicBatch($last) : null,
				'processed' => false,
				'message' => null,
			];
		}

		$batchId = (int) $batch['ID'];
		if ((string) $batch['STATUS'] === self::BATCH_STOPPED) {
			return [
				'ok' => true,
				'batch' => self::publicBatch($batch),
				'processed' => false,
				'message' => 'stopped',
			];
		}

		$running = self::nextJob($batchId, self::JOB_RUNNING);
		if ($running) {
			self::advanceRunningWave($running);
			$batch = self::getBatch($batchId) ?: $batch;
			self::maybeFinishBatch($batchId);
			$batch = self::getBatch($batchId) ?: $batch;
			return [
				'ok' => true,
				'batch' => self::publicBatch($batch),
				'processed' => true,
				'message' => 'poll',
			];
		}

		$queued = self::nextJobs($batchId, self::JOB_QUEUED, self::API_CHUNK);
		if ($queued !== []) {
			self::startQueuedWave($queued);
			$batch = self::getBatch($batchId) ?: $batch;
			return [
				'ok' => true,
				'batch' => self::publicBatch($batch),
				'processed' => true,
				'message' => 'started_wave',
			];
		}

		self::maybeFinishBatch($batchId);
		$batch = self::getBatch($batchId) ?: $batch;
		return [
			'ok' => true,
			'batch' => self::publicBatch($batch),
			'processed' => false,
			'message' => 'idle',
		];
	}

	public static function stop(?int $batchId = null): array
	{
		self::ensureTables();
		global $DB;
		$batch = $batchId ? self::getBatch($batchId) : self::activeBatch();
		if (!$batch) {
			return ['ok' => false, 'error' => 'Нет активной пачки'];
		}
		$id = (int) $batch['ID'];
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_phrase_batches
			SET STATUS = '" . self::BATCH_STOPPED . "', UPDATED_AT = '" . $now . "'
			WHERE ID = " . $id . "
		");
		$DB->Query("
			UPDATE titlo_phrase_jobs
			SET STATUS = '" . self::JOB_FAILED . "',
				ERROR = 'stopped',
				UPDATED_AT = '" . $now . "'
			WHERE BATCH_ID = " . $id . "
			  AND STATUS IN ('" . self::JOB_QUEUED . "','" . self::JOB_RUNNING . "')
		");
		$batch = self::getBatch($id);
		return ['ok' => true, 'batch' => $batch ? self::publicBatch($batch) : null];
	}

	public static function status(?int $batchId = null): array
	{
		self::ensureTables();
		$batch = $batchId ? self::getBatch($batchId) : (self::activeBatch() ?: self::latestBatch());
		return [
			'ok' => true,
			'batch' => $batch ? self::publicBatch($batch) : null,
		];
	}

	/**
	 * @return array|null
	 */
	protected static function activeBatch()
	{
		global $DB;
		$res = $DB->Query("
			SELECT * FROM titlo_phrase_batches
			WHERE STATUS = '" . self::BATCH_RUNNING . "'
			ORDER BY ID DESC
			LIMIT 1
		");
		$row = $res->Fetch();
		return $row ?: null;
	}

	/**
	 * @return array|null
	 */
	protected static function latestBatch()
	{
		global $DB;
		$res = $DB->Query('SELECT * FROM titlo_phrase_batches ORDER BY ID DESC LIMIT 1');
		$row = $res->Fetch();
		return $row ?: null;
	}

	/**
	 * @return array|null
	 */
	protected static function getBatch(int $id)
	{
		global $DB;
		$res = $DB->Query('SELECT * FROM titlo_phrase_batches WHERE ID = ' . (int) $id);
		$row = $res->Fetch();
		return $row ?: null;
	}

	/**
	 * @return array|null
	 */
	protected static function nextJob(int $batchId, string $status)
	{
		$jobs = self::nextJobs($batchId, $status, 1);
		return $jobs[0] ?? null;
	}

	/**
	 * @return array<int, array>
	 */
	protected static function nextJobs(int $batchId, string $status, int $limit): array
	{
		global $DB;
		$limit = max(1, min(100, $limit));
		$res = $DB->Query("
			SELECT * FROM titlo_phrase_jobs
			WHERE BATCH_ID = " . (int) $batchId . "
			  AND STATUS = '" . $DB->ForSql($status) . "'
			ORDER BY ID ASC
			LIMIT " . (int) $limit . '
		');
		$out = [];
		while ($row = $res->Fetch()) {
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param array<int, array> $jobs
	 */
	protected static function startQueuedWave(array $jobs): void
	{
		global $DB;
		if ($jobs === []) {
			return;
		}
		$now = date('Y-m-d H:i:s');
		$items = [];
		$ids = [];
		foreach ($jobs as $job) {
			$ids[] = (int) $job['ID'];
			$items[] = [
				'name' => (string) $job['NAME'],
				'url' => (string) ($job['URL'] ?? ''),
				'external_id' => (string) (int) $job['ID'],
			];
		}

		try {
			$client = new ApiClient();
			$promptId = 0;
			$entityType = 'E';
			$batchId = (int) ($jobs[0]['BATCH_ID'] ?? 0);
			if ($batchId > 0) {
				$b = $DB->Query('SELECT PROMPT_ID, ENTITY_TYPE FROM titlo_phrase_batches WHERE ID=' . $batchId)->Fetch();
				$promptId = (int) ($b['PROMPT_ID'] ?? 0);
				$entityType = strtoupper((string) ($b['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
			}
			$res = count($items) === 1
				? $client->startPhraseGenerate($items[0]['name'], $items[0]['url'], $promptId, $entityType)
				: $client->startPhraseBatch($items, $promptId, $entityType);
			$recordId = (int) ($res['record_id'] ?? 0);
			if ($recordId <= 0) {
				throw new \RuntimeException('Пустой record_id от API');
			}
			$idList = implode(',', $ids);
			$DB->Query("
				UPDATE titlo_phrase_jobs SET
					STATUS = '" . self::JOB_RUNNING . "',
					RECORD_ID = " . $recordId . ",
					POLL_TRIES = 0,
					ERROR = NULL,
					UPDATED_AT = '" . $now . "'
				WHERE ID IN (" . $idList . ")
			");
		} catch (\Throwable $e) {
			if (self::isRateLimited($e)) {
				// Не жжём wave — следующий tick повторит старт.
				$idList = implode(',', $ids);
				$DB->Query("
					UPDATE titlo_phrase_jobs SET
						UPDATED_AT = '" . $now . "',
						ERROR = '" . $DB->ForSql('rate_limited_retry') . "'
					WHERE ID IN (" . $idList . ")
				");
				return;
			}
			foreach ($jobs as $job) {
				self::failJob((int) $job['ID'], (int) $job['BATCH_ID'], $e->getMessage());
			}
		}
	}

	/** @deprecated single-job start — оставлен для совместимости, тик использует wave */
	protected static function startQueuedJob(array $job): void
	{
		self::startQueuedWave([$job]);
	}

	protected static function advanceRunningWave(array $job): void
	{
		global $DB;
		$batchId = (int) $job['BATCH_ID'];
		$recordId = (int) ($job['RECORD_ID'] ?? 0);
		$tries = (int) ($job['POLL_TRIES'] ?? 0) + 1;
		$now = date('Y-m-d H:i:s');

		if ($recordId <= 0) {
			self::failJob((int) $job['ID'], $batchId, 'Нет record_id');
			return;
		}

		$wave = [];
		$res = $DB->Query("
			SELECT * FROM titlo_phrase_jobs
			WHERE BATCH_ID = " . $batchId . "
			  AND STATUS = '" . self::JOB_RUNNING . "'
			  AND RECORD_ID = " . $recordId . "
			ORDER BY ID ASC
		");
		while ($row = $res->Fetch()) {
			$wave[] = $row;
		}
		if ($wave === []) {
			return;
		}
		$waveIds = array_map(static function ($r) {
			return (int) $r['ID'];
		}, $wave);
		$idList = implode(',', $waveIds);

		try {
			$client = new ApiClient();
			$resApi = $client->getGenerate($recordId);
			$status = (string) ($resApi['status'] ?? 'pending');

			if ($status === 'completed') {
				$phrases = [];
				$byExternal = [];
				if (!empty($resApi['items']) && is_array($resApi['items'])) {
					foreach ($resApi['items'] as $row) {
						$result = trim((string) ($row['result'] ?? ''));
						$phrases[] = $result;
						$ext = trim((string) ($row['external_id'] ?? ''));
						if ($ext !== '') {
							$byExternal[$ext] = $result;
						}
					}
				} elseif (isset($resApi['result']) && is_array($resApi['result'])) {
					foreach ($resApi['result'] as $p) {
						$phrases[] = trim((string) $p);
					}
				} else {
					// одиночный ответ — на всю волну из 1
					$phrases[] = trim((string) ($resApi['result'] ?? ''));
				}

				$doneInc = 0;
				$failInc = 0;
				foreach ($wave as $i => $wjob) {
					$jid = (int) $wjob['ID'];
					$extKey = (string) $jid;
					$phrase = isset($byExternal[$extKey])
						? $byExternal[$extKey]
						: trim((string) ($phrases[$i] ?? ''));
					if ($phrase === '') {
						self::failJob($jid, $batchId, 'Пустой ответ генерации', false);
						$failInc++;
						continue;
					}
					$ok = self::savePhraseForBatch($batchId, (int) $wjob['ENTITY_ID'], $phrase);
					if (!$ok) {
						self::failJob($jid, $batchId, 'Не удалось сохранить UF', false);
						$failInc++;
						continue;
					}
					$DB->Query("
						UPDATE titlo_phrase_jobs SET
							STATUS = '" . self::JOB_DONE . "',
							RESULT = '" . $DB->ForSql(mb_substr($phrase, 0, 255)) . "',
							ERROR = NULL,
							POLL_TRIES = " . $tries . ",
							UPDATED_AT = '" . $now . "'
						WHERE ID = " . $jid . "
					");
					$doneInc++;
				}
				if ($doneInc > 0 || $failInc > 0) {
					$DB->Query("
						UPDATE titlo_phrase_batches SET
							DONE_CNT = DONE_CNT + " . (int) $doneInc . ",
							FAIL_CNT = FAIL_CNT + " . (int) $failInc . ",
							UPDATED_AT = '" . $now . "'
						WHERE ID = " . $batchId . "
					");
				}
				return;
			}

			if ($status === 'failed') {
				$err = (string) ($resApi['error'] ?? 'generation_failed');
				foreach ($wave as $wjob) {
					self::failJob((int) $wjob['ID'], $batchId, $err, false);
				}
				$DB->Query("
					UPDATE titlo_phrase_batches SET
						FAIL_CNT = FAIL_CNT + " . count($wave) . ",
						UPDATED_AT = '" . $now . "'
					WHERE ID = " . $batchId . "
				");
				return;
			}

			if ($tries >= 90) {
				foreach ($wave as $wjob) {
					self::failJob((int) $wjob['ID'], $batchId, 'Таймаут ожидания генерации', false);
				}
				$DB->Query("
					UPDATE titlo_phrase_batches SET
						FAIL_CNT = FAIL_CNT + " . count($wave) . ",
						UPDATED_AT = '" . $now . "'
					WHERE ID = " . $batchId . "
				");
				return;
			}

			$DB->Query("
				UPDATE titlo_phrase_jobs SET
					POLL_TRIES = " . $tries . ",
					UPDATED_AT = '" . $now . "'
				WHERE ID IN (" . $idList . ")
			");
		} catch (\Throwable $e) {
			if (self::isRateLimited($e)) {
				$DB->Query("
					UPDATE titlo_phrase_jobs SET
						POLL_TRIES = " . max(0, $tries - 1) . ",
						UPDATED_AT = '" . $now . "',
						ERROR = '" . $DB->ForSql('rate_limited_retry') . "'
					WHERE ID IN (" . $idList . ")
				");
				return;
			}
			foreach ($wave as $wjob) {
				self::failJob((int) $wjob['ID'], $batchId, $e->getMessage());
			}
		}
	}

	protected static function advanceRunningJob(array $job): void
	{
		self::advanceRunningWave($job);
	}

	protected static function isRateLimited(\Throwable $e): bool
	{
		$msg = mb_strtolower($e->getMessage());
		$code = (int) $e->getCode();
		return $code === 429
			|| strpos($msg, 'too many attempts') !== false
			|| strpos($msg, 'too many requests') !== false
			|| strpos($msg, 'rate limit') !== false;
	}

	/**
	 * Вернуть в очередь jobs, упавшие из‑за rate limit (можно после поднятия throttle).
	 */
	public static function requeueRateLimited(?int $batchId = null): array
	{
		self::ensureTables();
		global $DB;
		$batch = $batchId ? self::getBatch($batchId) : self::activeBatch();
		if (!$batch) {
			$batch = self::latestBatch();
		}
		if (!$batch) {
			return ['ok' => false, 'error' => 'Нет пачки'];
		}
		$id = (int) $batch['ID'];
		$now = date('Y-m-d H:i:s');
		$res = $DB->Query("
			SELECT COUNT(*) AS CNT FROM titlo_phrase_jobs
			WHERE BATCH_ID = " . $id . "
			  AND STATUS = '" . self::JOB_FAILED . "'
			  AND ERROR LIKE '%Too Many Attempts%'
		")->Fetch();
		$cnt = (int) ($res['CNT'] ?? 0);
		if ($cnt > 0) {
			$DB->Query("
				UPDATE titlo_phrase_jobs SET
					STATUS = '" . self::JOB_QUEUED . "',
					RECORD_ID = NULL,
					RESULT = NULL,
					ERROR = NULL,
					POLL_TRIES = 0,
					UPDATED_AT = '" . $now . "'
				WHERE BATCH_ID = " . $id . "
				  AND STATUS = '" . self::JOB_FAILED . "'
				  AND ERROR LIKE '%Too Many Attempts%'
			");
			$DB->Query("
				UPDATE titlo_phrase_batches SET
					FAIL_CNT = GREATEST(0, FAIL_CNT - " . $cnt . "),
					STATUS = '" . self::BATCH_RUNNING . "',
					UPDATED_AT = '" . $now . "'
				WHERE ID = " . $id . "
			");
		}
		$batch = self::getBatch($id);
		return [
			'ok' => true,
			'requeued' => $cnt,
			'batch' => $batch ? self::publicBatch($batch) : null,
		];
	}

	protected static function failJob(int $jobId, int $batchId, string $error, bool $bumpFailCnt = true): void
	{
		global $DB;
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_phrase_jobs SET
				STATUS = '" . self::JOB_FAILED . "',
				ERROR = '" . $DB->ForSql(mb_substr($error, 0, 2000)) . "',
				UPDATED_AT = '" . $now . "'
			WHERE ID = " . (int) $jobId . "
		");
		if ($bumpFailCnt) {
			$DB->Query("
				UPDATE titlo_phrase_batches SET
					FAIL_CNT = FAIL_CNT + 1,
					UPDATED_AT = '" . $now . "'
				WHERE ID = " . (int) $batchId . "
			");
		}
	}

	protected static function maybeFinishBatch(int $batchId): void
	{
		global $DB;
		$pending = $DB->Query("
			SELECT COUNT(*) AS CNT FROM titlo_phrase_jobs
			WHERE BATCH_ID = " . (int) $batchId . "
			  AND STATUS IN ('" . self::JOB_QUEUED . "','" . self::JOB_RUNNING . "')
		")->Fetch();
		if ((int) ($pending['CNT'] ?? 0) > 0) {
			return;
		}
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_phrase_batches SET
				STATUS = '" . self::BATCH_DONE . "',
				UPDATED_AT = '" . $now . "'
			WHERE ID = " . (int) $batchId . "
			  AND STATUS = '" . self::BATCH_RUNNING . "'
		");
	}

	/**
	 * @param array $batch
	 * @return array<string, mixed>
	 */
	protected static function publicBatch(array $batch): array
	{
		$total = (int) $batch['TOTAL'];
		$done = (int) $batch['DONE_CNT'];
		$fail = (int) $batch['FAIL_CNT'];
		$left = max(0, $total - $done - $fail);
		$entityType = strtoupper((string) ($batch['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
		return [
			'id' => (int) $batch['ID'],
			'entity_type' => $entityType,
			'filter' => (string) $batch['FILTER'],
			'q' => (string) $batch['Q'],
			'total' => $total,
			'done' => $done,
			'failed' => $fail,
			'left' => $left,
			'status' => (string) $batch['STATUS'],
			'updated_at' => (string) ($batch['UPDATED_AT'] ?? ''),
		];
	}

	protected static function savePhraseForBatch(int $batchId, int $entityId, string $phrase): bool
	{
		$batch = self::getBatch($batchId);
		$entityType = strtoupper((string) ($batch['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
		if ($entityType === 'S') {
			return CatalogRepository::saveSectionPhrase($entityId, $phrase, false);
		}
		return CatalogRepository::saveElementPhrase($entityId, $phrase, false);
	}
}
