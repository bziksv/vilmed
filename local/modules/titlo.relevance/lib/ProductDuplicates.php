<?php

namespace Titlo\Relevance;

/**
 * Поиск дублей товаров по схожести названий / коротких фраз.
 * Закрытие от индексации — через IndexingControl (UF + SEO, без привязки к сайту).
 */
class ProductDuplicates
{
	public const UF_DECISION = 'UF_TITLO_DUP_DECISION'; // keep | noindex | ''

	public const DECISION_KEEP = 'keep';
	public const DECISION_NOINDEX = 'noindex';

	/** Порог similar_text (0–100) внутри бакета. */
	public const SIMILARITY_MIN = 80;

	public static function ensureFields(): void
	{
		if (!\Bitrix\Main\Loader::includeModule('iblock')) {
			return;
		}
		IndexingControl::ensureFields();
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return;
		}
		$entityId = 'IBLOCK_' . $iblockId . '_ELEMENT';
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
			'XML_ID' => self::UF_DECISION,
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
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: решение по дублю', 'en' => 'Titlo duplicate decision'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Дубль', 'en' => 'Dup'],
			'LIST_FILTER_LABEL' => ['ru' => 'Дубль', 'en' => 'Dup'],
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
		$status = (string) ($params['status'] ?? 'open'); // open|resolved|all|keep|noindex|decided
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

		$products = self::loadProducts($q);
		$clusters = self::buildClusters($products, $minScore);

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

		// Поиск по ID: показать карточку даже без дублей / вне фильтра статуса
		if ($searchId > 0 && $filtered === [] && isset($products[$searchId])) {
			$item = $products[$searchId];
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
	 * Список карточек с решением (даже если больше не попадают в кластер).
	 *
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
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
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

		$where = [
			'BE.IBLOCK_ID = ' . (int) $iblockId,
			'(BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)',
			'(BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)',
		];
		if ($status === 'keep') {
			$where[] = "UTS.{$decisionCol} = 'keep'";
		} elseif ($status === 'noindex') {
			$where[] = "UTS.{$decisionCol} = 'noindex'";
		} else {
			$where[] = "UTS.{$decisionCol} IN ('keep','noindex')";
		}
		if ($q !== '') {
			if (ctype_digit($q)) {
				$where[] = 'BE.ID = ' . (int) $q;
			} else {
				$like = Config::forLike($q);
				$where[] = '(BE.NAME LIKE "%' . $like . '%" OR BE.CODE LIKE "%' . $like . '%")';
			}
		}

		$whereSql = implode(' AND ', $where);
		$countRow = $DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_element BE
			INNER JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
		")->Fetch();
		$total = (int) ($countRow['CNT'] ?? 0);
		$pages = max(1, (int) ceil(max(1, $total) / $pageSize));
		if ($total === 0) {
			$pages = 1;
		}
		$offset = ($page - 1) * $pageSize;

		$res = $DB->Query("
			SELECT BE.ID, BE.NAME, BE.CODE, UTS.{$decisionCol} AS TITLO_DECISION,
				UTS." . UserFields::PHRASE_FIELD . " AS TITLO_PHRASE
			FROM b_iblock_element BE
			INNER JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
			ORDER BY UTS.{$decisionCol} ASC, BE.ID DESC
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		');

		$items = [];
		$ids = [];
		while ($row = $res->Fetch()) {
			$id = (int) $row['ID'];
			$ids[] = $id;
			$code = (string) $row['CODE'];
			$decision = trim((string) ($row['TITLO_DECISION'] ?? ''));
			$items[] = [
				'id' => $id,
				'name' => (string) $row['NAME'],
				'code' => $code,
				'url' => '',
				'phrase' => trim((string) ($row['TITLO_PHRASE'] ?? '')),
				'decision' => $decision,
				'noindex' => false,
				'sim_to_top' => null,
				'sim_label' => '',
			];
		}
		$urlMap = UrlBuilder::mapElementUrls($ids);
		foreach ($items as &$item) {
			$item['url'] = $urlMap[$item['id']] ?? UrlBuilder::product($item['code'], $item['id']);
		}
		unset($item);
		$noindexMap = IndexingControl::mapNoindex($ids);
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
	 * У каждой строки — % похожести к первой (эталонной) карточке кластера.
	 *
	 * @param array<int, array> $items
	 * @return array<int, array>
	 */
	protected static function annotateSimilarities(array $items): array
	{
		if ($items === []) {
			return $items;
		}
		$top = $items[0];
		$topNorm = (string) ($top['norm'] ?? self::normalize($top['phrase'] !== '' ? $top['phrase'] : $top['name']));
		foreach ($items as $i => &$item) {
			$norm = (string) ($item['norm'] ?? self::normalize($item['phrase'] !== '' ? $item['phrase'] : $item['name']));
			if ($i === 0) {
				$item['sim_to_top'] = 100;
				$item['sim_label'] = 'эталон кластера';
			} else {
				$score = self::similarity($topNorm, $norm);
				$item['sim_to_top'] = $score;
				$item['sim_label'] = 'похож на #' . $top['id'] . ' на ' . $score . '%';
			}
		}
		unset($item);
		return $items;
	}

	/**
	 * @return array<int, array{id:int,name:string,code:string,url:string,phrase:string,noindex:bool,decision:string,norm:string,bucket:string}>
	 */
	protected static function loadProducts(string $q): array
	{
		$q = trim($q);
		if ($q !== '' && ctype_digit($q)) {
			return self::loadProductsAroundId((int) $q);
		}

		return self::fetchProducts($q, true);
	}

	/**
	 * По ID: сама карточка + кандидаты в тот же бакет (иначе кластер из 1 элемента не находится).
	 *
	 * @return array<int, array>
	 */
	protected static function loadProductsAroundId(int $id): array
	{
		if ($id <= 0) {
			return [];
		}

		$seedMap = self::fetchProductsByIds([$id], false);
		if ($seedMap === []) {
			return [];
		}
		$seed = $seedMap[$id];
		$bucket = (string) ($seed['bucket'] ?? '');
		$out = [$id => $seed];

		$tokens = self::searchTokensFromNorm($seed['norm']);
		if ($tokens === [] && $bucket === '') {
			return $out;
		}

		// Кандидаты по значимым словам названия (без полной выгрузки каталога)
		$likeParts = [];
		global $DB;
		foreach (array_slice($tokens !== [] ? $tokens : preg_split('/\s+/u', $bucket) ?: [], 0, 3) as $tok) {
			$tok = trim((string) $tok);
			if ($tok === '' || mb_strlen($tok) < 3) {
				continue;
			}
			$likeParts[] = 'BE.NAME LIKE "%' . Config::forLike($tok) . '%"';
		}
		if ($likeParts === []) {
			return $out;
		}

		// AND по 2+ токенам сужает шум; при одном токене — OR с ID уже в seed
		$whereExtra = '(' . implode(' AND ', $likeParts) . ') OR BE.ID = ' . (int) $id;
		$candidates = self::fetchProductsSql($whereExtra, true);

		foreach ($candidates as $cid => $item) {
			if ($bucket !== '' && (string) ($item['bucket'] ?? '') !== $bucket) {
				// оставляем близкие по LIKE даже с другим бакетом — similarity отрежет в buildClusters
				// но для скорости держим тот же бакет, если он есть
				continue;
			}
			$out[$cid] = $item;
		}
		$out[$id] = $seed;

		// Если бакет отфильтровал всех — всё равно оставим LIKE-кандидатов (до 60)
		if (count($out) < 2 && $bucket !== '') {
			$n = 0;
			foreach ($candidates as $cid => $item) {
				$out[$cid] = $item;
				if (++$n >= 60) {
					break;
				}
			}
			$out[$id] = $seed;
		}

		return $out;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, array>
	 */
	protected static function fetchProductsByIds(array $ids, bool $activeOnly): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		if ($ids === []) {
			return [];
		}
		global $DB;
		$iblockId = Config::iblockId();
		$whereExtra = 'BE.ID IN (' . implode(',', $ids) . ')';
		return self::fetchProductsSql($whereExtra, $activeOnly);
	}

	/**
	 * @return array<int, array>
	 */
	protected static function fetchProducts(string $q, bool $activeOnly): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;

		$parts = [];
		$q = trim($q);
		if ($q !== '') {
			if (ctype_digit($q)) {
				$parts[] = 'BE.ID = ' . (int) $q;
			} else {
				$like = Config::forLike($q);
				$parts[] = '(BE.NAME LIKE "%' . $like . '%" OR BE.CODE LIKE "%' . $like . '%" OR UTS.' . $phraseCol . ' LIKE "%' . $like . '%")';
			}
		}

		$whereExtra = $parts !== [] ? implode(' AND ', $parts) : '1=1';
		return self::fetchProductsSql($whereExtra, $activeOnly);
	}

	/**
	 * @return array<int, array>
	 */
	protected static function fetchProductsSql(string $whereExtra, bool $activeOnly): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;
		$decisionCol = self::UF_DECISION;

		$where = [
			'BE.IBLOCK_ID = ' . (int) $iblockId,
			'(BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)',
			'(BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)',
			$whereExtra,
		];
		if ($activeOnly) {
			$where[] = "BE.ACTIVE = 'Y'";
		}

		$hasDecision = self::hasDecisionColumn();
		$decisionSelect = $hasDecision ? ('UTS.' . $decisionCol . ' AS TITLO_DECISION') : 'NULL AS TITLO_DECISION';

		$sql = '
			SELECT
				BE.ID,
				BE.NAME,
				BE.CODE,
				UTS.' . $phraseCol . ' AS TITLO_PHRASE,
				' . $decisionSelect . '
			FROM b_iblock_element BE
			LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BE.ID
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY BE.ID DESC
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
			$phrase = trim((string) ($row['TITLO_PHRASE'] ?? ''));
			$decision = trim((string) ($row['TITLO_DECISION'] ?? ''));
			if ($decision !== self::DECISION_KEEP && $decision !== self::DECISION_NOINDEX) {
				$decision = '';
			}
			$norm = self::normalize($phrase !== '' ? $phrase : $name);
			$bucket = self::bucketKey($norm);
			$items[$id] = [
				'id' => $id,
				'name' => $name,
				'code' => $code,
				'url' => '',
				'phrase' => $phrase,
				'noindex' => false,
				'decision' => $decision,
				'norm' => $norm,
				'bucket' => $bucket,
			];
		}

		$urlMap = UrlBuilder::mapElementUrls($ids);
		foreach ($items as $id => &$item) {
			$item['url'] = $urlMap[$id] ?? UrlBuilder::product($item['code'], $id);
		}
		unset($item);

		$noindexMap = IndexingControl::mapNoindex($ids);
		foreach ($noindexMap as $id => $flag) {
			if (isset($items[$id])) {
				$items[$id]['noindex'] = (bool) $flag;
			}
		}

		return $items;
	}

	/**
	 * @return string[]
	 */
	protected static function searchTokensFromNorm(string $norm): array
	{
		$tokens = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$out = [];
		foreach ($tokens as $t) {
			$t = trim($t, '-/');
			if ($t === '' || self::isWeakToken($t) || self::isGenericToken($t) || mb_strlen($t) < 4) {
				continue;
			}
			$out[] = $t;
			if (count($out) >= 4) {
				break;
			}
		}
		return $out;
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

	/** @var bool|null */
	protected static $decisionColumnCache = null;

	protected static function hasDecisionColumn(): bool
	{
		global $DB;
		if (self::$decisionColumnCache !== null) {
			return self::$decisionColumnCache;
		}
		$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_element';
		$res = $DB->Query("SHOW COLUMNS FROM {$uts} LIKE '" . self::UF_DECISION . "'");
		self::$decisionColumnCache = (bool) $res->Fetch();
		return self::$decisionColumnCache;
	}

	/**
	 * @param array<int, array> $products
	 * @return array<int, array{key:string,score:int,items:array,size:int}>
	 */
	protected static function buildClusters(array $products, int $minScore): array
	{
		$buckets = [];
		foreach ($products as $p) {
			$b = $p['bucket'];
			if ($b === '') {
				continue;
			}
			$buckets[$b][] = $p;
		}

		$clusters = [];
		foreach ($buckets as $key => $group) {
			if (count($group) < 2) {
				continue;
			}
			if (count($group) > 80) {
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
					$score = self::similarity($group[$i]['norm'], $group[$j]['norm']);
					if ($score >= $minScore) {
						$members[] = $group[$j];
						$used[$j] = true;
						$maxScore = min($maxScore, $score);
					}
				}
				if (count($members) < 2) {
					continue;
				}
				// Линейки без модели (десятки размеров) — не дубли для SEO
				$hasModel = (bool) preg_match('/[a-z].*\d|\d.*[a-z]/ui', $key);
				if (!$hasModel && count($members) > 6) {
					continue;
				}
				if (count($members) > 20) {
					continue;
				}
				usort($members, static function ($a, $b) {
					$as = ($a['phrase'] !== '' ? 0 : 1) + ($a['noindex'] ? 2 : 0);
					$bs = ($b['phrase'] !== '' ? 0 : 1) + ($b['noindex'] ? 2 : 0);
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

	public static function normalize(string $name): string
	{
		$s = mb_strtolower(trim($name));
		$s = str_replace(['ё'], ['е'], $s);
		$s = preg_replace('/\([^)]*(кита|германи|росси|япони|итали|франци|сша|usa|china|germany)[^)]*\)/ui', ' ', $s);
		$s = preg_replace('/\b(китай|китая|germany|china|сша|usa)\b/ui', ' ', $s);
		$s = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $s);
		$s = preg_replace('/\s+/u', ' ', $s);
		return trim((string) $s);
	}

	public static function bucketKey(string $norm): string
	{
		$tokens = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$sig = [];
		foreach ($tokens as $t) {
			$t = trim($t, '-/');
			if ($t === '' || self::isWeakToken($t) || self::isGenericToken($t)) {
				continue;
			}
			// бренд/модель: латиница ≥2 букв или смесь букв+цифр (dc-70, sv300)
			if (preg_match('/[a-z]{2,}/u', $t) || preg_match('/[a-z]+\-?\d|\d+\-?[a-z]/ui', $t)) {
				$sig[] = $t;
			}
		}
		$sig = array_values(array_unique($sig));
		sort($sig, SORT_STRING);
		if (count($sig) >= 2) {
			return implode(' ', array_slice($sig, 0, 6));
		}
		if (count($sig) === 1) {
			$idx = array_search($sig[0], $tokens, true);
			$around = [];
			if ($idx !== false) {
				for ($k = max(0, (int) $idx - 1); $k <= min(count($tokens) - 1, (int) $idx + 2); $k++) {
					$tok = $tokens[$k];
					if (!self::isWeakToken($tok) && !self::isGenericToken($tok)) {
						$around[] = $tok;
					}
				}
			}
			$around = array_values(array_unique($around !== [] ? $around : $sig));
			// один бренд без модели — слабо; требуем ещё слово или цифровую модель рядом
			if (count($around) < 2 && !preg_match('/\d/u', implode('', $around))) {
				return '';
			}
			return implode(' ', $around);
		}

		// Без бренда — только если есть ≥3 нетиповых русских слова (иначе шум)
		$ru = [];
		foreach ($tokens as $t) {
			if (self::isWeakToken($t) || self::isGenericToken($t) || mb_strlen($t) < 5) {
				continue;
			}
			if (preg_match('/^[а-я]+$/u', $t)) {
				$ru[] = $t;
			}
		}
		$ru = array_values(array_unique($ru));
		if (count($ru) < 3) {
			return '';
		}
		return implode(' ', array_slice($ru, 0, 4));
	}

	protected static function isWeakToken(string $t): bool
	{
		$t = mb_strtolower($t);
		if (in_array($t, ['a1', 'a2', 'а1', 'а2', 'м', 'm', 'мм', 'шт', 'см', 'для', 'или', 'без', 'с', 'и', 'на', 'от', 'по'], true)) {
			return true;
		}
		if (preg_match('/^\d+([\/\-]\d+)?$/u', $t)) {
			return true;
		}
		return false;
	}

	/** Слишком общие слова каталога — не ключ кластера сами по себе. */
	protected static function isGenericToken(string $t): bool
	{
		$t = mb_strtolower($t);
		static $stop = null;
		if ($stop === null) {
			$stop = array_flip([
				'шовный', 'материал', 'игла', 'иглы', 'изгиб', 'колющая', 'режущая', 'петля',
				'медицинский', 'медицинская', 'плакат', 'плакаты', 'система', 'аппарат',
				'ультразвуковой', 'ультразвуковая', 'диагностическая', 'диагностический',
				'сканер', 'датчик', 'набор', 'комплект', 'модель', 'серия', 'класса',
				'экспертного', 'портативный', 'стационарный', 'мобильный', 'инструмент',
				'монополярный', 'биполярный', 'одноразовый', 'многоразовый',
			]);
		}
		return isset($stop[$t]);
	}

	public static function similarity(string $a, string $b): int
	{
		if ($a === '' || $b === '') {
			return 0;
		}
		if ($a === $b) {
			return 100;
		}
		similar_text($a, $b, $pct);
		return (int) round($pct);
	}

	/**
	 * @return array{ok:bool,error?:string}
	 */
	public static function applyDecision(int $elementId, string $decision): array
	{
		if ($elementId <= 0) {
			return ['ok' => false, 'error' => 'bad id'];
		}
		if (!Config::elementBelongsToCatalog($elementId)) {
			return ['ok' => false, 'error' => 'element not in catalog iblock'];
		}
		if (!in_array($decision, [self::DECISION_KEEP, self::DECISION_NOINDEX, 'clear'], true)) {
			return ['ok' => false, 'error' => 'bad decision'];
		}

		self::ensureFields();

		if ($decision === 'clear') {
			self::setDecisionUf($elementId, '');
			IndexingControl::setNoindex($elementId, false);
			AuditLog::write('dup_decision_clear', ['element_id' => $elementId]);
			return ['ok' => true];
		}

		if ($decision === self::DECISION_KEEP) {
			self::setDecisionUf($elementId, self::DECISION_KEEP);
			IndexingControl::setNoindex($elementId, false);
			return ['ok' => true];
		}

		self::setDecisionUf($elementId, self::DECISION_NOINDEX);
		$idx = IndexingControl::setNoindex($elementId, true);
		return [
			'ok' => !empty($idx['ok']),
			'channels' => $idx['channels'] ?? [],
			'error' => $idx['error'] ?? null,
		];
	}

	/**
	 * Эталон keep + остальные noindex (кнопка на весь кластер).
	 *
	 * @param int[] $ids все ID кластера (включая эталон)
	 * @return array{ok:bool,keep_id?:int,noindex_count?:int,error?:string}
	 */
	public static function applyClusterKeepAndNoindexOthers(int $keepId, array $ids): array
	{
		$ids = Config::filterCatalogElementIds($ids, 200);
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

		AuditLog::write('dup_cluster_noindex_others', [
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
	 * Закрыть весь блок (включая эталон) от индексации.
	 *
	 * @param int[] $ids
	 * @return array{ok:bool,noindex_count?:int,error?:string}
	 */
	public static function applyClusterNoindexAll(array $ids): array
	{
		$ids = Config::filterCatalogElementIds($ids, 200);
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

		AuditLog::write('dup_cluster_noindex_all', [
			'ids' => $ids,
			'noindex_count' => $n,
		]);

		return [
			'ok' => $errors === [],
			'noindex_count' => $n,
			'error' => $errors !== [] ? implode('; ', array_slice($errors, 0, 5)) : null,
		];
	}

	protected static function setDecisionUf(int $elementId, string $value): void
	{
		$iblockId = Config::iblockId();
		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update('IBLOCK_' . $iblockId . '_ELEMENT', $elementId, [
				self::UF_DECISION => $value !== '' ? $value : false,
			]);
		}

		global $DB;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		if (!self::hasDecisionColumn()) {
			// Bitrix создаст колонку после Add UF — invalidate cache
			self::$decisionColumnCache = null;
			return;
		}
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $elementId)->Fetch();
		$sqlVal = $value !== '' ? ("'" . $DB->ForSql($value) . "'") : 'NULL';
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . self::UF_DECISION . '=' . $sqlVal . ' WHERE VALUE_ID=' . (int) $elementId);
		} else {
			$DB->Query('INSERT INTO ' . $uts . ' (VALUE_ID, ' . self::UF_DECISION . ') VALUES (' . (int) $elementId . ', ' . $sqlVal . ')');
		}
	}

	public static function setRobotsNoindex(int $elementId, bool $on): void
	{
		IndexingControl::setNoindex($elementId, $on);
	}
}
