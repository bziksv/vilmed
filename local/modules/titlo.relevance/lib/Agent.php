<?php

namespace Titlo\Relevance;

use Bitrix\Main\Loader;

class Agent
{
	/**
	 * Bitrix agent: массовые фразы, затем очередь автопроработки.
	 */
	public static function run(): string
	{
		if (!Loader::includeModule('titlo.relevance') || !Loader::includeModule('iblock')) {
			return '\\Titlo\\Relevance\\Agent::run();';
		}

		PhraseBulk::ensureTables();
		BatchQueue::ensureSchema();

		// Несколько шагов за тик агента — иначе 5k будут идти сутками
		$phraseBusy = false;
		for ($i = 0; $i < 5; $i++) {
			$tick = PhraseBulk::tick();
			$batch = $tick['batch'] ?? null;
			if ($batch && ($batch['status'] ?? '') === PhraseBulk::BATCH_RUNNING) {
				$phraseBusy = true;
			}
			if (!$batch || ($batch['status'] ?? '') !== PhraseBulk::BATCH_RUNNING) {
				break;
			}
			if (empty($tick['processed'])) {
				break;
			}
		}
		// Пока крутится массовая генерация фраз — не блокируем агент sleep'ами релевантности
		if ($phraseBusy) {
			return '\\Titlo\\Relevance\\Agent::run();';
		}

		$rows = array_merge(
			BatchQueue::listWorkable(BatchQueue::CONCURRENCY, 'E'),
			BatchQueue::listWorkable(BatchQueue::CONCURRENCY, 'S')
		);
		foreach ($rows as $row) {
			try {
				self::tickQueueRow($row);
			} catch (\Throwable $e) {
				BatchQueue::mark((int) $row['ID'], BatchQueue::STATUS_FAILED, [
					'ERROR' => $e->getMessage(),
				]);
			}
		}

		return '\\Titlo\\Relevance\\Agent::run();';
	}

	/**
	 * Один тик state machine по строке очереди.
	 *
	 * @param array $row
	 */
	public static function tickQueueRow(array $row): void
	{
		$id = (int) $row['ID'];
		$status = (string) ($row['STATUS'] ?? '');
		$step = (string) ($row['STEP'] ?? BatchQueue::STEP_ANALYZE);
		if ($status === BatchQueue::STATUS_QUEUED) {
			if (!BatchQueue::claim($id)) {
				return;
			}
			$step = BatchQueue::STEP_ANALYZE;
			$row['STATUS'] = BatchQueue::STATUS_RUNNING;
			$row['STEP'] = $step;
			$row['UPDATED_AT'] = BatchQueue::now();
		}

		$client = new ApiClient();

		switch ($step) {
			case BatchQueue::STEP_ANALYZE:
				self::stepAnalyze($client, $row);
				break;
			case BatchQueue::STEP_WAIT_ANALYSIS:
				self::stepWaitAnalysis($client, $row, false);
				break;
			case BatchQueue::STEP_GENERATE:
				self::stepGenerate($client, $row, false);
				break;
			case BatchQueue::STEP_WAIT_GENERATE:
				self::stepWaitGenerate($client, $row, false);
				break;
			case BatchQueue::STEP_GENERATE_PREVIEW:
				self::stepGenerate($client, $row, true);
				break;
			case BatchQueue::STEP_WAIT_GENERATE_PREVIEW:
				self::stepWaitGenerate($client, $row, true);
				break;
			case BatchQueue::STEP_SAVE:
				self::stepSave($row);
				break;
			case BatchQueue::STEP_RECHECK:
				self::stepRecheck($client, $row);
				break;
			case BatchQueue::STEP_WAIT_RECHECK:
				self::stepWaitAnalysis($client, $row, true);
				break;
			default:
				BatchQueue::mark($id, BatchQueue::STATUS_FAILED, [
					'ERROR' => 'Unknown step: ' . $step,
				]);
		}
	}

	protected static function crawlUrl(array $row): string
	{
		$browse = trim((string) ($row['URL'] ?? ''));
		if ($browse === '' || !Config::isAllowedAnalysisUrl($browse)) {
			throw new \RuntimeException('url must belong to configured site host');
		}
		list($crawlUrl, $crawlErr) = UrlBuilder::forCabinetCrawl($browse);
		if ($crawlErr !== null || $crawlUrl === '') {
			throw new \RuntimeException($crawlErr ?: 'crawl url empty');
		}
		if (!Config::isAllowedAnalysisUrl($crawlUrl)) {
			$host = Config::hostOf($crawlUrl);
			// локальный кабинет может краулить loopback после rewrite
			if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
				throw new \RuntimeException('crawl url failed allowlist');
			}
		}
		return $crawlUrl;
	}

	protected static function stepAnalyze(ApiClient $client, array $row): void
	{
		$id = (int) $row['ID'];
		$crawlUrl = self::crawlUrl($row);
		$result = $client->startAnalysis($crawlUrl, (string) $row['PHRASE'], [
			'engine' => Config::analysisEngineDefault(),
			'region' => Config::analysisRegionDefault(),
			'top' => Config::analysisTopDefault(),
		]);
		$analysisId = (string) ($result['analysis_id'] ?? '');
		if ($analysisId === '') {
			throw new \RuntimeException('Empty analysis_id');
		}
		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, [
			'STEP' => BatchQueue::STEP_WAIT_ANALYSIS,
			'ANALYSIS_ID' => $analysisId,
			'ERROR' => null,
		]);
	}

	/**
	 * @param bool $isRecheck after-save analysis
	 */
	protected static function stepWaitAnalysis(ApiClient $client, array $row, bool $isRecheck): void
	{
		$id = (int) $row['ID'];
		$analysisId = (string) ($row['ANALYSIS_ID'] ?? '');
		if ($analysisId === '') {
			throw new \RuntimeException('Missing analysis_id');
		}
		$status = $client->getAnalysis($analysisId);
		$st = (string) ($status['status'] ?? '');
		if ($st === 'failed') {
			$err = trim((string) ($status['error'] ?? ''));
			if ($err === '' || $err === 'analysis_failed') {
				$err = 'кабинет вернул failed без текста';
			}
			$which = $isRecheck ? 'повторный анализ после сохранения' : 'ожидание анализа';
			throw new \RuntimeException($which . ': ' . $err);
		}
		if ($st !== 'done') {
			// UPDATED_AT не трогаем на каждом poll — иначе таймаут никогда не сработает
			$waitSec = BatchQueue::ageSeconds($row['UPDATED_AT'] ?? null);
			$maxWait = 900; // любой non-terminal (queued/running/…)
			if ($waitSec > $maxWait) {
				$which = $isRecheck ? 'повторный анализ' : 'анализ';
				throw new \RuntimeException(
					$which . ' завис в кабинете (status=' . $st . ', ' . $waitSec . ' с). Локально: ./scripts/dev-relevance-queue.sh'
				);
			}
			return;
		}

		$historyId = isset($status['history_id']) ? (int) $status['history_id'] : 0;
		if ($historyId <= 0) {
			throw new \RuntimeException('Empty history_id');
		}

		$entityId = (int) $row['ENTITY_ID'];
		$entityType = strtoupper((string) ($row['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
		$entity = $entityType === 'S'
			? CatalogRepository::findSection($entityId)
			: CatalogRepository::findElement($entityId);
		$scorePayload = [
			'entity_type' => $entityType,
			'entity_id' => $entityId,
			'name' => (string) ($entity['name'] ?? ''),
			'url' => (string) ($row['URL'] ?? ''),
			'phrase' => (string) ($row['PHRASE'] ?? ''),
			'history_id' => $historyId,
			'points' => $status['points'] ?? null,
			'points_ideal' => $status['points_ideal'] ?? ($status['ideal'] ?? null),
			'coverage' => $status['coverage'] ?? null,
			'density' => $status['density'] ?? null,
			'position' => $status['position'] ?? null,
			'engine' => (string) ($status['engine'] ?? Config::analysisEngineDefault()),
			'region' => (string) ($status['region'] ?? Config::analysisRegionDefault()),
			'top' => $status['top'] ?? Config::analysisTopDefault(),
			'role' => $isRecheck ? 'after' : 'before',
		];
		// подтянуть метрики из history если в analysis их нет
		try {
			$hist = $client->getHistory($historyId);
			foreach (['points', 'points_ideal', 'coverage', 'density', 'position', 'engine', 'region', 'top'] as $k) {
				if (($scorePayload[$k] === null || $scorePayload[$k] === '') && isset($hist[$k])) {
					$scorePayload[$k] = $hist[$k];
				}
			}
			if (isset($hist['score'])) {
				$scorePayload['points'] = $scorePayload['points'] ?? $hist['score'];
			}
		} catch (\Throwable $e) {
			// не критично
		}

		$wh = WorkHistory::recordScore($scorePayload);
		$workId = isset($wh['row']['id']) ? (int) $wh['row']['id'] : (int) ($row['WORK_HISTORY_ID'] ?? 0);

		if ($isRecheck) {
			if ($entityType === 'S') {
				CatalogRepository::markSectionAutoAt($entityId);
			} else {
				CatalogRepository::markElementAutoAt($entityId);
			}
			BatchQueue::mark($id, BatchQueue::STATUS_DONE, [
				'STEP' => BatchQueue::STEP_DONE,
				'HISTORY_ID' => $historyId,
				'WORK_HISTORY_ID' => $workId > 0 ? $workId : null,
				'ERROR' => null,
			]);
			return;
		}

		$runMode = strtolower((string) ($row['RUN_MODE'] ?? BatchQueue::MODE_FULL));
		if ($runMode === BatchQueue::MODE_ANALYZE_ONLY) {
			BatchQueue::mark($id, BatchQueue::STATUS_DONE, [
				'STEP' => BatchQueue::STEP_DONE,
				'HISTORY_ID' => $historyId,
				'WORK_HISTORY_ID' => $workId > 0 ? $workId : null,
				'ERROR' => null,
			]);
			return;
		}

		$policy = (string) ($row['EXISTING_POLICY'] ?? BatchQueue::POLICY_OVERWRITE);
		$hasDetail = $entityType === 'S'
			? !CatalogRepository::isSectionDescriptionEmpty($entityId)
			: !CatalogRepository::isElementDetailEmpty($entityId);
		if ($hasDetail && $policy === BatchQueue::POLICY_SKIP) {
			if ($entityType === 'S') {
				CatalogRepository::markSectionAutoAt($entityId);
			} else {
				CatalogRepository::markElementAutoAt($entityId);
			}
			BatchQueue::mark($id, BatchQueue::STATUS_SKIPPED, [
				'STEP' => BatchQueue::STEP_DONE,
				'HISTORY_ID' => $historyId,
				'WORK_HISTORY_ID' => $workId > 0 ? $workId : null,
				'ERROR' => null,
			]);
			return;
		}

		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, [
			'STEP' => BatchQueue::STEP_GENERATE,
			'HISTORY_ID' => $historyId,
			'WORK_HISTORY_ID' => $workId > 0 ? $workId : null,
			'ERROR' => null,
		]);
	}

	protected static function buildKeywordsFromTlp(ApiClient $client, int $historyId, int $missLim, int $diffLim): array
	{
		$tlp = $client->getTlp($historyId);
		$missing = is_array($tlp['missing'] ?? null) ? $tlp['missing'] : [];
		$diff = is_array($tlp['diff'] ?? null) ? $tlp['diff'] : [];
		$keywords = [];
		foreach (array_slice($missing, 0, max(0, $missLim)) as $item) {
			$word = trim((string) ($item['word'] ?? ''));
			if ($word === '') {
				continue;
			}
			$keywords[] = [
				'word' => $word,
				'count' => max(1, (int) ($item['suggested_count'] ?? 1)),
			];
		}
		foreach (array_slice($diff, 0, max(0, $diffLim)) as $item) {
			$word = trim((string) ($item['word'] ?? ''));
			if ($word === '') {
				continue;
			}
			$keywords[] = [
				'word' => $word,
				'count' => max(1, (int) ($item['suggested_count'] ?? 1)),
			];
		}
		// кабинет режет до 80
		if (count($keywords) > 80) {
			$keywords = array_slice($keywords, 0, 80);
		}
		return $keywords;
	}

	protected static function stepGenerate(ApiClient $client, array $row, bool $preview): void
	{
		$id = (int) $row['ID'];
		$historyId = (int) ($row['HISTORY_ID'] ?? 0);
		if ($historyId <= 0) {
			throw new \RuntimeException('history_id required for generate');
		}
		$keywords = self::buildKeywordsFromTlp(
			$client,
			$historyId,
			(int) ($row['TLP_MISSING_LIMIT'] ?? 200),
			(int) ($row['TLP_DIFF_LIMIT'] ?? 5)
		);
		$type = $preview ? Prompts::TYPE_PREVIEW : Prompts::TYPE_DETAIL;
		$promptId = $preview
			? (int) ($row['PROMPT_PREVIEW_ID'] ?? 0)
			: (int) ($row['PROMPT_DETAIL_ID'] ?? 0);
		$extra = ['history_id' => $historyId];
		if ($promptId > 0) {
			$extra['prompt_id'] = $promptId;
		}
		$res = $client->startGenerate($type, self::crawlUrl($row), $keywords, $extra);
		$recordId = (int) ($res['record_id'] ?? 0);
		if ($recordId <= 0) {
			throw new \RuntimeException('Empty generate record_id');
		}
		$fields = [
			'STEP' => $preview ? BatchQueue::STEP_WAIT_GENERATE_PREVIEW : BatchQueue::STEP_WAIT_GENERATE,
			'ERROR' => null,
		];
		if ($preview) {
			$fields['GEN_PREVIEW_RECORD_ID'] = $recordId;
		} else {
			$fields['GEN_RECORD_ID'] = $recordId;
		}
		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, $fields);
	}

	protected static function stepWaitGenerate(ApiClient $client, array $row, bool $preview): void
	{
		$id = (int) $row['ID'];
		$recordId = $preview
			? (int) ($row['GEN_PREVIEW_RECORD_ID'] ?? 0)
			: (int) ($row['GEN_RECORD_ID'] ?? 0);
		if ($recordId <= 0) {
			throw new \RuntimeException('Missing gen record_id');
		}
		$res = $client->getGenerate($recordId);
		$st = (string) ($res['status'] ?? '');
		if ($st === 'failed') {
			$err = (string) ($res['error'] ?? 'generate_failed');
			$attempt = (int) ($row['GEN_ATTEMPTS'] ?? 0);
			if ($attempt < 2 && self::isTransientRemoteError($err)) {
				BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, [
					'STEP' => $preview ? BatchQueue::STEP_GENERATE_PREVIEW : BatchQueue::STEP_GENERATE,
					'GEN_ATTEMPTS' => $attempt + 1,
					'ERROR' => null,
				]);
				return;
			}
			throw new \RuntimeException($err);
		}
		if ($st !== 'completed' && $st !== 'done') {
			// Не bump UPDATED_AT на poll — иначе ceiling не сработает
			$waitSec = BatchQueue::ageSeconds($row['UPDATED_AT'] ?? null);
			if ($waitSec > 900) {
				throw new \RuntimeException(
					'Генерация зависла (status=' . $st . ', ' . $waitSec . ' с)'
				);
			}
			return;
		}
		$text = (string) ($res['result'] ?? '');
		if (trim($text) === '') {
			throw new \RuntimeException('Empty generate result');
		}

		$next = [
			'ERROR' => null,
		];
		if ($preview) {
			$next['PREVIEW_TEXT'] = $text;
			$next['STEP'] = BatchQueue::STEP_SAVE;
		} else {
			$next['DETAIL_TEXT'] = $text;
			$entityType = strtoupper((string) ($row['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
			$wantPreview = $entityType !== 'S' && (($row['GEN_PREVIEW'] ?? 'N') === 'Y');
			$next['STEP'] = $wantPreview
				? BatchQueue::STEP_GENERATE_PREVIEW
				: BatchQueue::STEP_SAVE;
		}
		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, $next);
	}

	protected static function isTransientRemoteError(string $err): bool
	{
		$err = mb_strtolower($err);
		foreach (['timed out', 'timeout', 'curl error 28', 'curl error 52', 'curl error 56', '502', '503', '504', '429'] as $needle) {
			if (mb_strpos($err, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	protected static function stepSave(array $row): void
	{
		$id = (int) $row['ID'];
		$entityId = (int) $row['ENTITY_ID'];
		$entityType = strtoupper((string) ($row['ENTITY_TYPE'] ?? 'E')) === 'S' ? 'S' : 'E';
		$detail = array_key_exists('DETAIL_TEXT', $row) ? (string) $row['DETAIL_TEXT'] : null;
		$preview = null;
		if ($entityType !== 'S' && ($row['GEN_PREVIEW'] ?? 'N') === 'Y' && array_key_exists('PREVIEW_TEXT', $row)) {
			$preview = (string) $row['PREVIEW_TEXT'];
		}
		if ($detail === null || trim($detail) === '') {
			throw new \RuntimeException('Nothing to save');
		}
		if ($entityType === 'S') {
			if (!CatalogRepository::saveSectionDescription($entityId, $detail)) {
				throw new \RuntimeException('saveSectionDescription failed');
			}
			$entity = CatalogRepository::findSection($entityId);
		} else {
			if (!CatalogRepository::saveElementTexts($entityId, $preview, $detail)) {
				throw new \RuntimeException('saveElementTexts failed');
			}
			$entity = CatalogRepository::findElement($entityId);
		}

		$work = WorkHistory::markSaved([
			'entity_type' => $entityType,
			'entity_id' => $entityId,
			'name' => (string) ($entity['name'] ?? ''),
			'url' => (string) ($row['URL'] ?? ''),
			'phrase' => (string) ($row['PHRASE'] ?? ''),
			'preview_chars' => $preview !== null ? mb_strlen($preview) : 0,
			'detail_chars' => mb_strlen($detail),
		]);
		$workId = isset($work['row']['id']) ? (int) $work['row']['id'] : (int) ($row['WORK_HISTORY_ID'] ?? 0);

		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, [
			'STEP' => BatchQueue::STEP_RECHECK,
			'WORK_HISTORY_ID' => $workId > 0 ? $workId : null,
			'ERROR' => null,
		]);
	}

	protected static function stepRecheck(ApiClient $client, array $row): void
	{
		$id = (int) $row['ID'];
		$result = $client->startAnalysis(self::crawlUrl($row), (string) $row['PHRASE'], [
			'engine' => Config::analysisEngineDefault(),
			'region' => Config::analysisRegionDefault(),
			'top' => Config::analysisTopDefault(),
		]);
		$analysisId = (string) ($result['analysis_id'] ?? '');
		if ($analysisId === '') {
			throw new \RuntimeException('Empty recheck analysis_id');
		}
		BatchQueue::mark($id, BatchQueue::STATUS_RUNNING, [
			'STEP' => BatchQueue::STEP_WAIT_RECHECK,
			'ANALYSIS_ID' => $analysisId,
			'ERROR' => null,
		]);
	}
}
