<?php

namespace Titlo\Relevance;

/**
 * История проработки товаров/категорий: снимок баллов до и после.
 */
class WorkHistory
{
	public const STATUS_OPEN = 'open';
	public const STATUS_SAVED = 'saved';
	public const STATUS_DONE = 'done';

	public const SCHEMA_VER = '1.2.1';

	public static function ensureTables(): void
	{
		global $DB;
		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_work_history (
				ID int(11) NOT NULL AUTO_INCREMENT,
				ENTITY_TYPE char(1) NOT NULL DEFAULT 'E',
				ENTITY_ID int(11) NOT NULL,
				NAME varchar(512) NOT NULL DEFAULT '',
				URL varchar(1024) NOT NULL DEFAULT '',
				PHRASE varchar(255) NOT NULL DEFAULT '',
				STATUS varchar(16) NOT NULL DEFAULT 'open',
				RUN_MODE varchar(16) NOT NULL DEFAULT 'full',
				PROMPT_DETAIL_ID int(11) NOT NULL DEFAULT 0,
				PROMPT_DETAIL_NAME varchar(255) NOT NULL DEFAULT '',
				PROMPT_PREVIEW_ID int(11) NOT NULL DEFAULT 0,
				PROMPT_PREVIEW_NAME varchar(255) NOT NULL DEFAULT '',
				BEFORE_HISTORY_ID int(11) DEFAULT NULL,
				BEFORE_POINTS double DEFAULT NULL,
				BEFORE_POINTS_IDEAL double DEFAULT NULL,
				BEFORE_COVERAGE double DEFAULT NULL,
				BEFORE_DENSITY double DEFAULT NULL,
				BEFORE_POSITION int(11) DEFAULT NULL,
				BEFORE_ENGINE varchar(16) DEFAULT NULL,
				BEFORE_REGION varchar(64) DEFAULT NULL,
				BEFORE_TOP int(11) DEFAULT NULL,
				BEFORE_AT datetime DEFAULT NULL,
				AFTER_HISTORY_ID int(11) DEFAULT NULL,
				AFTER_POINTS double DEFAULT NULL,
				AFTER_POINTS_IDEAL double DEFAULT NULL,
				AFTER_COVERAGE double DEFAULT NULL,
				AFTER_DENSITY double DEFAULT NULL,
				AFTER_POSITION int(11) DEFAULT NULL,
				AFTER_ENGINE varchar(16) DEFAULT NULL,
				AFTER_REGION varchar(64) DEFAULT NULL,
				AFTER_TOP int(11) DEFAULT NULL,
				AFTER_AT datetime DEFAULT NULL,
				PREVIEW_CHARS_BEFORE int(11) DEFAULT NULL,
				DETAIL_CHARS_BEFORE int(11) DEFAULT NULL,
				PREVIEW_CHARS_AFTER int(11) DEFAULT NULL,
				DETAIL_CHARS_AFTER int(11) DEFAULT NULL,
				SAVED_AT datetime DEFAULT NULL,
				USER_ID int(11) NOT NULL DEFAULT 0,
				CREATED_AT datetime NOT NULL,
				UPDATED_AT datetime NOT NULL,
				PRIMARY KEY (ID),
				KEY ix_entity (ENTITY_TYPE, ENTITY_ID, ID),
				KEY ix_status (STATUS, UPDATED_AT),
				KEY ix_updated (UPDATED_AT),
				KEY ix_phrase (PHRASE(64))
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");
		self::ensureRunModeColumn();
		self::ensurePromptColumns();
		self::backfillPromptsFromQueueOnce();
	}

	/**
	 * Старые циклы создавались до колонок промпта — подтянуть из очереди по WORK_HISTORY_ID / history_id.
	 */
	protected static function backfillPromptsFromQueueOnce(): void
	{
		if (\Bitrix\Main\Config\Option::get('titlo.relevance', 'work_history_prompt_backfill_v2', '') === '1') {
			return;
		}
		try {
			self::backfillPromptsFromQueue();
			\Bitrix\Main\Config\Option::set('titlo.relevance', 'work_history_prompt_backfill_v2', '1');
		} catch (\Throwable $e) {
			// очередь/промпты могут ещё не существовать — флаг не ставим
		}
	}

	/**
	 * @return int сколько строк обновлено
	 */
	public static function backfillPromptsFromQueue(): int
	{
		global $DB;
		$updated = 0;
		$seen = [];
		// ENTITY_ID + UPPER(ENTITY_TYPE): без collation-сравнения CHAR vs VARCHAR
		$sql = "
			SELECT wh.ID AS WH_ID, q.ID AS Q_ID, q.PROMPT_DETAIL_ID, q.PROMPT_PREVIEW_ID, q.RUN_MODE
			FROM titlo_work_history wh
			INNER JOIN titlo_relevance_queue q
				ON (
					q.WORK_HISTORY_ID = wh.ID
					OR (
						q.HISTORY_ID IS NOT NULL
						AND q.HISTORY_ID > 0
						AND q.ENTITY_ID = wh.ENTITY_ID
						AND UPPER(q.ENTITY_TYPE) = UPPER(wh.ENTITY_TYPE)
						AND (
							q.HISTORY_ID = wh.BEFORE_HISTORY_ID
							OR q.HISTORY_ID = wh.AFTER_HISTORY_ID
						)
					)
				)
			WHERE wh.PROMPT_DETAIL_ID = 0
				AND q.PROMPT_DETAIL_ID > 0
			ORDER BY q.ID DESC
		";
		$res = $DB->Query($sql);
		if (!$res) {
			throw new \RuntimeException('work_history prompt backfill query failed');
		}
		while ($row = $res->Fetch()) {
			$whId = (int) ($row['WH_ID'] ?? 0);
			if ($whId <= 0 || isset($seen[$whId])) {
				continue;
			}
			$seen[$whId] = true;
			$detailId = (int) ($row['PROMPT_DETAIL_ID'] ?? 0);
			$previewId = (int) ($row['PROMPT_PREVIEW_ID'] ?? 0);
			$fields = self::promptFieldsFromPayload([
				'prompt_detail_id' => $detailId,
				'prompt_preview_id' => $previewId,
			]);
			$runMode = trim((string) ($row['RUN_MODE'] ?? ''));
			if ($runMode !== '') {
				$fields['RUN_MODE'] = BatchQueue::normalizeRunMode($runMode);
			}
			if ($fields === []) {
				continue;
			}
			self::updateRow($whId, $fields);
			$updated++;
		}

		return $updated;
	}

	protected static function ensureRunModeColumn(): void
	{
		global $DB;
		$cols = self::existingColumns();
		if (!isset($cols['RUN_MODE'])) {
			$DB->Query("ALTER TABLE titlo_work_history ADD COLUMN RUN_MODE varchar(16) NOT NULL DEFAULT 'full' AFTER STATUS");
		}
	}

	protected static function ensurePromptColumns(): void
	{
		global $DB;
		$cols = self::existingColumns();
		$alters = [
			'PROMPT_DETAIL_ID' => "ALTER TABLE titlo_work_history ADD COLUMN PROMPT_DETAIL_ID int(11) NOT NULL DEFAULT 0 AFTER RUN_MODE",
			'PROMPT_DETAIL_NAME' => "ALTER TABLE titlo_work_history ADD COLUMN PROMPT_DETAIL_NAME varchar(255) NOT NULL DEFAULT '' AFTER PROMPT_DETAIL_ID",
			'PROMPT_PREVIEW_ID' => "ALTER TABLE titlo_work_history ADD COLUMN PROMPT_PREVIEW_ID int(11) NOT NULL DEFAULT 0 AFTER PROMPT_DETAIL_NAME",
			'PROMPT_PREVIEW_NAME' => "ALTER TABLE titlo_work_history ADD COLUMN PROMPT_PREVIEW_NAME varchar(255) NOT NULL DEFAULT '' AFTER PROMPT_PREVIEW_ID",
		];
		foreach ($alters as $col => $sql) {
			if (!isset($cols[$col])) {
				$DB->Query($sql);
			}
		}
	}

	/**
	 * @return array<string,bool>
	 */
	protected static function existingColumns(): array
	{
		global $DB;
		$cols = [];
		$res = $DB->Query('SHOW COLUMNS FROM titlo_work_history');
		while ($row = $res->Fetch()) {
			$field = (string) ($row['Field'] ?? $row['FIELD'] ?? '');
			if ($field !== '') {
				$cols[$field] = true;
			}
		}

		return $cols;
	}

	/**
	 * Зафиксировать «до» (первый анализ в цикле) или «после» (после save).
	 *
	 * @param array{
	 *   entity_type?:string,entity_id:int,name?:string,url?:string,phrase?:string,
	 *   history_id?:int,points?:mixed,points_ideal?:mixed,coverage?:mixed,density?:mixed,
	 *   position?:mixed,engine?:string,region?:string,top?:mixed,checked_at?:string,
	 *   role?:string,run_mode?:string
	 * } $payload
	 * @return array{ok:bool,row?:array,error?:string}
	 */
	public static function recordScore(array $payload): array
	{
		self::ensureTables();
		$entityType = strtoupper((string) ($payload['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
		$entityId = (int) ($payload['entity_id'] ?? 0);
		if ($entityId <= 0) {
			return ['ok' => false, 'error' => 'entity_id required'];
		}
		$belongs = $entityType === 'S'
			? Config::sectionBelongsToCatalog($entityId)
			: Config::elementBelongsToCatalog($entityId);
		if (!$belongs) {
			return ['ok' => false, 'error' => 'entity not in catalog'];
		}

		$role = strtolower((string) ($payload['role'] ?? 'auto'));
		$historyId = (int) ($payload['history_id'] ?? 0);
		if ($historyId <= 0) {
			return ['ok' => false, 'error' => 'history_id required'];
		}
		$runMode = BatchQueue::normalizeRunMode((string) ($payload['run_mode'] ?? BatchQueue::MODE_FULL));
		$promptFields = self::promptFieldsFromPayload($payload);
		$sizeFields = self::sizeFieldsForRole($payload, $entityType, $entityId, $role);

		$open = self::latestOpenCycle($entityType, $entityId);
		$now = date('Y-m-d H:i:s');

		// auto: после save новый history → after; иначе обновляем/ставим before
		if ($role === 'auto') {
			if ($open && (int) ($open['BEFORE_HISTORY_ID'] ?? 0) > 0) {
				if ((int) ($open['BEFORE_HISTORY_ID'] ?? 0) === $historyId) {
					$role = 'before';
				} elseif ((string) ($open['STATUS'] ?? '') === self::STATUS_SAVED) {
					$role = 'after';
				} else {
					// новый анализ до сохранения текстов — новый baseline
					$role = 'before';
				}
			} else {
				$role = 'before';
			}
			// роль могла смениться — пересчитать размер под итоговую роль
			$sizeFields = self::sizeFieldsForRole($payload, $entityType, $entityId, $role);
		}

		if ($role === 'after') {
			if (!$open || (int) ($open['BEFORE_HISTORY_ID'] ?? 0) <= 0) {
				$role = 'before';
			} elseif ((int) ($open['BEFORE_HISTORY_ID'] ?? 0) === $historyId) {
				$role = 'before';
			}
			$sizeFields = self::sizeFieldsForRole($payload, $entityType, $entityId, $role);
		}

		$score = self::normalizeScore($payload);
		global $USER;
		$userId = (is_object($USER) && method_exists($USER, 'GetID')) ? (int) $USER->GetID() : 0;
		$name = trim((string) ($payload['name'] ?? ($open['NAME'] ?? '')));
		$url = trim((string) ($payload['url'] ?? ($open['URL'] ?? '')));
		$phrase = trim((string) ($payload['phrase'] ?? ($open['PHRASE'] ?? '')));

		if ($role === 'before') {
			if ($open && in_array((string) $open['STATUS'], [self::STATUS_OPEN, self::STATUS_SAVED], true)) {
				self::updateRow((int) $open['ID'], array_merge([
					'NAME' => $name !== '' ? $name : (string) $open['NAME'],
					'URL' => $url !== '' ? $url : (string) $open['URL'],
					'PHRASE' => $phrase !== '' ? $phrase : (string) $open['PHRASE'],
					'RUN_MODE' => $runMode,
					'BEFORE_HISTORY_ID' => $score['history_id'],
					'BEFORE_POINTS' => $score['points'],
					'BEFORE_POINTS_IDEAL' => $score['points_ideal'],
					'BEFORE_COVERAGE' => $score['coverage'],
					'BEFORE_DENSITY' => $score['density'],
					'BEFORE_POSITION' => $score['position'],
					'BEFORE_ENGINE' => $score['engine'],
					'BEFORE_REGION' => $score['region'],
					'BEFORE_TOP' => $score['top'],
					'BEFORE_AT' => $score['checked_at'] ?: $now,
					'UPDATED_AT' => $now,
				], $promptFields, $sizeFields));
				$row = self::find((int) $open['ID']);
			} else {
				$id = self::insertRow(array_merge([
					'ENTITY_TYPE' => $entityType,
					'ENTITY_ID' => $entityId,
					'NAME' => $name,
					'URL' => $url,
					'PHRASE' => $phrase,
					'STATUS' => self::STATUS_OPEN,
					'RUN_MODE' => $runMode,
					'BEFORE_HISTORY_ID' => $score['history_id'],
					'BEFORE_POINTS' => $score['points'],
					'BEFORE_POINTS_IDEAL' => $score['points_ideal'],
					'BEFORE_COVERAGE' => $score['coverage'],
					'BEFORE_DENSITY' => $score['density'],
					'BEFORE_POSITION' => $score['position'],
					'BEFORE_ENGINE' => $score['engine'],
					'BEFORE_REGION' => $score['region'],
					'BEFORE_TOP' => $score['top'],
					'BEFORE_AT' => $score['checked_at'] ?: $now,
					'USER_ID' => $userId,
					'CREATED_AT' => $now,
					'UPDATED_AT' => $now,
				], $promptFields, $sizeFields));
				$row = self::find($id);
			}
			return ['ok' => true, 'row' => self::serialize($row), 'role' => 'before'];
		}

		// after
		self::updateRow((int) $open['ID'], array_merge([
			'NAME' => $name !== '' ? $name : (string) $open['NAME'],
			'URL' => $url !== '' ? $url : (string) $open['URL'],
			'PHRASE' => $phrase !== '' ? $phrase : (string) $open['PHRASE'],
			'RUN_MODE' => $runMode,
			'AFTER_HISTORY_ID' => $score['history_id'],
			'AFTER_POINTS' => $score['points'],
			'AFTER_POINTS_IDEAL' => $score['points_ideal'],
			'AFTER_COVERAGE' => $score['coverage'],
			'AFTER_DENSITY' => $score['density'],
			'AFTER_POSITION' => $score['position'],
			'AFTER_ENGINE' => $score['engine'],
			'AFTER_REGION' => $score['region'],
			'AFTER_TOP' => $score['top'],
			'AFTER_AT' => $score['checked_at'] ?: $now,
			'STATUS' => self::STATUS_DONE,
			'UPDATED_AT' => $now,
		], $promptFields, $sizeFields));
		$row = self::find((int) $open['ID']);
		AuditLog::write('work_history_done', [
			'id' => (int) $open['ID'],
			'entity' => $entityType . $entityId,
			'before' => $row['BEFORE_POINTS'] ?? null,
			'after' => $row['AFTER_POINTS'] ?? null,
		]);
		return ['ok' => true, 'row' => self::serialize($row), 'role' => 'after'];
	}

	/**
	 * После сохранения текстов в Bitrix.
	 *
	 * @param array{
	 *   entity_type?:string,entity_id:int,name?:string,url?:string,phrase?:string,
	 *   preview_chars?:int,detail_chars?:int,
	 *   prompt_detail_id?:int,prompt_preview_id?:int,run_mode?:string
	 * } $payload
	 */
	public static function markSaved(array $payload): array
	{
		self::ensureTables();
		$entityType = strtoupper((string) ($payload['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
		$entityId = (int) ($payload['entity_id'] ?? 0);
		if ($entityId <= 0) {
			return ['ok' => false, 'error' => 'entity_id required'];
		}

		$open = self::latestOpenCycle($entityType, $entityId);
		$now = date('Y-m-d H:i:s');
		$previewAfter = isset($payload['preview_chars']) ? (int) $payload['preview_chars'] : null;
		$detailAfter = isset($payload['detail_chars']) ? (int) $payload['detail_chars'] : null;
		$name = trim((string) ($payload['name'] ?? ''));
		$url = trim((string) ($payload['url'] ?? ''));
		$phrase = trim((string) ($payload['phrase'] ?? ''));
		$promptFields = self::promptFieldsFromPayload($payload);

		if (!$open) {
			global $USER;
			$userId = (is_object($USER) && method_exists($USER, 'GetID')) ? (int) $USER->GetID() : 0;
			$id = self::insertRow(array_merge([
				'ENTITY_TYPE' => $entityType,
				'ENTITY_ID' => $entityId,
				'NAME' => $name,
				'URL' => $url,
				'PHRASE' => $phrase,
				'STATUS' => self::STATUS_SAVED,
				'PREVIEW_CHARS_AFTER' => $previewAfter,
				'DETAIL_CHARS_AFTER' => $detailAfter,
				'SAVED_AT' => $now,
				'USER_ID' => $userId,
				'CREATED_AT' => $now,
				'UPDATED_AT' => $now,
			], $promptFields));
			return ['ok' => true, 'row' => self::serialize(self::find($id))];
		}

		$fields = array_merge([
			'STATUS' => self::STATUS_SAVED,
			'SAVED_AT' => $now,
			'UPDATED_AT' => $now,
			'PREVIEW_CHARS_AFTER' => $previewAfter,
			'DETAIL_CHARS_AFTER' => $detailAfter,
		], $promptFields);
		if ($name !== '') {
			$fields['NAME'] = $name;
		}
		if ($url !== '') {
			$fields['URL'] = $url;
		}
		if ($phrase !== '') {
			$fields['PHRASE'] = $phrase;
		}
		if (isset($payload['run_mode']) && trim((string) $payload['run_mode']) !== '') {
			$fields['RUN_MODE'] = BatchQueue::normalizeRunMode((string) $payload['run_mode']);
		}
		self::updateRow((int) $open['ID'], $fields);
		return ['ok' => true, 'row' => self::serialize(self::find((int) $open['ID']))];
	}

	/**
	 * @param array $payload
	 * @return array{PROMPT_DETAIL_ID?:int,PROMPT_DETAIL_NAME?:string,PROMPT_PREVIEW_ID?:int,PROMPT_PREVIEW_NAME?:string}
	 */
	protected static function promptFieldsFromPayload(array $payload): array
	{
		$out = [];
		$detailId = (int) ($payload['prompt_detail_id'] ?? 0);
		$previewId = (int) ($payload['prompt_preview_id'] ?? 0);
		if ($detailId > 0) {
			$out['PROMPT_DETAIL_ID'] = $detailId;
			$name = trim((string) ($payload['prompt_detail_name'] ?? ''));
			if ($name === '') {
				$name = self::promptNameById($detailId);
			}
			$out['PROMPT_DETAIL_NAME'] = $name;
		}
		if ($previewId > 0) {
			$out['PROMPT_PREVIEW_ID'] = $previewId;
			$name = trim((string) ($payload['prompt_preview_name'] ?? ''));
			if ($name === '') {
				$name = self::promptNameById($previewId);
			}
			$out['PROMPT_PREVIEW_NAME'] = $name;
		}

		return $out;
	}

	/**
	 * Снимок длины текстов «до» / «после» (символы HTML в каталоге).
	 *
	 * @return array<string,int>
	 */
	protected static function sizeFieldsForRole(array $payload, string $entityType, int $entityId, string $role): array
	{
		$preview = array_key_exists('preview_chars', $payload) ? (int) $payload['preview_chars'] : null;
		$detail = array_key_exists('detail_chars', $payload) ? (int) $payload['detail_chars'] : null;
		if ($preview === null || $detail === null) {
			$fromCatalog = self::catalogTextChars($entityType, $entityId);
			if ($preview === null) {
				$preview = $fromCatalog['preview'];
			}
			if ($detail === null) {
				$detail = $fromCatalog['detail'];
			}
		}
		if ($role === 'after') {
			return [
				'PREVIEW_CHARS_AFTER' => max(0, (int) $preview),
				'DETAIL_CHARS_AFTER' => max(0, (int) $detail),
			];
		}

		return [
			'PREVIEW_CHARS_BEFORE' => max(0, (int) $preview),
			'DETAIL_CHARS_BEFORE' => max(0, (int) $detail),
		];
	}

	/**
	 * @return array{preview:int,detail:int}
	 */
	protected static function catalogTextChars(string $entityType, int $entityId): array
	{
		if ($entityId <= 0) {
			return ['preview' => 0, 'detail' => 0];
		}
		try {
			if ($entityType === 'S') {
				$entity = CatalogRepository::findSection($entityId);
				$len = mb_strlen((string) ($entity['description'] ?? ''));

				return ['preview' => $len, 'detail' => 0];
			}
			$entity = CatalogRepository::findElement($entityId);

			return [
				'preview' => mb_strlen((string) ($entity['preview_text'] ?? '')),
				'detail' => mb_strlen((string) ($entity['detail_text'] ?? '')),
			];
		} catch (\Throwable $e) {
			return ['preview' => 0, 'detail' => 0];
		}
	}

	protected static function promptNameById(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		$row = Prompts::find($id);

		return $row ? trim((string) ($row['name'] ?? '')) : '';
	}

	/**
	 * @param array{preset?:string,entity_type?:string,q?:string,page?:int,page_size?:int} $params
	 * @return array{items:array,total:int,page:int,page_size:int,preset:string}
	 */
	public static function list(array $params): array
	{
		self::ensureTables();
		global $DB;

		$preset = (string) ($params['preset'] ?? 'all');
		$entityType = strtoupper((string) ($params['entity_type'] ?? ''));
		$q = trim((string) ($params['q'] ?? ''));
		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = (int) ($params['page_size'] ?? 25);
		if ($pageSize < 10) {
			$pageSize = 10;
		}
		if ($pageSize > 100) {
			$pageSize = 100;
		}
		$offset = ($page - 1) * $pageSize;

		if ($preset === 'never') {
			return self::listNeverStarted($entityType === 'S' ? 'S' : ($entityType === 'E' ? 'E' : ''), $q, $page, $pageSize);
		}

		$where = ['1=1'];
		if ($entityType === 'E' || $entityType === 'S') {
			$where[] = "ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'";
		}

		switch ($preset) {
			case 'open':
				$where[] = "STATUS = '" . self::STATUS_OPEN . "'";
				break;
			case 'saved':
				$where[] = "STATUS = '" . self::STATUS_SAVED . "'";
				break;
			case 'not_done':
				$where[] = "STATUS IN ('" . self::STATUS_OPEN . "','" . self::STATUS_SAVED . "')";
				break;
			case 'done':
				$where[] = "STATUS = '" . self::STATUS_DONE . "'";
				break;
			case 'improved':
				$where[] = "STATUS = '" . self::STATUS_DONE . "' AND AFTER_POINTS IS NOT NULL AND BEFORE_POINTS IS NOT NULL AND AFTER_POINTS > BEFORE_POINTS";
				break;
			case 'worsened':
				$where[] = "STATUS = '" . self::STATUS_DONE . "' AND AFTER_POINTS IS NOT NULL AND BEFORE_POINTS IS NOT NULL AND AFTER_POINTS < BEFORE_POINTS";
				break;
			case 'same':
				$where[] = "STATUS = '" . self::STATUS_DONE . "' AND AFTER_POINTS IS NOT NULL AND BEFORE_POINTS IS NOT NULL AND AFTER_POINTS = BEFORE_POINTS";
				break;
			case 'week':
				$where[] = 'UPDATED_AT >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
				break;
			case 'products':
				$where[] = "ENTITY_TYPE = 'E'";
				break;
			case 'sections':
				$where[] = "ENTITY_TYPE = 'S'";
				break;
			case 'all':
			default:
				break;
		}

		if ($q !== '') {
			$like = "'%" . Config::forLike($q) . "%'";
			$parts = [
				'NAME LIKE ' . $like,
				'PHRASE LIKE ' . $like,
				'URL LIKE ' . $like,
			];
			if (ctype_digit($q)) {
				$parts[] = 'ENTITY_ID = ' . (int) $q;
				$parts[] = 'ID = ' . (int) $q;
				$parts[] = 'BEFORE_HISTORY_ID = ' . (int) $q;
				$parts[] = 'AFTER_HISTORY_ID = ' . (int) $q;
			}
			$where[] = '(' . implode(' OR ', $parts) . ')';
		}

		$whereSql = implode(' AND ', $where);
		$total = (int) ($DB->Query('SELECT COUNT(*) AS C FROM titlo_work_history WHERE ' . $whereSql)->Fetch()['C'] ?? 0);
		$res = $DB->Query('
			SELECT * FROM titlo_work_history
			WHERE ' . $whereSql . '
			ORDER BY UPDATED_AT DESC, ID DESC
			LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		');
		$items = [];
		while ($row = $res->Fetch()) {
			$items[] = self::serialize($row);
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'preset' => $preset,
		];
	}

	/**
	 * Товары/категории каталога, по которым ещё нет ни одной записи проработки.
	 */
	protected static function listNeverStarted(string $entityType, string $q, int $page, int $pageSize): array
	{
		$type = ($entityType === 'S') ? 'S' : 'E';
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize, 'preset' => 'never'];
		}

		global $DB;
		$offset = ($page - 1) * $pageSize;
		$q = trim($q);
		$extra = '';
		if ($q !== '') {
			$like = "'%" . Config::forLike($q) . "%'";
			if ($type === 'S') {
				$extra = ' AND (BS.NAME LIKE ' . $like . ' OR BS.CODE LIKE ' . $like;
				if (ctype_digit($q)) {
					$extra .= ' OR BS.ID = ' . (int) $q;
				}
				$extra .= ')';
			} else {
				$extra = ' AND (BE.NAME LIKE ' . $like . ' OR BE.CODE LIKE ' . $like;
				if (ctype_digit($q)) {
					$extra .= ' OR BE.ID = ' . (int) $q;
				}
				$extra .= ')';
			}
		}

		if ($type === 'S') {
			$total = (int) ($DB->Query('
				SELECT COUNT(*) AS C
				FROM b_iblock_section BS
				LEFT JOIN titlo_work_history W
					ON W.ENTITY_TYPE = \'S\' AND W.ENTITY_ID = BS.ID
				WHERE BS.IBLOCK_ID = ' . (int) $iblockId . '
				  AND BS.ACTIVE = \'Y\'
				  AND W.ID IS NULL
				  ' . $extra . '
			')->Fetch()['C'] ?? 0);
			$res = $DB->Query('
				SELECT BS.ID, BS.NAME, BS.CODE
				FROM b_iblock_section BS
				LEFT JOIN titlo_work_history W
					ON W.ENTITY_TYPE = \'S\' AND W.ENTITY_ID = BS.ID
				WHERE BS.IBLOCK_ID = ' . (int) $iblockId . '
				  AND BS.ACTIVE = \'Y\'
				  AND W.ID IS NULL
				  ' . $extra . '
				ORDER BY BS.ID DESC
				LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) $offset . '
			');
		} else {
			$total = (int) ($DB->Query('
				SELECT COUNT(*) AS C
				FROM b_iblock_element BE
				LEFT JOIN titlo_work_history W
					ON W.ENTITY_TYPE = \'E\' AND W.ENTITY_ID = BE.ID
				WHERE BE.IBLOCK_ID = ' . (int) $iblockId . '
				  AND BE.ACTIVE = \'Y\'
				  AND (BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)
				  AND W.ID IS NULL
				  ' . $extra . '
			')->Fetch()['C'] ?? 0);
			$res = $DB->Query('
				SELECT BE.ID, BE.NAME, BE.CODE
				FROM b_iblock_element BE
				LEFT JOIN titlo_work_history W
					ON W.ENTITY_TYPE = \'E\' AND W.ENTITY_ID = BE.ID
				WHERE BE.IBLOCK_ID = ' . (int) $iblockId . '
				  AND BE.ACTIVE = \'Y\'
				  AND (BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)
				  AND W.ID IS NULL
				  ' . $extra . '
				ORDER BY BE.ID DESC
				LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) $offset . '
			');
		}

		$items = [];
		while ($row = $res->Fetch()) {
			$id = (int) $row['ID'];
			$entity = $type === 'S'
				? CatalogRepository::findSection($id)
				: CatalogRepository::findElement($id);
			$items[] = [
				'id' => 0,
				'entity_type' => $type,
				'entity_id' => $id,
				'name' => (string) ($entity['name'] ?? $row['NAME'] ?? ''),
				'url' => (string) ($entity['url'] ?? ''),
				'phrase' => (string) ($entity['phrase'] ?? ''),
				'status' => 'never',
				'before' => null,
				'after' => null,
				'delta_points' => null,
				'saved_at' => null,
				'updated_at' => null,
				'created_at' => null,
				'single_url' => 'titlo_relevance_single.php?lang=' . LANGUAGE_ID . '&ENTITY=' . $type . '&ID=' . $id,
			];
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'preset' => 'never',
		];
	}

	/**
	 * Последний снимок баллов по сущностям (AFTER если есть, иначе BEFORE).
	 *
	 * @param int[] $entityIds
	 * @return array<int, array{points:?float,points_ideal:?float,history_id:?int,at:string,work_id:int}>
	 */
	public static function mapLatestScores(array $entityIds, string $entityType = 'E'): array
	{
		self::ensureTables();
		$entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
		if ($entityIds === []) {
			return [];
		}
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		global $DB;
		$out = [];
		foreach (array_chunk($entityIds, 400) as $chunk) {
			$in = implode(',', $chunk);
			$res = $DB->Query("
				SELECT wh.*
				FROM titlo_work_history wh
				INNER JOIN (
					SELECT ENTITY_ID, MAX(ID) AS mid
					FROM titlo_work_history
					WHERE ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'
					  AND ENTITY_ID IN (" . $in . ")
					GROUP BY ENTITY_ID
				) last ON last.mid = wh.ID
			");
			while ($row = $res->Fetch()) {
				$eid = (int) $row['ENTITY_ID'];
				$afterHid = $row['AFTER_HISTORY_ID'] !== null && $row['AFTER_HISTORY_ID'] !== ''
					? (int) $row['AFTER_HISTORY_ID'] : 0;
				$useAfter = $afterHid > 0 && $row['AFTER_POINTS'] !== null && $row['AFTER_POINTS'] !== '';
				if ($useAfter) {
					$out[$eid] = [
						'points' => (float) $row['AFTER_POINTS'],
						'points_ideal' => $row['AFTER_POINTS_IDEAL'] !== null && $row['AFTER_POINTS_IDEAL'] !== ''
							? (float) $row['AFTER_POINTS_IDEAL'] : null,
						'history_id' => $afterHid,
						'at' => (string) ($row['AFTER_AT'] ?? ''),
						'work_id' => (int) $row['ID'],
					];
				} else {
					$beforeHid = $row['BEFORE_HISTORY_ID'] !== null && $row['BEFORE_HISTORY_ID'] !== ''
						? (int) $row['BEFORE_HISTORY_ID'] : 0;
					$out[$eid] = [
						'points' => $row['BEFORE_POINTS'] !== null && $row['BEFORE_POINTS'] !== ''
							? (float) $row['BEFORE_POINTS'] : null,
						'points_ideal' => $row['BEFORE_POINTS_IDEAL'] !== null && $row['BEFORE_POINTS_IDEAL'] !== ''
							? (float) $row['BEFORE_POINTS_IDEAL'] : null,
						'history_id' => $beforeHid > 0 ? $beforeHid : null,
						'at' => (string) ($row['BEFORE_AT'] ?? ''),
						'work_id' => (int) $row['ID'],
					];
				}
			}
		}
		return $out;
	}

	/**
	 * @return array|null
	 */
	protected static function latestOpenCycle(string $entityType, int $entityId)
	{
		global $DB;
		$res = $DB->Query("
			SELECT * FROM titlo_work_history
			WHERE ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'
			  AND ENTITY_ID = " . (int) $entityId . "
			  AND STATUS IN ('" . self::STATUS_OPEN . "','" . self::STATUS_SAVED . "')
			ORDER BY ID DESC
			LIMIT 1
		");
		$row = $res->Fetch();
		return $row ?: null;
	}

	/**
	 * @return array|null
	 */
	public static function find(int $id)
	{
		global $DB;
		$row = $DB->Query('SELECT * FROM titlo_work_history WHERE ID = ' . (int) $id)->Fetch();
		return $row ?: null;
	}

	/**
	 * Закрыть цикл «только анализ», чтобы он не висел в open и не перехватывался refine.
	 */
	public static function closeAnalyzeOnlyCycle(int $id): void
	{
		$id = (int) $id;
		if ($id <= 0) {
			return;
		}
		$row = self::find($id);
		if (!$row) {
			return;
		}
		$status = (string) ($row['STATUS'] ?? '');
		if ($status === self::STATUS_DONE) {
			return;
		}
		self::updateRow($id, [
			'STATUS' => self::STATUS_DONE,
			'UPDATED_AT' => date('Y-m-d H:i:s'),
		]);
	}

	/**
	 * Пометить open/saved циклы сущности как done (без after), чтобы новый before создал новый цикл.
	 */
	public static function abandonOpenCycles(string $entityType, int $entityId): void
	{
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$entityId = (int) $entityId;
		if ($entityId <= 0) {
			return;
		}
		global $DB;
		$now = date('Y-m-d H:i:s');
		$DB->Query("
			UPDATE titlo_work_history SET
				STATUS = '" . self::STATUS_DONE . "',
				UPDATED_AT = '" . $DB->ForSql($now) . "'
			WHERE ENTITY_TYPE = '" . $DB->ForSql($entityType) . "'
			  AND ENTITY_ID = " . $entityId . "
			  AND STATUS IN ('" . self::STATUS_OPEN . "','" . self::STATUS_SAVED . "')
		");
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	protected static function insertRow(array $fields): int
	{
		global $DB;
		$cols = [];
		$vals = [];
		foreach ($fields as $k => $v) {
			$col = self::assertColumnName((string) $k);
			$cols[] = $col;
			$vals[] = self::sqlValue($v);
		}
		$DB->Query('INSERT INTO titlo_work_history (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
		return (int) $DB->LastID();
	}

	/**
	 * @param array<string,mixed> $fields
	 */
	protected static function updateRow(int $id, array $fields): void
	{
		global $DB;
		$sets = [];
		foreach ($fields as $k => $v) {
			$col = self::assertColumnName((string) $k);
			$sets[] = $col . ' = ' . self::sqlValue($v);
		}
		if ($sets === []) {
			return;
		}
		$DB->Query('UPDATE titlo_work_history SET ' . implode(', ', $sets) . ' WHERE ID = ' . (int) $id);
	}

	protected static function assertColumnName(string $k): string
	{
		if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) {
			throw new \InvalidArgumentException('invalid work_history column: ' . $k);
		}
		static $allowed = null;
		if ($allowed === null) {
			$allowed = self::existingColumns();
			// на свежей схеме до ensureTables — fallback regex-only уже пройден
			if ($allowed === []) {
				return $k;
			}
		}
		if (!isset($allowed[$k])) {
			throw new \InvalidArgumentException('unknown work_history column: ' . $k);
		}

		return $k;
	}

	/**
	 * @param mixed $v
	 */
	protected static function sqlValue($v): string
	{
		global $DB;
		if ($v === null) {
			return 'NULL';
		}
		if (is_int($v) || is_float($v)) {
			return (string) $v;
		}
		return "'" . $DB->ForSql((string) $v) . "'";
	}

	/**
	 * @param array $payload
	 * @return array{history_id:int,points:?float,points_ideal:?float,coverage:?float,density:?float,position:?int,engine:string,region:string,top:?int,checked_at:string}
	 */
	protected static function normalizeScore(array $payload): array
	{
		$numOrNull = static function ($v): ?float {
			if ($v === null || $v === '') {
				return null;
			}
			return (float) $v;
		};
		$intOrNull = static function ($v): ?int {
			if ($v === null || $v === '') {
				return null;
			}
			return (int) $v;
		};
		$engine = strtolower(trim((string) ($payload['engine'] ?? 'yandex')));
		if (!in_array($engine, ['yandex', 'google'], true)) {
			$engine = 'yandex';
		}
		$checked = trim((string) ($payload['checked_at'] ?? $payload['last_check'] ?? $payload['created_at'] ?? ''));
		if ($checked !== '' && strtotime($checked)) {
			$checked = date('Y-m-d H:i:s', strtotime($checked));
		} else {
			$checked = '';
		}
		return [
			'history_id' => (int) ($payload['history_id'] ?? 0),
			'points' => $numOrNull($payload['points'] ?? null),
			'points_ideal' => $numOrNull($payload['points_ideal'] ?? null),
			'coverage' => $numOrNull($payload['coverage'] ?? null),
			'density' => $numOrNull($payload['density'] ?? null),
			'position' => $intOrNull($payload['position'] ?? null),
			'engine' => $engine,
			'region' => trim((string) ($payload['region'] ?? '')),
			'top' => $intOrNull($payload['top'] ?? null),
			'checked_at' => $checked,
		];
	}

	/**
	 * Город для UI без хвоста «[213]».
	 */
	protected static function regionLabelForUi(string $regionId, string $engine): string
	{
		$regionId = trim($regionId);
		if ($regionId === '') {
			return '';
		}
		$label = Config::analysisRegionLabel($regionId, $engine !== '' ? $engine : null);
		$label = preg_replace('/\s*\[[^\]]+\]\s*$/', '', $label);
		$label = trim((string) $label);

		return $label !== '' ? $label : $regionId;
	}

	/**
	 * @param array|null $row
	 */
	public static function serialize($row): ?array
	{
		if (!$row) {
			return null;
		}
		$beforePoints = $row['BEFORE_POINTS'] !== null && $row['BEFORE_POINTS'] !== '' ? (float) $row['BEFORE_POINTS'] : null;
		$afterPoints = $row['AFTER_POINTS'] !== null && $row['AFTER_POINTS'] !== '' ? (float) $row['AFTER_POINTS'] : null;
		$delta = ($beforePoints !== null && $afterPoints !== null) ? round($afterPoints - $beforePoints, 2) : null;
		$entityType = (string) $row['ENTITY_TYPE'];
		$entityId = (int) $row['ENTITY_ID'];
		$runMode = BatchQueue::normalizeRunMode((string) ($row['RUN_MODE'] ?? BatchQueue::MODE_FULL));
		$previewBefore = $row['PREVIEW_CHARS_BEFORE'] !== null && $row['PREVIEW_CHARS_BEFORE'] !== '' ? (int) $row['PREVIEW_CHARS_BEFORE'] : null;
		$detailBefore = $row['DETAIL_CHARS_BEFORE'] !== null && $row['DETAIL_CHARS_BEFORE'] !== '' ? (int) $row['DETAIL_CHARS_BEFORE'] : null;
		$previewAfter = $row['PREVIEW_CHARS_AFTER'] !== null && $row['PREVIEW_CHARS_AFTER'] !== '' ? (int) $row['PREVIEW_CHARS_AFTER'] : null;
		$detailAfter = $row['DETAIL_CHARS_AFTER'] !== null && $row['DETAIL_CHARS_AFTER'] !== '' ? (int) $row['DETAIL_CHARS_AFTER'] : null;
		// товар — DETAIL, категория — DESCRIPTION (лежит в preview_chars)
		$textCharsBefore = $entityType === 'S' ? $previewBefore : $detailBefore;
		$textCharsAfter = $entityType === 'S' ? $previewAfter : $detailAfter;
		$deltaTextChars = ($textCharsBefore !== null && $textCharsAfter !== null)
			? ((int) $textCharsAfter - (int) $textCharsBefore)
			: null;
		return [
			'id' => (int) $row['ID'],
			'entity_type' => $entityType,
			'entity_id' => $entityId,
			'name' => (string) $row['NAME'],
			'url' => (string) $row['URL'],
			'phrase' => (string) $row['PHRASE'],
			'status' => (string) $row['STATUS'],
			'run_mode' => $runMode,
			'run_mode_label' => BatchQueue::runModeLabel($runMode),
			'prompt_detail_id' => (int) ($row['PROMPT_DETAIL_ID'] ?? 0),
			'prompt_detail_name' => (string) ($row['PROMPT_DETAIL_NAME'] ?? ''),
			'prompt_preview_id' => (int) ($row['PROMPT_PREVIEW_ID'] ?? 0),
			'prompt_preview_name' => (string) ($row['PROMPT_PREVIEW_NAME'] ?? ''),
			'before' => [
				'history_id' => $row['BEFORE_HISTORY_ID'] !== null ? (int) $row['BEFORE_HISTORY_ID'] : null,
				'points' => $beforePoints,
				'points_ideal' => $row['BEFORE_POINTS_IDEAL'] !== null && $row['BEFORE_POINTS_IDEAL'] !== '' ? (float) $row['BEFORE_POINTS_IDEAL'] : null,
				'coverage' => $row['BEFORE_COVERAGE'] !== null && $row['BEFORE_COVERAGE'] !== '' ? (float) $row['BEFORE_COVERAGE'] : null,
				'density' => $row['BEFORE_DENSITY'] !== null && $row['BEFORE_DENSITY'] !== '' ? (float) $row['BEFORE_DENSITY'] : null,
				'position' => $row['BEFORE_POSITION'] !== null && $row['BEFORE_POSITION'] !== '' ? (int) $row['BEFORE_POSITION'] : null,
				'engine' => (string) ($row['BEFORE_ENGINE'] ?? ''),
				'region' => (string) ($row['BEFORE_REGION'] ?? ''),
				'region_label' => self::regionLabelForUi((string) ($row['BEFORE_REGION'] ?? ''), (string) ($row['BEFORE_ENGINE'] ?? '')),
				'top' => $row['BEFORE_TOP'] !== null && $row['BEFORE_TOP'] !== '' ? (int) $row['BEFORE_TOP'] : null,
				'at' => (string) ($row['BEFORE_AT'] ?? ''),
			],
			'after' => [
				'history_id' => $row['AFTER_HISTORY_ID'] !== null ? (int) $row['AFTER_HISTORY_ID'] : null,
				'points' => $afterPoints,
				'points_ideal' => $row['AFTER_POINTS_IDEAL'] !== null && $row['AFTER_POINTS_IDEAL'] !== '' ? (float) $row['AFTER_POINTS_IDEAL'] : null,
				'coverage' => $row['AFTER_COVERAGE'] !== null && $row['AFTER_COVERAGE'] !== '' ? (float) $row['AFTER_COVERAGE'] : null,
				'density' => $row['AFTER_DENSITY'] !== null && $row['AFTER_DENSITY'] !== '' ? (float) $row['AFTER_DENSITY'] : null,
				'position' => $row['AFTER_POSITION'] !== null && $row['AFTER_POSITION'] !== '' ? (int) $row['AFTER_POSITION'] : null,
				'engine' => (string) ($row['AFTER_ENGINE'] ?? ''),
				'region' => (string) ($row['AFTER_REGION'] ?? ''),
				'region_label' => self::regionLabelForUi((string) ($row['AFTER_REGION'] ?? ''), (string) ($row['AFTER_ENGINE'] ?? '')),
				'top' => $row['AFTER_TOP'] !== null && $row['AFTER_TOP'] !== '' ? (int) $row['AFTER_TOP'] : null,
				'at' => (string) ($row['AFTER_AT'] ?? ''),
			],
			'delta_points' => $delta,
			'preview_chars_before' => $row['PREVIEW_CHARS_BEFORE'] !== null ? (int) $row['PREVIEW_CHARS_BEFORE'] : null,
			'detail_chars_before' => $row['DETAIL_CHARS_BEFORE'] !== null ? (int) $row['DETAIL_CHARS_BEFORE'] : null,
			'preview_chars_after' => $row['PREVIEW_CHARS_AFTER'] !== null ? (int) $row['PREVIEW_CHARS_AFTER'] : null,
			'detail_chars_after' => $row['DETAIL_CHARS_AFTER'] !== null ? (int) $row['DETAIL_CHARS_AFTER'] : null,
			'text_chars_before' => $textCharsBefore,
			'text_chars_after' => $textCharsAfter,
			'delta_text_chars' => $deltaTextChars,
			'saved_at' => (string) ($row['SAVED_AT'] ?? ''),
			'created_at' => (string) ($row['CREATED_AT'] ?? ''),
			'updated_at' => (string) ($row['UPDATED_AT'] ?? ''),
			'single_url' => 'titlo_relevance_single.php?lang=' . LANGUAGE_ID . '&ENTITY=' . $entityType . '&ID=' . $entityId,
		];
	}
}
