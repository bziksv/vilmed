<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Titlo\Relevance\Agent;
use Titlo\Relevance\ApiClient;
use Titlo\Relevance\BatchQueue;
use Titlo\Relevance\CatalogRepository;
use Titlo\Relevance\Config;
use Titlo\Relevance\PhraseBulk;
use Titlo\Relevance\ProductDuplicates;
use Titlo\Relevance\Prompts;
use Titlo\Relevance\SectionDuplicates;
use Titlo\Relevance\UrlBuilder;
use Titlo\Relevance\UserFields;
use Titlo\Relevance\WorkHistory;

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=UTF-8');

/** @global CUser $USER */

function titlo_json(array $payload, int $code = 200): void
{
	http_response_code($code);
	echo \Bitrix\Main\Web\Json::encode($payload);
	die();
}

if (!$USER->IsAuthorized() || !$USER->IsAdmin()) {
	titlo_json(['ok' => false, 'error' => 'forbidden'], 403);
}

if (!check_bitrix_sessid()) {
	titlo_json(['ok' => false, 'error' => 'bad sessid'], 403);
}

if (!Loader::includeModule('iblock') || !Loader::includeModule('titlo.relevance')) {
	titlo_json(['ok' => false, 'error' => 'module not loaded'], 500);
}

$action = (string) ($_POST['action'] ?? '');

try {
	$client = new ApiClient();

	switch ($action) {
		case 'search':
			$items = CatalogRepository::search(
				strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E',
				(string) ($_POST['q'] ?? ''),
				20
			);
			titlo_json(['ok' => true, 'items' => $items]);

		case 'load_entity':
			$type = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$id = (int) ($_POST['entity_id'] ?? 0);
			$entity = $type === 'S' ? CatalogRepository::findSection($id) : CatalogRepository::findElement($id);
			if (!$entity) {
				titlo_json([
					'ok' => false,
					'error' => $type === 'S'
						? ('Категория #' . $id . ' не найдена в инфоблоке каталога')
						: ('Товар #' . $id . ' не найден в инфоблоке каталога'),
				], 404);
			}
			if (($entity['type'] ?? $type) !== $type) {
				titlo_json(['ok' => false, 'error' => 'type mismatch'], 422);
			}
			titlo_json(['ok' => true, 'entity' => $entity]);

		case 'start_analysis':
			$url = trim((string) ($_POST['url'] ?? ''));
			if ($url === '' || !Config::isAllowedAnalysisUrl($url)) {
				titlo_json(['ok' => false, 'error' => 'url must belong to configured site host'], 422);
			}
			list($crawlUrl, $crawlErr) = UrlBuilder::forCabinetCrawl($url);
			if ($crawlErr !== null || $crawlUrl === '') {
				titlo_json(['ok' => false, 'error' => $crawlErr ?: 'crawl url empty'], 422);
			}
			$engine = strtolower(trim((string) ($_POST['engine'] ?? Config::analysisEngineDefault())));
			if (!in_array($engine, ['yandex', 'google'], true)) {
				$engine = 'yandex';
			}
			$region = trim((string) ($_POST['region'] ?? Config::analysisRegionDefault($engine)));
			if ($region === '') {
				$region = Config::analysisRegionDefault($engine);
			}
			$top = (int) ($_POST['top'] ?? Config::analysisTopDefault());
			if ($top < 10) {
				$top = 10;
			}
			if ($top > 50) {
				$top = 50;
			}
			$res = $client->startAnalysis(
				$crawlUrl,
				trim((string) ($_POST['phrase'] ?? '')),
				[
					'engine' => $engine,
					'region' => $region,
					'top' => $top,
				]
			);
			titlo_json([
				'ok' => true,
				'analysis_id' => $res['analysis_id'] ?? null,
				'status' => $res['status'] ?? 'queued',
				'progress' => 0,
				'engine' => $engine,
				'region' => $region,
				'top' => $top,
				'crawl_url' => $crawlUrl,
			]);

		case 'poll_analysis':
			$res = $client->getAnalysis(trim((string) ($_POST['analysis_id'] ?? '')));
			titlo_json([
				'ok' => true,
				'analysis_id' => $res['analysis_id'] ?? null,
				'status' => $res['status'] ?? 'queued',
				'progress' => $res['progress'] ?? 0,
				'history_id' => $res['history_id'] ?? null,
				'error' => $res['error'] ?? null,
			]);

		case 'missing_phrases':
			$historyId = (int) ($_POST['history_id'] ?? 0);
			$mode = strtolower(trim((string) ($_POST['mode'] ?? 'tlp')));
			if ($mode === 'tlp') {
				$res = $client->getTlp($historyId);
				titlo_json([
					'ok' => true,
					'history_id' => $res['history_id'] ?? $historyId,
					'mode' => 'tlp',
					'missing' => $res['missing'] ?? [],
					'diff' => $res['diff'] ?? [],
					'missing_total' => (int) ($res['missing_total'] ?? 0),
					'diff_total' => (int) ($res['diff_total'] ?? 0),
					'defaults' => $res['defaults'] ?? ['missing_limit' => 300, 'diff_limit' => 5],
				]);
			}
			$res = $client->getMissingPhrases($historyId, 'zero');
			titlo_json([
				'ok' => true,
				'history_id' => $historyId,
				'phrases' => $res['phrases'] ?? [],
				'unigram' => $res['unigram'] ?? [],
			]);

		case 'tfidf_clouds':
			$historyId = (int) ($_POST['history_id'] ?? 0);
			if ($historyId <= 0) {
				titlo_json(['ok' => false, 'error' => 'history_id required'], 422);
			}
			$res = $client->getClouds($historyId, (int) ($_POST['limit'] ?? 100));
			titlo_json([
				'ok' => true,
				'history_id' => $res['history_id'] ?? $historyId,
				'competitors' => $res['competitors'] ?? [],
				'landing' => $res['landing'] ?? [],
			]);

		case 'get_history':
			$historyId = (int) ($_POST['history_id'] ?? 0);
			if ($historyId <= 0) {
				titlo_json(['ok' => false, 'error' => 'history_id required'], 422);
			}
			$res = $client->getHistory($historyId);
			titlo_json(array_merge(['ok' => true], $res));

		case 'list_histories':
			$url = trim((string) ($_POST['url'] ?? ''));
			$phrase = trim((string) ($_POST['phrase'] ?? ''));
			if ($url === '' && $phrase === '') {
				titlo_json(['ok' => false, 'error' => 'url or phrase required'], 422);
			}
			if ($url !== '' && !Config::isAllowedAnalysisUrl($url)) {
				titlo_json(['ok' => false, 'error' => 'url must belong to configured site host'], 422);
			}
			$res = $client->listHistories(
				$url !== '' ? $url : null,
				$phrase !== '' ? $phrase : null,
				(int) ($_POST['limit'] ?? 10),
				Config::hostOf(Config::siteUrl()) ?: null
			);
			titlo_json(array_merge(['ok' => true], $res));

		case 'start_generate':
			$genUrl = trim((string) ($_POST['url'] ?? ''));
			if ($genUrl === '' || !Config::isAllowedAnalysisUrl($genUrl)) {
				titlo_json(['ok' => false, 'error' => 'url must belong to configured site host'], 422);
			}
			list($crawlUrl, $crawlErr) = UrlBuilder::forCabinetCrawl($genUrl);
			if ($crawlErr !== null || $crawlUrl === '') {
				titlo_json(['ok' => false, 'error' => $crawlErr ?: 'crawl url empty'], 422);
			}
			$keywords = [];
			$raw = (string) ($_POST['keywords'] ?? '[]');
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$keywords = array_slice($decoded, 0, 200);
			}
			$extra = [];
			$hid = (int) ($_POST['history_id'] ?? 0);
			if ($hid > 0) {
				$extra['history_id'] = $hid;
			}
			$promptId = (int) ($_POST['prompt_id'] ?? 0);
			if ($promptId > 0) {
				$extra['prompt_id'] = $promptId;
			}
			$type = trim((string) ($_POST['type'] ?? 'preview'));
			$allowedGen = [
				Prompts::TYPE_PREVIEW,
				Prompts::TYPE_DETAIL,
				Prompts::TYPE_CATEGORY,
			];
			if (!in_array($type, $allowedGen, true)) {
				titlo_json(['ok' => false, 'error' => 'type must be preview, detail or category'], 422);
			}
			$res = $client->startGenerate(
				$type,
				$crawlUrl,
				$keywords,
				$extra
			);
			$used = $promptId > 0 ? \Titlo\Relevance\Prompts::find($promptId) : null;
			titlo_json([
				'ok' => true,
				'record_id' => $res['record_id'] ?? null,
				'status' => $res['status'] ?? 'pending',
				'prompt_id' => $promptId ?: \Titlo\Relevance\Prompts::getActiveId($type),
				'prompt_name' => $used['name'] ?? null,
			]);

		case 'poll_generate':
			$res = $client->getGenerate((int) ($_POST['record_id'] ?? 0));
			titlo_json([
				'ok' => true,
				'record_id' => $res['record_id'] ?? null,
				'status' => $res['status'] ?? 'pending',
				'result' => $res['result'] ?? null,
				'error' => $res['error'] ?? null,
			]);

		case 'save':
			$type = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$id = (int) ($_POST['entity_id'] ?? 0);
			if ($id <= 0) {
				titlo_json(['ok' => false, 'error' => 'entity_id required'], 422);
			}
			$phrase = array_key_exists('phrase', $_POST) ? trim((string) $_POST['phrase']) : null;
			$errors = [];
			if ($type === 'S') {
				$ok = CatalogRepository::saveSectionDescription($id, (string) ($_POST['description'] ?? ''));
				if (!$ok) {
					$errors[] = 'section description';
				}
				if ($phrase !== null) {
					if (!CatalogRepository::saveSectionPhrase($id, $phrase)) {
						$errors[] = 'section phrase';
					}
				}
			} else {
				$preview = array_key_exists('preview_text', $_POST) ? (string) $_POST['preview_text'] : null;
				$detail = array_key_exists('detail_text', $_POST) ? (string) $_POST['detail_text'] : null;
				if (!CatalogRepository::saveElementTexts($id, $preview, $detail)) {
					$errors[] = 'element texts';
				}
				if ($phrase !== null) {
					if (!CatalogRepository::saveElementPhrase($id, $phrase)) {
						$errors[] = 'element phrase';
					}
				}
			}
			$okAll = $errors === [];
			$workRow = null;
			if ($okAll) {
				$preview = array_key_exists('preview_text', $_POST) ? (string) $_POST['preview_text'] : '';
				$detail = array_key_exists('detail_text', $_POST) ? (string) $_POST['detail_text'] : '';
				$description = (string) ($_POST['description'] ?? '');
				$work = WorkHistory::markSaved([
					'entity_type' => $type,
					'entity_id' => $id,
					'name' => trim((string) ($_POST['name'] ?? '')),
					'url' => trim((string) ($_POST['url'] ?? '')),
					'phrase' => $phrase !== null ? $phrase : '',
					'preview_chars' => $type === 'S' ? mb_strlen($description) : mb_strlen($preview),
					'detail_chars' => $type === 'S' ? 0 : mb_strlen($detail),
				]);
				$workRow = $work['row'] ?? null;
			}
			titlo_json([
				'ok' => $okAll,
				'error' => $okAll ? null : ('update failed: ' . implode('; ', $errors)),
				'work' => $workRow,
			]);

		case 'work_record_score':
			$hid = (int) ($_POST['history_id'] ?? 0);
			$points = $_POST['points'] ?? null;
			$pointsIdeal = $_POST['points_ideal'] ?? null;
			$coverage = $_POST['coverage'] ?? null;
			$density = $_POST['density'] ?? null;
			$position = $_POST['position'] ?? null;
			$engine = (string) ($_POST['engine'] ?? '');
			$region = (string) ($_POST['region'] ?? '');
			$top = $_POST['top'] ?? null;
			$checkedAt = (string) ($_POST['checked_at'] ?? '');
			if ($hid > 0) {
				try {
					$hist = $client->getHistory($hid);
					foreach (['points', 'points_ideal', 'coverage', 'density', 'position', 'engine', 'region', 'top'] as $k) {
						if (isset($hist[$k]) && $hist[$k] !== '' && $hist[$k] !== null) {
							if ($k === 'points') {
								$points = $hist[$k];
							} elseif ($k === 'points_ideal') {
								$pointsIdeal = $hist[$k];
							} elseif ($k === 'coverage') {
								$coverage = $hist[$k];
							} elseif ($k === 'density') {
								$density = $hist[$k];
							} elseif ($k === 'position') {
								$position = $hist[$k];
							} elseif ($k === 'engine') {
								$engine = (string) $hist[$k];
							} elseif ($k === 'region') {
								$region = (string) $hist[$k];
							} elseif ($k === 'top') {
								$top = $hist[$k];
							}
						}
					}
					if (isset($hist['score']) && ($points === null || $points === '')) {
						$points = $hist['score'];
					}
					if (($checkedAt === '') && !empty($hist['last_check'])) {
						$checkedAt = (string) $hist['last_check'];
					} elseif (($checkedAt === '') && !empty($hist['created_at'])) {
						$checkedAt = (string) $hist['created_at'];
					}
				} catch (\Throwable $e) {
					// оставляем клиентские поля только если cabinet недоступен
				}
			}
			$res = WorkHistory::recordScore([
				'entity_type' => (string) ($_POST['entity_type'] ?? 'E'),
				'entity_id' => (int) ($_POST['entity_id'] ?? 0),
				'name' => (string) ($_POST['name'] ?? ''),
				'url' => (string) ($_POST['url'] ?? ''),
				'phrase' => (string) ($_POST['phrase'] ?? ''),
				'history_id' => $hid,
				'points' => $points,
				'points_ideal' => $pointsIdeal,
				'coverage' => $coverage,
				'density' => $density,
				'position' => $position,
				'engine' => $engine,
				'region' => $region,
				'top' => $top,
				'checked_at' => $checkedAt,
				'role' => (string) ($_POST['role'] ?? 'auto'),
				'run_mode' => (string) ($_POST['run_mode'] ?? BatchQueue::MODE_FULL),
			]);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'list_work_history':
			$list = WorkHistory::list([
				'preset' => (string) ($_POST['preset'] ?? 'all'),
				'entity_type' => (string) ($_POST['entity_type'] ?? ''),
				'q' => (string) ($_POST['q'] ?? ''),
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 25),
			]);
			titlo_json(array_merge(['ok' => true], $list));

		case 'enqueue':
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$entityId = (int) ($_POST['entity_id'] ?? 0);
			$enqueueUrl = trim((string) ($_POST['url'] ?? ''));
			if ($entityId <= 0) {
				titlo_json(['ok' => false, 'error' => 'entity_id required'], 422);
			}
			$belongs = $entityType === 'S'
				? Config::sectionBelongsToCatalog($entityId)
				: Config::elementBelongsToCatalog($entityId);
			if (!$belongs) {
				titlo_json(['ok' => false, 'error' => 'entity not in catalog iblock'], 404);
			}
			if ($enqueueUrl === '' || !Config::isAllowedAnalysisUrl($enqueueUrl)) {
				titlo_json(['ok' => false, 'error' => 'url must belong to configured site host'], 422);
			}
			try {
				$qid = BatchQueue::enqueue(
					$entityType,
					$entityId,
					$enqueueUrl,
					trim((string) ($_POST['phrase'] ?? '')),
					[
						'prompt_detail_id' => (int) ($_POST['prompt_detail_id'] ?? 0),
						'prompt_preview_id' => (int) ($_POST['prompt_preview_id'] ?? 0),
						'gen_preview' => in_array((string) ($_POST['gen_preview'] ?? ''), ['1', 'Y', 'y', 'true'], true),
						'existing_policy' => (string) ($_POST['existing_policy'] ?? BatchQueue::POLICY_OVERWRITE),
						'tlp_missing_limit' => (int) ($_POST['tlp_missing_limit'] ?? 300),
						'tlp_diff_limit' => (int) ($_POST['tlp_diff_limit'] ?? 5),
					]
				);
				titlo_json(['ok' => true, 'queue_id' => $qid]);
			} catch (\InvalidArgumentException $e) {
				titlo_json(['ok' => false, 'error' => $e->getMessage()], 422);
			}

		case 'auto_list_products':
			BatchQueue::ensureSchema();
			UserFields::ensurePhraseField();
			$list = CatalogRepository::listElementsForAuto([
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 25),
				'q' => (string) ($_POST['q'] ?? ''),
				'filter' => (string) ($_POST['filter'] ?? 'todo'),
				'sort' => (string) ($_POST['sort'] ?? 'id_desc'),
			]);
			titlo_json(array_merge(['ok' => true], $list));

		case 'auto_list_sections':
			BatchQueue::ensureSchema();
			UserFields::ensurePhraseField();
			$list = CatalogRepository::listSectionsForAuto([
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 25),
				'q' => (string) ($_POST['q'] ?? ''),
				'filter' => (string) ($_POST['filter'] ?? 'todo'),
				'sort' => (string) ($_POST['sort'] ?? 'id_desc'),
			]);
			titlo_json(array_merge(['ok' => true], $list));

		case 'auto_enqueue_batch':
			BatchQueue::ensureSchema();
			UserFields::ensurePhraseField();
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$rawIds = $_POST['ids'] ?? [];
			if (is_string($rawIds)) {
				$decoded = json_decode($rawIds, true);
				$rawIds = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $rawIds) ?: []);
			}
			if (!is_array($rawIds)) {
				$rawIds = [];
			}
			$items = [];
			foreach ($rawIds as $rawId) {
				$id = (int) $rawId;
				if ($id > 0) {
					$items[] = ['entity_id' => $id];
				}
			}
			if ($items === []) {
				titlo_json(['ok' => false, 'error' => 'ids required'], 422);
			}
			$opts = [
				'entity_type' => $entityType,
				'prompt_detail_id' => (int) ($_POST['prompt_detail_id'] ?? 0),
				'prompt_preview_id' => (int) ($_POST['prompt_preview_id'] ?? 0),
				'gen_preview' => $entityType !== 'S' && in_array((string) ($_POST['gen_preview'] ?? ''), ['1', 'Y', 'y', 'true'], true),
				'existing_policy' => (string) ($_POST['existing_policy'] ?? BatchQueue::POLICY_OVERWRITE),
				'tlp_missing_limit' => (int) ($_POST['tlp_missing_limit'] ?? 300),
				'tlp_diff_limit' => (int) ($_POST['tlp_diff_limit'] ?? 5),
				'run_mode' => (string) ($_POST['run_mode'] ?? BatchQueue::MODE_FULL),
			];
			// сохранить дефолты панели
			$runMode = BatchQueue::normalizeRunMode((string) $opts['run_mode']);
			$opts['run_mode'] = $runMode;
			if ($runMode === BatchQueue::MODE_REFINE) {
				$opts['existing_policy'] = BatchQueue::POLICY_OVERWRITE;
			}
			Config::setAutoOption('existing_policy', $entityType, $opts['existing_policy'] === BatchQueue::POLICY_SKIP ? BatchQueue::POLICY_SKIP : BatchQueue::POLICY_OVERWRITE);
			Config::setAutoOption('tlp_missing', $entityType, (string) $opts['tlp_missing_limit']);
			Config::setAutoOption('tlp_diff', $entityType, (string) $opts['tlp_diff_limit']);
			Config::setAutoOption('run_mode', $entityType, $runMode);
			if ($entityType !== 'S') {
				Config::setAutoOption('gen_preview', $entityType, $opts['gen_preview'] ? 'Y' : 'N');
			}
			if ($opts['prompt_detail_id'] > 0) {
				$promptType = $entityType === 'S' ? Prompts::TYPE_CATEGORY : Prompts::TYPE_DETAIL;
				Prompts::setActiveId($promptType, $opts['prompt_detail_id']);
			}
			if ($opts['prompt_preview_id'] > 0) {
				Prompts::setActiveId(Prompts::TYPE_PREVIEW, $opts['prompt_preview_id']);
			}
			$res = BatchQueue::enqueueBatch($items, $opts);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'auto_requeue_failed':
			BatchQueue::ensureSchema();
			$opts = [
				'include_skipped' => in_array((string) ($_POST['include_skipped'] ?? ''), ['1', 'Y', 'y', 'true'], true),
				'run_mode' => (string) ($_POST['run_mode'] ?? ''),
				'existing_policy' => (string) ($_POST['existing_policy'] ?? ''),
			];
			$res = BatchQueue::requeueFailed($opts);
			titlo_json(array_merge(['ok' => !empty($res['ok'])], $res), !empty($res['ok']) ? 200 : 422);

		case 'auto_requeue_jobs':
			BatchQueue::ensureSchema();
			$rawIds = (string) ($_POST['ids'] ?? '[]');
			$decoded = json_decode($rawIds, true);
			if (!is_array($decoded)) {
				$decoded = [];
			}
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$opts = [
				'entity_type' => $entityType,
				'run_mode' => (string) ($_POST['run_mode'] ?? BatchQueue::MODE_FULL),
				'existing_policy' => (string) ($_POST['existing_policy'] ?? BatchQueue::POLICY_OVERWRITE),
				'prompt_detail_id' => (int) ($_POST['prompt_detail_id'] ?? 0),
				'prompt_preview_id' => (int) ($_POST['prompt_preview_id'] ?? 0),
				'gen_preview' => $entityType !== 'S' && in_array((string) ($_POST['gen_preview'] ?? ''), ['1', 'Y', 'y', 'true'], true),
				'tlp_missing_limit' => (int) ($_POST['tlp_missing_limit'] ?? 300),
				'tlp_diff_limit' => (int) ($_POST['tlp_diff_limit'] ?? 5),
			];
			if ($opts['run_mode'] === BatchQueue::MODE_FULL || $opts['run_mode'] === BatchQueue::MODE_REFINE) {
				$opts['run_mode'] = BatchQueue::normalizeRunMode((string) $opts['run_mode']);
				Config::setAutoOption('run_mode', $entityType, $opts['run_mode']);
				Config::setAutoOption(
					'existing_policy',
					$entityType,
					$opts['existing_policy'] === BatchQueue::POLICY_SKIP && $opts['run_mode'] !== BatchQueue::MODE_REFINE
						? BatchQueue::POLICY_SKIP
						: BatchQueue::POLICY_OVERWRITE
				);
			} elseif ($opts['run_mode'] === BatchQueue::MODE_ANALYZE_ONLY) {
				Config::setAutoOption('run_mode', $entityType, BatchQueue::MODE_ANALYZE_ONLY);
			} else {
				$opts['run_mode'] = BatchQueue::MODE_FULL;
				Config::setAutoOption('run_mode', $entityType, BatchQueue::MODE_FULL);
			}
			if ($entityType !== 'S') {
				Config::setAutoOption('gen_preview', $entityType, !empty($opts['gen_preview']) ? 'Y' : 'N');
			}
			$res = BatchQueue::requeueByIds($decoded, $opts);
			titlo_json(array_merge(['ok' => !empty($res['ok'])], $res), !empty($res['ok']) ? 200 : 422);

		case 'auto_queue_status':
			BatchQueue::ensureSchema();
			// подтолкнуть очередь из UI (если агент редко тикает на local)
			$tick = max(0, min(3, (int) ($_POST['tick'] ?? 1)));
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? ''));
			if ($entityType !== 'S' && $entityType !== 'E') {
				$entityType = '';
			}
			for ($i = 0; $i < $tick; $i++) {
				$rows = $entityType !== ''
					? BatchQueue::listWorkable(BatchQueue::CONCURRENCY, $entityType)
					: array_merge(
						BatchQueue::listWorkable(BatchQueue::CONCURRENCY, 'E'),
						BatchQueue::listWorkable(BatchQueue::CONCURRENCY, 'S')
					);
				if ($rows === []) {
					break;
				}
				foreach ($rows as $row) {
					try {
						Agent::tickQueueRow($row);
					} catch (\Throwable $e) {
						BatchQueue::mark((int) $row['ID'], BatchQueue::STATUS_FAILED, [
							'ERROR' => $e->getMessage(),
						]);
					}
				}
			}
			$list = BatchQueue::listRecent(
				(int) ($_POST['limit'] ?? 40),
				trim((string) ($_POST['batch_key'] ?? '')),
				$entityType
			);
			titlo_json(array_merge(['ok' => true], $list));

		case 'list_names':
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$params = [
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 25),
				'q' => (string) ($_POST['q'] ?? ''),
				'filter' => (string) ($_POST['filter'] ?? 'empty'),
				'active' => (string) ($_POST['active'] ?? 'Y'),
			];
			$list = $entityType === 'S'
				? CatalogRepository::listSectionsForPhraseCheck($params)
				: CatalogRepository::listElementsForPhraseCheck($params);
			titlo_json(array_merge(['ok' => true, 'entity_type' => $entityType], $list));

		case 'save_phrase':
			$id = (int) ($_POST['entity_id'] ?? 0);
			if ($id <= 0) {
				titlo_json(['ok' => false, 'error' => 'entity_id required'], 422);
			}
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$skip = null;
			if (array_key_exists('skip', $_POST)) {
				$skip = in_array((string) $_POST['skip'], ['1', 'Y', 'y', 'true'], true);
			}
			$ok = $entityType === 'S'
				? CatalogRepository::saveSectionPhrase($id, (string) ($_POST['phrase'] ?? ''), $skip)
				: CatalogRepository::saveElementPhrase($id, (string) ($_POST['phrase'] ?? ''), $skip);
			titlo_json([
				'ok' => (bool) $ok,
				'error' => $ok ? null : 'update failed',
				'phrase' => $ok
					? ($entityType === 'S' ? CatalogRepository::getSectionPhrase($id) : CatalogRepository::getElementPhrase($id))
					: '',
				'phrase_at' => $ok
					? ($entityType === 'S' ? CatalogRepository::getSectionPhraseAt($id) : CatalogRepository::getElementPhraseAt($id))
					: '',
			]);

		case 'clear_phrase':
			$id = (int) ($_POST['entity_id'] ?? 0);
			if ($id <= 0) {
				titlo_json(['ok' => false, 'error' => 'entity_id required'], 422);
			}
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$ok = $entityType === 'S'
				? CatalogRepository::saveSectionPhrase($id, '', null)
				: CatalogRepository::saveElementPhrase($id, '', null);
			titlo_json([
				'ok' => (bool) $ok,
				'error' => $ok ? null : 'update failed',
				'phrase' => '',
				'phrase_at' => '',
			]);

		case 'save_skip':
			$id = (int) ($_POST['entity_id'] ?? 0);
			if ($id <= 0) {
				titlo_json(['ok' => false, 'error' => 'entity_id required'], 422);
			}
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			$skip = in_array((string) ($_POST['skip'] ?? ''), ['1', 'Y', 'y', 'true'], true);
			$ok = $entityType === 'S'
				? CatalogRepository::saveSectionSkip($id, $skip)
				: CatalogRepository::saveElementSkip($id, $skip);
			titlo_json(['ok' => (bool) $ok, 'error' => $ok ? null : 'update failed']);

		case 'generate_phrase':
			$id = (int) ($_POST['entity_id'] ?? 0);
			$name = trim((string) ($_POST['name'] ?? ''));
			$url = '';
			$entityType = strtoupper((string) ($_POST['entity_type'] ?? 'E')) === 'S' ? 'S' : 'E';
			if ($id > 0) {
				$belongs = $entityType === 'S'
					? Config::sectionBelongsToCatalog($id)
					: Config::elementBelongsToCatalog($id);
				if (!$belongs) {
					titlo_json(['ok' => false, 'error' => 'entity not in catalog iblock'], 404);
				}
				$entity = $entityType === 'S'
					? CatalogRepository::findSection($id)
					: CatalogRepository::findElement($id);
				if ($entity) {
					if ($name === '') {
						$name = $entity['name'];
					}
					$url = $entity['url'];
				}
			}
			if ($name === '') {
				titlo_json(['ok' => false, 'error' => 'name required'], 422);
			}
			if ($url !== '' && !Config::isAllowedAnalysisUrl($url) && !preg_match('#^/#', $url)) {
				// относительный path ок; абсолютный — только хост магазина
				titlo_json(['ok' => false, 'error' => 'url must belong to configured site host'], 422);
			}
			$promptId = (int) ($_POST['prompt_id'] ?? 0);
			$res = $client->startPhraseGenerate($name, $url, $promptId, $entityType);
			titlo_json([
				'ok' => true,
				'record_id' => $res['record_id'] ?? null,
				'status' => $res['status'] ?? 'pending',
			]);

		case 'list_prompts':
			$type = trim((string) ($_POST['type'] ?? ''));
			$runMode = trim((string) ($_POST['run_mode'] ?? ''));
			$filterTypes = [
				\Titlo\Relevance\Prompts::TYPE_DETAIL => true,
				\Titlo\Relevance\Prompts::TYPE_CATEGORY => true,
			];
			if ($type === '') {
				$payload = [];
				foreach (array_keys(\Titlo\Relevance\Prompts::catalog()) as $t) {
					$mode = isset($filterTypes[$t]) ? $runMode : '';
					$payload[$t] = \Titlo\Relevance\Prompts::selectPayload($t, $mode);
				}
				titlo_json(['ok' => true, 'by_type' => $payload]);
			}
			$mode = isset($filterTypes[$type]) ? $runMode : '';
			titlo_json(array_merge(['ok' => true], \Titlo\Relevance\Prompts::selectPayload($type, $mode)));

		case 'bulk_phrase_start':
			if ((string) ($_POST['confirm'] ?? '') !== '1') {
				titlo_json(['ok' => false, 'error' => 'confirm required'], 422);
			}
			$res = PhraseBulk::start(
				(string) ($_POST['filter'] ?? 'todo'),
				(string) ($_POST['q'] ?? ''),
				(int) ($_POST['limit'] ?? PhraseBulk::DEFAULT_LIMIT),
				(int) ($_POST['prompt_id'] ?? 0),
				(string) ($_POST['entity_type'] ?? 'E')
			);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'bulk_phrase_tick':
			titlo_json(PhraseBulk::tick());

		case 'bulk_phrase_status':
			titlo_json(PhraseBulk::status(
				isset($_POST['batch_id']) ? (int) $_POST['batch_id'] : null
			));

		case 'bulk_phrase_stop':
			titlo_json(PhraseBulk::stop(
				isset($_POST['batch_id']) ? (int) $_POST['batch_id'] : null
			));

		case 'list_duplicates':
			@set_time_limit(120);
			$list = ProductDuplicates::listClusters([
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 15),
				'q' => (string) ($_POST['q'] ?? ''),
				'status' => (string) ($_POST['status'] ?? 'open'),
				'min_score' => (int) ($_POST['min_score'] ?? ProductDuplicates::SIMILARITY_MIN),
			]);
			titlo_json(array_merge(['ok' => true], $list));

		case 'dup_decision':
			$id = (int) ($_POST['entity_id'] ?? 0);
			$decision = trim((string) ($_POST['decision'] ?? ''));
			$res = ProductDuplicates::applyDecision($id, $decision);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'dup_cluster_noindex_others':
			$keepId = (int) ($_POST['keep_id'] ?? 0);
			$rawIds = $_POST['ids'] ?? [];
			if (is_string($rawIds)) {
				$rawIds = preg_split('/\s*,\s*/', $rawIds) ?: [];
			}
			if (!is_array($rawIds)) {
				$rawIds = [];
			}
			$res = ProductDuplicates::applyClusterKeepAndNoindexOthers($keepId, $rawIds);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'list_section_duplicates':
			@set_time_limit(120);
			$list = SectionDuplicates::listClusters([
				'page' => (int) ($_POST['page'] ?? 1),
				'page_size' => (int) ($_POST['page_size'] ?? 15),
				'q' => (string) ($_POST['q'] ?? ''),
				'status' => (string) ($_POST['status'] ?? 'open'),
				'min_score' => (int) ($_POST['min_score'] ?? SectionDuplicates::SIMILARITY_MIN),
			]);
			titlo_json(array_merge(['ok' => true], $list));

		case 'section_dup_decision':
			$id = (int) ($_POST['entity_id'] ?? 0);
			$decision = trim((string) ($_POST['decision'] ?? ''));
			$res = SectionDuplicates::applyDecision($id, $decision);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'section_dup_cluster_noindex_others':
			$keepId = (int) ($_POST['keep_id'] ?? 0);
			$rawIds = $_POST['ids'] ?? [];
			if (is_string($rawIds)) {
				$rawIds = preg_split('/\s*,\s*/', $rawIds) ?: [];
			}
			if (!is_array($rawIds)) {
				$rawIds = [];
			}
			$res = SectionDuplicates::applyClusterKeepAndNoindexOthers($keepId, $rawIds);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'section_dup_cluster_noindex_all':
			$rawIds = $_POST['ids'] ?? [];
			if (is_string($rawIds)) {
				$rawIds = preg_split('/\s*,\s*/', $rawIds) ?: [];
			}
			if (!is_array($rawIds)) {
				$rawIds = [];
			}
			$res = SectionDuplicates::applyClusterNoindexAll($rawIds);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		case 'dup_cluster_noindex_all':
			$rawIds = $_POST['ids'] ?? [];
			if (is_string($rawIds)) {
				$rawIds = preg_split('/\s*,\s*/', $rawIds) ?: [];
			}
			if (!is_array($rawIds)) {
				$rawIds = [];
			}
			$res = ProductDuplicates::applyClusterNoindexAll($rawIds);
			titlo_json($res, !empty($res['ok']) ? 200 : 422);

		default:
			titlo_json(['ok' => false, 'error' => 'unknown action'], 400);
	}
} catch (\Throwable $e) {
	if (class_exists(\Titlo\Relevance\AuditLog::class)) {
		try {
			\Titlo\Relevance\AuditLog::write('ajax_error', [
				'action' => $action ?? '',
				'message' => $e->getMessage(),
			]);
		} catch (\Throwable $ignore) {
		}
	}
	titlo_json(['ok' => false, 'error' => 'internal error'], 500);
}
