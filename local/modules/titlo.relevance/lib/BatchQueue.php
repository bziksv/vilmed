<?php

namespace Titlo\Relevance;

class BatchQueue
{
	public const STATUS_QUEUED = 'queued';
	public const STATUS_RUNNING = 'running';
	public const STATUS_DONE = 'done';
	public const STATUS_FAILED = 'failed';
	public const STATUS_SKIPPED = 'skipped';

	/** Дата/время для очереди — всегда Europe/Moscow (не UTC php.ini). */
	public static function now(): string
	{
		try {
			return (new \DateTime('now', new \DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s');
		} catch (\Throwable $e) {
			return date('Y-m-d H:i:s');
		}
	}

	/**
	 * Возраст UPDATED_AT/CREATED_AT в секундах (парсинг как Europe/Moscow).
	 */
	public static function ageSeconds(?string $datetime): int
	{
		$datetime = trim((string) $datetime);
		if ($datetime === '') {
			return 0;
		}
		try {
			$tz = new \DateTimeZone('Europe/Moscow');
			$then = new \DateTime($datetime, $tz);
			$now = new \DateTime('now', $tz);
			return max(0, $now->getTimestamp() - $then->getTimestamp());
		} catch (\Throwable $e) {
			$ts = strtotime($datetime);
			return $ts ? max(0, time() - $ts) : 0;
		}
	}

	/**
	 * Атомарный claim queued → running. false = уже забрал другой воркер.
	 */
	public static function claim(int $id): bool
	{
		self::ensureSchema();
		$id = (int) $id;
		if ($id <= 0) {
			return false;
		}
		global $DB;
		$now = self::now();
		$DB->Query("
			UPDATE titlo_relevance_queue SET
				STATUS = '" . self::STATUS_RUNNING . "',
				STEP = '" . self::STEP_ANALYZE . "',
				ERROR = NULL,
				UPDATED_AT = '" . $now . "'
			WHERE ID = " . $id . "
			  AND STATUS = '" . self::STATUS_QUEUED . "'
		");
		if (method_exists($DB, 'AffectedRowsCount')) {
			return (int) $DB->AffectedRowsCount() > 0;
		}
		if (isset($DB->db_Conn) && is_object($DB->db_Conn) && property_exists($DB->db_Conn, 'affected_rows')) {
			return (int) $DB->db_Conn->affected_rows > 0;
		}
		// Без affected_rows не считаем claim успешным (fail closed)
		return false;
	}

	public const STEP_ANALYZE = 'analyze';
	public const STEP_WAIT_ANALYSIS = 'wait_analysis';
	public const STEP_GENERATE = 'generate';
	public const STEP_WAIT_GENERATE = 'wait_generate';
	public const STEP_GENERATE_PREVIEW = 'generate_preview';
	public const STEP_WAIT_GENERATE_PREVIEW = 'wait_generate_preview';
	public const STEP_SAVE = 'save';
	public const STEP_RECHECK = 'recheck';
	public const STEP_WAIT_RECHECK = 'wait_recheck';
	public const STEP_DONE = 'done';

	public const POLICY_OVERWRITE = 'overwrite';
	public const POLICY_SKIP = 'skip';

	public const MODE_FULL = 'full';
	public const MODE_ANALYZE_ONLY = 'analyze_only';

	public const SCHEMA_VER = '1.3.0';

	/** @var bool */
	protected static $schemaReady = false;

	public static function ensureSchema(): void
	{
		if (self::$schemaReady) {
			return;
		}
		global $DB;
		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_relevance_queue (
				ID int(11) NOT NULL AUTO_INCREMENT,
				ENTITY_TYPE char(1) NOT NULL DEFAULT 'E',
				ENTITY_ID int(11) NOT NULL DEFAULT 0,
				URL varchar(2048) NOT NULL DEFAULT '',
				PHRASE varchar(255) NOT NULL DEFAULT '',
				STATUS varchar(32) NOT NULL DEFAULT 'queued',
				STEP varchar(32) NOT NULL DEFAULT 'analyze',
				HISTORY_ID int(11) DEFAULT NULL,
				ANALYSIS_ID varchar(64) DEFAULT NULL,
				GEN_RECORD_ID int(11) DEFAULT NULL,
				GEN_PREVIEW_RECORD_ID int(11) DEFAULT NULL,
				DETAIL_TEXT mediumtext,
				PREVIEW_TEXT mediumtext,
				PROMPT_DETAIL_ID int(11) NOT NULL DEFAULT 0,
				PROMPT_PREVIEW_ID int(11) NOT NULL DEFAULT 0,
				GEN_PREVIEW char(1) NOT NULL DEFAULT 'N',
				EXISTING_POLICY varchar(16) NOT NULL DEFAULT 'overwrite',
				TLP_MISSING_LIMIT int(11) NOT NULL DEFAULT 200,
				TLP_DIFF_LIMIT int(11) NOT NULL DEFAULT 5,
				WORK_HISTORY_ID int(11) DEFAULT NULL,
				RUN_MODE varchar(16) NOT NULL DEFAULT 'full',
				BATCH_KEY varchar(64) NOT NULL DEFAULT '',
				ERROR text,
				CREATED_AT datetime DEFAULT NULL,
				UPDATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_status (STATUS),
				KEY ix_entity (ENTITY_TYPE, ENTITY_ID),
				KEY ix_batch (BATCH_KEY),
				KEY ix_step (STATUS, STEP, ID)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$cols = self::existingColumns();
		$alters = [
			'STEP' => "ALTER TABLE titlo_relevance_queue ADD COLUMN STEP varchar(32) NOT NULL DEFAULT 'analyze' AFTER STATUS",
			'GEN_RECORD_ID' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN GEN_RECORD_ID int(11) DEFAULT NULL AFTER ANALYSIS_ID',
			'GEN_PREVIEW_RECORD_ID' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN GEN_PREVIEW_RECORD_ID int(11) DEFAULT NULL AFTER GEN_RECORD_ID',
			'GEN_ATTEMPTS' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN GEN_ATTEMPTS int(11) NOT NULL DEFAULT 0 AFTER GEN_PREVIEW_RECORD_ID',
			'DETAIL_TEXT' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN DETAIL_TEXT mediumtext AFTER GEN_PREVIEW_RECORD_ID',
			'PREVIEW_TEXT' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN PREVIEW_TEXT mediumtext AFTER DETAIL_TEXT',
			'PROMPT_DETAIL_ID' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN PROMPT_DETAIL_ID int(11) NOT NULL DEFAULT 0 AFTER PREVIEW_TEXT',
			'PROMPT_PREVIEW_ID' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN PROMPT_PREVIEW_ID int(11) NOT NULL DEFAULT 0 AFTER PROMPT_DETAIL_ID',
			'GEN_PREVIEW' => "ALTER TABLE titlo_relevance_queue ADD COLUMN GEN_PREVIEW char(1) NOT NULL DEFAULT 'N' AFTER PROMPT_PREVIEW_ID",
			'EXISTING_POLICY' => "ALTER TABLE titlo_relevance_queue ADD COLUMN EXISTING_POLICY varchar(16) NOT NULL DEFAULT 'overwrite' AFTER GEN_PREVIEW",
			'TLP_MISSING_LIMIT' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN TLP_MISSING_LIMIT int(11) NOT NULL DEFAULT 200 AFTER EXISTING_POLICY',
			'TLP_DIFF_LIMIT' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN TLP_DIFF_LIMIT int(11) NOT NULL DEFAULT 5 AFTER TLP_MISSING_LIMIT',
			'WORK_HISTORY_ID' => 'ALTER TABLE titlo_relevance_queue ADD COLUMN WORK_HISTORY_ID int(11) DEFAULT NULL AFTER TLP_DIFF_LIMIT',
			'RUN_MODE' => "ALTER TABLE titlo_relevance_queue ADD COLUMN RUN_MODE varchar(16) NOT NULL DEFAULT 'full' AFTER WORK_HISTORY_ID",
			'BATCH_KEY' => "ALTER TABLE titlo_relevance_queue ADD COLUMN BATCH_KEY varchar(64) NOT NULL DEFAULT '' AFTER RUN_MODE",
		];
		foreach ($alters as $col => $sql) {
			if (!isset($cols[$col])) {
				$DB->Query($sql);
			}
		}

		// Старые CREATED_AT/UPDATED_AT писались в UTC (php default) — один раз сдвигаем в МСК.
		$tzVer = 'msk_1';
		if (\Bitrix\Main\Config\Option::get(Config::MODULE_ID, 'queue_datetime_tz', '') !== $tzVer) {
			$DB->Query('
				UPDATE titlo_relevance_queue SET
					CREATED_AT = IF(CREATED_AT IS NULL, NULL, DATE_ADD(CREATED_AT, INTERVAL 3 HOUR)),
					UPDATED_AT = IF(UPDATED_AT IS NULL, NULL, DATE_ADD(UPDATED_AT, INTERVAL 3 HOUR))
			');
			\Bitrix\Main\Config\Option::set(Config::MODULE_ID, 'queue_datetime_tz', $tzVer);
		}

		self::$schemaReady = true;
	}

	/**
	 * @return array<string, true>
	 */
	protected static function existingColumns(): array
	{
		global $DB;
		$out = [];
		$res = $DB->Query('SHOW COLUMNS FROM titlo_relevance_queue');
		while ($row = $res->Fetch()) {
			$field = (string) ($row['Field'] ?? $row['FIELD'] ?? '');
			if ($field !== '') {
				$out[$field] = true;
			}
		}
		return $out;
	}

	/**
	 * @param array{
	 *   prompt_detail_id?:int,prompt_preview_id?:int,gen_preview?:bool,
	 *   existing_policy?:string,tlp_missing_limit?:int,tlp_diff_limit?:int,
	 *   run_mode?:string,batch_key?:string
	 * } $opts
	 */
	public static function enqueue(string $entityType, int $entityId, string $url, string $phrase, array $opts = []): int
	{
		self::ensureSchema();
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$entityId = (int) $entityId;
		$url = trim($url);
		if ($entityId <= 0) {
			throw new \InvalidArgumentException('entity_id required');
		}
		$belongs = $entityType === 'S'
			? Config::sectionBelongsToCatalog($entityId)
			: Config::elementBelongsToCatalog($entityId);
		if (!$belongs) {
			throw new \InvalidArgumentException('entity not in catalog iblock');
		}
		if ($url === '' || !Config::isAllowedAnalysisUrl($url)) {
			throw new \InvalidArgumentException('url must belong to configured site host');
		}
		$phrase = trim($phrase);
		if ($phrase === '') {
			throw new \InvalidArgumentException('phrase required');
		}

		$active = self::activeByEntityIds([$entityId], $entityType);
		if (isset($active[$entityId])) {
			throw new \InvalidArgumentException('already queued or running');
		}

		$policy = strtolower((string) ($opts['existing_policy'] ?? self::POLICY_OVERWRITE));
		if ($policy !== self::POLICY_SKIP) {
			$policy = self::POLICY_OVERWRITE;
		}
		$runMode = strtolower((string) ($opts['run_mode'] ?? self::MODE_FULL));
		if ($runMode !== self::MODE_ANALYZE_ONLY) {
			$runMode = self::MODE_FULL;
		}
		$missLim = (int) ($opts['tlp_missing_limit'] ?? 200);
		$diffLim = (int) ($opts['tlp_diff_limit'] ?? 5);
		if ($missLim < 0) {
			$missLim = 0;
		}
		if ($missLim > 500) {
			$missLim = 500;
		}
		if ($diffLim < 0) {
			$diffLim = 0;
		}
		if ($diffLim > 200) {
			$diffLim = 200;
		}

		global $DB;
		$now = self::now();
		$batchKey = trim((string) ($opts['batch_key'] ?? ''));
		if ($batchKey === '') {
			$batchKey = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
		}
		$genPreview = !empty($opts['gen_preview']) ? 'Y' : 'N';
		$DB->Query("
			INSERT INTO titlo_relevance_queue
			(ENTITY_TYPE, ENTITY_ID, URL, PHRASE, STATUS, STEP,
			 PROMPT_DETAIL_ID, PROMPT_PREVIEW_ID, GEN_PREVIEW, EXISTING_POLICY,
			 TLP_MISSING_LIMIT, TLP_DIFF_LIMIT, RUN_MODE, BATCH_KEY, CREATED_AT, UPDATED_AT)
			VALUES (
				'" . $DB->ForSql($entityType) . "',
				" . (int) $entityId . ",
				'" . $DB->ForSql($url) . "',
				'" . $DB->ForSql($phrase) . "',
				'" . self::STATUS_QUEUED . "',
				'" . self::STEP_ANALYZE . "',
				" . (int) ($opts['prompt_detail_id'] ?? 0) . ",
				" . (int) ($opts['prompt_preview_id'] ?? 0) . ",
				'" . $genPreview . "',
				'" . $DB->ForSql($policy) . "',
				" . (int) $missLim . ",
				" . (int) $diffLim . ",
				'" . $DB->ForSql($runMode) . "',
				'" . $DB->ForSql($batchKey) . "',
				'" . $now . "',
				'" . $now . "'
			)
		");
		return (int) $DB->LastID();
	}

	/**
	 * @param array<int, array{entity_id:int,url?:string,phrase?:string}> $items
	 * @param array{
	 *   entity_type?:string,prompt_detail_id?:int,prompt_preview_id?:int,gen_preview?:bool,
	 *   existing_policy?:string,tlp_missing_limit?:int,tlp_diff_limit?:int,
	 *   run_mode?:string,batch_key?:string
	 * } $opts
	 * @return array{ok:bool,batch_key:string,queued:int,errors:array<int,string>}
	 */
	public static function enqueueBatch(array $items, array $opts = []): array
	{
		self::ensureSchema();
		$entityType = strtoupper((string) ($opts['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
		$batchKey = trim((string) ($opts['batch_key'] ?? ''));
		if ($batchKey === '') {
			$batchKey = date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);
		}
		$opts['batch_key'] = $batchKey;
		if ($entityType === 'S') {
			// у категорий нет анонса
			$opts['gen_preview'] = false;
		}
		$queued = 0;
		$errors = [];
		foreach ($items as $item) {
			$id = (int) ($item['entity_id'] ?? 0);
			try {
				$entity = $entityType === 'S'
					? CatalogRepository::findSection($id)
					: CatalogRepository::findElement($id);
				if (!$entity) {
					throw new \InvalidArgumentException('not found');
				}
				$url = trim((string) ($item['url'] ?? ($entity['url'] ?? '')));
				$phrase = trim((string) ($item['phrase'] ?? ($entity['titlo_phrase'] ?? $entity['phrase'] ?? '')));
				if ($phrase === '') {
					$phrase = $entityType === 'S'
						? CatalogRepository::getSectionPhrase($id)
						: CatalogRepository::getElementPhrase($id);
				}
				if ($url === '') {
					throw new \InvalidArgumentException('url empty');
				}
				if ($phrase === '') {
					throw new \InvalidArgumentException('no phrase');
				}
				self::enqueue($entityType, $id, $url, $phrase, $opts);
				$runMode = strtolower((string) ($opts['run_mode'] ?? self::MODE_FULL));
				if ($runMode !== self::MODE_ANALYZE_ONLY) {
					if ($entityType === 'S') {
						CatalogRepository::clearSectionAutoAt($id);
					} else {
						CatalogRepository::clearElementAutoAt($id);
					}
				}
				$queued++;
			} catch (\Throwable $e) {
				$errors[$id] = $e->getMessage();
			}
		}
		return [
			'ok' => $queued > 0,
			'batch_key' => $batchKey,
			'queued' => $queued,
			'errors' => $errors,
		];
	}

	/**
	 * Вернуть выбранные задания в очередь (с шага analyze).
	 * Режим и политика берутся из $opts (панель запуска).
	 *
	 * @param int[] $ids
	 * @param array{
	 *   run_mode?:string,existing_policy?:string,
	 *   prompt_detail_id?:int,prompt_preview_id?:int,gen_preview?:bool,
	 *   tlp_missing_limit?:int,tlp_diff_limit?:int
	 * } $opts
	 * @return array{ok:bool,requeued:int,ids:int[],error?:string}
	 */
	public static function requeueByIds(array $ids, array $opts = []): array
	{
		self::ensureSchema();
		global $DB;
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return ['ok' => false, 'requeued' => 0, 'ids' => [], 'error' => 'nothing_selected'];
		}

		$now = self::now();
		$sets = [
			"STATUS = '" . self::STATUS_QUEUED . "'",
			"STEP = '" . self::STEP_ANALYZE . "'",
			'ANALYSIS_ID = NULL',
			'HISTORY_ID = NULL',
			'GEN_RECORD_ID = NULL',
			'GEN_PREVIEW_RECORD_ID = NULL',
			'WORK_HISTORY_ID = NULL',
			'DETAIL_TEXT = NULL',
			'PREVIEW_TEXT = NULL',
			'ERROR = NULL',
			"UPDATED_AT = '" . $now . "'",
		];

		$runMode = isset($opts['run_mode']) ? strtolower((string) $opts['run_mode']) : '';
		if ($runMode !== self::MODE_ANALYZE_ONLY) {
			$runMode = self::MODE_FULL;
		}
		$sets[] = "RUN_MODE = '" . $DB->ForSql($runMode) . "'";

		$policy = isset($opts['existing_policy']) ? strtolower((string) $opts['existing_policy']) : '';
		if ($policy !== self::POLICY_SKIP) {
			$policy = self::POLICY_OVERWRITE;
		}
		$sets[] = "EXISTING_POLICY = '" . $DB->ForSql($policy) . "'";

		if (array_key_exists('prompt_detail_id', $opts)) {
			$sets[] = 'PROMPT_DETAIL_ID = ' . (int) $opts['prompt_detail_id'];
		}
		if (array_key_exists('prompt_preview_id', $opts)) {
			$sets[] = 'PROMPT_PREVIEW_ID = ' . (int) $opts['prompt_preview_id'];
		}
		if (array_key_exists('gen_preview', $opts)) {
			$sets[] = "GEN_PREVIEW = '" . (!empty($opts['gen_preview']) ? 'Y' : 'N') . "'";
		}
		if (array_key_exists('tlp_missing_limit', $opts)) {
			$miss = max(0, min(500, (int) $opts['tlp_missing_limit']));
			$sets[] = 'TLP_MISSING_LIMIT = ' . $miss;
		}
		if (array_key_exists('tlp_diff_limit', $opts)) {
			$diff = max(0, min(200, (int) $opts['tlp_diff_limit']));
			$sets[] = 'TLP_DIFF_LIMIT = ' . $diff;
		}

		$in = implode(',', $ids);
		// Не трогаем уже выполняющиеся — только завершённые/ошибочные/ожидающие.
		$allowed = "'" . self::STATUS_FAILED . "','" . self::STATUS_SKIPPED . "','"
			. self::STATUS_DONE . "','" . self::STATUS_QUEUED . "'";
		$typeFilter = '';
		$wantType = strtoupper(trim((string) ($opts['entity_type'] ?? '')));
		if ($wantType === 'S' || $wantType === 'E') {
			$typeFilter = " AND ENTITY_TYPE = '" . $DB->ForSql($wantType) . "'";
		}
		$entityIdsE = [];
		$entityIdsS = [];
		$res = $DB->Query("
			SELECT ID, ENTITY_TYPE, ENTITY_ID FROM titlo_relevance_queue
			WHERE ID IN ({$in}) AND STATUS IN ({$allowed})" . $typeFilter . '
		');
		$okIds = [];
		while ($row = $res->Fetch()) {
			$okIds[] = (int) $row['ID'];
			$etype = strtoupper((string) ($row['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
			$eid = (int) $row['ENTITY_ID'];
			if ($etype === 'S') {
				$entityIdsS[] = $eid;
			} else {
				$entityIdsE[] = $eid;
			}
		}
		if ($okIds === []) {
			return ['ok' => false, 'requeued' => 0, 'ids' => [], 'error' => 'nothing_to_requeue'];
		}
		$inOk = implode(',', $okIds);
		$DB->Query('UPDATE titlo_relevance_queue SET ' . implode(', ', $sets) . ' WHERE ID IN (' . $inOk . ')');

		if ($runMode !== self::MODE_ANALYZE_ONLY) {
			foreach (array_unique($entityIdsE) as $eid) {
				if ($eid > 0) {
					CatalogRepository::clearElementAutoAt($eid);
				}
			}
			foreach (array_unique($entityIdsS) as $eid) {
				if ($eid > 0) {
					CatalogRepository::clearSectionAutoAt($eid);
				}
			}
		}

		return ['ok' => true, 'requeued' => count($okIds), 'ids' => $okIds];
	}

	/**
	 * Вернуть failed (и опционально skipped) в queued с шага analyze.
	 *
	 * @param array{include_skipped?:bool,run_mode?:string,existing_policy?:string} $opts
	 * @return array{ok:bool,requeued:int,ids:int[]}
	 */
	public static function requeueFailed(array $opts = []): array
	{
		self::ensureSchema();
		global $DB;
		$includeSkipped = !empty($opts['include_skipped']);
		$statuses = "'" . self::STATUS_FAILED . "'";
		if ($includeSkipped) {
			$statuses .= ",'" . self::STATUS_SKIPPED . "'";
		}
		$ids = [];
		$res = $DB->Query("SELECT ID FROM titlo_relevance_queue WHERE STATUS IN ({$statuses}) ORDER BY ID ASC");
		while ($row = $res->Fetch()) {
			$ids[] = (int) $row['ID'];
		}
		if ($ids === []) {
			return ['ok' => true, 'requeued' => 0, 'ids' => []];
		}
		return self::requeueByIds($ids, $opts);
	}

	/** Сколько заданий одного типа (товар/категория) крутить параллельно. */
	public const CONCURRENCY = 2;

	/**
	 * Следующая задача: queued или running (продолжение шага).
	 *
	 * @return array|null
	 */
	public static function nextWorkable(string $entityType = '')
	{
		$rows = self::listWorkable(1, $entityType);
		return $rows[0] ?? null;
	}

	/**
	 * До $limit заданий одного типа (или всех, если $entityType пуст):
	 * сначала running, затем добор из queued.
	 *
	 * @return array<int, array>
	 */
	public static function listWorkable(int $limit = 2, string $entityType = ''): array
	{
		self::ensureSchema();
		if ($limit < 1) {
			$limit = 1;
		}
		if ($limit > 10) {
			$limit = 10;
		}
		$entityType = strtoupper(trim($entityType));
		$typeSql = '';
		if ($entityType === 'S' || $entityType === 'E') {
			global $DB;
			$typeSql = " AND ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'";
		}
		global $DB;
		$out = [];
		$seen = [];

		$runningStatus = self::STATUS_RUNNING;
		$res = $DB->Query(
			"SELECT * FROM titlo_relevance_queue
			WHERE STATUS = '" . $DB->ForSql($runningStatus) . "'" . $typeSql . '
			ORDER BY ID ASC
			LIMIT ' . (int) $limit
		);
		while ($row = $res->Fetch()) {
			$id = (int) ($row['ID'] ?? 0);
			if ($id <= 0 || isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$out[] = $row;
		}

		$need = $limit - count($out);
		if ($need > 0) {
			$queuedStatus = self::STATUS_QUEUED;
			$res = $DB->Query(
				"SELECT * FROM titlo_relevance_queue
				WHERE STATUS = '" . $DB->ForSql($queuedStatus) . "'" . $typeSql . '
				ORDER BY ID ASC
				LIMIT ' . (int) $need
			);
			while ($row = $res->Fetch()) {
				$id = (int) ($row['ID'] ?? 0);
				if ($id <= 0 || isset($seen[$id])) {
					continue;
				}
				$seen[$id] = true;
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * @deprecated use nextWorkable
	 * @return array|null
	 */
	public static function nextQueued()
	{
		self::ensureSchema();
		global $DB;
		$res = $DB->Query("
			SELECT * FROM titlo_relevance_queue
			WHERE STATUS = '" . self::STATUS_QUEUED . "'
			ORDER BY ID ASC
			LIMIT 1
		");
		$row = $res->Fetch();
		return $row ?: null;
	}

	public static function mark(int $id, string $status, array $extra = []): void
	{
		self::ensureSchema();
		global $DB;
		$sets = [
			"STATUS = '" . $DB->ForSql($status) . "'",
			"UPDATED_AT = '" . self::now() . "'",
		];
		$map = [
			'STEP' => 'STEP',
			'HISTORY_ID' => 'HISTORY_ID',
			'ANALYSIS_ID' => 'ANALYSIS_ID',
			'GEN_RECORD_ID' => 'GEN_RECORD_ID',
			'GEN_PREVIEW_RECORD_ID' => 'GEN_PREVIEW_RECORD_ID',
			'GEN_ATTEMPTS' => 'GEN_ATTEMPTS',
			'DETAIL_TEXT' => 'DETAIL_TEXT',
			'PREVIEW_TEXT' => 'PREVIEW_TEXT',
			'WORK_HISTORY_ID' => 'WORK_HISTORY_ID',
			'ERROR' => 'ERROR',
		];
		foreach ($map as $key => $col) {
			if (!array_key_exists($key, $extra)) {
				continue;
			}
			$val = $extra[$key];
			if ($val === null) {
				$sets[] = $col . ' = NULL';
			} elseif (in_array($col, ['HISTORY_ID', 'GEN_RECORD_ID', 'GEN_PREVIEW_RECORD_ID', 'WORK_HISTORY_ID', 'GEN_ATTEMPTS'], true)) {
				$sets[] = $col . ' = ' . (int) $val;
			} else {
				$sets[] = $col . " = '" . $DB->ForSql((string) $val) . "'";
			}
		}
		$DB->Query('UPDATE titlo_relevance_queue SET ' . implode(', ', $sets) . ' WHERE ID = ' . (int) $id);
	}

	/**
	 * @return array{items:array,active:int,counts:array}
	 */
	public static function listRecent(int $limit = 40, string $batchKey = '', string $entityType = ''): array
	{
		self::ensureSchema();
		global $DB;
		$limit = max(1, min(100, $limit));
		$where = [];
		if ($batchKey !== '') {
			$where[] = "BATCH_KEY = '" . $DB->ForSql($batchKey) . "'";
		}
		$entityType = strtoupper(trim($entityType));
		if ($entityType === 'S' || $entityType === 'E') {
			$where[] = "ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'";
		}
		$whereSql = $where === [] ? '1=1' : implode(' AND ', $where);
		$res = $DB->Query("
			SELECT * FROM titlo_relevance_queue
			WHERE {$whereSql}
			ORDER BY ID DESC
			LIMIT " . (int) $limit . '
		');
		$items = [];
		while ($row = $res->Fetch()) {
			$items[] = self::serializeRow($row);
		}

		$counts = [
			'queued' => 0,
			'running' => 0,
			'done' => 0,
			'failed' => 0,
			'skipped' => 0,
		];
		$countWhere = ($entityType === 'S' || $entityType === 'E')
			? "WHERE ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'"
			: '';
		$cres = $DB->Query('
			SELECT STATUS, COUNT(*) AS C FROM titlo_relevance_queue
			' . $countWhere . '
			GROUP BY STATUS
		');
		while ($c = $cres->Fetch()) {
			$st = (string) ($c['STATUS'] ?? '');
			if (isset($counts[$st])) {
				$counts[$st] = (int) ($c['C'] ?? 0);
			}
		}
		$active = $counts['queued'] + $counts['running'];

		return [
			'items' => $items,
			'active' => $active,
			'counts' => $counts,
		];
	}

	/**
	 * Активные job по entity_id.
	 *
	 * @param int[] $entityIds
	 * @return array<int, array>
	 */
	public static function activeByEntityIds(array $entityIds, string $entityType = 'E'): array
	{
		self::ensureSchema();
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
		if ($entityIds === []) {
			return [];
		}
		global $DB;
		$res = $DB->Query("
			SELECT * FROM titlo_relevance_queue
			WHERE ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'
			  AND ENTITY_ID IN (" . implode(',', $entityIds) . ")
			  AND STATUS IN ('" . self::STATUS_QUEUED . "','" . self::STATUS_RUNNING . "')
			ORDER BY ID DESC
		");
		$out = [];
		while ($row = $res->Fetch()) {
			$eid = (int) $row['ENTITY_ID'];
			if (!isset($out[$eid])) {
				$out[$eid] = self::serializeRow($row);
			}
		}
		return $out;
	}

	/**
	 * @param array $row
	 * @return array
	 */
	public static function serializeRow(array $row): array
	{
		$entityId = (int) ($row['ENTITY_ID'] ?? 0);
		$entityType = strtoupper((string) ($row['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
		$status = (string) ($row['STATUS'] ?? '');
		$step = (string) ($row['STEP'] ?? '');
		$runMode = (string) ($row['RUN_MODE'] ?? self::MODE_FULL);
		$policy = (string) ($row['EXISTING_POLICY'] ?? self::POLICY_OVERWRITE);
		return [
			'id' => (int) ($row['ID'] ?? 0),
			'entity_type' => $entityType,
			'entity_id' => $entityId,
			'url' => (string) ($row['URL'] ?? ''),
			'phrase' => (string) ($row['PHRASE'] ?? ''),
			'status' => $status,
			'step' => $step,
			'status_label' => self::statusLabel($status),
			'step_label' => self::stepLabel($step),
			'result_label' => self::resultLabel($status, $step, $runMode, $policy),
			'run_mode' => $runMode,
			'run_mode_label' => $runMode === self::MODE_ANALYZE_ONLY ? 'только анализ' : 'полная',
			'history_id' => isset($row['HISTORY_ID']) && $row['HISTORY_ID'] !== null ? (int) $row['HISTORY_ID'] : null,
			'analysis_id' => $row['ANALYSIS_ID'] !== null && $row['ANALYSIS_ID'] !== '' ? (string) $row['ANALYSIS_ID'] : null,
			'gen_record_id' => isset($row['GEN_RECORD_ID']) && $row['GEN_RECORD_ID'] !== null ? (int) $row['GEN_RECORD_ID'] : null,
			'work_history_id' => isset($row['WORK_HISTORY_ID']) && $row['WORK_HISTORY_ID'] !== null ? (int) $row['WORK_HISTORY_ID'] : null,
			'batch_key' => (string) ($row['BATCH_KEY'] ?? ''),
			'existing_policy' => $policy,
			'gen_preview' => (($row['GEN_PREVIEW'] ?? 'N') === 'Y'),
			'error' => $row['ERROR'] !== null && $row['ERROR'] !== '' ? (string) $row['ERROR'] : null,
			'created_at' => (string) ($row['CREATED_AT'] ?? ''),
			'updated_at' => (string) ($row['UPDATED_AT'] ?? ''),
			'single_url' => 'titlo_relevance_single.php?lang=' . LANGUAGE_ID . '&ENTITY=' . $entityType . '&ID=' . $entityId,
		];
	}

	public static function statusLabel(string $status): string
	{
		$map = [
			self::STATUS_QUEUED => 'в очереди',
			self::STATUS_RUNNING => 'выполняется',
			self::STATUS_DONE => 'готово',
			self::STATUS_FAILED => 'ошибка',
			self::STATUS_SKIPPED => 'текст не писали',
		];
		return $map[$status] ?? $status;
	}

	public static function stepLabel(string $step): string
	{
		$map = [
			self::STEP_ANALYZE => 'запуск анализа',
			self::STEP_WAIT_ANALYSIS => 'ожидание анализа',
			self::STEP_GENERATE => 'генерация описания',
			self::STEP_WAIT_GENERATE => 'ожидание описания',
			self::STEP_GENERATE_PREVIEW => 'генерация анонса',
			self::STEP_WAIT_GENERATE_PREVIEW => 'ожидание анонса',
			self::STEP_SAVE => 'сохранение',
			self::STEP_RECHECK => 'повторный анализ',
			self::STEP_WAIT_RECHECK => 'ожидание повторного анализа',
			self::STEP_DONE => 'готово',
		];
		return $map[$step] ?? $step;
	}

	/**
	 * Итог задания.
	 */
	public static function resultLabel(string $status, string $step, string $runMode, string $policy): string
	{
		if ($status === self::STATUS_FAILED) {
			$stepRu = self::stepLabel($step !== '' ? $step : self::STEP_ANALYZE);
			return 'Ошибка: ' . $stepRu;
		}
		if ($status === self::STATUS_QUEUED || $status === self::STATUS_RUNNING) {
			return self::stepLabel($step !== '' ? $step : self::STEP_ANALYZE);
		}
		if ($runMode === self::MODE_ANALYZE_ONLY && ($status === self::STATUS_DONE || $status === self::STATUS_SKIPPED)) {
			return 'Только анализ (текст не писали)';
		}
		if ($status === self::STATUS_SKIPPED && $policy === self::POLICY_SKIP) {
			return 'Текст не писали: описание уже было';
		}
		if ($status === self::STATUS_DONE) {
			return 'Готово: текст сохранён';
		}
		return self::statusLabel($status);
	}
}
