<?php

namespace Titlo\Relevance;

/**
 * Дубли категорий (разделов инфоблока) — тот же принцип, что у товаров:
 * кластеры похожих названий → keep / noindex.
 */
class SectionDuplicates
{
	public const UF_DECISION = 'UF_TITLO_DUP_DECISION';
	public const DECISION_KEEP = 'keep';
	public const DECISION_NOINDEX = 'noindex';
	public const SIMILARITY_MIN = 80;

	public static function ensureFields(): void
	{
		if (!\Bitrix\Main\Loader::includeModule('iblock')) {
			return;
		}
		IndexingControl::ensureSectionFields();
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return;
		}
		$entityId = 'IBLOCK_' . $iblockId . '_SECTION';
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::UF_DECISION,
		])->Fetch();
		if ($exists) {
			return;
		}
		$o = new \CUserTypeEntity();
		$o->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::UF_DECISION,
			'USER_TYPE_ID' => 'string',
			'XML_ID' => self::UF_DECISION . '_S',
			'SORT' => 120,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'S',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => [
				'SIZE' => 20,
				'ROWS' => 1,
				'MIN_LENGTH' => 0,
				'MAX_LENGTH' => 20,
				'DEFAULT_VALUE' => '',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: решение по дублю раздела', 'en' => 'Titlo section dup decision'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Дубль раздела', 'en' => 'Section dup'],
			'LIST_FILTER_LABEL' => ['ru' => 'Дубль раздела', 'en' => 'Section dup'],
			'HELP_MESSAGE' => [
				'ru' => 'keep = прорабатывать; noindex = закрыть от индексации',
				'en' => 'keep / noindex',
			],
		]);
	}

	/**
	 * @param array{q?:string,status?:string,page?:int,page_size?:int,min_score?:int} $params
	 * @return array{clusters:array,total:int,page:int,pages:int,page_size:int,mode?:string,items?:array}
	 */
	public static function listClusters(array $params): array
	{
		self::ensureFields();

		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(50, (int) ($params['page_size'] ?? 20)));
		$q = trim((string) ($params['q'] ?? ''));
		$status = (string) ($params['status'] ?? 'open');
		$minScore = (int) ($params['min_score'] ?? self::SIMILARITY_MIN);
		if ($minScore < 50) {
			$minScore = 50;
		}
		if ($minScore > 99) {
			$minScore = 99;
		}

		if (in_array($status, ['keep', 'noindex', 'decided'], true)) {
			return self::listDecisions([
				'page' => $page,
				'page_size' => $pageSize,
				'q' => $q,
				'status' => $status,
			]);
		}

		$sections = self::loadSections($q);
		$clusters = self::buildClusters($sections, $minScore);
		$searchId = (ctype_digit($q) && $q !== '') ? (int) $q : 0;

		$filtered = [];
		foreach ($clusters as $cluster) {
			if ($searchId > 0 && !self::clusterHasId($cluster, $searchId)) {
				continue;
			}
			$st = self::clusterStatus($cluster);
			if ($status === 'open' && $st !== 'open') {
				continue;
			}
			if ($status === 'resolved' && $st !== 'resolved') {
				continue;
			}
			$cluster['status'] = $st;
			$cluster['items'] = self::annotateSimilarities($cluster['items']);
			$filtered[] = $cluster;
		}

		if ($searchId > 0 && $filtered === [] && isset($sections[$searchId])) {
			$item = $sections[$searchId];
			$filtered[] = [
				'key' => $item['bucket'] !== '' ? $item['bucket'] : ('id:' . $searchId),
				'score' => 100,
				'items' => self::annotateSimilarities([$item]),
				'size' => 1,
				'status' => 'open',
				'single' => true,
			];
		}

		$total = count($filtered);
		$pages = max(1, (int) ceil($total / $pageSize));
		$slice = array_slice($filtered, ($page - 1) * $pageSize, $pageSize);

		return [
			'mode' => 'clusters',
			'clusters' => $slice,
			'total' => $total,
			'page' => $page,
			'pages' => $pages,
			'page_size' => $pageSize,
		];
	}

	/**
	 * @param array{q?:string,status?:string,page?:int,page_size?:int} $params
	 * @return array{mode:string,items:array,clusters:array,total:int,page:int,pages:int,page_size:int}
	 */
	public static function listDecisions(array $params): array
	{
		self::ensureFields();
		global $DB;

		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(50, (int) ($params['page_size'] ?? 25)));
		$q = trim((string) ($params['q'] ?? ''));
		$status = (string) ($params['status'] ?? 'decided');

		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$decisionCol = self::UF_DECISION;

		if (!self::hasDecisionColumn()) {
			return [
				'mode' => 'decisions',
				'items' => [],
				'clusters' => [],
				'total' => 0,
				'page' => 1,
				'pages' => 1,
				'page_size' => $pageSize,
			];
		}

		$where = ['BS.IBLOCK_ID = ' . (int) $iblockId];
		if ($status === 'keep') {
			$where[] = "UTS.{$decisionCol} = 'keep'";
		} elseif ($status === 'noindex') {
			$where[] = "UTS.{$decisionCol} = 'noindex'";
		} else {
			$where[] = "UTS.{$decisionCol} IN ('keep','noindex')";
		}
		if ($q !== '') {
			if (ctype_digit($q)) {
				$where[] = 'BS.ID = ' . (int) $q;
			} else {
				$like = $DB->ForSql($q);
				$where[] = '(BS.NAME LIKE "%' . $like . '%" OR BS.CODE LIKE "%' . $like . '%")';
			}
		}

		$whereSql = implode(' AND ', $where);
		$countRow = $DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_section BS
			INNER JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
		")->Fetch();
		$total = (int) ($countRow['CNT'] ?? 0);
		$pages = max(1, (int) ceil(max(1, $total) / $pageSize));
		if ($total === 0) {
			$pages = 1;
		}
		$offset = ($page - 1) * $pageSize;

		$res = $DB->Query("
			SELECT BS.ID, BS.NAME, BS.CODE, UTS.{$decisionCol} AS TITLO_DECISION
			FROM b_iblock_section BS
			INNER JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
			ORDER BY UTS.{$decisionCol} ASC, BS.ID DESC
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		');

		$items = [];
		$ids = [];
		while ($row = $res->Fetch()) {
			$id = (int) $row['ID'];
			$ids[] = $id;
			$items[] = [
				'id' => $id,
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'url' => '',
				'decision' => trim((string) ($row['TITLO_DECISION'] ?? '')),
				'noindex' => false,
				'sim_to_top' => null,
				'sim_label' => '',
			];
		}
		$urlMap = UrlBuilder::mapSectionUrls($ids);
		foreach ($items as &$item) {
			$item['url'] = $urlMap[$item['id']] ?? UrlBuilder::category($item['code'], $item['id']);
		}
		unset($item);
		$noindexMap = IndexingControl::mapSectionNoindex($ids);
		foreach ($items as &$item) {
			$item['noindex'] = !empty($noindexMap[$item['id']]);
		}
		unset($item);

		return [
			'mode' => 'decisions',
			'items' => $items,
			'clusters' => [],
			'total' => $total,
			'page' => $page,
			'pages' => $pages,
			'page_size' => $pageSize,
		];
	}

	/**
	 * @param array<int, array> $items
	 * @return array<int, array>
	 */
	protected static function annotateSimilarities(array $items): array
	{
		if ($items === []) {
			return $items;
		}
		$top = $items[0];
		$topNorm = (string) ($top['norm'] ?? ProductDuplicates::normalize($top['name']));
		foreach ($items as $i => &$item) {
			$norm = (string) ($item['norm'] ?? ProductDuplicates::normalize($item['name']));
			if ($i === 0) {
				$item['sim_to_top'] = 100;
				$item['sim_label'] = 'эталон кластера';
			} else {
				$score = ProductDuplicates::similarity($topNorm, $norm);
				$item['sim_to_top'] = $score;
				$item['sim_label'] = 'похож на #' . $top['id'] . ' на ' . $score . '%';
			}
		}
		unset($item);
		return $items;
	}

	/**
	 * @return array<int, array>
	 */
	protected static function loadSections(string $q): array
	{
		$q = trim($q);
		if ($q !== '' && ctype_digit($q)) {
			return self::loadAroundId((int) $q);
		}
		return self::fetchSections($q, true);
	}

	/**
	 * @return array<int, array>
	 */
	protected static function loadAroundId(int $id): array
	{
		if ($id <= 0) {
			return [];
		}
		$seedMap = self::fetchSectionsByIds([$id], false);
		if ($seedMap === []) {
			return [];
		}
		$seed = $seedMap[$id];
		$bucket = (string) ($seed['bucket'] ?? '');
		$extra = self::fetchSections('', true);
		$out = $seedMap;
		foreach ($extra as $sid => $row) {
			if ($bucket !== '' && $row['bucket'] === $bucket) {
				$out[$sid] = $row;
			} elseif (ProductDuplicates::similarity($seed['norm'], $row['norm']) >= self::SIMILARITY_MIN - 5) {
				$out[$sid] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, array>
	 */
	protected static function fetchSectionsByIds(array $ids, bool $activeOnly): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return [];
		}
		return self::fetchSectionsSql('BS.ID IN (' . implode(',', $ids) . ')', $activeOnly);
	}

	/**
	 * @return array<int, array>
	 */
	protected static function fetchSections(string $q, bool $activeOnly): array
	{
		global $DB;
		$parts = [];
		$q = trim($q);
		if ($q !== '') {
			if (ctype_digit($q)) {
				$parts[] = 'BS.ID = ' . (int) $q;
			} else {
				$like = $DB->ForSql($q);
				$parts[] = '(BS.NAME LIKE "%' . $like . '%" OR BS.CODE LIKE "%' . $like . '%")';
			}
		}
		$whereExtra = $parts !== [] ? implode(' AND ', $parts) : '1=1';
		return self::fetchSectionsSql($whereExtra, $activeOnly);
	}

	/**
	 * @return array<int, array>
	 */
	protected static function fetchSectionsSql(string $whereExtra, bool $activeOnly): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$decisionCol = self::UF_DECISION;
		$where = [
			'BS.IBLOCK_ID = ' . (int) $iblockId,
			$whereExtra,
		];
		if ($activeOnly) {
			$where[] = "BS.ACTIVE = 'Y'";
		}

		$hasDecision = self::hasDecisionColumn();
		$decisionSelect = $hasDecision ? ('UTS.' . $decisionCol . ' AS TITLO_DECISION') : 'NULL AS TITLO_DECISION';
		$join = $hasDecision
			? 'LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BS.ID'
			: 'LEFT JOIN ' . $uts . ' UTS ON 1=0';

		$sql = '
			SELECT
				BS.ID,
				BS.NAME,
				BS.CODE,
				' . $decisionSelect . '
			FROM b_iblock_section BS
			' . $join . '
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY BS.ID DESC
			LIMIT 5000
		';

		$items = [];
		$ids = [];
		$res = $DB->Query($sql);
		while ($row = $res->Fetch()) {
			$id = (int) $row['ID'];
			$ids[] = $id;
			$name = (string) $row['NAME'];
			$code = (string) $row['CODE'];
			$decision = trim((string) ($row['TITLO_DECISION'] ?? ''));
			if ($decision !== self::DECISION_KEEP && $decision !== self::DECISION_NOINDEX) {
				$decision = '';
			}
			$norm = ProductDuplicates::normalize($name);
			$bucket = self::bucketKey($norm);
			$items[$id] = [
				'id' => $id,
				'name' => $name,
				'code' => $code,
				'url' => '',
				'noindex' => false,
				'decision' => $decision,
				'norm' => $norm,
				'bucket' => $bucket,
			];
		}

		$urlMap = UrlBuilder::mapSectionUrls($ids);
		foreach ($items as $id => &$item) {
			$item['url'] = $urlMap[$id] ?? UrlBuilder::category($item['code'], $id);
		}
		unset($item);

		$noindexMap = IndexingControl::mapSectionNoindex($ids);
		foreach ($noindexMap as $id => $flag) {
			if (isset($items[$id])) {
				$items[$id]['noindex'] = (bool) $flag;
			}
		}

		return $items;
	}

	protected static function bucketKey(string $norm): string
	{
		$tokens = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$sig = [];
		foreach ($tokens as $t) {
			$t = trim($t, '-/');
			if ($t === '' || mb_strlen($t) < 4) {
				continue;
			}
			if (in_array($t, ['для', 'или', 'без', 'каталог', 'раздел', 'категория', 'товары'], true)) {
				continue;
			}
			$sig[] = $t;
			if (count($sig) >= 3) {
				break;
			}
		}
		if (count($sig) < 2) {
			return $norm !== '' ? mb_substr($norm, 0, 40) : '';
		}
		return implode(' ', $sig);
	}

	/**
	 * @param array{items?:array} $cluster
	 */
	protected static function clusterHasId(array $cluster, int $id): bool
	{
		foreach ($cluster['items'] ?? [] as $item) {
			if ((int) ($item['id'] ?? 0) === $id) {
				return true;
			}
		}
		return false;
	}

	protected static function hasDecisionColumn(): bool
	{
		global $DB;
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}
		$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_section';
		$res = $DB->Query("SHOW TABLES LIKE '" . $DB->ForSql($uts) . "'");
		if (!$res->Fetch()) {
			return $cache = false;
		}
		$res = $DB->Query("SHOW COLUMNS FROM {$uts} LIKE '" . self::UF_DECISION . "'");
		$cache = (bool) $res->Fetch();
		return $cache;
	}

	/**
	 * @param array<int, array> $sections
	 * @return array<int, array{key:string,score:int,items:array,size:int}>
	 */
	protected static function buildClusters(array $sections, int $minScore): array
	{
		$buckets = [];
		foreach ($sections as $p) {
			$b = $p['bucket'];
			if ($b === '') {
				continue;
			}
			$buckets[$b][] = $p;
		}

		$clusters = [];
		foreach ($buckets as $key => $group) {
			if (count($group) < 2 || count($group) > 40) {
				continue;
			}
			$used = [];
			$n = count($group);
			for ($i = 0; $i < $n; $i++) {
				if (isset($used[$i])) {
					continue;
				}
				$members = [$group[$i]];
				$maxScore = 100;
				$used[$i] = true;
				for ($j = $i + 1; $j < $n; $j++) {
					if (isset($used[$j])) {
						continue;
					}
					$score = ProductDuplicates::similarity($group[$i]['norm'], $group[$j]['norm']);
					if ($score >= $minScore) {
						$members[] = $group[$j];
						$used[$j] = true;
						$maxScore = min($maxScore, $score);
					}
				}
				if (count($members) < 2) {
					continue;
				}
				// Линейки размеров / артикулов в разделах — не SEO-дубли
				if (count($members) > 12) {
					continue;
				}
				usort($members, static function ($a, $b) {
					$as = ($a['noindex'] ? 2 : 0);
					$bs = ($b['noindex'] ? 2 : 0);
					if ($as !== $bs) {
						return $as <=> $bs;
					}
					return $a['id'] <=> $b['id'];
				});
				$clusters[] = [
					'key' => $key,
					'score' => (int) $maxScore,
					'items' => $members,
					'size' => count($members),
				];
			}
		}

		usort($clusters, static function ($a, $b) {
			return $b['size'] <=> $a['size'] ?: $b['score'] <=> $a['score'];
		});

		return $clusters;
	}

	/**
	 * @param array{items:array} $cluster
	 */
	protected static function clusterStatus(array $cluster): string
	{
		$keep = 0;
		$closed = 0;
		$n = count($cluster['items']);
		foreach ($cluster['items'] as $item) {
			if ($item['decision'] === self::DECISION_KEEP) {
				$keep++;
			}
			if ($item['decision'] === self::DECISION_NOINDEX || !empty($item['noindex'])) {
				$closed++;
			}
		}
		// Эталон + все остальные закрыты
		if ($keep === 1 && $closed === $n - 1) {
			return 'resolved';
		}
		// Весь блок закрыт от индексации
		if ($n > 0 && $closed === $n) {
			return 'resolved';
		}
		return 'open';
	}

	/**
	 * @return array{ok:bool,error?:string,channels?:array}
	 */
	public static function applyDecision(int $sectionId, string $decision): array
	{
		if ($sectionId <= 0) {
			return ['ok' => false, 'error' => 'bad id'];
		}
		if (!Config::sectionBelongsToCatalog($sectionId)) {
			return ['ok' => false, 'error' => 'section not in catalog iblock'];
		}
		if (!in_array($decision, [self::DECISION_KEEP, self::DECISION_NOINDEX, 'clear'], true)) {
			return ['ok' => false, 'error' => 'bad decision'];
		}

		self::ensureFields();

		if ($decision === 'clear') {
			self::setDecisionUf($sectionId, '');
			IndexingControl::setSectionNoindex($sectionId, false);
			AuditLog::write('section_dup_decision_clear', ['section_id' => $sectionId]);
			return ['ok' => true];
		}

		if ($decision === self::DECISION_KEEP) {
			self::setDecisionUf($sectionId, self::DECISION_KEEP);
			IndexingControl::setSectionNoindex($sectionId, false);
			return ['ok' => true];
		}

		self::setDecisionUf($sectionId, self::DECISION_NOINDEX);
		$idx = IndexingControl::setSectionNoindex($sectionId, true);
		return [
			'ok' => !empty($idx['ok']),
			'channels' => $idx['channels'] ?? [],
			'error' => $idx['error'] ?? null,
		];
	}

	/**
	 * @param int[] $ids
	 * @return array{ok:bool,keep_id?:int,noindex_count?:int,error?:string}
	 */
	public static function applyClusterKeepAndNoindexOthers(int $keepId, array $ids): array
	{
		$ids = Config::filterCatalogSectionIds($ids, 200);
		if ($keepId <= 0 || $ids === [] || !in_array($keepId, $ids, true)) {
			return ['ok' => false, 'error' => 'bad cluster ids'];
		}
		self::ensureFields();

		$keep = self::applyDecision($keepId, self::DECISION_KEEP);
		if (empty($keep['ok'])) {
			return ['ok' => false, 'error' => $keep['error'] ?? 'keep failed'];
		}

		$n = 0;
		$errors = [];
		foreach ($ids as $id) {
			if ($id === $keepId) {
				continue;
			}
			$r = self::applyDecision($id, self::DECISION_NOINDEX);
			if (!empty($r['ok'])) {
				$n++;
			} else {
				$errors[] = '#' . $id . ': ' . ($r['error'] ?? 'fail');
			}
		}

		AuditLog::write('section_dup_cluster_noindex_others', [
			'keep_id' => $keepId,
			'ids' => $ids,
			'noindex_count' => $n,
		]);

		return [
			'ok' => $errors === [],
			'keep_id' => $keepId,
			'noindex_count' => $n,
			'error' => $errors !== [] ? implode('; ', array_slice($errors, 0, 5)) : null,
		];
	}

	/**
	 * @param int[] $ids
	 * @return array{ok:bool,noindex_count?:int,error?:string}
	 */
	public static function applyClusterNoindexAll(array $ids): array
	{
		$ids = Config::filterCatalogSectionIds($ids, 200);
		if ($ids === []) {
			return ['ok' => false, 'error' => 'bad cluster ids'];
		}
		self::ensureFields();

		$n = 0;
		$errors = [];
		foreach ($ids as $id) {
			$r = self::applyDecision($id, self::DECISION_NOINDEX);
			if (!empty($r['ok'])) {
				$n++;
			} else {
				$errors[] = '#' . $id . ': ' . ($r['error'] ?? 'fail');
			}
		}

		AuditLog::write('section_dup_cluster_noindex_all', [
			'ids' => $ids,
			'noindex_count' => $n,
		]);

		return [
			'ok' => $errors === [],
			'noindex_count' => $n,
			'error' => $errors !== [] ? implode('; ', array_slice($errors, 0, 5)) : null,
		];
	}

	protected static function setDecisionUf(int $sectionId, string $value): void
	{
		$iblockId = Config::iblockId();
		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update('IBLOCK_' . $iblockId . '_SECTION', $sectionId, [
				self::UF_DECISION => $value !== '' ? $value : false,
			]);
		}

		global $DB;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		if (!self::hasDecisionColumn()) {
			return;
		}
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $sectionId)->Fetch();
		$sqlVal = $value !== '' ? ("'" . $DB->ForSql($value) . "'") : 'NULL';
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . self::UF_DECISION . '=' . $sqlVal . ' WHERE VALUE_ID=' . (int) $sectionId);
		} else {
			$DB->Query('INSERT INTO ' . $uts . ' (VALUE_ID, ' . self::UF_DECISION . ') VALUES (' . (int) $sectionId . ', ' . $sqlVal . ')');
		}
	}
}
