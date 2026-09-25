<?php

namespace Titlo\Relevance;

class CatalogRepository
{
	/**
	 * Поиск в списках: ID / название / код / короткая фраза / полный URL.
	 * Несколько слов — все токены обязательны (AND), порядок и регистр не важны,
	 * достаточно вхождения части слова (LIKE %token%).
	 * Пустая строка — без доп. условия.
	 */
	public static function sqlPhraseSearchCondition(
		string $q,
		string $tableAlias,
		string $entityType,
		string $phraseCol
	): string {
		$q = trim($q);
		if ($q === '') {
			return '';
		}
		$alias = preg_replace('#[^A-Za-z0-9_]#', '', $tableAlias) ?: 'BE';
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$phraseCol = preg_replace('#[^A-Za-z0-9_]#', '', $phraseCol) ?: 'UF_TITLO_PHRASE';

		$urlIds = UrlBuilder::resolveCatalogIdsFromUrl($q, $entityType);
		if ($urlIds !== null) {
			if ($urlIds === []) {
				return '1 = 0';
			}
			$ids = array_values(array_unique(array_filter(array_map('intval', $urlIds))));
			if ($ids === []) {
				return '1 = 0';
			}
			return $alias . '.ID IN (' . implode(',', $ids) . ')';
		}

		if (ctype_digit($q)) {
			return $alias . '.ID = ' . (int) $q;
		}

		$tokens = self::searchTokens($q);
		if ($tokens === []) {
			return '1 = 0';
		}

		$parts = [];
		foreach ($tokens as $token) {
			$like = Config::forLike($token);
			$parts[] = '(' . $alias . '.NAME LIKE "%' . $like . '%" OR '
				. $alias . '.CODE LIKE "%' . $like . '%" OR UTS.' . $phraseCol . ' LIKE "%' . $like . '%")';
		}
		return '(' . implode(' AND ', $parts) . ')';
	}

	/**
	 * SQL-выражение релевантности для ORDER BY (больше = лучше).
	 */
	public static function sqlSearchRelevanceScore(string $tableAlias, string $q, ?string $phraseCol = null): string
	{
		global $DB;
		$alias = preg_replace('#[^A-Za-z0-9_]#', '', $tableAlias) ?: 'BE';
		$q = trim($q);
		if ($q === '' || ctype_digit($q)) {
			return '0';
		}
		$tokens = self::searchTokens($q);
		if ($tokens === []) {
			return '0';
		}

		$fullLike = Config::forLike($q);
		$parts = [
			'(CASE WHEN LOWER(' . $alias . '.NAME) = LOWER("' . $DB->ForSql($q) . '") THEN 1000 ELSE 0 END)',
			'(CASE WHEN ' . $alias . '.NAME LIKE "' . $fullLike . '%" THEN 400 ELSE 0 END)',
			'(CASE WHEN ' . $alias . '.NAME LIKE "%' . $fullLike . '%" THEN 200 ELSE 0 END)',
			'(CASE WHEN ' . $alias . '.CODE LIKE "%' . $fullLike . '%" THEN 100 ELSE 0 END)',
		];
		$safePhrase = $phraseCol !== null && $phraseCol !== ''
			? preg_replace('#[^A-Za-z0-9_]#', '', $phraseCol)
			: '';
		if ($safePhrase !== '') {
			$parts[] = '(CASE WHEN UTS.' . $safePhrase . ' LIKE "%' . $fullLike . '%" THEN 80 ELSE 0 END)';
		}
		foreach ($tokens as $i => $token) {
			$like = Config::forLike($token);
			$w = max(10, 80 - ($i * 10));
			$parts[] = '(CASE WHEN ' . $alias . '.NAME LIKE "%' . $like . '%" THEN ' . $w . ' ELSE 0 END)';
			if ($safePhrase !== '') {
				$parts[] = '(CASE WHEN UTS.' . $safePhrase . ' LIKE "%' . $like . '%" THEN ' . (int) max(5, $w / 2) . ' ELSE 0 END)';
			}
		}
		return '(' . implode(' + ', $parts) . ')';
	}

	/**
	 * @return array|null
	 */
	public static function findElement(int $id)
	{
		$iblockId = Config::iblockId();
		$res = \CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $id],
			false,
			false,
			['ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'DETAIL_PAGE_URL', 'IBLOCK_ID']
		);
		if ($ob = $res->GetNextElement()) {
			$fields = $ob->GetFields();
			// UF через GetList/GetFields часто пустой — читаем b_uts напрямую (как на «Проверке названий»).
			$phrase = self::getElementPhrase((int) $fields['ID']);

			$entity = [
				'type' => 'E',
				'id' => (int) $fields['ID'],
				'name' => (string) $fields['NAME'],
				'code' => (string) $fields['CODE'],
				'preview_text' => (string) $fields['~PREVIEW_TEXT'],
				'detail_text' => (string) $fields['~DETAIL_TEXT'],
				'titlo_phrase' => $phrase,
				'url' => UrlBuilder::fromDetailPageUrl(
					(string) ($fields['DETAIL_PAGE_URL'] ?? ''),
					(string) $fields['CODE'],
					'E'
				),
			];
			$entity['preferred_phrase'] = self::preferredPhrase($entity);

			return $entity;
		}
		return null;
	}

	/**
	 * @return array|null
	 */
	public static function findSection(int $id)
	{
		$iblockId = Config::iblockId();
		$res = \CIBlockSection::GetList(
			[],
			['IBLOCK_ID' => $iblockId, 'ID' => $id],
			false,
			['ID', 'NAME', 'CODE', 'DESCRIPTION', 'SECTION_PAGE_URL', 'IBLOCK_ID']
		);
		if ($fields = $res->GetNext()) {
			$phrase = self::getSectionPhrase((int) $fields['ID']);
			$entity = [
				'type' => 'S',
				'id' => (int) $fields['ID'],
				'name' => (string) $fields['NAME'],
				'code' => (string) $fields['CODE'],
				'description' => (string) $fields['~DESCRIPTION'],
				'titlo_phrase' => $phrase,
				'url' => UrlBuilder::fromDetailPageUrl(
					(string) ($fields['SECTION_PAGE_URL'] ?? ''),
					(string) $fields['CODE'],
					'S',
					(int) $fields['ID']
				),
			];
			$entity['preferred_phrase'] = self::preferredPhrase($entity);

			return $entity;
		}
		return null;
	}

	/**
	 * Search by ID / NAME / CODE / URL.
	 * Multi-word: все токены должны встретиться в NAME или CODE (AND).
	 * Сортировка по релевантности, не по алфавиту.
	 *
	 * @return array<int, array{id:int,name:string,code:string,type:string}>
	 */
	public static function search(string $entityType, string $q, int $limit = 20): array
	{
		global $DB;

		$iblockId = Config::iblockId();
		$q = trim($q);
		$limit = max(1, min(50, $limit));
		$out = [];
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';

		$urlIds = UrlBuilder::resolveCatalogIdsFromUrl($q, $entityType);
		if ($urlIds !== null) {
			foreach ($urlIds as $id) {
				$id = (int) $id;
				if ($id <= 0) {
					continue;
				}
				if ($entityType === 'S') {
					$row = \CIBlockSection::GetList(
						[],
						['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N'],
						false,
						['ID', 'NAME', 'CODE'],
						['nTopCount' => 1]
					)->Fetch();
				} else {
					$row = \CIBlockElement::GetList(
						[],
						['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N', 'SHOW_NEW' => 'Y'],
						false,
						['nTopCount' => 1],
						['ID', 'NAME', 'CODE']
					)->Fetch();
				}
				if ($row) {
					$out[] = [
						'id' => (int) $row['ID'],
						'name' => (string) $row['NAME'],
						'code' => (string) $row['CODE'],
						'type' => $entityType,
					];
				}
			}
			return $out;
		}

		if ($q === '') {
			return [];
		}

		if (ctype_digit($q)) {
			$id = (int) $q;
			if ($entityType === 'S') {
				$row = \CIBlockSection::GetList(
					[],
					['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N'],
					false,
					['ID', 'NAME', 'CODE'],
					['nTopCount' => 1]
				)->Fetch();
			} else {
				$row = \CIBlockElement::GetList(
					[],
					['IBLOCK_ID' => $iblockId, 'ID' => $id, 'CHECK_PERMISSIONS' => 'N', 'SHOW_NEW' => 'Y'],
					false,
					['nTopCount' => 1],
					['ID', 'NAME', 'CODE']
				)->Fetch();
			}
			if ($row) {
				return [[
					'id' => (int) $row['ID'],
					'name' => (string) $row['NAME'],
					'code' => (string) $row['CODE'],
					'type' => $entityType,
				]];
			}
			return [];
		}

		$table = $entityType === 'S' ? 'b_iblock_section' : 'b_iblock_element';
		$where = [
			'T.IBLOCK_ID = ' . (int) $iblockId,
		];
		if ($entityType === 'E') {
			$where[] = "T.ACTIVE = 'Y'";
			$where[] = '(T.WF_STATUS_ID IS NULL OR T.WF_STATUS_ID = 1)';
			$where[] = '(T.WF_PARENT_ELEMENT_ID IS NULL OR T.WF_PARENT_ELEMENT_ID = 0)';
		} else {
			$where[] = "T.ACTIVE = 'Y'";
		}

		$tokens = self::searchTokens($q);
		if ($tokens === []) {
			return [];
		}

		$tokenConds = [];
		foreach ($tokens as $token) {
			$like = Config::forLike($token);
			$tokenConds[] = '(T.NAME LIKE "%' . $like . '%" OR T.CODE LIKE "%' . $like . '%")';
		}
		$where[] = '(' . implode(' AND ', $tokenConds) . ')';

		// Релевантность: точное имя → начинается с запроса → все токены ближе к началу → короче имя
		$fullLike = Config::forLike($q);
		$scoreParts = [
			'(CASE WHEN LOWER(T.NAME) = LOWER("' . $DB->ForSql($q) . '") THEN 1000 ELSE 0 END)',
			'(CASE WHEN T.NAME LIKE "' . $fullLike . '%" THEN 400 ELSE 0 END)',
			'(CASE WHEN T.NAME LIKE "%' . $fullLike . '%" THEN 200 ELSE 0 END)',
			'(CASE WHEN T.CODE LIKE "%' . $fullLike . '%" THEN 100 ELSE 0 END)',
		];
		foreach ($tokens as $i => $token) {
			$like = Config::forLike($token);
			$w = max(10, 80 - ($i * 10));
			$scoreParts[] = '(CASE WHEN T.NAME LIKE "%' . $like . '%" THEN ' . $w . ' ELSE 0 END)';
		}
		$scoreSql = '(' . implode(' + ', $scoreParts) . ')';

		$sql = '
			SELECT T.ID, T.NAME, T.CODE, ' . $scoreSql . ' AS SCORE
			FROM ' . $table . ' T
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY SCORE DESC, CHAR_LENGTH(T.NAME) ASC, T.NAME ASC
			LIMIT ' . (int) $limit;

		$res = $DB->Query($sql);
		while ($row = $res->Fetch()) {
			$out[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'type' => $entityType,
			];
		}
		return $out;
	}

	/**
	 * Токены поискового запроса (слова ≥ 2 символов, без пустых).
	 *
	 * @return string[]
	 */
	public static function searchTokens(string $q): array
	{
		$q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
		if ($q === '') {
			return [];
		}
		$parts = preg_split('/[\s,;|]+/u', $q) ?: [];
		$out = [];
		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ($part === '') {
				continue;
			}
			// короткие служебные отбрасываем, но оставляем латиницу брендов (ka, 3m и т.п. ≥ 2)
			if (mb_strlen($part) < 2) {
				continue;
			}
			$out[] = $part;
		}
		// если всё отфильтровали — ищем целой фразой
		if ($out === [] && $q !== '') {
			$out[] = $q;
		}
		return array_values(array_unique($out));
	}

	/**
	 * Paginated product list for name-check UI.
	 *
	 * @return array{items: array, total: int, page: int, page_size: int, pages: int}
	 */
	public static function listElementsForPhraseCheck(array $params): array
	{
		global $DB;

		$iblockId = Config::iblockId();
		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(100, (int) ($params['page_size'] ?? 25)));
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo'); // all|empty|filled|long|skip|todo|country|country_todo
		$active = (string) ($params['active'] ?? 'Y');

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;
		$skipCol = UserFields::SKIP_FIELD;
		$phraseAtCol = UserFields::PHRASE_AT_FIELD;

		$where = ['BE.IBLOCK_ID = ' . (int) $iblockId];
		if ($active === 'Y' || $active === 'N') {
			$where[] = "BE.ACTIVE = '" . $DB->ForSql($active) . "'";
		}
		// Не в корзине
		$where[] = '(BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)';
		$where[] = '(BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)';

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BE', 'E', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		// skip / work filters
		if ($filterMode === 'skip') {
			$where[] = 'UTS.' . $skipCol . ' = 1';
		} elseif ($filterMode !== 'all') {
			$where[] = '(UTS.' . $skipCol . ' IS NULL OR UTS.' . $skipCol . ' = 0 OR UTS.' . $skipCol . ' = "")';
		}

		if ($filterMode === 'long' || $filterMode === 'todo') {
			$where[] = 'CHAR_LENGTH(BE.NAME) > 50';
		}
		if ($filterMode === 'empty' || $filterMode === 'todo' || $filterMode === 'country_todo') {
			$where[] = '(UTS.' . $phraseCol . ' IS NULL OR UTS.' . $phraseCol . ' = "")';
		} elseif ($filterMode === 'filled') {
			$where[] = '(UTS.' . $phraseCol . ' IS NOT NULL AND UTS.' . $phraseCol . ' <> "")';
		}

		if ($filterMode === 'country' || $filterMode === 'country_todo') {
			$where[] = CountryInName::sqlNameHasCountry('BE.NAME', $DB);
		}

		$sectionIds = self::sectionIdsFromParams($params);
		$branchSql = self::sqlElementInAnySectionSubtree('BE', $sectionIds, $iblockId);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		}

		$whereSql = implode(' AND ', $where);
		$offset = ($page - 1) * $pageSize;

		$countSql = "
			SELECT COUNT(*) AS CNT
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
		";
		$countRow = $DB->Query($countSql)->Fetch();
		$total = (int) ($countRow['CNT'] ?? 0);

		$listSql = "
			SELECT
				BE.ID,
				BE.NAME,
				BE.CODE,
				BE.ACTIVE,
				UTS.{$phraseCol} AS TITLO_PHRASE,
				UTS.{$skipCol} AS TITLO_SKIP,
				UTS.{$phraseAtCol} AS TITLO_PHRASE_AT,
				UTS." . UserFields::NAME_ORIG_FIELD . " AS TITLO_NAME_ORIG
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
			ORDER BY BE.ID DESC
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		';

		$items = [];
		$res = $DB->Query($listSql);
		while ($row = $res->Fetch()) {
			$name = (string) $row['NAME'];
			$phrase = trim((string) ($row['TITLO_PHRASE'] ?? ''));
			$skip = UserFields::isSkipValue($row['TITLO_SKIP'] ?? 0);
			$code = (string) $row['CODE'];
			$nameLen = mb_strlen($name);
			$hasCountry = CountryInName::contains($name);
			$phraseAt = UserFields::formatPhraseAt($row['TITLO_PHRASE_AT'] ?? null);
			$nameOrig = trim((string) ($row['TITLO_NAME_ORIG'] ?? ''));

			$items[] = [
				'id' => (int) $row['ID'],
				'name' => $name,
				'name_len' => $nameLen,
				'name_orig' => $nameOrig,
				'code' => $code,
				'active' => (string) $row['ACTIVE'],
				'titlo_phrase' => $phrase,
				'phrase_len' => mb_strlen($phrase),
				'phrase_at' => $phraseAt,
				'skip' => $skip,
				'has_country' => $hasCountry,
				'url' => '',
				'needs_short' => $nameLen > 50 && $phrase === '' && !$skip,
			];
		}

		$urlMap = UrlBuilder::mapElementUrls(array_column($items, 'id'));
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::product($item['code'], $id);
		}
		unset($item);

		$pages = $pageSize > 0 ? (int) max(1, (int) ceil($total / $pageSize)) : 1;
		if ($total === 0) {
			$pages = 1;
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'pages' => $pages,
		];
	}

	/**
	 * @param array{filter?:string,q?:string,active?:string} $params
	 * @return array{0:string,1:string} [whereSql, uts]
	 */
	protected static function phraseBulkElementWhere(array $params): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo');
		$active = (string) ($params['active'] ?? 'Y');

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;
		$skipCol = UserFields::SKIP_FIELD;

		$where = ['BE.IBLOCK_ID = ' . (int) $iblockId];
		if ($active === 'Y' || $active === 'N') {
			$where[] = "BE.ACTIVE = '" . $DB->ForSql($active) . "'";
		}
		$where[] = '(BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)';
		$where[] = '(BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)';

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BE', 'E', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		if ($filterMode === 'skip') {
			$where[] = 'UTS.' . $skipCol . ' = 1';
		} elseif ($filterMode !== 'all') {
			$where[] = '(UTS.' . $skipCol . ' IS NULL OR UTS.' . $skipCol . ' = 0 OR UTS.' . $skipCol . ' = "")';
		}

		if ($filterMode === 'long' || $filterMode === 'todo') {
			$where[] = 'CHAR_LENGTH(BE.NAME) > 50';
		}
		if ($filterMode === 'empty' || $filterMode === 'todo' || $filterMode === 'country_todo') {
			$where[] = '(UTS.' . $phraseCol . ' IS NULL OR UTS.' . $phraseCol . ' = "")';
		} elseif ($filterMode === 'filled') {
			$where[] = '1 = 0';
		}

		if ($filterMode === 'country' || $filterMode === 'country_todo') {
			$where[] = CountryInName::sqlNameHasCountry('BE.NAME', $DB);
		}

		$sectionIds = self::sectionIdsFromParams($params);
		$branchSql = self::sqlElementInAnySectionSubtree('BE', $sectionIds, $iblockId);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		}

		return [implode(' AND ', $where), $uts];
	}

	public static function countElementsForPhraseBulk(array $params): int
	{
		global $DB;
		[$whereSql, $uts] = self::phraseBulkElementWhere($params);
		$row = $DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
		")->Fetch();
		return (int) ($row['CNT'] ?? 0);
	}

	/**
	 * ID+NAME+URL для массовой генерации фраз (без пагинации UI).
	 *
	 * @param array{filter?:string,q?:string,limit?:int,active?:string} $params
	 * @return array<int, array{id:int,name:string,url:string}>
	 */
	public static function listElementsForPhraseBulk(array $params): array
	{
		global $DB;

		$limit = max(1, min(PhraseBulk::MAX_LIMIT, (int) ($params['limit'] ?? 500)));
		[$whereSql, $uts] = self::phraseBulkElementWhere($params);

		$sql = "
			SELECT BE.ID, BE.NAME, BE.CODE
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
			ORDER BY BE.ID DESC
			LIMIT " . (int) $limit . '
		';

		$items = [];
		$res = $DB->Query($sql);
		while ($row = $res->Fetch()) {
			$code = (string) $row['CODE'];
			$items[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => $code,
				'url' => '',
			];
		}
		$urlMap = UrlBuilder::mapElementUrls(array_column($items, 'id'));
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::product($item['code'], $id);
			unset($item['code']);
		}
		unset($item);
		return $items;
	}

	/**
	 * Пагинированный список категорий для проверки названий.
	 *
	 * @return array{items: array, total: int, page: int, page_size: int, pages: int}
	 */
	public static function listSectionsForPhraseCheck(array $params): array
	{
		global $DB;

		$iblockId = Config::iblockId();
		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(100, (int) ($params['page_size'] ?? 25)));
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo');
		$active = (string) ($params['active'] ?? 'Y');

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$phraseCol = UserFields::PHRASE_FIELD;
		$skipCol = UserFields::SKIP_FIELD;
		$phraseAtCol = UserFields::PHRASE_AT_FIELD;

		$where = ['BS.IBLOCK_ID = ' . (int) $iblockId];
		if ($active === 'Y' || $active === 'N') {
			$where[] = "BS.ACTIVE = '" . $DB->ForSql($active) . "'";
		}

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BS', 'S', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		if ($filterMode === 'skip') {
			$where[] = 'UTS.' . $skipCol . ' = 1';
		} elseif ($filterMode !== 'all') {
			$where[] = '(UTS.' . $skipCol . ' IS NULL OR UTS.' . $skipCol . ' = 0 OR UTS.' . $skipCol . ' = "")';
		}

		if ($filterMode === 'long' || $filterMode === 'todo') {
			$where[] = 'CHAR_LENGTH(BS.NAME) > 50';
		}
		if ($filterMode === 'empty' || $filterMode === 'todo' || $filterMode === 'country_todo') {
			$where[] = '(UTS.' . $phraseCol . ' IS NULL OR UTS.' . $phraseCol . ' = "")';
		} elseif ($filterMode === 'filled') {
			$where[] = '(UTS.' . $phraseCol . ' IS NOT NULL AND UTS.' . $phraseCol . ' <> "")';
		}

		if ($filterMode === 'country' || $filterMode === 'country_todo') {
			$where[] = CountryInName::sqlNameHasCountry('BS.NAME', $DB);
		}

		$sectionIds = self::sectionIdsFromParams($params);
		$branchSql = self::sqlSectionInAnySubtree($sectionIds);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		}

		$whereSql = implode(' AND ', $where);
		$offset = ($page - 1) * $pageSize;

		$countSql = "
			SELECT COUNT(*) AS CNT
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
		";
		$countRow = $DB->Query($countSql)->Fetch();
		$total = (int) ($countRow['CNT'] ?? 0);

		$listSql = "
			SELECT
				BS.ID,
				BS.NAME,
				BS.CODE,
				BS.ACTIVE,
				UTS.{$phraseCol} AS TITLO_PHRASE,
				UTS.{$skipCol} AS TITLO_SKIP,
				UTS.{$phraseAtCol} AS TITLO_PHRASE_AT,
				UTS." . UserFields::NAME_ORIG_FIELD . " AS TITLO_NAME_ORIG
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
			ORDER BY BS.ID DESC
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		';

		$items = [];
		$res = $DB->Query($listSql);
		while ($row = $res->Fetch()) {
			$name = (string) $row['NAME'];
			$phrase = trim((string) ($row['TITLO_PHRASE'] ?? ''));
			$skip = UserFields::isSkipValue($row['TITLO_SKIP'] ?? 0);
			$code = (string) $row['CODE'];
			$nameLen = mb_strlen($name);
			$hasCountry = CountryInName::contains($name);
			$phraseAt = UserFields::formatPhraseAt($row['TITLO_PHRASE_AT'] ?? null);
			$nameOrig = trim((string) ($row['TITLO_NAME_ORIG'] ?? ''));

			$items[] = [
				'id' => (int) $row['ID'],
				'name' => $name,
				'name_len' => $nameLen,
				'name_orig' => $nameOrig,
				'code' => $code,
				'active' => (string) $row['ACTIVE'],
				'titlo_phrase' => $phrase,
				'phrase_len' => mb_strlen($phrase),
				'phrase_at' => $phraseAt,
				'skip' => $skip,
				'has_country' => $hasCountry,
				'url' => '',
				'needs_short' => $nameLen > 50 && $phrase === '' && !$skip,
			];
		}

		$urlMap = UrlBuilder::mapSectionUrls(array_column($items, 'id'));
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::category($item['code'], $id);
		}
		unset($item);

		$pages = $pageSize > 0 ? (int) max(1, (int) ceil($total / $pageSize)) : 1;
		if ($total === 0) {
			$pages = 1;
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'pages' => $pages,
		];
	}

	/**
	 * @param array{filter?:string,q?:string,active?:string} $params
	 * @return array{0:string,1:string}
	 */
	protected static function phraseBulkSectionWhere(array $params): array
	{
		global $DB;
		$iblockId = Config::iblockId();
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo');
		$active = (string) ($params['active'] ?? 'Y');

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$phraseCol = UserFields::PHRASE_FIELD;
		$skipCol = UserFields::SKIP_FIELD;

		$where = ['BS.IBLOCK_ID = ' . (int) $iblockId];
		if ($active === 'Y' || $active === 'N') {
			$where[] = "BS.ACTIVE = '" . $DB->ForSql($active) . "'";
		}

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BS', 'S', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		if ($filterMode === 'skip') {
			$where[] = 'UTS.' . $skipCol . ' = 1';
		} elseif ($filterMode !== 'all') {
			$where[] = '(UTS.' . $skipCol . ' IS NULL OR UTS.' . $skipCol . ' = 0 OR UTS.' . $skipCol . ' = "")';
		}

		if ($filterMode === 'long' || $filterMode === 'todo') {
			$where[] = 'CHAR_LENGTH(BS.NAME) > 50';
		}
		if ($filterMode === 'empty' || $filterMode === 'todo' || $filterMode === 'country_todo') {
			$where[] = '(UTS.' . $phraseCol . ' IS NULL OR UTS.' . $phraseCol . ' = "")';
		} elseif ($filterMode === 'filled') {
			$where[] = '1 = 0';
		}

		if ($filterMode === 'country' || $filterMode === 'country_todo') {
			$where[] = CountryInName::sqlNameHasCountry('BS.NAME', $DB);
		}

		$sectionIds = self::sectionIdsFromParams($params);
		$branchSql = self::sqlSectionInAnySubtree($sectionIds);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		}

		return [implode(' AND ', $where), $uts];
	}

	public static function countSectionsForPhraseBulk(array $params): int
	{
		global $DB;
		[$whereSql, $uts] = self::phraseBulkSectionWhere($params);
		$row = $DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
		")->Fetch();
		return (int) ($row['CNT'] ?? 0);
	}

	/**
	 * ID+NAME+URL категорий для массовой генерации фраз.
	 *
	 * @param array{filter?:string,q?:string,limit?:int,active?:string} $params
	 * @return array<int, array{id:int,name:string,url:string}>
	 */
	public static function listSectionsForPhraseBulk(array $params): array
	{
		global $DB;

		$limit = max(1, min(PhraseBulk::MAX_LIMIT, (int) ($params['limit'] ?? 500)));
		[$whereSql, $uts] = self::phraseBulkSectionWhere($params);

		$sql = "
			SELECT BS.ID, BS.NAME, BS.CODE
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
			ORDER BY BS.ID DESC
			LIMIT " . (int) $limit . '
		';

		$items = [];
		$res = $DB->Query($sql);
		while ($row = $res->Fetch()) {
			$code = (string) $row['CODE'];
			$items[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => $code,
				'url' => '',
			];
		}
		$urlMap = UrlBuilder::mapSectionUrls(array_column($items, 'id'));
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::category($item['code'], $id);
			unset($item['code']);
		}
		unset($item);
		return $items;
	}

	public static function saveElementPhrase(int $id, string $phrase, ?bool $skip = null): bool
	{
		if (!Config::elementBelongsToCatalog($id)) {
			return false;
		}
		$phrase = trim($phrase);
		$max = UserFields::PHRASE_MAX_LEN;
		if (mb_strlen($phrase) > $max) {
			$phrase = rtrim(mb_substr($phrase, 0, $max));
		}

		$fields = [
			UserFields::PHRASE_FIELD => $phrase,
		];
		if ($skip !== null) {
			$fields[UserFields::SKIP_FIELD] = $skip ? 1 : 0;
		}

		// CIBlockElement::Update для UF часто возвращает true, но b_uts_* не трогает.
		$ok = self::updateElementUserFields($id, $fields);
		if ($ok) {
			// datetime UF через UserFieldManager чувствителен к формату сайта — пишем SQL.
			self::writePhraseAtSql($id, $phrase !== '' ? date('Y-m-d H:i:s') : null);
			\CIBlock::clearIblockTagCache(Config::iblockId());
		}
		return $ok;
	}

	/**
	 * Короткая фраза из UF_TITLO_PHRASE (b_uts_*), надёжнее GetList.
	 */
	public static function getElementPhrase(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return '';
		}
		$col = UserFields::PHRASE_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$row = $DB->Query(
			'SELECT ' . $col . ' AS PHRASE FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id
		)->Fetch();
		return trim((string) ($row['PHRASE'] ?? ''));
	}

	/**
	 * Дата последней проработки названия (формат d.m.Y H:i).
	 */
	public static function getElementPhraseAt(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		global $DB;
		$iblockId = Config::iblockId();
		$col = UserFields::PHRASE_AT_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$row = $DB->Query(
			'SELECT ' . $col . ' AS PHRASE_AT FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id
		)->Fetch();
		return UserFields::formatPhraseAt($row['PHRASE_AT'] ?? null);
	}

	protected static function writePhraseAtSql(int $id, ?string $mysqlDatetime): void
	{
		if ($id <= 0) {
			return;
		}
		global $DB;
		$iblockId = Config::iblockId();
		$col = UserFields::PHRASE_AT_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$valSql = $mysqlDatetime === null || $mysqlDatetime === ''
			? 'NULL'
			: "'" . $DB->ForSql($mysqlDatetime) . "'";

		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . $col . ' = ' . $valSql . ' WHERE VALUE_ID = ' . (int) $id);
			return;
		}
		$DB->Query(
			'INSERT INTO ' . $uts . ' (VALUE_ID, ' . $col . ') VALUES (' . (int) $id . ', ' . $valSql . ')'
		);
	}

	public static function saveElementSkip(int $id, bool $skip): bool
	{
		if (!Config::elementBelongsToCatalog($id)) {
			return false;
		}
		$ok = self::updateElementUserFields($id, [
			UserFields::SKIP_FIELD => $skip ? 1 : 0,
		]);
		if ($ok) {
			\CIBlock::clearIblockTagCache(Config::iblockId());
		}
		return $ok;
	}

	/**
	 * Заменить NAME короткой фразой. Оригинал один раз пишется в UF_TITLO_NAME_ORIG.
	 *
	 * @return array{ok:bool,error?:string,name?:string,name_orig?:string,phrase?:string}
	 */
	public static function applyPhraseToName(string $entityType, int $id, string $phrase = ''): array
	{
		UserFields::ensurePhraseField();
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$id = (int) $id;
		if ($id <= 0) {
			return ['ok' => false, 'error' => 'entity_id required'];
		}
		$belongs = $entityType === 'S'
			? Config::sectionBelongsToCatalog($id)
			: Config::elementBelongsToCatalog($id);
		if (!$belongs) {
			return ['ok' => false, 'error' => 'entity not in catalog'];
		}

		$phrase = trim($phrase);
		if ($phrase === '') {
			$phrase = $entityType === 'S'
				? self::getSectionPhrase($id)
				: self::getElementPhrase($id);
		}
		if ($entityType === 'S') {
			$phrase = self::normalizeSectionPhrase($phrase);
		} else {
			$max = UserFields::PHRASE_MAX_LEN;
			if (mb_strlen($phrase) > $max) {
				$phrase = rtrim(mb_substr($phrase, 0, $max));
			}
		}
		if ($phrase === '') {
			return ['ok' => false, 'error' => 'Сначала укажите короткую фразу'];
		}
		if (mb_strlen($phrase) > 255) {
			$phrase = rtrim(mb_substr($phrase, 0, 255));
		}

		$current = $entityType === 'S'
			? self::getSectionNameAndOrig($id)
			: self::getElementNameAndOrig($id);
		if ($current === null) {
			return ['ok' => false, 'error' => 'not found'];
		}

		$name = $current['name'];
		$nameOrig = $current['name_orig'];

		if ($name === $phrase) {
			// всё равно синхронизируем UF-фразу
			if ($entityType === 'S') {
				self::saveSectionPhrase($id, $phrase, null);
			} else {
				self::saveElementPhrase($id, $phrase, null);
			}
			return [
				'ok' => true,
				'name' => $name,
				'name_orig' => $nameOrig,
				'phrase' => $phrase,
				'unchanged' => true,
			];
		}

		if ($nameOrig === '') {
			$nameOrig = $name;
			$ufOk = $entityType === 'S'
				? self::updateSectionUserFields($id, [UserFields::NAME_ORIG_FIELD => $nameOrig])
				: self::updateElementUserFields($id, [UserFields::NAME_ORIG_FIELD => $nameOrig]);
			if (!$ufOk) {
				return ['ok' => false, 'error' => 'Не удалось сохранить оригинал NAME'];
			}
		}

		if ($entityType === 'S') {
			$sec = new \CIBlockSection();
			$ok = (bool) $sec->Update($id, ['NAME' => $phrase]);
			$err = $sec->LAST_ERROR ?: '';
		} else {
			$el = new \CIBlockElement();
			$ok = (bool) $el->Update($id, ['NAME' => $phrase]);
			$err = $el->LAST_ERROR ?: '';
		}
		if (!$ok) {
			return ['ok' => false, 'error' => $err !== '' ? strip_tags($err) : 'Update NAME failed'];
		}

		if ($entityType === 'S') {
			self::saveSectionPhrase($id, $phrase, null);
		} else {
			self::saveElementPhrase($id, $phrase, null);
		}

		\CIBlock::clearIblockTagCache(Config::iblockId());
		AuditLog::write('apply_phrase_to_name', [
			'entity_type' => $entityType,
			'entity_id' => $id,
			'from' => $name,
			'to' => $phrase,
			'name_orig' => $nameOrig,
		]);

		return [
			'ok' => true,
			'name' => $phrase,
			'name_orig' => $nameOrig,
			'phrase' => $phrase,
		];
	}

	/**
	 * Вернуть NAME из UF_TITLO_NAME_ORIG.
	 *
	 * @return array{ok:bool,error?:string,name?:string,name_orig?:string}
	 */
	public static function restoreOriginalName(string $entityType, int $id): array
	{
		UserFields::ensurePhraseField();
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		$id = (int) $id;
		if ($id <= 0) {
			return ['ok' => false, 'error' => 'entity_id required'];
		}
		$belongs = $entityType === 'S'
			? Config::sectionBelongsToCatalog($id)
			: Config::elementBelongsToCatalog($id);
		if (!$belongs) {
			return ['ok' => false, 'error' => 'entity not in catalog'];
		}

		$current = $entityType === 'S'
			? self::getSectionNameAndOrig($id)
			: self::getElementNameAndOrig($id);
		if ($current === null) {
			return ['ok' => false, 'error' => 'not found'];
		}
		$nameOrig = $current['name_orig'];
		if ($nameOrig === '') {
			return ['ok' => false, 'error' => 'Оригинал NAME не сохранён'];
		}
		if ($current['name'] === $nameOrig) {
			return [
				'ok' => true,
				'name' => $current['name'],
				'name_orig' => $nameOrig,
				'unchanged' => true,
			];
		}

		if ($entityType === 'S') {
			$sec = new \CIBlockSection();
			$ok = (bool) $sec->Update($id, ['NAME' => $nameOrig]);
			$err = $sec->LAST_ERROR ?: '';
		} else {
			$el = new \CIBlockElement();
			$ok = (bool) $el->Update($id, ['NAME' => $nameOrig]);
			$err = $el->LAST_ERROR ?: '';
		}
		if (!$ok) {
			return ['ok' => false, 'error' => $err !== '' ? strip_tags($err) : 'Update NAME failed'];
		}

		\CIBlock::clearIblockTagCache(Config::iblockId());
		AuditLog::write('restore_original_name', [
			'entity_type' => $entityType,
			'entity_id' => $id,
			'from' => $current['name'],
			'to' => $nameOrig,
		]);

		return [
			'ok' => true,
			'name' => $nameOrig,
			'name_orig' => $nameOrig,
		];
	}

	/**
	 * @return array{name:string,name_orig:string}|null
	 */
	protected static function getElementNameAndOrig(int $id): ?array
	{
		global $DB;
		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$col = UserFields::NAME_ORIG_FIELD;
		$row = $DB->Query(
			'SELECT BE.NAME, UTS.' . $col . ' AS NAME_ORIG
			FROM b_iblock_element BE
			LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BE.ID
			WHERE BE.ID = ' . (int) $id . ' AND BE.IBLOCK_ID = ' . (int) $iblockId . '
			LIMIT 1'
		)->Fetch();
		if (!$row) {
			return null;
		}
		return [
			'name' => (string) ($row['NAME'] ?? ''),
			'name_orig' => trim((string) ($row['NAME_ORIG'] ?? '')),
		];
	}

	/**
	 * @return array{name:string,name_orig:string}|null
	 */
	protected static function getSectionNameAndOrig(int $id): ?array
	{
		global $DB;
		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$col = UserFields::NAME_ORIG_FIELD;
		$row = $DB->Query(
			'SELECT BS.NAME, UTS.' . $col . ' AS NAME_ORIG
			FROM b_iblock_section BS
			LEFT JOIN ' . $uts . ' UTS ON UTS.VALUE_ID = BS.ID
			WHERE BS.ID = ' . (int) $id . ' AND BS.IBLOCK_ID = ' . (int) $iblockId . '
			LIMIT 1'
		)->Fetch();
		if (!$row) {
			return null;
		}
		return [
			'name' => (string) ($row['NAME'] ?? ''),
			'name_orig' => trim((string) ($row['NAME_ORIG'] ?? '')),
		];
	}

	/**
	 * Надёжная запись UF элемента инфоблока в b_uts_iblock_*_element.
	 *
	 * @param array<string, mixed> $fields
	 */
	protected static function updateElementUserFields(int $id, array $fields): bool
	{
		if ($id <= 0 || $fields === []) {
			return false;
		}

		$iblockId = Config::iblockId();
		$entityId = 'IBLOCK_' . $iblockId . '_ELEMENT';

		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update($entityId, $id, $fields);
		}

		// Проверяем факт записи — Update UF тоже бывает «тихим».
		global $DB;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();

		$setParts = [];
		foreach ($fields as $col => $val) {
			if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
				continue;
			}
			if ($val === null) {
				$setParts[] = $col . ' = NULL';
			} elseif (is_int($val) || (is_string($val) && ctype_digit($val))) {
				$setParts[] = $col . ' = ' . (int) $val;
			} else {
				$setParts[] = $col . " = '" . $DB->ForSql((string) $val) . "'";
			}
		}
		if ($setParts === []) {
			return false;
		}

		if ($row) {
			$sql = 'UPDATE ' . $uts . ' SET ' . implode(', ', $setParts) . ' WHERE VALUE_ID = ' . (int) $id;
			$DB->Query($sql);
		} else {
			$cols = ['VALUE_ID'];
			$vals = [(string) (int) $id];
			foreach ($fields as $col => $val) {
				if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
					continue;
				}
				$cols[] = $col;
				if ($val === null) {
					$vals[] = 'NULL';
				} elseif (is_int($val) || (is_string($val) && ctype_digit($val))) {
					$vals[] = (string) (int) $val;
				} else {
					$vals[] = "'" . $DB->ForSql((string) $val) . "'";
				}
			}
			$sql = 'INSERT INTO ' . $uts . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
			$DB->Query($sql);
		}

		$check = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();
		return (bool) $check;
	}

	public static function saveSectionPhrase(int $id, string $phrase, ?bool $skip = null): bool
	{
		if (!Config::sectionBelongsToCatalog($id)) {
			return false;
		}
		$phrase = self::normalizeSectionPhrase($phrase);

		$fields = [
			UserFields::PHRASE_FIELD => $phrase,
		];
		if ($skip !== null) {
			$fields[UserFields::SKIP_FIELD] = $skip ? 1 : 0;
		}

		$ok = self::updateSectionUserFields($id, $fields);
		if ($ok) {
			self::writeSectionPhraseAtSql($id, $phrase !== '' ? date('Y-m-d H:i:s') : null);
			\CIBlock::clearIblockTagCache(Config::iblockId());
		}
		return $ok;
	}

	/**
	 * Категории: тип/уточнение/бренд важнее «купить».
	 * «купить» добавляем в конец только если влезает в лимит без обрезания базы.
	 * Пустая фраза = сброс в непроработанные.
	 */
	public static function normalizeSectionPhrase(string $phrase): string
	{
		$phrase = trim(preg_replace('/\s+/u', ' ', trim($phrase)));
		if ($phrase === '') {
			return '';
		}

		$max = UserFields::PHRASE_MAX_LEN;
		$hadKupit = (bool) preg_match('/\bкупить\b/ui', $phrase);
		$base = trim(preg_replace('/\bкупить\b/ui', '', $phrase));
		$base = trim(preg_replace('/\s+/u', ' ', $base));
		$base = trim($base, " \t\n\r\0\x0B,.;:!-–—");
		if ($base === '') {
			return $hadKupit ? 'купить' : '';
		}

		$base = self::cutPhraseAtWord($base, $max);
		$suffix = ' купить';
		if (mb_strlen($base) + mb_strlen($suffix) <= $max) {
			return $base . $suffix;
		}
		// Не влезает «купить» — оставляем смысл целиком, без насильственной обрезки ради суффикса.
		return $base;
	}

	/** Обрезка по границе слова, без середины слова. */
	protected static function cutPhraseAtWord(string $text, int $maxLen): string
	{
		$text = trim($text);
		if ($maxLen < 1) {
			return '';
		}
		if (mb_strlen($text) <= $maxLen) {
			return $text;
		}
		$cut = rtrim(mb_substr($text, 0, $maxLen));
		$space = mb_strrpos($cut, ' ');
		if ($space !== false && $space >= 8) {
			$cut = rtrim(mb_substr($cut, 0, $space));
		}
		return rtrim($cut, " \t,.;:!-–—");
	}

	public static function getSectionPhrase(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		global $DB;
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return '';
		}
		$col = UserFields::PHRASE_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$row = $DB->Query(
			'SELECT ' . $col . ' AS PHRASE FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id
		)->Fetch();
		return trim((string) ($row['PHRASE'] ?? ''));
	}

	public static function getSectionPhraseAt(int $id): string
	{
		if ($id <= 0) {
			return '';
		}
		global $DB;
		$iblockId = Config::iblockId();
		$col = UserFields::PHRASE_AT_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$row = $DB->Query(
			'SELECT ' . $col . ' AS PHRASE_AT FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id
		)->Fetch();
		return UserFields::formatPhraseAt($row['PHRASE_AT'] ?? null);
	}

	protected static function writeSectionPhraseAtSql(int $id, ?string $mysqlDatetime): void
	{
		if ($id <= 0) {
			return;
		}
		global $DB;
		$iblockId = Config::iblockId();
		$col = UserFields::PHRASE_AT_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$valSql = $mysqlDatetime === null || $mysqlDatetime === ''
			? 'NULL'
			: "'" . $DB->ForSql($mysqlDatetime) . "'";

		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . $col . ' = ' . $valSql . ' WHERE VALUE_ID = ' . (int) $id);
			return;
		}
		$DB->Query(
			'INSERT INTO ' . $uts . ' (VALUE_ID, ' . $col . ') VALUES (' . (int) $id . ', ' . $valSql . ')'
		);
	}

	public static function saveSectionSkip(int $id, bool $skip): bool
	{
		if (!Config::sectionBelongsToCatalog($id)) {
			return false;
		}
		$ok = self::updateSectionUserFields($id, [
			UserFields::SKIP_FIELD => $skip ? 1 : 0,
		]);
		if ($ok) {
			\CIBlock::clearIblockTagCache(Config::iblockId());
		}
		return $ok;
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	protected static function updateSectionUserFields(int $id, array $fields): bool
	{
		if ($id <= 0 || $fields === []) {
			return false;
		}

		$iblockId = Config::iblockId();
		$entityId = 'IBLOCK_' . $iblockId . '_SECTION';

		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update($entityId, $id, $fields);
		}

		global $DB;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();

		$setParts = [];
		foreach ($fields as $col => $val) {
			if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
				continue;
			}
			if ($val === null) {
				$setParts[] = $col . ' = NULL';
			} elseif (is_int($val) || (is_string($val) && ctype_digit($val))) {
				$setParts[] = $col . ' = ' . (int) $val;
			} else {
				$setParts[] = $col . " = '" . $DB->ForSql((string) $val) . "'";
			}
		}
		if ($setParts === []) {
			return false;
		}

		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . implode(', ', $setParts) . ' WHERE VALUE_ID = ' . (int) $id);
		} else {
			$cols = ['VALUE_ID'];
			$vals = [(string) (int) $id];
			foreach ($fields as $col => $val) {
				if (!preg_match('/^UF_[A-Z0-9_]+$/', $col)) {
					continue;
				}
				$cols[] = $col;
				if ($val === null) {
					$vals[] = 'NULL';
				} elseif (is_int($val) || (is_string($val) && ctype_digit($val))) {
					$vals[] = (string) (int) $val;
				} else {
					$vals[] = "'" . $DB->ForSql((string) $val) . "'";
				}
			}
			$DB->Query('INSERT INTO ' . $uts . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');
		}

		$check = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();
		return (bool) $check;
	}

	public static function saveElementTexts(int $id, ?string $preview, ?string $detail): bool
	{
		if (!Config::elementBelongsToCatalog($id)) {
			return false;
		}
		$fields = [];
		if ($preview !== null) {
			$fields['PREVIEW_TEXT'] = $preview;
			$fields['PREVIEW_TEXT_TYPE'] = 'text';
		}
		if ($detail !== null) {
			$fields['DETAIL_TEXT'] = self::sanitizeCatalogHtml($detail);
			$fields['DETAIL_TEXT_TYPE'] = 'html';
		}
		if ($fields === []) {
			return false;
		}

		$el = new \CIBlockElement();
		$ok = (bool) $el->Update($id, $fields);
		if ($ok) {
			self::flushPublicPageCache($id, 'E');
		}
		return $ok;
	}

	/**
	 * Публичная карточка кешируется отдельно от БД.
	 * catalog: CACHE_TYPE=Y / CACHE_TIME=36000000 — clearIblockTagCache это не сбрасывает.
	 * Плюс композит html_pages. Без сброса повторный анализ видит старый HTML и тот же балл.
	 */
	public static function flushPublicPageCache(int $entityId, string $entityType = 'E', ?string $publicUrl = null): void
	{
		$iblockId = Config::iblockId();
		if ($iblockId > 0) {
			\CIBlock::clearIblockTagCache($iblockId);
		}

		if (class_exists('\\CBitrixComponent')) {
			\CBitrixComponent::clearComponentCache('bitrix:catalog');
			\CBitrixComponent::clearComponentCache('bitrix:catalog.element');
			\CBitrixComponent::clearComponentCache('bitrix:catalog.section');
		}

		if ($publicUrl === null || $publicUrl === '') {
			if ($entityType === 'S') {
				$entity = self::findSection($entityId);
			} else {
				$entity = self::findElement($entityId);
			}
			$publicUrl = (string) ($entity['url'] ?? '');
		}
		self::deleteCompositePage($publicUrl);
	}

	protected static function siteDocumentRoot(): string
	{
		$doc = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
		if ($doc !== '' && is_dir($doc . '/bitrix/html_pages')) {
			return $doc;
		}
		// Агент Bitrix иногда без DOCUMENT_ROOT. lib → module → modules → local → корень сайта.
		$guess = dirname(__DIR__, 4);
		if (is_dir($guess . '/bitrix/html_pages')) {
			return $guess;
		}

		return $doc;
	}

	protected static function deleteCompositePage(string $publicUrl): void
	{
		$path = parse_url($publicUrl, PHP_URL_PATH);
		if (!is_string($path) || $path === '' || $path === '/') {
			return;
		}
		$path = '/' . trim($path, '/');

		$host = parse_url($publicUrl, PHP_URL_HOST);
		$host = is_string($host) ? $host : '';
		if ($host !== '' && class_exists('\\Bitrix\\Main\\Composite\\Helper')) {
			try {
				\Bitrix\Main\Composite\Helper::delete($host, $path . '/');
			} catch (\Throwable $e) {
				// версия ядра может отличаться сигнатурой — ниже файловый fallback
			}
		}

		$doc = self::siteDocumentRoot();
		$base = $doc . '/bitrix/html_pages';
		if ($doc === '' || !is_dir($base)) {
			return;
		}
		$hosts = @scandir($base);
		if (!is_array($hosts)) {
			return;
		}
		foreach ($hosts as $hostDir) {
			if ($hostDir === '.' || $hostDir === '..' || $hostDir === '') {
				continue;
			}
			$target = $base . '/' . $hostDir . $path;
			foreach ([$target . '@.html', $target . '/index@.html', $target . '.html'] as $file) {
				if (is_file($file)) {
					@unlink($file);
				}
			}
			if (is_dir($target)) {
				self::rrmdir($target);
			}
		}
	}

	protected static function rrmdir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}
		$items = @scandir($dir);
		if (!is_array($items)) {
			return;
		}
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$full = $dir . '/' . $item;
			if (is_dir($full)) {
				self::rrmdir($full);
			} else {
				@unlink($full);
			}
		}
		@rmdir($dir);
	}

	/**
	 * Дерево активных разделов каталога для select (порядок LEFT_MARGIN).
	 *
	 * @return array<int, array{id:int,name:string,depth:int,label:string}>
	 */
	public static function listSectionTreeForSelect(): array
	{
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return [];
		}
		$res = \CIBlockSection::GetList(
			['LEFT_MARGIN' => 'ASC'],
			['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
			false,
			['ID', 'NAME', 'DEPTH_LEVEL']
		);
		$out = [];
		while ($row = $res->Fetch()) {
			$depth = max(1, (int) ($row['DEPTH_LEVEL'] ?? 1));
			$name = (string) ($row['NAME'] ?? '');
			$pad = $depth > 1 ? str_repeat('— ', $depth - 1) : '';
			$out[] = [
				'id' => (int) $row['ID'],
				'name' => $name,
				'depth' => $depth,
				'label' => $pad . $name,
			];
		}
		return $out;
	}

	/**
	 * Нормализация списка ID разделов из GET/POST (CSV, JSON, массив, одиночный int).
	 *
	 * @param mixed $raw
	 * @return int[] уникальные ID > 0
	 */
	public static function normalizeSectionIds($raw): array
	{
		if ($raw === null || $raw === '' || $raw === []) {
			return [];
		}
		if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
			$id = (int) $raw;
			return $id > 0 ? [$id] : [];
		}
		if (is_string($raw)) {
			$trim = trim($raw);
			if ($trim === '') {
				return [];
			}
			if ($trim[0] === '[') {
				$decoded = json_decode($trim, true);
				$raw = is_array($decoded) ? $decoded : [];
			} else {
				$raw = preg_split('/[\s,;]+/', $trim) ?: [];
			}
		}
		if (!is_array($raw)) {
			return [];
		}
		$out = [];
		foreach ($raw as $v) {
			$id = (int) $v;
			if ($id > 0) {
				$out[$id] = $id;
			}
		}
		return array_values($out);
	}

	/**
	 * Извлечь section_ids из params (поддержка section_ids + legacy section_id).
	 *
	 * @param array $params
	 * @return int[]
	 */
	public static function sectionIdsFromParams(array $params): array
	{
		if (array_key_exists('section_ids', $params) && $params['section_ids'] !== null && $params['section_ids'] !== '') {
			$ids = self::normalizeSectionIds($params['section_ids']);
			if ($ids !== []) {
				return $ids;
			}
		}
		return self::normalizeSectionIds($params['section_id'] ?? 0);
	}

	/**
	 * Границы поддерева раздела (LEFT_MARGIN / RIGHT_MARGIN) в каталоге модуля.
	 *
	 * @return array{left:int,right:int}|null
	 */
	public static function getSectionSubtreeMargins(int $sectionId): ?array
	{
		$sectionId = (int) $sectionId;
		$iblockId = Config::iblockId();
		if ($sectionId <= 0 || $iblockId <= 0 || !Config::sectionBelongsToCatalog($sectionId)) {
			return null;
		}
		global $DB;
		$row = $DB->Query(
			'SELECT LEFT_MARGIN, RIGHT_MARGIN FROM b_iblock_section'
			. ' WHERE ID = ' . $sectionId . ' AND IBLOCK_ID = ' . (int) $iblockId
			. ' LIMIT 1'
		)->Fetch();
		if (!$row) {
			return null;
		}
		$left = (int) ($row['LEFT_MARGIN'] ?? 0);
		$right = (int) ($row['RIGHT_MARGIN'] ?? 0);
		if ($left <= 0 || $right <= $left) {
			return null;
		}
		return ['left' => $left, 'right' => $right];
	}

	/**
	 * SQL-условие: товар привязан к разделу ветки (основной или доп. через b_iblock_section_element).
	 */
	public static function sqlElementInSectionSubtree(string $elementAlias, int $left, int $right, int $iblockId): string
	{
		$alias = preg_replace('#[^A-Za-z0-9_]#', '', $elementAlias) ?: 'BE';
		$iblockId = (int) $iblockId;
		$left = (int) $left;
		$right = (int) $right;
		return '(
			EXISTS (
				SELECT 1 FROM b_iblock_section_element BSE
				INNER JOIN b_iblock_section BSS ON BSS.ID = BSE.IBLOCK_SECTION_ID AND BSS.IBLOCK_ID = ' . $iblockId . '
				WHERE BSE.IBLOCK_ELEMENT_ID = ' . $alias . '.ID
				  AND BSS.LEFT_MARGIN >= ' . $left . '
				  AND BSS.RIGHT_MARGIN <= ' . $right . '
			)
			OR EXISTS (
				SELECT 1 FROM b_iblock_section BSS2
				WHERE BSS2.ID = ' . $alias . '.IBLOCK_SECTION_ID
				  AND BSS2.IBLOCK_ID = ' . $iblockId . '
				  AND BSS2.LEFT_MARGIN >= ' . $left . '
				  AND BSS2.RIGHT_MARGIN <= ' . $right . '
			)
		)';
	}

	/**
	 * OR по нескольким веткам каталога для товаров.
	 *
	 * @param int[] $sectionIds
	 */
	public static function sqlElementInAnySectionSubtree(string $elementAlias, array $sectionIds, int $iblockId): ?string
	{
		$parts = [];
		foreach (self::normalizeSectionIds($sectionIds) as $sectionId) {
			$margins = self::getSectionSubtreeMargins($sectionId);
			if ($margins !== null) {
				$parts[] = self::sqlElementInSectionSubtree(
					$elementAlias,
					$margins['left'],
					$margins['right'],
					$iblockId
				);
			}
		}
		if ($parts === []) {
			return null;
		}
		if (count($parts) === 1) {
			return $parts[0];
		}
		return '(' . implode(' OR ', $parts) . ')';
	}

	/**
	 * OR по нескольким веткам для списка категорий (LEFT/RIGHT margin).
	 *
	 * @param int[] $sectionIds
	 */
	public static function sqlSectionInAnySubtree(array $sectionIds): ?string
	{
		$parts = [];
		foreach (self::normalizeSectionIds($sectionIds) as $sectionId) {
			$margins = self::getSectionSubtreeMargins($sectionId);
			if ($margins !== null) {
				$parts[] = '(BS.LEFT_MARGIN >= ' . (int) $margins['left']
					. ' AND BS.RIGHT_MARGIN <= ' . (int) $margins['right'] . ')';
			}
		}
		if ($parts === []) {
			return null;
		}
		if (count($parts) === 1) {
			return $parts[0];
		}
		return '(' . implode(' OR ', $parts) . ')';
	}

	/**
	 * Товары с короткой фразой — кандидаты на автопроработку.
	 *
	 * @param array{page?:int,page_size?:int,q?:string,filter?:string,sort?:string,section_id?:int,section_ids?:int[]|string} $params
	 *        filter: all|todo|done|scored (todo = без UF_TITLO_AUTO_AT; scored = есть балл в work_history)
	 *        sort: id_desc|score_asc
	 *        section_ids: ветки каталога (раздел + подразделы); пусто = все
	 * @return array{items:array,total:int,page:int,page_size:int,pages:int,sort:string,section_id:int,section_ids:int[]}
	 */
	public static function listElementsForAuto(array $params): array
	{
		global $DB;

		WorkHistory::ensureTables();

		$iblockId = Config::iblockId();
		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(100, (int) ($params['page_size'] ?? 25)));
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo'); // all|todo|done|scored
		$sort = (string) ($params['sort'] ?? 'id_desc');
		if ($sort !== 'score_asc') {
			$sort = 'id_desc';
		}
		$sectionIds = self::sectionIdsFromParams($params);
		$sectionId = $sectionIds[0] ?? 0;

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$phraseCol = UserFields::PHRASE_FIELD;
		$autoAtCol = UserFields::AUTO_AT_FIELD;

		$where = [
			'BE.IBLOCK_ID = ' . (int) $iblockId,
			"BE.ACTIVE = 'Y'",
			'(BE.WF_STATUS_ID IS NULL OR BE.WF_STATUS_ID = 1)',
			'(BE.WF_PARENT_ELEMENT_ID IS NULL OR BE.WF_PARENT_ELEMENT_ID = 0)',
			'(UTS.' . $phraseCol . ' IS NOT NULL AND UTS.' . $phraseCol . ' <> "")',
		];

		if ($filterMode === 'todo') {
			$where[] = '(UTS.' . $autoAtCol . ' IS NULL OR UTS.' . $autoAtCol . ' = "0000-00-00 00:00:00")';
		} elseif ($filterMode === 'done') {
			$where[] = '(UTS.' . $autoAtCol . ' IS NOT NULL AND UTS.' . $autoAtCol . ' <> "0000-00-00 00:00:00")';
		} elseif ($filterMode === 'scored') {
			$where[] = 'EXISTS (
				SELECT 1 FROM titlo_work_history whx
				WHERE whx.ENTITY_TYPE = \'E\' AND whx.ENTITY_ID = BE.ID
				  AND (whx.BEFORE_POINTS IS NOT NULL OR whx.AFTER_POINTS IS NOT NULL)
			)';
		}

		$branchSql = self::sqlElementInAnySectionSubtree('BE', $sectionIds, $iblockId);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		} else {
			$sectionIds = [];
			$sectionId = 0;
		}

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BE', 'E', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		$whereSql = implode(' AND ', $where);
		$offset = ($page - 1) * $pageSize;

		$whJoin = "
			LEFT JOIN (
				SELECT wh.ENTITY_ID,
					wh.ID AS WH_ID,
					wh.BEFORE_POINTS, wh.AFTER_POINTS,
					wh.BEFORE_POINTS_IDEAL, wh.AFTER_POINTS_IDEAL,
					wh.BEFORE_HISTORY_ID, wh.AFTER_HISTORY_ID,
					wh.BEFORE_AT, wh.AFTER_AT
				FROM titlo_work_history wh
				INNER JOIN (
					SELECT ENTITY_ID, MAX(ID) AS mid
					FROM titlo_work_history
					WHERE ENTITY_TYPE = 'E'
					GROUP BY ENTITY_ID
				) last ON last.mid = wh.ID
			) WH ON WH.ENTITY_ID = BE.ID
		";

		$total = (int) ($DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			WHERE {$whereSql}
		")->Fetch()['CNT'] ?? 0);

		$orderSql = 'BE.ID DESC';
		if ($sort === 'score_asc') {
			$orderSql = '(COALESCE(WH.AFTER_POINTS, WH.BEFORE_POINTS) IS NULL) ASC,'
				. ' COALESCE(WH.AFTER_POINTS, WH.BEFORE_POINTS) ASC,'
				. ' BE.ID DESC';
		}
		if ($q !== '' && !ctype_digit($q)) {
			$orderSql = self::sqlSearchRelevanceScore('BE', $q, $phraseCol) . ' DESC, ' . $orderSql;
		}

		$res = $DB->Query("
			SELECT
				BE.ID,
				BE.NAME,
				BE.CODE,
				BE.DETAIL_TEXT,
				CHAR_LENGTH(COALESCE(BE.DETAIL_TEXT, '')) AS DETAIL_LEN,
				UTS.{$phraseCol} AS TITLO_PHRASE,
				UTS.{$autoAtCol} AS TITLO_AUTO_AT,
				WH.WH_ID,
				WH.BEFORE_POINTS, WH.AFTER_POINTS,
				WH.BEFORE_POINTS_IDEAL, WH.AFTER_POINTS_IDEAL,
				WH.BEFORE_HISTORY_ID, WH.AFTER_HISTORY_ID,
				WH.BEFORE_AT, WH.AFTER_AT
			FROM b_iblock_element BE
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BE.ID
			{$whJoin}
			WHERE {$whereSql}
			ORDER BY {$orderSql}
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		');

		$items = [];
		while ($row = $res->Fetch()) {
			$detailRaw = (string) ($row['DETAIL_TEXT'] ?? '');
			$detailPlain = trim(html_entity_decode(strip_tags($detailRaw), ENT_QUOTES, 'UTF-8'));
			$autoAt = UserFields::formatPhraseAt($row['TITLO_AUTO_AT'] ?? null);
			$score = null;
			$whId = (int) ($row['WH_ID'] ?? 0);
			if ($whId > 0) {
				$afterHid = $row['AFTER_HISTORY_ID'] !== null && $row['AFTER_HISTORY_ID'] !== ''
					? (int) $row['AFTER_HISTORY_ID'] : 0;
				$useAfter = $afterHid > 0 && $row['AFTER_POINTS'] !== null && $row['AFTER_POINTS'] !== '';
				if ($useAfter) {
					$score = [
						'points' => (float) $row['AFTER_POINTS'],
						'points_ideal' => $row['AFTER_POINTS_IDEAL'] !== null && $row['AFTER_POINTS_IDEAL'] !== ''
							? (float) $row['AFTER_POINTS_IDEAL'] : null,
						'history_id' => $afterHid,
						'at' => (string) ($row['AFTER_AT'] ?? ''),
						'work_id' => $whId,
					];
				} else {
					$beforeHid = $row['BEFORE_HISTORY_ID'] !== null && $row['BEFORE_HISTORY_ID'] !== ''
						? (int) $row['BEFORE_HISTORY_ID'] : 0;
					$score = [
						'points' => $row['BEFORE_POINTS'] !== null && $row['BEFORE_POINTS'] !== ''
							? (float) $row['BEFORE_POINTS'] : null,
						'points_ideal' => $row['BEFORE_POINTS_IDEAL'] !== null && $row['BEFORE_POINTS_IDEAL'] !== ''
							? (float) $row['BEFORE_POINTS_IDEAL'] : null,
						'history_id' => $beforeHid > 0 ? $beforeHid : null,
						'at' => (string) ($row['BEFORE_AT'] ?? ''),
						'work_id' => $whId,
					];
					if ($score['points'] === null && $score['history_id'] === null) {
						$score = null;
					}
				}
			}
			$items[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'titlo_phrase' => trim((string) ($row['TITLO_PHRASE'] ?? '')),
				'detail_chars' => mb_strlen($detailPlain),
				'has_detail' => $detailPlain !== '',
				'auto_at' => $autoAt,
				'auto_done' => $autoAt !== '',
				'score' => $score,
				'url' => '',
			];
		}

		$urlMap = UrlBuilder::mapElementUrls(array_column($items, 'id'));
		$queueMap = BatchQueue::activeByEntityIds(array_column($items, 'id'));
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::product($item['code'], $id);
			$item['queue'] = $queueMap[$id] ?? null;
			$item['single_url'] = 'titlo_relevance_single.php?lang=' . LANGUAGE_ID . '&ENTITY=E&ID=' . $id;
		}
		unset($item);

		$pages = $pageSize > 0 ? (int) max(1, (int) ceil($total / $pageSize)) : 1;
		if ($total === 0) {
			$pages = 1;
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'pages' => $pages,
			'sort' => $sort,
			'section_id' => $sectionId,
			'section_ids' => $sectionIds,
		];
	}

	/**
	 * Пагинированный список категорий для автопроработки.
	 *
	 * @param array{page?:int,page_size?:int,q?:string,filter?:string,sort?:string,section_id?:int,section_ids?:int[]|string} $params
	 * @return array{items:array,total:int,page:int,page_size:int,pages:int,sort:string,section_id:int,section_ids:int[]}
	 */
	public static function listSectionsForAuto(array $params): array
	{
		global $DB;

		WorkHistory::ensureTables();

		$iblockId = Config::iblockId();
		$page = max(1, (int) ($params['page'] ?? 1));
		$pageSize = max(1, min(100, (int) ($params['page_size'] ?? 25)));
		$q = trim((string) ($params['q'] ?? ''));
		$filterMode = (string) ($params['filter'] ?? 'todo');
		$sort = (string) ($params['sort'] ?? 'id_desc');
		if ($sort !== 'score_asc') {
			$sort = 'id_desc';
		}
		$sectionIds = self::sectionIdsFromParams($params);
		$sectionId = $sectionIds[0] ?? 0;

		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$phraseCol = UserFields::PHRASE_FIELD;
		$autoAtCol = UserFields::AUTO_AT_FIELD;

		$where = [
			'BS.IBLOCK_ID = ' . (int) $iblockId,
			"BS.ACTIVE = 'Y'",
			'(UTS.' . $phraseCol . ' IS NOT NULL AND UTS.' . $phraseCol . ' <> "")',
		];

		if ($filterMode === 'todo') {
			$where[] = '(UTS.' . $autoAtCol . ' IS NULL OR UTS.' . $autoAtCol . ' = "0000-00-00 00:00:00")';
		} elseif ($filterMode === 'done') {
			$where[] = '(UTS.' . $autoAtCol . ' IS NOT NULL AND UTS.' . $autoAtCol . ' <> "0000-00-00 00:00:00")';
		} elseif ($filterMode === 'scored') {
			$where[] = 'EXISTS (
				SELECT 1 FROM titlo_work_history whx
				WHERE whx.ENTITY_TYPE = \'S\' AND whx.ENTITY_ID = BS.ID
				  AND (whx.BEFORE_POINTS IS NOT NULL OR whx.AFTER_POINTS IS NOT NULL)
			)';
		}

		$branchSql = self::sqlSectionInAnySubtree($sectionIds);
		if ($branchSql !== null) {
			$where[] = $branchSql;
		} else {
			$sectionIds = [];
			$sectionId = 0;
		}

		if ($q !== '') {
			$cond = self::sqlPhraseSearchCondition($q, 'BS', 'S', $phraseCol);
			if ($cond !== '') {
				$where[] = $cond;
			}
		}

		$whereSql = implode(' AND ', $where);
		$offset = ($page - 1) * $pageSize;

		$whJoin = "
			LEFT JOIN (
				SELECT wh.ENTITY_ID,
					wh.ID AS WH_ID,
					wh.BEFORE_POINTS, wh.AFTER_POINTS,
					wh.BEFORE_POINTS_IDEAL, wh.AFTER_POINTS_IDEAL,
					wh.BEFORE_HISTORY_ID, wh.AFTER_HISTORY_ID,
					wh.BEFORE_AT, wh.AFTER_AT
				FROM titlo_work_history wh
				INNER JOIN (
					SELECT ENTITY_ID, MAX(ID) AS mid
					FROM titlo_work_history
					WHERE ENTITY_TYPE = 'S'
					GROUP BY ENTITY_ID
				) last ON last.mid = wh.ID
			) WH ON WH.ENTITY_ID = BS.ID
		";

		$total = (int) ($DB->Query("
			SELECT COUNT(*) AS CNT
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			WHERE {$whereSql}
		")->Fetch()['CNT'] ?? 0);

		$orderSql = 'BS.ID DESC';
		if ($sort === 'score_asc') {
			$orderSql = '(COALESCE(WH.AFTER_POINTS, WH.BEFORE_POINTS) IS NULL) ASC,'
				. ' COALESCE(WH.AFTER_POINTS, WH.BEFORE_POINTS) ASC,'
				. ' BS.ID DESC';
		}
		if ($q !== '' && !ctype_digit($q)) {
			$orderSql = self::sqlSearchRelevanceScore('BS', $q, $phraseCol) . ' DESC, ' . $orderSql;
		}

		$res = $DB->Query("
			SELECT
				BS.ID,
				BS.NAME,
				BS.CODE,
				BS.DESCRIPTION,
				CHAR_LENGTH(COALESCE(BS.DESCRIPTION, '')) AS DETAIL_LEN,
				UTS.{$phraseCol} AS TITLO_PHRASE,
				UTS.{$autoAtCol} AS TITLO_AUTO_AT,
				WH.WH_ID,
				WH.BEFORE_POINTS, WH.AFTER_POINTS,
				WH.BEFORE_POINTS_IDEAL, WH.AFTER_POINTS_IDEAL,
				WH.BEFORE_HISTORY_ID, WH.AFTER_HISTORY_ID,
				WH.BEFORE_AT, WH.AFTER_AT
			FROM b_iblock_section BS
			LEFT JOIN {$uts} UTS ON UTS.VALUE_ID = BS.ID
			{$whJoin}
			WHERE {$whereSql}
			ORDER BY {$orderSql}
			LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset . '
		');

		$items = [];
		while ($row = $res->Fetch()) {
			$detailRaw = (string) ($row['DESCRIPTION'] ?? '');
			$detailPlain = trim(html_entity_decode(strip_tags($detailRaw), ENT_QUOTES, 'UTF-8'));
			$autoAt = UserFields::formatPhraseAt($row['TITLO_AUTO_AT'] ?? null);
			$score = null;
			$whId = (int) ($row['WH_ID'] ?? 0);
			if ($whId > 0) {
				$afterHid = $row['AFTER_HISTORY_ID'] !== null && $row['AFTER_HISTORY_ID'] !== ''
					? (int) $row['AFTER_HISTORY_ID'] : 0;
				$useAfter = $afterHid > 0 && $row['AFTER_POINTS'] !== null && $row['AFTER_POINTS'] !== '';
				if ($useAfter) {
					$score = [
						'points' => (float) $row['AFTER_POINTS'],
						'points_ideal' => $row['AFTER_POINTS_IDEAL'] !== null && $row['AFTER_POINTS_IDEAL'] !== ''
							? (float) $row['AFTER_POINTS_IDEAL'] : null,
						'history_id' => $afterHid,
						'at' => (string) ($row['AFTER_AT'] ?? ''),
						'work_id' => $whId,
					];
				} else {
					$beforeHid = $row['BEFORE_HISTORY_ID'] !== null && $row['BEFORE_HISTORY_ID'] !== ''
						? (int) $row['BEFORE_HISTORY_ID'] : 0;
					$score = [
						'points' => $row['BEFORE_POINTS'] !== null && $row['BEFORE_POINTS'] !== ''
							? (float) $row['BEFORE_POINTS'] : null,
						'points_ideal' => $row['BEFORE_POINTS_IDEAL'] !== null && $row['BEFORE_POINTS_IDEAL'] !== ''
							? (float) $row['BEFORE_POINTS_IDEAL'] : null,
						'history_id' => $beforeHid > 0 ? $beforeHid : null,
						'at' => (string) ($row['BEFORE_AT'] ?? ''),
						'work_id' => $whId,
					];
					if ($score['points'] === null && $score['history_id'] === null) {
						$score = null;
					}
				}
			}
			$items[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'titlo_phrase' => trim((string) ($row['TITLO_PHRASE'] ?? '')),
				'detail_chars' => mb_strlen($detailPlain),
				'has_detail' => $detailPlain !== '',
				'auto_at' => $autoAt,
				'auto_done' => $autoAt !== '',
				'score' => $score,
				'url' => '',
			];
		}

		$urlMap = UrlBuilder::mapSectionUrls(array_column($items, 'id'));
		$queueMap = BatchQueue::activeByEntityIds(array_column($items, 'id'), 'S');
		foreach ($items as &$item) {
			$id = $item['id'];
			$item['url'] = $urlMap[$id] ?? UrlBuilder::category($item['code'], $id);
			$item['queue'] = $queueMap[$id] ?? null;
			$item['single_url'] = 'titlo_relevance_single.php?lang=' . LANGUAGE_ID . '&ENTITY=S&ID=' . $id;
		}
		unset($item);

		$pages = $pageSize > 0 ? (int) max(1, (int) ceil($total / $pageSize)) : 1;
		if ($total === 0) {
			$pages = 1;
		}

		return [
			'items' => $items,
			'total' => $total,
			'page' => $page,
			'page_size' => $pageSize,
			'pages' => $pages,
			'sort' => $sort,
			'section_id' => $sectionId,
			'section_ids' => $sectionIds,
		];
	}

	public static function isElementDetailEmpty(int $id): bool
	{
		if ($id <= 0) {
			return true;
		}
		$el = \CIBlockElement::GetList(
			[],
			['ID' => $id, 'IBLOCK_ID' => Config::iblockId()],
			false,
			false,
			['ID', 'DETAIL_TEXT']
		)->GetNext();
		if (!$el) {
			return true;
		}
		$plain = trim(html_entity_decode(strip_tags((string) ($el['~DETAIL_TEXT'] ?? $el['DETAIL_TEXT'] ?? '')), ENT_QUOTES, 'UTF-8'));
		return $plain === '';
	}

	public static function isSectionDescriptionEmpty(int $id): bool
	{
		if ($id <= 0) {
			return true;
		}
		$sec = \CIBlockSection::GetList(
			[],
			['ID' => $id, 'IBLOCK_ID' => Config::iblockId()],
			false,
			['ID', 'DESCRIPTION']
		)->GetNext();
		if (!$sec) {
			return true;
		}
		$plain = trim(html_entity_decode(strip_tags((string) ($sec['~DESCRIPTION'] ?? $sec['DESCRIPTION'] ?? '')), ENT_QUOTES, 'UTF-8'));
		return $plain === '';
	}

	public static function markElementAutoAt(int $id, ?string $mysqlDatetime = null): bool
	{
		if ($mysqlDatetime === null || $mysqlDatetime === '') {
			$mysqlDatetime = date('Y-m-d H:i:s');
		}
		return self::writeAutoAt('E', $id, $mysqlDatetime);
	}

	/** Сброс отметки автопроработки — товар снова «в работе». */
	public static function clearElementAutoAt(int $id): bool
	{
		return self::writeAutoAt('E', $id, null);
	}

	public static function markSectionAutoAt(int $id, ?string $mysqlDatetime = null): bool
	{
		if ($mysqlDatetime === null || $mysqlDatetime === '') {
			$mysqlDatetime = date('Y-m-d H:i:s');
		}
		return self::writeAutoAt('S', $id, $mysqlDatetime);
	}

	/** Сброс отметки автопроработки категории. */
	public static function clearSectionAutoAt(int $id): bool
	{
		return self::writeAutoAt('S', $id, null);
	}

	/**
	 * @param string $entityType E|S
	 * @param string|null $mysqlDatetime null = очистить колонку
	 */
	protected static function writeAutoAt(string $entityType, int $id, ?string $mysqlDatetime): bool
	{
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';
		if ($id <= 0) {
			return false;
		}
		$belongs = $entityType === 'S'
			? Config::sectionBelongsToCatalog($id)
			: Config::elementBelongsToCatalog($id);
		if (!$belongs) {
			return false;
		}
		global $DB;
		$iblockId = Config::iblockId();
		$col = UserFields::AUTO_AT_FIELD;
		$uts = 'b_uts_iblock_' . (int) $iblockId . ($entityType === 'S' ? '_section' : '_element');
		$clear = ($mysqlDatetime === null);
		$valSql = $clear ? 'NULL' : ("'" . $DB->ForSql($mysqlDatetime) . "'");
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID = ' . (int) $id)->Fetch();
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . $col . ' = ' . $valSql . ' WHERE VALUE_ID = ' . (int) $id);
		} elseif (!$clear) {
			$DB->Query(
				'INSERT INTO ' . $uts . ' (VALUE_ID, ' . $col . ') VALUES (' . (int) $id . ', ' . $valSql . ')'
			);
		}
		\CIBlock::clearIblockTagCache($iblockId);
		return true;
	}

	public static function saveSectionDescription(int $id, string $description): bool
	{
		if (!Config::sectionBelongsToCatalog($id)) {
			return false;
		}
		$bs = new \CIBlockSection();
		$ok = (bool) $bs->Update($id, [
			'DESCRIPTION' => self::sanitizeCatalogHtml($description),
			'DESCRIPTION_TYPE' => 'html',
		]);
		if ($ok) {
			self::flushPublicPageCache($id, 'S');
		}
		return $ok;
	}

	/**
	 * Allowlist HTML перед записью в каталог (DETAIL_TEXT / DESCRIPTION).
	 * Теги/атрибуты под .vmd-desc; href только http(s)/mailto/#.
	 */
	public static function sanitizeCatalogHtml(string $html): string
	{
		$html = (string) $html;
		if (trim($html) === '') {
			return '';
		}

		// W3C: «error parsing attribute name» — `src="…"; title="…"` (лишняя `;` между атрибутами)
		$html = preg_replace('/(["\'])\s*;\s+(?=[a-zA-Z_][\w:-]*=)/', '$1 ', $html) ?? $html;
		// `</br>` → `<br>` (иначе Unexpected end tag : br)
		$html = preg_replace('#</br\s*>#i', '<br>', $html) ?? $html;
		// `<h2Title</h2>` без `>` после имени тега
		$html = preg_replace('#<h([1-6])([^>\s/][^<]*)</h\1>#u', '<h$1>$2</h$1>', $html) ?? $html;
		// лишний `</p>` сразу после списка
		$html = preg_replace('#</ul>\s*</p>#i', '</ul>', $html) ?? $html;
		$html = preg_replace('#</ol>\s*</p>#i', '</ol>', $html) ?? $html;
		// «Заголовок</p><ul>» без открывающего <p>
		$html = preg_replace('#(^|[\n>])([А-ЯA-Z][^<\n]{1,80})</p>(\s*<ul)#u', '$1<p>$2</p>$3', $html) ?? $html;
		// typo text.м</ul>
		$html = preg_replace('#([^>])\.м</ul>#u', '$1.</li></ul>', $html) ?? $html;
		// <li>заголовок:</li><ul>…</ul> → <li>заголовок:<ul>…</ul></li>
		$html = preg_replace(
			'#<li>([^<]*)</li>\s*(<(?:ul|ol)\b[^>]*>.*?</(?:ul|ol)>)#is',
			'<li>$1$2</li>',
			$html
		) ?? $html;
		// <p>…<ol>/<ul>…</p> — списки нельзя внутри <p> (браузер закрывает p → orphan </p>)
		$html = preg_replace_callback(
			'#<p(\b[^>]*)>([\s\S]*?)</p>#i',
			static function (array $m): string {
				$attrs = $m[1];
				$inner = $m[2];
				if (!preg_match('#<(?:ul|ol)\b#i', $inner)) {
					return $m[0];
				}
				$parts = preg_split('#(<(?:ul|ol)\b[^>]*>.*?</(?:ul|ol)>)#is', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
				$out = '';
				foreach ($parts as $part) {
					if ($part === '' || $part === null) {
						continue;
					}
					if (preg_match('#^<(?:ul|ol)\b#i', $part)) {
						$out .= $part;
						continue;
					}
					$trim = preg_replace('#(?:\s*<br\s*/?>\s*)+#i', "\n", $part) ?? $part;
					if (trim(strip_tags($trim, '<img><a><strong><b><em><i><span><br>')) === '') {
						continue;
					}
					$out .= '<p' . $attrs . '>' . $part . '</p>';
				}
				return $out;
			},
			$html
		) ?? $html;
		// `<ul></li>текст` — </li> вместо <li>
		$html = preg_replace_callback('#<ul>(.*?)</ul>#is', static function (array $m): string {
			$inner = $m[1];
			if (!preg_match('#^\s*</li>#u', $inner)) {
				return $m[0];
			}
			$inner = preg_replace_callback(
				'#</li>([^<]+?)(?=</li>|<li>|</ul>|$)#us',
				static function (array $mm): string {
					$text = rtrim($mm[1]);
					return $text === '' ? '' : '<li>' . $text . '</li>';
				},
				$inner
			) ?? $inner;
			$inner = preg_replace('#</li>\s*</li>#u', '</li>', $inner) ?? $inner;
			return '<ul>' . $inner . '</ul>';
		}, $html) ?? $html;
		$html = preg_replace_callback('#<ol>(.*?)</ol>#is', static function (array $m): string {
			$inner = $m[1];
			if (!preg_match('#^\s*</li>#u', $inner)) {
				return $m[0];
			}
			$inner = preg_replace_callback(
				'#</li>([^<]+?)(?=</li>|<li>|</ol>|$)#us',
				static function (array $mm): string {
					$text = rtrim($mm[1]);
					return $text === '' ? '' : '<li>' . $text . '</li>';
				},
				$inner
			) ?? $inner;
			$inner = preg_replace('#</li>\s*</li>#u', '</li>', $inner) ?? $inner;
			return '<ol>' . $inner . '</ol>';
		}, $html) ?? $html;

		// Lucide-иконки: <div class="ic"><circle/…> без <svg> → W3C «Tag circle invalid»
		$html = preg_replace_callback(
			'#(<div\b[^>]*\bclass="[^"]*\bic\b[^"]*"[^>]*>)(.*?)(</div>)#is',
			static function (array $m): string {
				$inner = $m[2];
				if (stripos($inner, '<svg') !== false) {
					return $m[0];
				}
				if (!preg_match('#<(?:circle|rect|path|line|polyline|polygon|g)\b#i', $inner)) {
					return $m[0];
				}

				return $m[1]
					. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
					. $inner
					. '</svg>'
					. $m[3];
			},
			$html
		) ?? $html;

		// FAQ: голый <summary> + .vmd-faq__a → <details> (не трогать уже обёрнутые)
		$html = preg_replace_callback(
			'#(.{0,24})<summary>(.*?)</summary>\s*(<div\b[^>]*\bclass="[^"]*\bvmd-faq__a\b[^"]*"[^>]*>.*?</div>)#is',
			static function (array $m): string {
				if (preg_match('/<details\b[^>]*>\s*$/i', $m[1])) {
					return $m[0];
				}

				return $m[1] . '<details><summary>' . $m[2] . '</summary>' . $m[3] . '</details>';
			},
			$html
		) ?? $html;
		// Снять двойную обёртку от прошлых прогонов
		$html = preg_replace('#<details(\s[^>]*)?>\s*<details(\s[^>]*)?>#i', '<details>', $html) ?? $html;
		$html = preg_replace('#</details>\s*</details>#i', '</details>', $html) ?? $html;

		// Старые HTML4-чекеры ругаются на <mark>
		$html = preg_replace('#<mark\b([^>]*)>#i', '<span class="vmd-mark"$1>', $html) ?? $html;
		$html = preg_replace('#</mark>#i', '</span>', $html) ?? $html;

		$allowedTags = [
			'article' => true, 'section' => true, 'header' => true, 'footer' => true,
			'div' => true, 'span' => true, 'p' => true, 'br' => true, 'hr' => true,
			'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'h5' => true, 'h6' => true,
			'ul' => true, 'ol' => true, 'li' => true, 'dl' => true, 'dt' => true, 'dd' => true,
			'a' => true, 'strong' => true, 'b' => true, 'em' => true, 'i' => true, 'u' => true,
			's' => true, 'small' => true, 'sub' => true, 'sup' => true, 'mark' => true,
			'blockquote' => true, 'pre' => true, 'code' => true,
			'table' => true, 'thead' => true, 'tbody' => true, 'tfoot' => true,
			'tr' => true, 'th' => true, 'td' => true, 'caption' => true,
			'figure' => true, 'figcaption' => true, 'img' => true,
			// FAQ-аккордеон .vmd-faq (нативный <details>)
			'details' => true, 'summary' => true,
			// Lucide-иконки в .ic
			'svg' => true, 'path' => true, 'circle' => true, 'line' => true,
			'polyline' => true, 'polygon' => true, 'rect' => true, 'g' => true,
			'defs' => true, 'title' => true, 'desc' => true, 'use' => true,
			'clippath' => true, 'lineargradient' => true, 'stop' => true,
		];
		$allowedAttrs = [
			'class' => true, 'title' => true, 'lang' => true,
			'href' => true, 'target' => true, 'rel' => true,
			'src' => true, 'alt' => true, 'width' => true, 'height' => true,
			'colspan' => true, 'rowspan' => true, 'scope' => true,
			'open' => true,
			// SVG
			'viewbox' => true, 'fill' => true, 'stroke' => true,
			'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
			'd' => true, 'cx' => true, 'cy' => true, 'r' => true, 'rx' => true, 'ry' => true,
			'x' => true, 'y' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
			'points' => true, 'transform' => true, 'xmlns' => true, 'xmlns:xlink' => true,
			'aria-hidden' => true, 'role' => true, 'focusable' => true,
		];

		$wrapped = '<?xml encoding="UTF-8"><div id="titlo-sanitize-root">' . $html . '</div>';
		$prev = libxml_use_internal_errors(true);
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$loaded = @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if (!$loaded) {
			return strip_tags($html, '<' . implode('><', array_keys($allowedTags)) . '>');
		}

		$root = $dom->getElementById('titlo-sanitize-root');
		if (!$root) {
			return '';
		}

		self::sanitizeDomNode($root, $allowedTags, $allowedAttrs);

		$out = '';
		foreach ($root->childNodes as $child) {
			$out .= $dom->saveHTML($child);
		}

		return $out;
	}

	/**
	 * @param array<string,bool> $allowedTags
	 * @param array<string,bool> $allowedAttrs
	 */
	protected static function sanitizeDomNode(\DOMNode $node, array $allowedTags, array $allowedAttrs): void
	{
		if (!($node instanceof \DOMElement) && !($node instanceof \DOMDocument)) {
			return;
		}

		$toRemove = [];
		foreach (iterator_to_array($node->childNodes) as $child) {
			if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
				continue;
			}
			if ($child instanceof \DOMComment) {
				$toRemove[] = $child;
				continue;
			}
			if (!($child instanceof \DOMElement)) {
				$toRemove[] = $child;
				continue;
			}

			$tag = strtolower($child->tagName);
			// DOM может отдавать clipPath как clippath — нормализуем ключ allowlist
			if ($tag === 'clippath') {
				$tag = 'clippath';
			}
			if (!isset($allowedTags[$tag])) {
				// unwrap: keep children, drop forbidden wrapper
				while ($child->firstChild) {
					$node->insertBefore($child->firstChild, $child);
				}
				$toRemove[] = $child;
				continue;
			}

			// SVG-примитивы только внутри <svg> — иначе W3C: Tag circle/rect/… invalid
			// (обёртка .ic → <svg> делается в sanitizeCatalogHtml до DOM)
			static $svgOnly = [
				'path' => true, 'circle' => true, 'line' => true, 'polyline' => true,
				'polygon' => true, 'rect' => true, 'g' => true, 'defs' => true,
				'use' => true, 'clippath' => true, 'lineargradient' => true, 'stop' => true,
			];
			if (isset($svgOnly[$tag])) {
				$inSvg = false;
				$walk = $child->parentNode;
				while ($walk instanceof \DOMElement) {
					if (strtolower($walk->tagName) === 'svg') {
						$inSvg = true;
						break;
					}
					$walk = $walk->parentNode;
				}
				if (!$inSvg) {
					// Последний шанс: обернуть одиночный примитив в svg
					$svg = $child->ownerDocument->createElement('svg');
					$svg->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
					$svg->setAttribute('viewBox', '0 0 24 24');
					$svg->setAttribute('fill', 'none');
					$svg->setAttribute('stroke', 'currentColor');
					$svg->setAttribute('stroke-width', '2');
					$svg->setAttribute('aria-hidden', 'true');
					$node->insertBefore($svg, $child);
					$svg->appendChild($child);
					self::sanitizeDomNode($svg, $allowedTags, $allowedAttrs);
					continue;
				}
			}

			$attrs = [];
			if ($child->hasAttributes()) {
				foreach (iterator_to_array($child->attributes) as $attr) {
					$attrs[] = $attr;
				}
			}
			foreach ($attrs as $attr) {
				$name = strtolower($attr->name);
				$val = (string) $attr->value;
				if (strpos($name, 'on') === 0 || !isset($allowedAttrs[$name])) {
					$child->removeAttribute($attr->name);
					continue;
				}
				if ($name === 'href' || $name === 'src') {
					$safe = self::sanitizeUrlAttr($val, $name === 'src');
					if ($safe === null) {
						$child->removeAttribute($attr->name);
					} else {
						$child->setAttribute($attr->name, $safe);
					}
					continue;
				}
				if ($name === 'target') {
					if ($val !== '_blank' && $val !== '_self') {
						$child->removeAttribute($attr->name);
					} else {
						$child->setAttribute('rel', 'noopener noreferrer');
					}
				}
			}

			self::sanitizeDomNode($child, $allowedTags, $allowedAttrs);
		}

		foreach ($toRemove as $dead) {
			if ($dead->parentNode) {
				$dead->parentNode->removeChild($dead);
			}
		}
	}

	protected static function sanitizeUrlAttr(string $url, bool $imgSrc): ?string
	{
		$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($url === '' || $url[0] === '#') {
			return $url === '' ? null : $url;
		}
		// protocol-relative //evil.com — запрет
		if (strpos($url, '//') === 0) {
			return null;
		}
		if (preg_match('#^(mailto|tel):#i', $url)) {
			return $url;
		}
		if ($imgSrc && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $url)) {
			return $url;
		}
		if (preg_match('#^https?://#i', $url) || preg_match('#^/[^/]#', $url) || $url === '/') {
			if (preg_match('#^\s*javascript\s*:#i', $url) || preg_match('#^\s*data\s*:#i', $url)) {
				return null;
			}
			return $url;
		}

		return null;
	}

	/**
	 * Preferred phrase for relevance: UF short name, else truncated NAME.
	 */
	public static function preferredPhrase(array $entity): string
	{
		$phrase = trim((string) ($entity['titlo_phrase'] ?? ''));
		if ($phrase !== '') {
			return mb_strlen($phrase) > 50 ? rtrim(mb_substr($phrase, 0, 50)) : $phrase;
		}
		$name = trim((string) ($entity['name'] ?? ''));
		if (mb_strlen($name) > 50) {
			return rtrim(mb_substr($name, 0, 50));
		}
		return $name;
	}
}
