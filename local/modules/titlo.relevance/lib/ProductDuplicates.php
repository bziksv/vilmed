<?php

namespace Titlo\Relevance;

/**
 * Дубли товаров по всему каталогу: одинаковое/похожее название при тех же бренде, модели и размере.
 * Карточки одной модели с разным размером/мощностью/серией — «линейка», это разные товары.
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

	public const KIND_EXACT = 'exact';
	public const KIND_COUNTRY = 'country';
	public const KIND_SIMILAR = 'similar';
	/** Одно название, но производители разные — разные товары. */
	public const KIND_HOMONYM = 'homonym';
	/** Одна модель/название, отличаются размером, серией, мощностью, страной — разные товары. */
	public const KIND_LINES = 'lines';

	public const H1_DEFAULT = 'SEO:ELEMENT_PAGE_TITLE';

	/** Слово встречается чаще — по нему кандидатов «похожих» не ищем. */
	protected const BLOCK_DF_MAX = 300;
	/** Пары внутри одного ключа — не больше (защита от n² на шумных словах). */
	protected const POSTING_MAX = 200;
	/** Линейку длиннее не показываем: это раздел каталога, а не группа карточек. */
	protected const LINE_MAX = 200;

	/** @var array<int, string> */
	protected static $redirects = [];
	/** @var array{checked:int,pending:int,blocked:int,excluded:int} */
	protected static $probeStats = ['checked' => 0, 'pending' => 0, 'blocked' => 0, 'excluded' => 0];

	/**
	 * @param array{q?:string,status?:string,kind?:string,page?:int,page_size?:int,min_score?:int} $params
	 * @return array{clusters:array,total:int,page:int,pages:int,page_size:int,mode?:string,items?:array}
	 */
	public static function listClusters(array $params): array
	{
		self::ensureFields();

		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(50, (int) ($params['page_size'] ?? 20)));
		$q = trim((string) ($params['q'] ?? ''));
		$status = (string) ($params['status'] ?? 'open');
		$kind = (string) ($params['kind'] ?? 'dups');
		$minScore = max(50, min(99, (int) ($params['min_score'] ?? self::SIMILARITY_MIN)));

		if (in_array($status, ['keep', 'noindex', 'decided'], true)) {
			return self::listDecisions([
				'page' => $page,
				'page_size' => $pageSize,
				'q' => $q,
				'status' => $status,
			]);
		}

		$cache = DupCache::load('E', $minScore);
		if ($cache === null) {
			return [
				'mode' => 'clusters',
				'need_build' => true,
				'clusters' => [],
				'total' => 0,
				'page' => 1,
				'pages' => 1,
				'page_size' => $pageSize,
				'build' => DupCache::progress('E'),
			];
		}
		$clusters = $cache['clusters'] ?? [];
		self::$redirects = $cache['redirects'] ?? [];
		self::$probeStats = $cache['probe'] ?? [];
		self::refreshLive($clusters);
		$searchId = (ctype_digit($q) && $q !== '') ? (int) $q : 0;
		$needle = ($q !== '' && $searchId === 0) ? mb_strtolower($q) : '';

		$byKind = ['exact' => 0, 'similar' => 0, 'homonym' => 0, 'lines' => 0];
		$ignoreSets = DupIgnore::sets('E');
		$filtered = [];
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
			if ($searchId === 0 && !self::kindMatches($kind, $group)) {
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

		$single = ($searchId > 0 && $filtered === []) ? self::fetchAllProducts([$searchId]) : [];
		if ($single !== []) {
			self::enrichItems($single);
			$item = $single[$searchId];
			$item['sim_label'] = isset(self::$redirects[$searchId])
				? ('не отдельная страница: ' . self::$redirects[$searchId] . ' (в дублях не участвует)')
				: 'пар-дублей не найдено';
			$filtered[] = [
				'key' => 'id:' . $searchId,
				'score' => 100,
				'kind' => '',
				'kind_label' => '',
				'items' => [$item],
				'size' => 1,
				'status' => 'open',
				'single' => true,
			];
		}

		$total = count($filtered);
		$pages = max(1, (int) ceil($total / $pageSize));
		$slice = array_slice($filtered, ($page - 1) * $pageSize, $pageSize);
		if (self::h1Source() !== ($cache['h1_source'] ?? '')) {
			self::refreshH1($slice);
		}

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
			'checked_products' => (int) ($cache['checked_products'] ?? 0),
			'cache' => [
				'built_at' => (int) ($cache['built_at'] ?? 0),
				'running' => DupCache::isRunning('E'),
			],
		];
	}

	/**
	 * Полный расчёт групп для кеша (фоновый пересчёт).
	 *
	 * @param callable(int,string,string):void $report
	 */
	public static function buildCache(int $minScore, callable $report): array
	{
		self::ensureFields();
		$report(2, 'fetch', 'Загружаю товары каталога…');
		$products = self::fetchAllProducts();
		$report(10, 'fetch', 'Загружено товаров: ' . count($products));
		$clusters = self::buildClusters($products, $minScore, $report);
		return [
			'clusters' => $clusters,
			'redirects' => self::$redirects,
			'probe' => self::$probeStats,
			'checked_products' => count($products),
			'h1_source' => self::h1Source(),
		];
	}

	/**
	 * Решения и noindex меняются кнопками — берём из БД, а не из кеша.
	 */
	protected static function refreshLive(array &$clusters): void
	{
		global $DB;
		$ids = [];
		foreach ($clusters as $cl) {
			foreach ($cl['items'] as $it) {
				$ids[(int) $it['id']] = true;
			}
		}
		if ($ids === []) {
			return;
		}
		$ids = array_keys($ids);
		$noindex = IndexingControl::mapNoindex($ids);
		$decisions = [];
		if (self::hasDecisionColumn()) {
			$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_element';
			foreach (array_chunk($ids, 1000) as $chunk) {
				$res = $DB->Query('SELECT VALUE_ID, ' . self::UF_DECISION . ' AS D FROM ' . $uts . ' WHERE VALUE_ID IN (' . implode(',', $chunk) . ')');
				while ($row = $res->Fetch()) {
					$d = trim((string) $row['D']);
					if ($d === self::DECISION_KEEP || $d === self::DECISION_NOINDEX) {
						$decisions[(int) $row['VALUE_ID']] = $d;
					}
				}
			}
		}
		foreach ($clusters as &$cl) {
			foreach ($cl['items'] as &$it) {
				$id = (int) $it['id'];
				$it['decision'] = $decisions[$id] ?? '';
				$it['noindex'] = !empty($noindex[$id]);
			}
			unset($it);
		}
		unset($cl);
	}

	protected static function refreshH1(array &$clusters): void
	{
		$items = [];
		foreach ($clusters as $cl) {
			foreach ($cl['items'] as $it) {
				$items[(int) $it['id']] = $it;
			}
		}
		$h1 = self::mapH1(array_keys($items), $items);
		foreach ($clusters as &$cl) {
			foreach ($cl['items'] as &$it) {
				$it['h1'] = $h1[(int) $it['id']] ?? '';
			}
			unset($it);
		}
		unset($cl);
	}

	protected static function kindGroup(string $kind): string
	{
		if ($kind === self::KIND_EXACT || $kind === self::KIND_COUNTRY) {
			return 'exact';
		}
		return $kind;
	}

	protected static function kindMatches(string $filter, string $group): bool
	{
		if ($filter === 'all') {
			return true;
		}
		if ($filter === 'dups' || $filter === '') {
			return $group === 'exact' || $group === 'similar';
		}
		return $filter === $group;
	}

	protected static function clusterMatches(array $cluster, string $needle): bool
	{
		foreach ($cluster['items'] ?? [] as $item) {
			foreach (['name', 'code', 'phrase', 'article'] as $f) {
				if (mb_stripos((string) ($item[$f] ?? ''), $needle) !== false) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Все активные товары каталога (без лимита) + артикул/производитель, если такие свойства есть.
	 *
	 * @return array<int, array>
	 */
	protected static function fetchAllProducts(array $onlyIds = []): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;
		$decisionSelect = self::hasDecisionColumn() ? ('UTS.' . self::UF_DECISION . ' AS TITLO_DECISION') : 'NULL AS TITLO_DECISION';
		$hasUts = (bool) $DB->Query("SHOW TABLES LIKE '{$uts}'")->Fetch();
		$phraseSelect = $hasUts && $DB->Query("SHOW COLUMNS FROM {$uts} LIKE '{$phraseCol}'")->Fetch()
			? ('UTS.' . $phraseCol . ' AS TITLO_PHRASE') : 'NULL AS TITLO_PHRASE';
		if (!$hasUts) {
			$decisionSelect = 'NULL AS TITLO_DECISION';
		}

		$res = $DB->Query('
			SELECT BE.ID, BE.NAME, BE.CODE, BE.IBLOCK_SECTION_ID, BE.SHOW_COUNTER,
				CHAR_LENGTH(BE.DETAIL_TEXT) AS TEXT_LEN, ' . $phraseSelect . ', ' . $decisionSelect . '
			FROM b_iblock_element BE
			' . ($hasUts ? 'LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BE.ID' : '') . '
			WHERE BE.IBLOCK_ID = ' . (int) $iblockId . "
				AND BE.ACTIVE = 'Y'
				AND (BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)
				AND (BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)
		" . ($onlyIds !== [] ? ' AND BE.ID IN (' . implode(',', array_map('intval', $onlyIds)) . ')' : ''));
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
				'section_id' => (int) $row['IBLOCK_SECTION_ID'],
				'views' => (int) $row['SHOW_COUNTER'],
				'text_len' => (int) $row['TEXT_LEN'],
				'phrase' => trim((string) ($row['TITLO_PHRASE'] ?? '')),
				'decision' => $decision,
				'noindex' => false,
				'url' => '',
				'article' => '',
				'manufacturer' => '',
			];
		}

		foreach (['article' => self::articlePropertyId(), 'manufacturer' => self::manufacturerPropertyId()] as $field => $pid) {
			if ($pid <= 0) {
				continue;
			}
			foreach (self::propertyValues($pid) as $id => $v) {
				if (isset($items[$id])) {
					$items[$id][$field] = $v;
				}
			}
		}
		return $items;
	}

	protected static function articlePropertyId(): int
	{
		return self::findPropertyId(['CML2_ARTICLE', 'ARTNUMBER', 'ARTICLE', 'ARTIKUL', 'SKU']);
	}

	protected static function manufacturerPropertyId(): int
	{
		return self::findPropertyId(['MANUFACTURER', 'BRAND', 'BRAND_REF', 'PROIZVODITEL', 'VENDOR', 'CML2_MANUFACTURER']);
	}

	/**
	 * @param string[] $codes
	 */
	protected static function findPropertyId(array $codes): int
	{
		static $cache = [];
		$key = implode(',', $codes);
		if (isset($cache[$key])) {
			return $cache[$key];
		}
		$found = 0;
		$res = \CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => Config::iblockId(), 'ACTIVE' => 'Y']);
		$byCode = [];
		while ($p = $res->Fetch()) {
			if ($p['MULTIPLE'] !== 'Y' && in_array($p['PROPERTY_TYPE'], ['S', 'L', 'E', 'N'], true)) {
				$byCode[strtoupper((string) $p['CODE'])] = (int) $p['ID'];
			}
		}
		foreach ($codes as $c) {
			if (isset($byCode[$c])) {
				$found = $byCode[$c];
				break;
			}
		}
		return $cache[$key] = $found;
	}

	/**
	 * Значения одиночного свойства для всех элементов (инфоблоки 1.0 и 2.0; списки — текст варианта).
	 *
	 * @return array<int, string>
	 */
	protected static function propertyValues(int $propertyId): array
	{
		global $DB;
		$iblockId = (int) Config::iblockId();
		$prop = \CIBlockProperty::GetByID($propertyId, $iblockId)->Fetch();
		if (!$prop) {
			return [];
		}
		$version = (int) (\CIBlock::GetArrayByID($iblockId, 'VERSION') ?: 1);
		$out = [];
		if ($version === 2) {
			$col = 'PROPERTY_' . $propertyId;
			$res = $DB->Query("SELECT IBLOCK_ELEMENT_ID AS ID, {$col} AS V FROM b_iblock_element_prop_s{$iblockId} WHERE {$col} IS NOT NULL AND {$col} <> ''");
		} else {
			$res = $DB->Query("SELECT IBLOCK_ELEMENT_ID AS ID, VALUE AS V FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = {$propertyId} AND VALUE <> ''");
		}
		while ($row = $res->Fetch()) {
			$out[(int) $row['ID']] = trim((string) $row['V']);
		}
		if ($prop['PROPERTY_TYPE'] === 'L' && $out !== []) {
			$enum = [];
			$r = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propertyId]);
			while ($e = $r->Fetch()) {
				$enum[(string) $e['ID']] = (string) $e['VALUE'];
			}
			foreach ($out as $id => $v) {
				$out[$id] = $enum[$v] ?? $v;
			}
		}
		if ($prop['PROPERTY_TYPE'] === 'E' && $out !== []) {
			$names = [];
			foreach (array_chunk(array_values(array_unique(array_map('intval', $out))), 500) as $chunk) {
				$r = $DB->Query('SELECT ID, NAME FROM b_iblock_element WHERE ID IN (' . implode(',', $chunk) . ')');
				while ($e = $r->Fetch()) {
					$names[(string) $e['ID']] = (string) $e['NAME'];
				}
			}
			foreach ($out as $id => $v) {
				$out[$id] = $names[$v] ?? $v;
			}
		}
		return $out;
	}

	/** Основы прилагательных, которые различают товары одной модели (цвет, возраст, сторона, размер). */
	protected const VARIANT_BASES = [
		'красн', 'зелен', 'син', 'черн', 'бел', 'желт', 'фиолетов', 'сер', 'голуб', 'розов', 'оранжев',
		'коричнев', 'прозрачн', 'бирюзов', 'бежев', 'серебрист', 'золотист', 'бордов', 'салатов',
		'детск', 'взросл', 'неонатальн', 'педиатрическ', 'подростков', 'мужск', 'женск', 'новорожденн',
		'лев', 'прав', 'мал', 'средн', 'больш', 'длинн', 'коротк', 'узк', 'широк',
		'стерильн', 'нестерильн', 'одноразов', 'многоразов',
		'прям', 'изогнут', 'углов', 'кругл', 'овальн', 'квадратн', 'прямоугольн', 'гибк', 'жестк',
		'плетен', 'кручен', 'одноканальн', 'двухканальн', 'трехканальн', 'четырехканальн', 'многоканальн',
	];

	/**
	 * Разбор названия товара: как у разделов, плюс
	 * «9мм» → «9 мм», «Star 5000 С» → модель «5000с», «Про» — часть модели,
	 * «без X» — отдельный признак, цвет/возраст/сторона/размер — вариант, а не смысл.
	 */
	public static function parseProductName(string $name): array
	{
		$s = preg_replace_callback('/(\d)\s*(мм|см|мл|мкм|мкл|мгц|кгц|гц|вт|квт|кг|гр|г|л|м|шт|уп|дптр)(?![\p{L}])/iu', static function ($m) {
			return $m[1] . mb_strtolower($m[2]);
		}, $name) ?? $name;
		$s = preg_replace('/(\d)\s+(\p{Lu})(?![\p{L}\p{N}])/u', '$1$2', $s) ?? $s;
		$s = preg_replace('/(?<![\p{L}\p{N}\-.])([a-zA-Z])(?![\p{L}\p{N}\-.])/u', ' zvmark$1 ', $s) ?? $s;
		$s = preg_replace_callback('/(?<![\p{L}\p{N}])(про|pro|плюс|plus|макс|max|мини|mini|лайт|lite)(?![\p{L}\p{N}])/iu', static function ($m) {
			$w = mb_strtolower($m[1]);
			$map = ['про' => 'pro', 'плюс' => 'plus', 'макс' => 'max', 'мини' => 'mini', 'лайт' => 'lite'];
			return ' zvmark' . ($map[$w] ?? $w) . ' ';
		}, $s) ?? $s;
		$s = preg_replace('/(?<![\p{L}\p{N}])без\s+(\p{L}+)/iu', ' без-$1 ', $s) ?? $s;
		$p = SectionDuplicates::parseName($s, false);
		foreach (['вт', 'квт', 'мгц', 'кгц', 'гц', 'мкл', 'уп', 'дптр', 'шт/уп', 'л', 'м', 'г'] as $u) {
			unset($p['core'][$u]);
		}
		foreach ($p['brands'] as $b => $_) {
			if (strpos($b, 'zvmark') === 0) {
				unset($p['brands'][$b]);
				$p['variants'][substr($b, 6)] = 1;
			}
		}
		$vw = self::variantStems();
		foreach ($p['core'] as $t => $_) {
			if (isset($vw[$t])) {
				unset($p['core'][$t]);
				$p['variants'][$t] = 1;
			}
		}
		ksort($p['variants']);
		$p['full'] = implode(' ', array_keys($p['core'])) . '|' . implode(' ', array_keys($p['brands']))
			. '|' . implode(' ', array_keys($p['variants'])) . '|' . implode(' ', array_keys($p['countries']));
		return $p;
	}

	/**
	 * Основы (как их режет parseName) для всех родов/чисел VARIANT_BASES.
	 *
	 * @return array<string, int>
	 */
	protected static function variantStems(): array
	{
		static $map = null;
		if ($map !== null) {
			return $map;
		}
		$map = [];
		$endings = ['ый', 'ий', 'ой', 'ая', 'яя', 'ое', 'ее', 'ые', 'ие', 'ого', 'его', 'ую', 'юю', 'ых', 'их', 'ым', 'им'];
		foreach (self::VARIANT_BASES as $base) {
			foreach ($endings as $e) {
				foreach (SectionDuplicates::parseName($base . $e)['core'] as $k => $_) {
					$map[$k] = 1;
				}
			}
		}
		return $map;
	}

	/**
	 * Слова, которыми названия различаются (для подписи «разница: …»).
	 */
	protected static function diffWords(string $refName, string $name): string
	{
		$words = static function (string $n): array {
			$out = [];
			if (preg_match_all('/[\p{L}]+(?:-[\p{L}]+)*/u', mb_strtolower($n), $m)) {
				foreach ($m[0] as $w) {
					foreach (array_keys(SectionDuplicates::parseName($w)['core']) as $k) {
						$out[$k] = $out[$k] ?? $w;
					}
				}
			}
			return $out;
		};
		$a = $words($refName);
		$b = $words($name);
		$plus = array_values(array_diff_key($b, $a));
		$minus = array_values(array_diff_key($a, $b));
		$parts = [];
		if ($plus !== []) {
			$parts[] = '+' . implode(', +', array_slice($plus, 0, 5));
		}
		if ($minus !== []) {
			$parts[] = '−' . implode(', −', array_slice($minus, 0, 5));
		}
		return $parts !== [] ? 'разница: ' . implode(' ', $parts) : '';
	}

	protected static function normManufacturer(string $m): string
	{
		return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($m)));
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
	 * Пара карточек — дубль? Разные бренды/модели/размеры — нет.
	 *
	 * @return array{score:int,kind:string}|null
	 */
	protected static function evalPair(int $a, int $b, array $products, int $minScore, array &$ctx): ?array
	{
		$pa = $ctx['parsed'][$a];
		$pb = $ctx['parsed'][$b];
		if ($pa['brands'] !== $pb['brands'] || $pa['variants'] !== $pb['variants']) {
			return null;
		}
		if ($pa['core'] === [] && $pa['brands'] === [] && $pa['variants'] === []) {
			return null;
		}
		if ($pa['countries'] !== $pb['countries'] && $pa['countries'] !== [] && $pb['countries'] !== []) {
			return null;
		}
		foreach ([[$pa, $pb], [$pb, $pa]] as [$x, $y]) {
			foreach ($x['core'] as $t => $_) {
				if (strpos($t, 'без-') === 0 && isset($y['core'][substr($t, strlen('без-'))])) {
					return null;
				}
			}
		}
		$ma = self::normManufacturer($products[$a]['manufacturer']);
		$mb = self::normManufacturer($products[$b]['manufacturer']);
		$otherMaker = $ma !== '' && $mb !== '' && $ma !== $mb;

		if ($pa['core'] === $pb['core']) {
			if ($otherMaker) {
				return ['score' => 100, 'kind' => self::KIND_HOMONYM];
			}
			return ['score' => 100, 'kind' => $pa['countries'] === $pb['countries'] ? self::KIND_EXACT : self::KIND_COUNTRY];
		}
		if ($otherMaker) {
			return null;
		}
		$score = self::weightedJaccard($pa['core'], $pb['core'], $ctx['idf']);
		return $score >= $minScore ? ['score' => $score, 'kind' => self::KIND_SIMILAR] : null;
	}

	/**
	 * @param array<int, array> $products
	 * @return array<int, array>
	 */
	protected static function buildClusters(array $products, int $minScore, ?callable $report = null): array
	{
		$report = $report ?? static function () {
		};
		$ctx = ['parsed' => [], 'idf' => []];
		$n = count($products);
		$i = 0;
		foreach ($products as $id => $p) {
			$ctx['parsed'][$id] = self::parseProductName($p['name']);
			if (++$i % 1000 === 0) {
				$report(10 + (int) (20 * $i / max(1, $n)), 'parse', 'Разбираю названия: ' . $i . ' / ' . $n);
			}
		}
		$report(30, 'cluster', 'Ищу пары и группы…');
		self::$probeStats = ['checked' => 0, 'pending' => 0, 'blocked' => 0, 'excluded' => 0];
		self::$redirects = [];

		$clusters = self::clusterize($products, $minScore, [], $ctx);
		$memberIds = [];
		foreach ($clusters as $cl) {
			foreach ($cl['ids'] as $id) {
				$memberIds[$id] = true;
			}
		}
		$report(48, 'urls', 'URL и 301-редиректы (' . count($memberIds) . ' товаров)…');
		$urls = UrlBuilder::mapElementUrls(array_keys($memberIds));
		$excluded = [];
		$paths = [];
		foreach ($urls as $id => $url) {
			$path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
			if ($path === '') {
				continue;
			}
			$to = RedirectMap::target($path);
			if ($to !== null && RedirectMap::normalizePath($to) !== RedirectMap::normalizePath($path)) {
				$excluded[$id] = '301 → ' . $to;
				continue;
			}
			$paths[$id] = $path;
		}

		$probe = [];
		if (UrlProbe::enabled()) {
			// Линейки — разные товары, их страницы по HTTP не проверяем (тысячи URL)
			$probePaths = [];
			foreach ($clusters as $cl) {
				if ($cl['kind'] === self::KIND_LINES) {
					continue;
				}
				foreach ($cl['ids'] as $id) {
					if (isset($paths[$id])) {
						$probePaths[$id] = $paths[$id];
					}
				}
			}
			$report(55, 'probe', 'Проверяю страницы на сайте (' . count($probePaths) . ')…');
			$probe = $probePaths !== [] ? UrlProbe::check($probePaths, 150.0, static function ($done, $total) use ($report) {
				$report(55 + (int) (33 * $done / max(1, $total)), 'probe', 'Проверяю страницы на сайте: ' . $done . ' / ' . $total);
			}) : [];
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
					$excluded[(int) $id] = ($r['type'] === UrlProbe::TYPE_CANONICAL ? 'canonical → ' : $r['code'] . ' → ') . $r['target'];
					self::$probeStats['excluded']++;
				}
			}
		}
		self::$redirects = $excluded;
		if ($excluded !== []) {
			$report(89, 'cluster2', 'Пересобираю группы без редиректов (' . count($excluded) . ')…');
			$clusters = self::clusterize($products, $minScore, $excluded, $ctx);
		}

		$report(91, 'enrich', 'H1, разделы, noindex…');
		$all = [];
		foreach ($clusters as $cl) {
			foreach ($cl['ids'] as $id) {
				$all[$id] = $products[$id];
			}
		}
		self::enrichItems($all, $urls);
		foreach ($all as $id => &$it) {
			$r = $probe[$id] ?? null;
			if ($r !== null && in_array($r['type'], [UrlProbe::TYPE_ERROR, UrlProbe::TYPE_BLOCKED], true)) {
				$it['url_note'] = $r['type'] === UrlProbe::TYPE_ERROR
					? ('страница отвечает HTTP ' . ($r['code'] ?: 'timeout'))
					: 'проверка URL заблокирована антиботом';
			}
		}
		unset($it);

		$report(96, 'finish', 'Сортирую группы (' . count($clusters) . ')…');
		$out = [];
		foreach ($clusters as $cl) {
			$out[] = self::finishCluster($cl, $all, $products, $minScore, $ctx);
		}
		usort($out, static function ($x, $y) {
			return (self::kindRank($x['kind']) <=> self::kindRank($y['kind']))
				?: ($y['size'] <=> $x['size'])
				?: ($y['score'] <=> $x['score'])
				?: strcmp($x['key'], $y['key']);
		});
		return $out;
	}

	/**
	 * Группы ID: дубли (union-find), тёзки (отдельный слой) и линейки (одна модель, разные варианты).
	 *
	 * @param array<int, string> $excluded
	 * @return array<int, array{root:string,kind:string,ids:int[],edges:array}>
	 */
	protected static function clusterize(array $products, int $minScore, array $excluded, array &$ctx): array
	{
		$df = [];
		$active = 0;
		foreach ($ctx['parsed'] as $id => $p) {
			if (isset($excluded[$id])) {
				continue;
			}
			$active++;
			foreach ($p['core'] as $t => $_) {
				$df[$t] = ($df[$t] ?? 0) + 1;
			}
		}
		$ctx['idf'] = [];
		foreach ($df as $t => $n) {
			$ctx['idf'][$t] = log(1 + $active / $n);
		}

		$postings = [];
		$lines = [];
		foreach ($ctx['parsed'] as $id => $p) {
			if (isset($excluded[$id])) {
				continue;
			}
			$sig = implode(' ', array_keys($p['core'])) . '|' . implode(' ', array_keys($p['brands']));
			$vk = implode(' ', array_keys($p['variants']));
			$postings['s:' . $sig . '|' . $vk][] = $id;
			if ($p['core'] !== [] || $p['brands'] !== []) {
				$lines[$sig][$vk . '|' . implode(' ', array_keys($p['countries']))][] = $id;
			}
			$stems = array_keys($p['core']);
			usort($stems, static function ($x, $y) use ($df) {
				return ($df[$x] <=> $df[$y]) ?: strcmp($x, $y);
			});
			$taken = 0;
			foreach ($stems as $t) {
				if ($df[$t] > self::BLOCK_DF_MAX) {
					break;
				}
				$postings['t:' . implode(' ', array_keys($p['brands'])) . '|' . $vk . '|' . $t][] = $id;
				if (++$taken >= 2) {
					break;
				}
			}
		}

		$edges = [0 => [], 1 => []];
		$seen = [];
		foreach ($postings as $ids) {
			$n = count($ids);
			if ($n < 2 || $n > self::POSTING_MAX) {
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
					$e = self::evalPair($a, $b, $products, $minScore, $ctx);
					if ($e === null) {
						continue;
					}
					$e['a'] = $a;
					$e['b'] = $b;
					$edges[$e['kind'] === self::KIND_HOMONYM ? 1 : 0][] = $e;
				}
			}
		}
		unset($seen, $postings);

		$parent = [];
		$find = static function (int $x) use (&$parent): int {
			while ($parent[$x] !== $x) {
				$parent[$x] = $parent[$parent[$x]];
				$x = $parent[$x];
			}
			return $x;
		};
		$out = [];
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
			$groups = [];
			foreach (array_keys($parent) as $id) {
				$groups[$find($id)][] = $id;
			}
			$gEdges = [];
			foreach ($list as $e) {
				$gEdges[$find($e['a'])][] = $e;
			}
			foreach ($groups as $root => $ids) {
				$kind = self::KIND_EXACT;
				foreach ($gEdges[$root] ?? [] as $e) {
					if (self::kindRank($e['kind']) > self::kindRank($kind)) {
						$kind = $e['kind'];
					}
				}
				$out[] = ['root' => $layer . ':' . $root, 'kind' => $kind, 'ids' => $ids, 'edges' => $gEdges[$root] ?? []];
			}
		}

		foreach ($lines as $sig => $byVariant) {
			if (count($byVariant) < 2) {
				continue;
			}
			$ids = array_merge(...array_values($byVariant));
			if (count($ids) > self::LINE_MAX) {
				continue;
			}
			$out[] = ['root' => '2:' . md5($sig), 'kind' => self::KIND_LINES, 'ids' => $ids, 'edges' => []];
		}
		return $out;
	}

	/**
	 * Эталон, подписи «почему дубль», сортировка строк.
	 */
	protected static function finishCluster(array $cl, array $all, array $products, int $minScore, array &$ctx): array
	{
		$items = [];
		foreach ($cl['ids'] as $id) {
			$items[] = $all[$id];
		}
		$isLine = $cl['kind'] === self::KIND_LINES;
		usort($items, static function ($x, $y) use ($isLine) {
			$kx = $x['decision'] === self::DECISION_KEEP ? 0 : 1;
			$ky = $y['decision'] === self::DECISION_KEEP ? 0 : 1;
			if ($kx !== $ky) {
				return $kx <=> $ky;
			}
			if ($isLine) {
				return strnatcasecmp($x['name'], $y['name']) ?: ($x['id'] <=> $y['id']);
			}
			$cx = ($x['noindex'] || $x['decision'] === self::DECISION_NOINDEX) ? 1 : 0;
			$cy = ($y['noindex'] || $y['decision'] === self::DECISION_NOINDEX) ? 1 : 0;
			return ($cx <=> $cy)
				?: ($y['views'] <=> $x['views'])
				?: ($y['text_len'] <=> $x['text_len'])
				?: ($x['id'] <=> $y['id']);
		});
		if ($isLine && $items[0]['decision'] !== self::DECISION_KEEP) {
			$top = 0;
			foreach ($items as $i => $it) {
				$closed = $it['noindex'] || $it['decision'] === self::DECISION_NOINDEX;
				if (!$closed && $it['views'] > $items[$top]['views']) {
					$top = $i;
				}
			}
			if ($top > 0) {
				array_unshift($items, array_splice($items, $top, 1)[0]);
			}
		}

		$ref = $items[0]['id'];
		$best = [];
		$minEdge = 100;
		foreach ($cl['edges'] as $e) {
			$minEdge = min($minEdge, $e['score']);
			foreach ([$e['a'], $e['b']] as $end) {
				if (!isset($best[$end]) || $e['score'] > $best[$end]['score']) {
					$best[$end] = $e;
				}
			}
		}

		$refArticle = (string) $items[0]['article'];
		foreach ($items as $i => &$item) {
			$item['distinct'] = false;
			if ($isLine) {
				$item['sim_to_top'] = null;
				$item['sim_label'] = self::variantNote($ctx['parsed'][$item['id']]);
				if ($i === 0) {
					$item['sim_label'] = 'эталон линейки: ' . ($item['decision'] === self::DECISION_KEEP
						? 'отмечен «прорабатывать»' : 'больше всего просмотров') . '; ' . $item['sim_label'];
				} elseif ($items[0]['views'] > 0) {
					$item['sim_label'] .= '; просмотров ' . (int) round(100 * $item['views'] / $items[0]['views']) . '% от эталона';
				}
				continue;
			}
			if ($i === 0) {
				$item['sim_to_top'] = 100;
				$item['sim_label'] = $item['decision'] === self::DECISION_KEEP
					? 'эталон: отмечен «прорабатывать»'
					: ('эталон: ' . ($item['views'] > 0 ? 'больше всего просмотров' : 'первая карточка'));
				continue;
			}
			$edge = self::evalPair(min($ref, $item['id']), max($ref, $item['id']), $products, $minScore, $ctx);
			if ($edge !== null) {
				$item['sim_to_top'] = $edge['score'];
				$item['sim_label'] = 'как #' . $ref . ': ' . self::reasonText($edge);
			} else {
				$e = $best[$item['id']] ?? ['score' => 0, 'kind' => '', 'a' => $ref, 'b' => $item['id']];
				$other = $e['a'] === $item['id'] ? $e['b'] : $e['a'];
				$item['sim_to_top'] = $e['score'];
				$item['sim_label'] = 'как #' . $other . ': ' . self::reasonText($e);
			}
			$diff = self::diffWords($items[0]['name'], $item['name']);
			if ($diff !== '') {
				$item['sim_label'] .= '; ' . $diff;
			}
			$art = (string) $item['article'];
			if ($refArticle !== '' && $art !== '') {
				$item['sim_label'] .= mb_strtolower($art) === mb_strtolower($refArticle)
					? '; артикул тот же'
					: '; артикул другой — проверьте, не разные ли это товары';
			}
		}
		unset($item);

		return [
			'key' => 'k' . $ref . '-' . $cl['root'],
			'title' => $items[0]['name'],
			'score' => $isLine ? 100 : $minEdge,
			'kind' => $cl['kind'],
			'kind_label' => self::kindLabel($cl['kind']),
			'items' => $items,
			'size' => count($items),
		];
	}

	protected static function variantNote(array $p): string
	{
		$parts = [];
		if ($p['variants'] !== []) {
			$parts[] = 'вариант: ' . implode(' ', array_map('strval', array_keys($p['variants'])));
		}
		if (!empty($p['country_words'])) {
			$parts[] = 'страна ' . implode(', ', array_map(static function ($w) {
				return mb_convert_case((string) $w, MB_CASE_TITLE, 'UTF-8');
			}, array_keys($p['country_words'])));
		}
		return $parts !== [] ? implode('; ', $parts) : 'базовая модель';
	}

	protected static function kindRank(string $kind): int
	{
		return [
			self::KIND_EXACT => 0,
			self::KIND_COUNTRY => 1,
			self::KIND_SIMILAR => 2,
			self::KIND_HOMONYM => 3,
			self::KIND_LINES => 4,
		][$kind] ?? 9;
	}

	protected static function kindLabel(string $kind): string
	{
		return [
			self::KIND_EXACT => 'одинаковые названия',
			self::KIND_COUNTRY => 'отличие только в стране',
			self::KIND_SIMILAR => 'похожие названия',
			self::KIND_HOMONYM => 'одно название — разные производители',
			self::KIND_LINES => 'линейка: размеры/модели — разные товары',
		][$kind] ?? '';
	}

	protected static function reasonText(array $edge): string
	{
		switch ($edge['kind'] ?? '') {
			case self::KIND_EXACT:
				return 'то же название';
			case self::KIND_COUNTRY:
				return 'то же название, страна указана только у одной';
			case self::KIND_HOMONYM:
				return 'то же название, другой производитель';
			default:
				return 'слова совпадают на ' . (int) ($edge['score'] ?? 0) . '%';
		}
	}

	/**
	 * URL, noindex, H1, раздел.
	 *
	 * @param array<int, array> $items
	 * @param array<int, string> $urls
	 */
	protected static function enrichItems(array &$items, array $urls = []): void
	{
		if ($items === []) {
			return;
		}
		$ids = array_keys($items);
		$missing = array_values(array_diff($ids, array_keys($urls)));
		if ($missing !== []) {
			$urls += UrlBuilder::mapElementUrls($missing);
		}
		$noindexMap = IndexingControl::mapNoindex($ids);
		$h1Map = self::mapH1($ids, $items);
		foreach ($items as $id => &$item) {
			$item['url'] = $urls[$id] ?? UrlBuilder::product($item['code'], $id);
			$item['noindex'] = !empty($noindexMap[$id]);
			$item['h1'] = $h1Map[$id] ?? '';
			$item['path'] = self::sectionPath((int) ($item['section_id'] ?? 0));
			$item['sim_to_top'] = null;
			$item['sim_label'] = '';
		}
		unset($item);
	}

	protected static function sectionPath(int $sectionId): string
	{
		static $sections = null;
		if ($sectionId <= 0) {
			return '';
		}
		if ($sections === null) {
			global $DB;
			$sections = [];
			$res = $DB->Query('SELECT ID, NAME, IBLOCK_SECTION_ID FROM b_iblock_section WHERE IBLOCK_ID = ' . (int) Config::iblockId());
			while ($row = $res->Fetch()) {
				$sections[(int) $row['ID']] = [(string) $row['NAME'], (int) $row['IBLOCK_SECTION_ID']];
			}
		}
		$parts = [];
		$guard = 0;
		while ($sectionId > 0 && isset($sections[$sectionId]) && $guard++ < 20) {
			array_unshift($parts, $sections[$sectionId][0]);
			$sectionId = $sections[$sectionId][1];
		}
		return implode(' › ', $parts);
	}

	/**
	 * Откуда брать H1 товара: SEO-шаблоны инфоблока, NAME, строковое свойство или UF.
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
			'SEO:ELEMENT_PAGE_TITLE' => 'SEO: заголовок товара (H1)',
			'SEO:ELEMENT_META_TITLE' => 'SEO: META TITLE',
			'NAME' => 'Название товара (NAME)',
		];
		$iblockId = Config::iblockId();
		if ($iblockId > 0) {
			$res = \CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'PROPERTY_TYPE' => 'S', 'MULTIPLE' => 'N']);
			while ($p = $res->Fetch()) {
				if ((string) $p['USER_TYPE'] !== '' && $p['USER_TYPE'] !== 'HTML') {
					continue;
				}
				$code = (string) $p['CODE'];
				if ($code === '' || !preg_match('/^[A-Za-z0-9_]+$/', $code)) {
					continue;
				}
				$opts['PROP:' . $code] = 'Свойство ' . $code . ' — ' . $p['NAME'];
			}
			$res = \CUserTypeEntity::GetList(['SORT' => 'ASC'], [
				'ENTITY_ID' => 'IBLOCK_' . $iblockId . '_ELEMENT',
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
		$src = Config::get('product_h1_source', self::H1_DEFAULT);
		return isset(self::h1SourceOptions()[$src]) ? $src : self::H1_DEFAULT;
	}

	public static function setH1Source(string $src): bool
	{
		if (!isset(self::h1SourceOptions()[$src])) {
			return false;
		}
		\Bitrix\Main\Config\Option::set(Config::MODULE_ID, 'product_h1_source', $src);
		return true;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, string>
	 */
	protected static function mapH1(array $ids, array $items): array
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
				$out[$id] = (string) ($items[$id]['name'] ?? '');
			}
			return $out;
		}
		if (strpos($src, 'PROP:') === 0) {
			$pid = self::findPropertyId([strtoupper(substr($src, 5))]);
			if ($pid <= 0) {
				$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => substr($src, 5)])->Fetch();
				$pid = $prop ? (int) $prop['ID'] : 0;
			}
			if ($pid <= 0) {
				return [];
			}
			$vals = self::propertyValues($pid);
			foreach ($ids as $id) {
				$out[$id] = $vals[$id] ?? '';
			}
			return $out;
		}
		if (strpos($src, 'UF:') === 0) {
			$col = substr($src, 3);
			if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
				return [];
			}
			$uts = 'b_uts_iblock_' . $iblockId . '_element';
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
				SELECT EI.ELEMENT_ID, EI.VALUE
				FROM b_iblock_element_iprop EI
				INNER JOIN b_iblock_iproperty IP ON IP.ID = EI.IPROP_ID
				WHERE EI.IBLOCK_ID = {$iblockId} AND IP.CODE = '" . $DB->ForSql($code) . "'
					AND EI.ELEMENT_ID IN (" . implode(',', $chunk) . ')
			');
			while ($row = $res->Fetch()) {
				$out[(int) $row['ELEMENT_ID']] = trim((string) $row['VALUE']);
			}
		}
		$missing = array_slice(array_diff($ids, array_keys($out)), 0, 300);
		foreach ($missing as $id) {
			$vals = (new \Bitrix\Iblock\InheritedProperty\ElementValues($iblockId, $id))->getValues();
			$out[$id] = trim((string) ($vals[$code] ?? ''));
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
		if (!$DB->Query("SHOW TABLES LIKE '{$uts}'")->Fetch()) {
			return self::$decisionColumnCache = false;
		}
		$res = $DB->Query("SHOW COLUMNS FROM {$uts} LIKE '" . self::UF_DECISION . "'");
		self::$decisionColumnCache = (bool) $res->Fetch();
		return self::$decisionColumnCache;
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
		if ($keep === 1 && $closed === $n - 1) {
			return 'resolved';
		}
		if ($n > 0 && $closed === $n) {
			return 'resolved';
		}
		return 'open';
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
