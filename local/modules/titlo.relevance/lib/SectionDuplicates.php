<?php

namespace Titlo\Relevance;

/**
 * Дубли категорий (разделов инфоблока): кластеры → keep / noindex.
 *
 * Пара разделов — дубль, если совпадают бренды/модели и размеры/серии,
 * а смысловые слова совпадают по весу (IDF) не ниже порога, либо у разделов
 * одни и те же товары. Вложенные разделы считаются дублем только при полностью
 * одинаковом названии (иначе это фасет «X → X, Япония»).
 */
class SectionDuplicates
{
	public const UF_DECISION = 'UF_TITLO_DUP_DECISION';
	public const DECISION_KEEP = 'keep';
	public const DECISION_NOINDEX = 'noindex';
	public const SIMILARITY_MIN = 80;

	public const KIND_EXACT = 'exact';
	public const KIND_NESTED = 'nested';
	public const KIND_COUNTRY = 'country';
	public const KIND_PRODUCTS = 'products';
	public const KIND_SIMILAR = 'similar';
	/** Одно название, но товары разные — скорее переименовать, чем закрывать. */
	public const KIND_HOMONYM = 'homonym';
	/** Дети одного родителя, отличаются только размером/серией/страной — эталон родитель. */
	public const KIND_VARIANTS = 'variants';

	/** Совпадение товаров, при котором разные по словам разделы — дубль. */
	protected const PRODUCTS_JACCARD_MIN = 80;
	/** Одинаковое название и общих товаров меньше — «тёзки», а не копии. */
	protected const HOMONYM_PRODUCTS_MAX = 30;
	/** Похожие названия и общих товаров меньше — разные разделы (линейки, «с/без»). */
	protected const SIMILAR_PRODUCTS_MIN = 10;
	/** Минимальное сходство слов для дубля «по товарам». */
	protected const PRODUCTS_NAME_MIN = 50;
	/** Слово встречается чаще — по нему кандидатов не ищем (слишком общее). */
	protected const BLOCK_DF_MAX = 400;

	/** @var array<int, string> */
	protected static $redirects = [];
	/** @var array{checked:int,pending:int,blocked:int,excluded:int} */
	protected static $probeStats = ['checked' => 0, 'pending' => 0, 'blocked' => 0, 'excluded' => 0];

	protected const STOP_WORDS = [
		'для', 'или', 'и', 'в', 'во', 'на', 'с', 'со', 'по', 'из', 'к', 'ко', 'от', 'до',
		'под', 'над', 'при', 'про', 'а', 'же', 'каталог', 'раздел', 'категория', 'товары',
		'серии', 'серия',
	];

	protected const COUNTRIES = [
		'россия', 'рф', 'китай', 'кнр', 'германия', 'япония', 'италия', 'франция', 'сша', 'америка',
		'корея', 'южная', 'венгрия', 'турция', 'индия', 'польша', 'тайвань', 'израиль', 'швейцария',
		'великобритания', 'англия', 'чехия', 'австрия', 'испания', 'швеция', 'финляндия', 'дания',
		'бельгия', 'нидерланды', 'голландия', 'беларусь', 'белоруссия', 'украина', 'казахстан',
		'пакистан', 'бразилия', 'канада', 'австралия', 'сингапур', 'малайзия', 'таиланд', 'вьетнам',
		'латвия', 'литва', 'эстония', 'словения', 'словакия', 'хорватия', 'сербия', 'португалия',
		'норвегия', 'ирландия', 'греция', 'мексика', 'аргентина', 'иран', 'египет', 'оаэ',
		'usa', 'china', 'germany', 'japan', 'italy', 'france', 'korea', 'russia', 'uk',
	];

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

		$kind = (string) ($params['kind'] ?? 'all');
		$sections = self::fetchAllSections();
		$clusters = self::buildClusters($sections, $minScore);
		$searchId = (ctype_digit($q) && $q !== '') ? (int) $q : 0;
		$needle = ($q !== '' && $searchId === 0) ? mb_strtolower($q) : '';

		$byKind = ['names' => 0, 'products' => 0, 'similar' => 0, 'homonym' => 0, 'variants' => 0];
		$filtered = [];
		$ignoreSets = DupIgnore::sets('S');
		foreach ($clusters as $cluster) {
			$group = self::kindGroup($cluster['kind']);
			$st = self::clusterStatus($cluster);
			if ($st === 'open' && DupIgnore::covers($ignoreSets, array_column($cluster['items'], 'id'))) {
				$st = DupIgnore::STATUS;
			}
			$cluster['status'] = $st;
			if ($st === 'open') {
				$byKind[$group]++;
			}
			if ($searchId > 0 && !self::clusterHasId($cluster, $searchId)) {
				continue;
			}
			if ($needle !== '' && !self::clusterMatches($cluster, $needle)) {
				continue;
			}
			if ($kind !== 'all' && $kind !== $group) {
				continue;
			}
			if ($status === 'open' && $st !== 'open') {
				continue;
			}
			if ($status === 'resolved' && $st !== 'resolved') {
				continue;
			}
			if ($status === DupIgnore::STATUS && $st !== DupIgnore::STATUS) {
				continue;
			}
			$filtered[] = $cluster;
		}

		if ($searchId > 0 && $filtered === [] && !empty($sections[$searchId]['active'])) {
			$single = [$searchId => $sections[$searchId]];
			self::enrichItems($single, $sections);
			$item = $single[$searchId];
			$item['sim_label'] = isset(self::$redirects[$searchId])
				? ('не отдельная страница: ' . self::$redirects[$searchId] . ' (в дублях не участвует)')
				: 'пар-дублей не найдено';
			$item['reasons'] = [];
			$filtered[] = [
				'key' => 'id:' . $searchId,
				'title' => $item['name'],
				'score' => 100,
				'kind' => '',
				'kind_label' => '',
				'items' => [$item],
				'size' => 1,
				'empty_count' => $item['products'] === 0 ? 1 : 0,
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
			'open_by_kind' => $byKind,
			'redirect_hidden' => count(self::$redirects),
			'probe' => self::$probeStats,
		];
	}

	protected static function kindGroup(string $kind): string
	{
		if ($kind === self::KIND_PRODUCTS) {
			return 'products';
		}
		if ($kind === self::KIND_SIMILAR) {
			return 'similar';
		}
		if ($kind === self::KIND_HOMONYM) {
			return 'homonym';
		}
		if ($kind === self::KIND_VARIANTS) {
			return 'variants';
		}
		return 'names';
	}

	protected static function clusterMatches(array $cluster, string $needle): bool
	{
		foreach ($cluster['items'] ?? [] as $item) {
			if (mb_stripos((string) $item['name'], $needle) !== false || mb_stripos((string) $item['code'], $needle) !== false) {
				return true;
			}
		}
		return false;
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
				$like = Config::forLike($q);
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
	 * Разбор названия: смысловые слова (основы), бренды/модели, размеры/серии, страны.
	 *
	 * @return array{core:array<string,int>,brands:array<string,int>,variants:array<string,int>,countries:array<string,int>,full:string}
	 */
	public static function parseName(string $name): array
	{
		static $stop = null;
		static $countries = null;
		if ($stop === null) {
			$stop = array_fill_keys(array_merge(self::STOP_WORDS, ['мм', 'см', 'мл', 'шт', 'кг', 'гр', 'мкм']), 1);
			$countries = [];
			foreach (self::COUNTRIES as $c) {
				$countries[self::stem($c)] = 1;
				$countries[$c] = 1;
			}
		}

		$letters = [];
		if (mb_strtoupper($name, 'UTF-8') !== $name
			&& preg_match_all('/(?<![\p{L}\p{N}])\p{Lu}(?![\p{L}\p{N}])/u', $name, $lm)) {
			foreach ($lm[0] as $l) {
				$l = mb_strtolower($l, 'UTF-8');
				if (!in_array($l, ['и', 'в', 'с', 'к', 'о', 'у'], true)) {
					$letters[$l] = 1;
				}
			}
		}
		$s = mb_strtolower($name, 'UTF-8');
		$s = str_replace('ё', 'е', $s);
		$s = preg_replace('/[\x{00AD}\x{200B}-\x{200F}\x{2060}\x{FEFF}]/u', '', $s) ?? $s;
		$s = preg_replace('/[\x{00A0}\x{2000}-\x{200A}]/u', ' ', $s) ?? $s;
		$s = preg_replace('/(\d)\s*,\s*(\d)/u', '$1.$2', $s) ?? $s;
		$s = preg_replace('/(\d)\s*([\/xх×*])\s*(\d)/u', '$1$2$3', $s) ?? $s;
		$s = preg_replace('/[^\p{L}\p{N}\/.\-×*]+/u', ' ', $s) ?? $s;
		$tokens = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

		$core = $brands = $variants = $found = $countryWords = [];
		foreach ($tokens as $t) {
			$t = trim($t, '.-/*');
			if ($t === '') {
				continue;
			}
			$hasDigit = preg_match('/\d/u', $t) === 1;
			if (!$hasDigit && mb_strlen($t) === 1) {
				if (isset($letters[$t])) {
					$variants[$t] = 1;
				}
				continue;
			}
			if (preg_match('/^\d+(?:[\/.xх×*]\d+)*$/u', $t)) {
				$variants[$t] = 1;
				continue;
			}
			if (isset($stop[$t])) {
				continue;
			}
			$hasLatin = preg_match('/[a-z]/u', $t) === 1;
			$hasCyr = preg_match('/[а-я]/u', $t) === 1;
			$st = self::stem($t);
			if (isset($countries[$t]) || isset($countries[$st])) {
				$found[$st] = 1;
				$countryWords[$t] = 1;
				continue;
			}
			if ($hasDigit) {
				if ($hasLatin) {
					$brands[$t] = 1;
				} else {
					$variants[$t] = 1;
				}
				continue;
			}
			if ($hasLatin && !$hasCyr) {
				if (strlen($t) <= 3) {
					$variants[$t] = 1;
				} else {
					$brands[$t] = 1;
				}
				continue;
			}
			$core[$st] = 1;
		}
		ksort($core);
		ksort($brands);
		ksort($variants);
		ksort($found);

		return [
			'core' => $core,
			'brands' => $brands,
			'variants' => $variants,
			'countries' => $found,
			'country_words' => $countryWords,
			'full' => implode(' ', array_keys($core)) . '|' . implode(' ', array_keys($brands))
				. '|' . implode(' ', array_keys($variants)) . '|' . implode(' ', array_keys($found)),
		];
	}

	protected static function stem(string $t): string
	{
		static $endings = [
			'ями', 'ами', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими', 'ых', 'их',
			'ие', 'ий', 'ый', 'ой', 'ая', 'яя', 'ое', 'ее', 'ые', 'ов', 'ев', 'ей',
			'ом', 'ем', 'ам', 'ям', 'ах', 'ях', 'ую', 'юю',
			'ы', 'и', 'а', 'я', 'е', 'о', 'у', 'ю', 'ь', 'й',
		];
		if (preg_match('/[a-z0-9]/', $t)) {
			return $t;
		}
		$len = mb_strlen($t);
		if ($len <= 4) {
			return $t;
		}
		// «-ия» (эндоскопия, Россия) — другое слово, чем «-ы» (эндоскопы): сводим падежи к «-ия»
		if (preg_match('/^(.{4,}?)(иями|иях|иям|ией|ия|ии|ию)$/u', $t, $m)) {
			return $m[1] . 'ия';
		}
		foreach ($endings as $e) {
			$el = mb_strlen($e);
			if ($len - $el >= 4 && mb_substr($t, -$el) === $e) {
				return mb_substr($t, 0, $len - $el);
			}
		}
		return $t;
	}

	/**
	 * Все разделы каталога (неактивные — только для хлебных крошек).
	 *
	 * @return array<int, array>
	 */
	protected static function fetchAllSections(): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$hasDecision = self::hasDecisionColumn();
		$decisionSelect = $hasDecision ? ('UTS.' . self::UF_DECISION . ' AS TITLO_DECISION') : 'NULL AS TITLO_DECISION';
		$join = $hasDecision ? ('LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BS.ID') : '';

		$res = $DB->Query('
			SELECT BS.ID, BS.NAME, BS.CODE, BS.ACTIVE, BS.IBLOCK_SECTION_ID,
				BS.LEFT_MARGIN, BS.RIGHT_MARGIN, BS.DEPTH_LEVEL, ' . $decisionSelect . '
			FROM b_iblock_section BS
			' . $join . '
			WHERE BS.IBLOCK_ID = ' . (int) $iblockId . '
		');
		$items = [];
		while ($row = $res->Fetch()) {
			$id = (int) $row['ID'];
			$decision = trim((string) ($row['TITLO_DECISION'] ?? ''));
			if ($decision !== self::DECISION_KEEP && $decision !== self::DECISION_NOINDEX) {
				$decision = '';
			}
			$items[$id] = [
				'id' => $id,
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'active' => $row['ACTIVE'] === 'Y',
				'parent' => (int) $row['IBLOCK_SECTION_ID'],
				'left' => (int) $row['LEFT_MARGIN'],
				'right' => (int) $row['RIGHT_MARGIN'],
				'depth' => (int) $row['DEPTH_LEVEL'],
				'decision' => $decision,
				'url' => '',
				'noindex' => false,
			];
		}
		return $items;
	}

	/**
	 * Активные товары разделов (с подразделами), лениво.
	 */
	protected static function productSet(int $id, array $sections, array &$ctx): array
	{
		if (isset($ctx['psets'][$id])) {
			return $ctx['psets'][$id];
		}
		if (!isset($ctx['direct'])) {
			global $DB;
			$iblockId = (int) Config::iblockId();
			$direct = [];
			$res = $DB->Query("
				SELECT SE.IBLOCK_SECTION_ID AS S, SE.IBLOCK_ELEMENT_ID AS E
				FROM b_iblock_section_element SE
				INNER JOIN b_iblock_element BE ON BE.ID = SE.IBLOCK_ELEMENT_ID
				WHERE BE.IBLOCK_ID = {$iblockId} AND BE.ACTIVE = 'Y'
			");
			while ($row = $res->Fetch()) {
				$direct[(int) $row['S']][(int) $row['E']] = true;
			}
			$order = [];
			foreach ($sections as $sid => $s) {
				if ($s['active']) {
					$order[] = $sid;
				}
			}
			usort($order, static function ($a, $b) use ($sections) {
				return $sections[$a]['left'] <=> $sections[$b]['left'];
			});
			$ctx['direct'] = $direct;
			$ctx['order'] = $order;
			$ctx['pos'] = array_flip($order);
		}
		$set = [];
		if (isset($ctx['pos'][$id])) {
			$right = $sections[$id]['right'];
			$n = count($ctx['order']);
			for ($i = $ctx['pos'][$id]; $i < $n; $i++) {
				$sid = $ctx['order'][$i];
				if ($sections[$sid]['left'] > $right) {
					break;
				}
				if (isset($ctx['direct'][$sid])) {
					$set += $ctx['direct'][$sid];
				}
			}
		}
		return $ctx['psets'][$id] = $set;
	}

	protected static function sectionPath(int $id, array $sections): string
	{
		$parts = [];
		$cur = (int) ($sections[$id]['parent'] ?? 0);
		$guard = 0;
		while ($cur > 0 && isset($sections[$cur]) && $guard++ < 12) {
			array_unshift($parts, $sections[$cur]['name']);
			$cur = $sections[$cur]['parent'];
		}
		return implode(' › ', $parts);
	}

	protected static function isNested(array $a, array $b): bool
	{
		return ($a['left'] < $b['left'] && $a['right'] > $b['right'])
			|| ($b['left'] < $a['left'] && $b['right'] > $a['right']);
	}

	/**
	 * @param array<string,int> $a
	 * @param array<string,int> $b
	 * @param array<string,float> $idf
	 */
	protected static function weightedJaccard(array $a, array $b, array $idf): int
	{
		$inter = 0.0;
		$union = 0.0;
		foreach ($a + $b as $t => $_) {
			$w = $idf[$t] ?? 1.0;
			$union += $w;
			if (isset($a[$t], $b[$t])) {
				$inter += $w;
			}
		}
		return $union > 0 ? (int) round(100 * $inter / $union) : 0;
	}

	/**
	 * @return array{score:int,kind:string,name_score:int,products:?int}|null
	 */
	protected static function evalPair(int $a, int $b, array $sections, int $minScore, array &$ctx): ?array
	{
		$pa = $ctx['parsed'][$a];
		$pb = $ctx['parsed'][$b];
		if ($pa['brands'] !== $pb['brands'] || $pa['variants'] !== $pb['variants']) {
			return null;
		}
		if (self::isNested($sections[$a], $sections[$b])) {
			return $pa['full'] === $pb['full']
				? ['score' => 100, 'kind' => self::KIND_NESTED, 'name_score' => 100, 'products' => null]
				: null;
		}
		if ($pa['core'] === [] && $pb['core'] === []) {
			if ($pa['brands'] === [] || $pa['countries'] !== $pb['countries']) {
				return null;
			}
			return ['score' => 100, 'kind' => self::KIND_EXACT, 'name_score' => 100, 'products' => null];
		}
		if ($pa['core'] === $pb['core']) {
			if ($pa['countries'] !== $pb['countries'] && $pa['countries'] !== [] && $pb['countries'] !== []) {
				return null;
			}
			$kind = $pa['countries'] === $pb['countries'] ? self::KIND_EXACT : self::KIND_COUNTRY;
			$j = self::productJaccard($a, $b, $sections, $ctx);
			if ($j !== null && $j < self::HOMONYM_PRODUCTS_MAX) {
				$kind = self::KIND_HOMONYM;
			}
			return ['score' => 100, 'kind' => $kind, 'name_score' => 100, 'products' => $j];
		}
		$nameScore = self::weightedJaccard($pa['core'], $pb['core'], $ctx['idf']);
		if ($nameScore >= $minScore) {
			$j = self::productJaccard($a, $b, $sections, $ctx);
			if ($j !== null && $j < self::SIMILAR_PRODUCTS_MIN) {
				return null;
			}
			return ['score' => $nameScore, 'kind' => self::KIND_SIMILAR, 'name_score' => $nameScore, 'products' => $j];
		}
		if ($nameScore < self::PRODUCTS_NAME_MIN) {
			return null;
		}
		$j = self::productJaccard($a, $b, $sections, $ctx);
		if ($j !== null && $j >= self::PRODUCTS_JACCARD_MIN) {
			return ['score' => $j, 'kind' => self::KIND_PRODUCTS, 'name_score' => $nameScore, 'products' => $j];
		}
		return null;
	}

	protected static function productJaccard(int $a, int $b, array $sections, array &$ctx): ?int
	{
		$sa = self::productSet($a, $sections, $ctx);
		$sb = self::productSet($b, $sections, $ctx);
		if ($sa === [] || $sb === []) {
			return null;
		}
		$inter = count(array_intersect_key($sa, $sb));
		$union = count($sa) + count($sb) - $inter;
		return $union > 0 ? (int) round(100 * $inter / $union) : null;
	}

	/**
	 * Линейки: дети одного родителя с тем же смыслом и брендом, но разным размером/серией/страной.
	 * Бренд — часть подписи, поэтому «Мониторы COMEN» / «Мониторы Mindray» сюда не попадают.
	 *
	 * @return array<string, array{0:int,1:int[]}> key => [parentId, memberIds]
	 */
	protected static function variantGroups(array $sections, array $ctx): array
	{
		$sig = static function (array $p): string {
			return implode(' ', array_keys($p['core'])) . '|' . implode(' ', array_keys($p['brands']));
		};
		$byParent = [];
		foreach ($ctx['parsed'] as $id => $p) {
			$pid = (int) $sections[$id]['parent'];
			if ($pid <= 0 || !isset($ctx['parsed'][$pid])) {
				continue;
			}
			if (($p['core'] === [] && $p['brands'] === []) || ($p['variants'] === [] && $p['countries'] === [])) {
				continue;
			}
			$byParent[$pid][$sig($p)][] = $id;
		}
		$out = [];
		foreach ($byParent as $pid => $bySig) {
			$parentSig = $sig($ctx['parsed'][$pid]);
			foreach ($bySig as $s => $members) {
				$fulls = [];
				foreach ($members as $m) {
					$fulls[$ctx['parsed'][$m]['full']] = true;
				}
				if (count($fulls) >= 2 || $s === $parentSig) {
					$out[$pid . ':' . md5($s)] = [$pid, $members];
				}
			}
		}
		return $out;
	}

	protected static function variantNote(array $p): string
	{
		$parts = [];
		if ($p['variants'] !== []) {
			$parts[] = 'размер/серия ' . implode(', ', array_map('strval', array_keys($p['variants'])));
		}
		if (!empty($p['country_words'])) {
			$parts[] = 'страна ' . implode(', ', array_map(static function ($w) {
				return mb_convert_case((string) $w, MB_CASE_TITLE, 'UTF-8');
			}, array_keys($p['country_words'])));
		}
		return $parts !== [] ? implode('; ', $parts) : 'вариант';
	}

	protected static function kindRank(string $kind): int
	{
		return [
			self::KIND_EXACT => 0,
			self::KIND_NESTED => 0,
			self::KIND_COUNTRY => 1,
			self::KIND_PRODUCTS => 2,
			self::KIND_SIMILAR => 3,
			self::KIND_HOMONYM => 4,
			self::KIND_VARIANTS => 5,
		][$kind] ?? 9;
	}

	protected static function kindLabel(string $kind): string
	{
		return [
			self::KIND_EXACT => 'одинаковые названия',
			self::KIND_NESTED => 'одноимённый вложенный раздел',
			self::KIND_COUNTRY => 'отличие только в стране',
			self::KIND_PRODUCTS => 'одни и те же товары',
			self::KIND_SIMILAR => 'похожие названия',
			self::KIND_HOMONYM => 'одно название — разные товары',
			self::KIND_VARIANTS => 'линейка: размеры/серии/страны → родитель',
		][$kind] ?? '';
	}

	protected static function reasonText(array $edge): string
	{
		switch ($edge['kind']) {
			case self::KIND_EXACT:
				return 'то же название';
			case self::KIND_NESTED:
				return 'то же название, вложен';
			case self::KIND_COUNTRY:
				return 'то же название, другая страна';
			case self::KIND_VARIANTS:
				return $edge['note'];
			case self::KIND_HOMONYM:
				return 'то же название, товары другие (общих ' . $edge['products'] . '%)';
			case self::KIND_PRODUCTS:
				return 'общих товаров ' . $edge['products'] . '%, слова ' . $edge['name_score'] . '%';
			default:
				return 'слова совпадают на ' . $edge['score'] . '%';
		}
	}

	/**
	 * @param array<int, array> $sections
	 * @return array<int, array>
	 */
	protected static function buildClusters(array $sections, int $minScore): array
	{
		$excluded = self::redirectedSections($sections);
		$clusters = self::clusterize($sections, $minScore, $excluded);
		self::$probeStats = ['checked' => 0, 'pending' => 0, 'blocked' => 0, 'excluded' => 0];

		if (UrlProbe::enabled() && $clusters !== []) {
			$paths = [];
			foreach ($clusters as $cl) {
				foreach ($cl['items'] as $it) {
					$path = (string) (parse_url((string) $it['url'], PHP_URL_PATH) ?? '');
					if ($path !== '') {
						$paths[(int) $it['id']] = $path;
					}
				}
			}
			$probe = UrlProbe::check($paths);
			$extra = [];
			foreach ($probe as $id => $r) {
				if ($r['type'] === UrlProbe::TYPE_PENDING) {
					self::$probeStats['pending']++;
					continue;
				}
				self::$probeStats['checked']++;
				if ($r['type'] === UrlProbe::TYPE_BLOCKED) {
					self::$probeStats['blocked']++;
				}
				if ($r['type'] === UrlProbe::TYPE_REDIRECT || $r['type'] === UrlProbe::TYPE_CANONICAL) {
					$extra[(int) $id] = ($r['type'] === UrlProbe::TYPE_CANONICAL ? 'canonical → ' : $r['code'] . ' → ') . $r['target'];
				}
			}
			self::$probeStats['excluded'] = count($extra);
			if ($extra !== []) {
				$excluded += $extra;
				$clusters = self::clusterize($sections, $minScore, $excluded);
			}
			foreach ($clusters as &$cl) {
				foreach ($cl['items'] as &$it) {
					$r = $probe[(int) $it['id']] ?? null;
					if ($r !== null && in_array($r['type'], [UrlProbe::TYPE_ERROR, UrlProbe::TYPE_BLOCKED], true)) {
						$it['url_note'] = $r['type'] === UrlProbe::TYPE_ERROR
							? ('страница отвечает HTTP ' . ($r['code'] ?: 'timeout'))
							: 'проверка URL заблокирована антиботом';
					}
				}
				unset($it);
			}
			unset($cl);
		}
		self::$redirects = $excluded;
		return $clusters;
	}

	/**
	 * @param array<int, array> $sections
	 * @param array<int, string> $excluded id => куда уходит
	 * @return array<int, array>
	 */
	protected static function clusterize(array $sections, int $minScore, array $excluded): array
	{
		$ctx = ['parsed' => [], 'idf' => [], 'psets' => []];
		$redirects = $excluded;
		$df = [];
		$active = 0;
		foreach ($sections as $id => $s) {
			if (!$s['active'] || isset($redirects[$id])) {
				continue;
			}
			$active++;
			$p = self::parseName($s['name']);
			$ctx['parsed'][$id] = $p;
			foreach ($p['core'] as $t => $_) {
				$df[$t] = ($df[$t] ?? 0) + 1;
			}
		}
		foreach ($df as $t => $n) {
			$ctx['idf'][$t] = log(1 + $active / $n);
		}

		// Кандидаты: одинаковый разбор названия + две самые редкие основы / бренд
		$postings = [];
		foreach ($ctx['parsed'] as $id => $p) {
			$postings['f:' . $p['full']][] = $id;
			$stems = array_keys($p['core']);
			usort($stems, static function ($x, $y) use ($df) {
				return ($df[$x] <=> $df[$y]) ?: strcmp($x, $y);
			});
			$taken = 0;
			foreach ($stems as $t) {
				if ($df[$t] > self::BLOCK_DF_MAX) {
					break;
				}
				$postings['t:' . $t][] = $id;
				if (++$taken >= 2) {
					break;
				}
			}
			if ($stems === []) {
				foreach ($p['brands'] as $b => $_) {
					$postings['b:' . $b][] = $id;
				}
			}
		}

		$parent = [];
		$find = static function (int $x) use (&$parent): int {
			while ($parent[$x] !== $x) {
				$parent[$x] = $parent[$parent[$x]];
				$x = $parent[$x];
			}
			return $x;
		};
		$edges = [];
		$seen = [];
		foreach ($postings as $ids) {
			$n = count($ids);
			if ($n < 2) {
				continue;
			}
			for ($i = 0; $i < $n; $i++) {
				for ($j = $i + 1; $j < $n; $j++) {
					$a = min($ids[$i], $ids[$j]);
					$b = max($ids[$i], $ids[$j]);
					$k = $a . ':' . $b;
					if (isset($seen[$k])) {
						continue;
					}
					$seen[$k] = true;
					$e = self::evalPair($a, $b, $sections, $minScore, $ctx);
					if ($e === null) {
						continue;
					}
					$e['a'] = $a;
					$e['b'] = $b;
					$edges[$e['kind'] === self::KIND_HOMONYM ? 1 : 0][] = $e;
				}
			}
		}
		unset($seen);

		// Тёзки (разные товары) не склеиваем с копиями: иначе «закрыть остальных» закроет живой раздел
		$groups = [];
		$groupEdges = [];
		foreach ($edges as $layer => $list) {
			$parent = [];
			foreach ($list as $e) {
				$parent[$e['a']] = $parent[$e['a']] ?? $e['a'];
				$parent[$e['b']] = $parent[$e['b']] ?? $e['b'];
				$ra = $find($e['a']);
				$rb = $find($e['b']);
				if ($ra !== $rb) {
					$parent[$ra] = $rb;
				}
			}
			foreach (array_keys($parent) as $id) {
				$groups[$layer . ':' . $find($id)][] = $id;
			}
			foreach ($list as $e) {
				$groupEdges[$layer . ':' . $find($e['a'])][] = $e;
			}
		}

		$forcedRef = [];
		foreach (self::variantGroups($sections, $ctx) as $key => [$parentId, $members]) {
			$root = '2:' . $key;
			$groups[$root] = array_merge([$parentId], $members);
			$forcedRef[$root] = $parentId;
			foreach ($members as $m) {
				$groupEdges[$root][] = [
					'a' => $parentId,
					'b' => $m,
					'score' => 100,
					'kind' => self::KIND_VARIANTS,
					'name_score' => 100,
					'products' => null,
					'note' => self::variantNote($ctx['parsed'][$m]),
				];
			}
		}

		$clusterIds = [];
		foreach ($groups as $ids) {
			foreach ($ids as $id) {
				$clusterIds[$id] = $sections[$id];
			}
		}
		self::enrichItems($clusterIds, $sections, $ctx);

		$clusters = [];
		foreach ($groups as $root => $ids) {
			$items = [];
			foreach ($ids as $id) {
				$items[] = $clusterIds[$id];
			}
			usort($items, static function ($x, $y) {
				$kx = $x['decision'] === self::DECISION_KEEP ? 0 : 1;
				$ky = $y['decision'] === self::DECISION_KEEP ? 0 : 1;
				if ($kx !== $ky) {
					return $kx <=> $ky;
				}
				$cx = ($x['noindex'] || $x['decision'] === self::DECISION_NOINDEX) ? 1 : 0;
				$cy = ($y['noindex'] || $y['decision'] === self::DECISION_NOINDEX) ? 1 : 0;
				return ($cx <=> $cy)
					?: ($y['products'] <=> $x['products'])
					?: ($x['depth'] <=> $y['depth'])
					?: ($x['id'] <=> $y['id']);
			});
			if (isset($forcedRef[$root])) {
				foreach ($items as $i => $it) {
					if ($it['id'] === $forcedRef[$root] && $i > 0) {
						array_splice($items, $i, 1);
						array_unshift($items, $it);
						break;
					}
				}
			}

			$ref = $items[0]['id'];
			$best = [];
			$minEdge = 100;
			$worstKind = self::KIND_EXACT;
			foreach ($groupEdges[$root] ?? [] as $e) {
				$minEdge = min($minEdge, $e['score']);
				if (self::kindRank($e['kind']) > self::kindRank($worstKind)) {
					$worstKind = $e['kind'];
				}
				foreach ([$e['a'], $e['b']] as $end) {
					if (!isset($best[$end]) || $e['score'] > $best[$end]['score']) {
						$best[$end] = $e;
					}
				}
			}

			$empty = 0;
			foreach ($items as $i => &$item) {
				if ($item['products'] === 0) {
					$empty++;
				}
				$item['distinct'] = false;
				if ($i === 0) {
					$item['sim_to_top'] = 100;
					$item['sim_label'] = isset($forcedRef[$root])
						? 'эталон: общий родитель линейки'
						: ('эталон: ' . ($item['products'] > 0 ? 'больше всего товаров' : 'первый по структуре'));
					continue;
				}
				if (isset($forcedRef[$root])) {
					$e = $best[$item['id']];
					$item['sim_to_top'] = 100;
					$item['sim_label'] = 'вариант родителя #' . $ref . ': ' . self::reasonText($e);
					continue;
				}
				$edge = self::evalPair(min($ref, $item['id']), max($ref, $item['id']), $sections, $minScore, $ctx);
				$item['distinct'] = $edge !== null && $edge['kind'] === self::KIND_HOMONYM && $worstKind !== self::KIND_HOMONYM;
				if ($edge !== null) {
					$item['sim_to_top'] = $edge['score'];
					$item['sim_label'] = 'как #' . $ref . ': ' . self::reasonText($edge);
				} else {
					$e = $best[$item['id']];
					$other = $e['a'] === $item['id'] ? $e['b'] : $e['a'];
					$item['sim_to_top'] = $e['score'];
					$item['sim_label'] = 'как #' . $other . ': ' . self::reasonText($e);
				}
				$j = self::productJaccard($ref, $item['id'], $sections, $ctx);
				if ($j !== null && ($edge === null || $edge['kind'] !== self::KIND_PRODUCTS)) {
					$item['sim_label'] .= '; общих товаров с эталоном ' . $j . '%';
				}
			}
			unset($item);

			$clusters[] = [
				'key' => 'k' . $ref . '-' . $root,
				'title' => $items[0]['name'],
				'score' => $minEdge,
				'kind' => $worstKind,
				'kind_label' => self::kindLabel($worstKind),
				'items' => $items,
				'size' => count($items),
				'empty_count' => $empty,
			];
		}

		usort($clusters, static function ($x, $y) {
			return (self::kindRank($x['kind']) <=> self::kindRank($y['kind']))
				?: ($y['size'] <=> $x['size'])
				?: ($y['score'] <=> $x['score'])
				?: strcmp($x['key'], $y['key']);
		});

		return $clusters;
	}

	/**
	 * Активные разделы, чей URL уходит 301-редиректом (.htaccess): это не отдельные страницы.
	 *
	 * @return array<int, string> id => URL назначения
	 */
	protected static function redirectedSections(array $sections): array
	{
		$ids = [];
		foreach ($sections as $id => $s) {
			if ($s['active']) {
				$ids[] = $id;
			}
		}
		$out = [];
		foreach (UrlBuilder::mapSectionUrls($ids) as $id => $url) {
			$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
			if ($path === '') {
				continue;
			}
			$to = RedirectMap::target($path);
			if ($to !== null && RedirectMap::normalizePath($to) !== RedirectMap::normalizePath($path)) {
				$out[$id] = '301 → ' . $to;
			}
		}
		return $out;
	}

	public const H1_DEFAULT = 'SEO:SECTION_PAGE_TITLE';

	/**
	 * Откуда брать H1 раздела: SEO-шаблоны инфоблока, NAME или строковое UF.
	 *
	 * @return array<string, string>
	 */
	public static function h1SourceOptions(): array
	{
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}
		$opts = [
			'SEO:SECTION_PAGE_TITLE' => 'SEO: заголовок раздела (H1)',
			'SEO:SECTION_META_TITLE' => 'SEO: META TITLE',
			'NAME' => 'Название раздела (NAME)',
		];
		$iblockId = Config::iblockId();
		if ($iblockId > 0) {
			$res = \CUserTypeEntity::GetList(['SORT' => 'ASC'], [
				'ENTITY_ID' => 'IBLOCK_' . $iblockId . '_SECTION',
				'USER_TYPE_ID' => 'string',
				'LANG' => LANGUAGE_ID,
			]);
			while ($u = $res->Fetch()) {
				$code = (string) $u['FIELD_NAME'];
				if (strpos($code, 'UF_TITLO_') === 0) {
					continue;
				}
				$label = trim((string) ($u['EDIT_FORM_LABEL'] ?? ''));
				$opts['UF:' . $code] = $code . ($label !== '' ? ' — ' . $label : '');
			}
		}
		return $cache = $opts;
	}

	public static function h1Source(): string
	{
		$src = Config::get('section_h1_source', self::H1_DEFAULT);
		return isset(self::h1SourceOptions()[$src]) ? $src : self::H1_DEFAULT;
	}

	public static function setH1Source(string $src): bool
	{
		if (!isset(self::h1SourceOptions()[$src])) {
			return false;
		}
		\Bitrix\Main\Config\Option::set(Config::MODULE_ID, 'section_h1_source', $src);
		return true;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, string>
	 */
	protected static function mapH1(array $ids, array $sections): array
	{
		global $DB;
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}
		$src = self::h1Source();
		$iblockId = (int) Config::iblockId();
		$out = [];
		if ($src === 'NAME') {
			foreach ($ids as $id) {
				$out[$id] = (string) ($sections[$id]['name'] ?? '');
			}
			return $out;
		}
		if (strpos($src, 'UF:') === 0) {
			$col = substr($src, 3);
			if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
				return [];
			}
			$uts = 'b_uts_iblock_' . $iblockId . '_section';
			if (!$DB->Query("SHOW COLUMNS FROM {$uts} LIKE '" . $DB->ForSql($col) . "'")->Fetch()) {
				return [];
			}
			foreach (array_chunk($ids, 500) as $chunk) {
				$res = $DB->Query("SELECT VALUE_ID, {$col} AS V FROM {$uts} WHERE VALUE_ID IN (" . implode(',', $chunk) . ')');
				while ($row = $res->Fetch()) {
					$out[(int) $row['VALUE_ID']] = trim((string) $row['V']);
				}
			}
			return $out;
		}
		$code = substr($src, 4);
		foreach (array_chunk($ids, 500) as $chunk) {
			$res = $DB->Query("
				SELECT SI.SECTION_ID, SI.VALUE
				FROM b_iblock_section_iprop SI
				INNER JOIN b_iblock_iproperty IP ON IP.ID = SI.IPROP_ID
				WHERE SI.IBLOCK_ID = {$iblockId} AND IP.CODE = '" . $DB->ForSql($code) . "'
					AND SI.SECTION_ID IN (" . implode(',', $chunk) . ')
			');
			while ($row = $res->Fetch()) {
				$out[(int) $row['SECTION_ID']] = trim((string) $row['VALUE']);
			}
		}
		$missing = array_slice(array_diff($ids, array_keys($out)), 0, 300);
		foreach ($missing as $id) {
			$vals = (new \Bitrix\Iblock\InheritedProperty\SectionValues($iblockId, $id))->getValues();
			$out[$id] = trim((string) ($vals[$code] ?? ''));
		}
		return $out;
	}

	/**
	 * URL, noindex, путь, число товаров.
	 *
	 * @param array<int, array> $items
	 */
	protected static function enrichItems(array &$items, array $sections, ?array &$ctx = null): void
	{
		if ($items === []) {
			return;
		}
		if ($ctx === null) {
			$ctx = ['psets' => []];
		}
		$ids = array_keys($items);
		$urlMap = UrlBuilder::mapSectionUrls($ids);
		$noindexMap = IndexingControl::mapSectionNoindex($ids);
		$h1Map = self::mapH1($ids, $sections);
		foreach ($items as $id => &$item) {
			$item['h1'] = $h1Map[$id] ?? '';
			$item['url'] = $urlMap[$id] ?? UrlBuilder::category($item['code'], $id);
			$item['noindex'] = !empty($noindexMap[$id]);
			$item['path'] = self::sectionPath($id, $sections);
			$item['products'] = count(self::productSet($id, $sections, $ctx));
			$item['sim_to_top'] = null;
			$item['sim_label'] = '';
		}
		unset($item);
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

	/**
	 * Товары закрываемых разделов, у которых не останется ни одной открытой категории.
	 *
	 * @param int[] $closeIds разделы, которые сейчас закроем
	 * @return array{ok:bool,products:array,targets:array,target_id:int,warn_url:string}
	 */
	public static function orphanProducts(array $closeIds, int $preferTargetId = 0): array
	{
		global $DB;
		$closeIds = Config::filterCatalogSectionIds($closeIds, 200);
		$iblockId = (int) Config::iblockId();
		if ($closeIds === [] || $iblockId <= 0) {
			return ['ok' => false, 'error' => 'bad ids', 'products' => [], 'targets' => [], 'target_id' => 0, 'warn_url' => ''];
		}
		$closeSet = array_fill_keys($closeIds, true);

		$productIds = [];
		$res = $DB->Query('
			SELECT DISTINCT SE.IBLOCK_ELEMENT_ID AS E
			FROM b_iblock_section_element SE
			INNER JOIN b_iblock_element BE ON BE.ID = SE.IBLOCK_ELEMENT_ID
			WHERE SE.IBLOCK_SECTION_ID IN (' . implode(',', $closeIds) . ") AND BE.ACTIVE = 'Y'
				AND BE.IBLOCK_ID = {$iblockId} AND SE.ADDITIONAL_PROPERTY_ID IS NULL
			LIMIT 2000
		");
		while ($row = $res->Fetch()) {
			$productIds[] = (int) $row['E'];
		}

		$links = [];
		$allSections = [];
		foreach (array_chunk($productIds, 500) as $chunk) {
			$res = $DB->Query('
				SELECT IBLOCK_ELEMENT_ID AS E, IBLOCK_SECTION_ID AS S FROM b_iblock_section_element
				WHERE IBLOCK_ELEMENT_ID IN (' . implode(',', $chunk) . ') AND ADDITIONAL_PROPERTY_ID IS NULL
			');
			while ($row = $res->Fetch()) {
				$links[(int) $row['E']][] = (int) $row['S'];
				$allSections[(int) $row['S']] = true;
			}
		}

		$sections = self::fetchAllSections();
		$openMap = self::openSectionMap(array_keys($allSections), $sections);

		$orphans = [];
		foreach ($links as $pid => $secs) {
			$hasOpen = false;
			foreach ($secs as $s) {
				if (!isset($closeSet[$s]) && !empty($openMap[$s])) {
					$hasOpen = true;
					break;
				}
			}
			if (!$hasOpen) {
				$orphans[$pid] = $secs;
			}
		}

		// Куда добавлять: эталон (если открыт и не закрывается) → открытые соседи по группе → открытые родители
		$candidates = [];
		if ($preferTargetId > 0) {
			$candidates[] = $preferTargetId;
		}
		foreach ($closeIds as $cid) {
			$cur = (int) ($sections[$cid]['parent'] ?? 0);
			$guard = 0;
			while ($cur > 0 && isset($sections[$cur]) && $guard++ < 12) {
				$candidates[] = $cur;
				$cur = (int) $sections[$cur]['parent'];
			}
		}
		$candidates = array_values(array_unique($candidates));
		$candOpen = self::openSectionMap($candidates, $sections);
		$targets = [];
		foreach ($candidates as $c) {
			if (isset($closeSet[$c]) || empty($candOpen[$c])) {
				continue;
			}
			$path = self::sectionPath($c, $sections);
			$targets[] = [
				'id' => $c,
				'name' => (string) $sections[$c]['name'],
				'path' => $path,
				'is_ref' => $c === $preferTargetId,
			];
		}

		$products = [];
		if ($orphans !== []) {
			$res = \CIBlockElement::GetList(
				['NAME' => 'ASC'],
				['IBLOCK_ID' => $iblockId, 'ID' => array_keys($orphans), 'CHECK_PERMISSIONS' => 'N'],
				false,
				['nTopCount' => 500],
				['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL']
			);
			while ($row = $res->GetNext()) {
				$pid = (int) $row['ID'];
				$products[] = [
					'id' => $pid,
					'name' => (string) $row['~NAME'],
					'url' => (string) $row['DETAIL_PAGE_URL'],
					'sections' => array_map(static function ($s) use ($sections) {
						return '#' . $s . ' ' . ($sections[$s]['name'] ?? '');
					}, $orphans[$pid]),
				];
			}
		}

		$iblock = \CIBlock::GetArrayByID($iblockId);
		$detailTpl = (string) ($iblock['DETAIL_PAGE_URL'] ?? '');
		$warnUrl = preg_match('/#SECTION_(ID|CODE|CODE_PATH)#/', $detailTpl)
			? 'URL товаров зависит от раздела (' . $detailTpl . '): добавляем только дополнительную категорию, основной раздел не меняется.'
			: '';

		return [
			'ok' => true,
			'products' => $products,
			'checked' => count($productIds),
			'targets' => $targets,
			'target_id' => $targets[0]['id'] ?? 0,
			'warn_url' => $warnUrl,
		];
	}

	/**
	 * Разделы, которые остаются в индексе: активные, без noindex/решения noindex.
	 *
	 * @param int[] $ids
	 * @return array<int, bool>
	 */
	protected static function openSectionMap(array $ids, array $sections): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}
		$noindex = IndexingControl::mapSectionNoindex($ids);
		$out = [];
		foreach ($ids as $id) {
			$s = $sections[$id] ?? null;
			$out[$id] = $s !== null && $s['active'] && empty($noindex[$id]) && $s['decision'] !== self::DECISION_NOINDEX;
		}
		return $out;
	}

	/**
	 * Добавить товарам дополнительную категорию (основной раздел не меняем).
	 *
	 * @param int[] $productIds
	 * @return array{ok:bool,added?:int,error?:string}
	 */
	public static function attachProducts(array $productIds, int $targetId): array
	{
		$iblockId = (int) Config::iblockId();
		if ($targetId <= 0 || !Config::sectionBelongsToCatalog($targetId)) {
			return ['ok' => false, 'error' => 'категория не из каталога'];
		}
		$productIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $productIds)))), 0, 1000);
		if ($productIds === []) {
			return ['ok' => false, 'error' => 'нет товаров'];
		}
		$added = 0;
		$res = \CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $productIds, 'CHECK_PERMISSIONS' => 'N'],
			false,
			false,
			['ID', 'IBLOCK_SECTION_ID']
		);
		while ($row = $res->Fetch()) {
			$pid = (int) $row['ID'];
			$current = [];
			$g = \CIBlockElement::GetElementGroups($pid, true, ['ID']);
			while ($s = $g->Fetch()) {
				$current[] = (int) $s['ID'];
			}
			if (in_array($targetId, $current, true)) {
				continue;
			}
			$current[] = $targetId;
			$main = (int) $row['IBLOCK_SECTION_ID'];
			\CIBlockElement::SetElementSection($pid, $current, false, 0, $main > 0 ? $main : null);
			if (class_exists('\Bitrix\Iblock\PropertyIndex\Manager')) {
				\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($iblockId, $pid);
			}
			$added++;
		}
		if ($added > 0 && method_exists('\CIBlock', 'clearIblockTagCache')) {
			\CIBlock::clearIblockTagCache($iblockId);
		}
		AuditLog::write('section_dup_attach_products', [
			'target_id' => $targetId,
			'product_ids' => $productIds,
			'added' => $added,
		]);
		return ['ok' => true, 'added' => $added];
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
