<?php

namespace Titlo\Relevance;

class CatalogRepository
{
	/**
	 * Поиск в «Проверке названий»: ID / название / код / полный URL.
	 * Пустая строка — без доп. условия.
	 */
	public static function sqlPhraseSearchCondition(
		string $q,
		string $tableAlias,
		string $entityType,
		string $phraseCol
	): string {
		global $DB;
		$q = trim($q);
		if ($q === '') {
			return '';
		}
		$alias = preg_replace('#[^A-Za-z0-9_]#', '', $tableAlias) ?: 'BE';
		$entityType = strtoupper($entityType) === 'S' ? 'S' : 'E';

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

		$like = Config::forLike($q);
		return '(' . $alias . '.NAME LIKE "%' . $like . '%" OR '
			. $alias . '.CODE LIKE "%' . $like . '%" OR UTS.' . $phraseCol . ' LIKE "%' . $like . '%")';
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
	 * @return array<int, array>
	 */
	public static function search(string $entityType, string $q, int $limit = 20): array
	{
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

		if ($entityType === 'S') {
			$filter = ['IBLOCK_ID' => $iblockId];
			if ($q !== '') {
				if (ctype_digit($q)) {
					$filter['ID'] = (int) $q;
				} else {
					$filter[] = [
						'LOGIC' => 'OR',
						['%NAME' => $q],
						['%=CODE' => $q],
					];
				}
			}
			$res = \CIBlockSection::GetList(['NAME' => 'ASC'], $filter, false, ['ID', 'NAME', 'CODE'], ['nTopCount' => $limit]);
			while ($row = $res->Fetch()) {
				$out[] = [
					'id' => (int) $row['ID'],
					'name' => (string) $row['NAME'],
					'code' => (string) $row['CODE'],
					'type' => 'S',
				];
			}
			return $out;
		}

		$filter = ['IBLOCK_ID' => $iblockId];
		if ($q !== '') {
			if (ctype_digit($q)) {
				$filter['ID'] = (int) $q;
			} else {
				$filter[] = [
					'LOGIC' => 'OR',
					['%NAME' => $q],
					['%=CODE' => $q],
				];
			}
		}
		$res = \CIBlockElement::GetList(['NAME' => 'ASC'], $filter, false, ['nTopCount' => $limit], ['ID', 'NAME', 'CODE']);
		while ($row = $res->Fetch()) {
			$out[] = [
				'id' => (int) $row['ID'],
				'name' => (string) $row['NAME'],
				'code' => (string) $row['CODE'],
				'type' => 'E',
			];
		}
		return $out;
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
				UTS.{$phraseAtCol} AS TITLO_PHRASE_AT
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

			$items[] = [
				'id' => (int) $row['ID'],
				'name' => $name,
				'name_len' => $nameLen,
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
				UTS.{$phraseAtCol} AS TITLO_PHRASE_AT
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

			$items[] = [
				'id' => (int) $row['ID'],
				'name' => $name,
				'name_len' => $nameLen,
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
		if (mb_strlen($phrase) > 50) {
			$phrase = rtrim(mb_substr($phrase, 0, 50));
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
	 * «купить» добавляем в конец только если влезает в 50 без обрезания базы.
	 * Пустая фраза = сброс в непроработанные.
	 */
	public static function normalizeSectionPhrase(string $phrase): string
	{
		$phrase = trim(preg_replace('/\s+/u', ' ', trim($phrase)));
		if ($phrase === '') {
			return '';
		}

		$hadKupit = (bool) preg_match('/\bкупить\b/ui', $phrase);
		$base = trim(preg_replace('/\bкупить\b/ui', '', $phrase));
		$base = trim(preg_replace('/\s+/u', ' ', $base));
		$base = trim($base, " \t\n\r\0\x0B,.;:!-–—");
		if ($base === '') {
			return $hadKupit ? 'купить' : '';
		}

		$base = self::cutPhraseAtWord($base, 50);
		$suffix = ' купить';
		if (mb_strlen($base) + mb_strlen($suffix) <= 50) {
			return $base . $suffix;
		}
		// Не влезает «купить» — оставляем смысл целиком (до 50), без насильственной обрезки ради суффикса.
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
			if (ctype_digit($q)) {
				$where[] = 'BE.ID = ' . (int) $q;
			} else {
				$like = Config::forLike($q);
				$where[] = '(BE.NAME LIKE "%' . $like . '%" OR BE.CODE LIKE "%' . $like . '%" OR UTS.' . $phraseCol . ' LIKE "%' . $like . '%")';
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
			if (ctype_digit($q)) {
				$where[] = 'BS.ID = ' . (int) $q;
			} else {
				$like = Config::forLike($q);
				$where[] = '(BS.NAME LIKE "%' . $like . '%" OR BS.CODE LIKE "%' . $like . '%" OR UTS.' . $phraseCol . ' LIKE "%' . $like . '%")';
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
		];
		$allowedAttrs = [
			'class' => true, 'title' => true, 'lang' => true,
			'href' => true, 'target' => true, 'rel' => true,
			'src' => true, 'alt' => true, 'width' => true, 'height' => true,
			'colspan' => true, 'rowspan' => true, 'scope' => true,
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
			if (!isset($allowedTags[$tag])) {
				// unwrap: keep children, drop forbidden wrapper
				while ($child->firstChild) {
					$node->insertBefore($child->firstChild, $child);
				}
				$toRemove[] = $child;
				continue;
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
