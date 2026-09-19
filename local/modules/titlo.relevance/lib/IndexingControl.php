<?php

namespace Titlo\Relevance;

use Bitrix\Main\Loader;

/**
 * Универсальное закрытие карточки от индексации (сайт-агностик).
 *
 * Всегда:
 * 1) UF_TITLO_NOINDEX — поле модуля
 * 2) meta robots на витрине (OnEpilog + OnEndBufferContent) — без robots.txt
 *
 * Опционально (настройки модуля):
 * 3) SEO ELEMENT_META_ROBOTS
 * 4) Свойство инфоблока — ТОЛЬКО если явно указан CODE (напр. ROBOTS_NOINDEX у шаблона)
 */
class IndexingControl
{
	public const UF_NOINDEX = 'UF_TITLO_NOINDEX';

	public static function ensureFields(): void
	{
		self::ensureEntityFields('ELEMENT');
	}

	public static function ensureSectionFields(): void
	{
		self::ensureEntityFields('SECTION');
	}

	protected static function ensureEntityFields(string $kind): void
	{
		if (!Loader::includeModule('iblock')) {
			return;
		}
		$iblockId = Config::iblockId();
		if ($iblockId <= 0) {
			return;
		}
		$kind = $kind === 'SECTION' ? 'SECTION' : 'ELEMENT';
		$entityId = 'IBLOCK_' . $iblockId . '_' . $kind;
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::UF_NOINDEX,
		])->Fetch();
		if ($exists) {
			return;
		}
		$o = new \CUserTypeEntity();
		$o->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::UF_NOINDEX,
			'USER_TYPE_ID' => 'boolean',
			'XML_ID' => self::UF_NOINDEX . ($kind === 'SECTION' ? '_S' : ''),
			'SORT' => 115,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'I',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => [
				'DEFAULT_VALUE' => 0,
				'DISPLAY' => 'CHECKBOX',
				'LABEL' => ['', ''],
				'LABEL_CHECKBOX' => 'Titlo: noindex',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: скрыть от индексации', 'en' => 'Titlo: noindex'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Titlo noindex', 'en' => 'Titlo noindex'],
			'LIST_FILTER_LABEL' => ['ru' => 'Titlo noindex', 'en' => 'Titlo noindex'],
			'HELP_MESSAGE' => [
				'ru' => 'Флаг модуля Titlo. Витрина ставит meta robots=noindex.',
				'en' => 'Titlo noindex flag.',
			],
		]);
	}

	public static function useSeoRobots(): bool
	{
		return Config::noindexUseSeo();
	}

	public static function configuredPropertyCode(): string
	{
		return Config::noindexProperty();
	}

	/**
	 * Свойство инфоблока — только при явном CODE в настройках.
	 *
	 * @return array{id:int,code:string,type:string,enum_id:int}|null
	 */
	public static function resolveProperty(?int $iblockId = null): ?array
	{
		static $cache = [];
		$iblockId = $iblockId ?: Config::iblockId();
		if (array_key_exists($iblockId, $cache)) {
			return $cache[$iblockId];
		}
		if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
			return $cache[$iblockId] = null;
		}

		$code = self::configuredPropertyCode();
		if ($code === '') {
			return $cache[$iblockId] = null;
		}

		$res = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'ACTIVE' => 'Y']);
		$prop = $res->Fetch();
		if (!$prop) {
			return $cache[$iblockId] = null;
		}
		$enumId = 0;
		if ($prop['PROPERTY_TYPE'] === 'L') {
			$en = \CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => (int) $prop['ID']]);
			$preferred = null;
			while ($e = $en->Fetch()) {
				$xml = mb_strtolower((string) $e['XML_ID']);
				$val = mb_strtolower(trim((string) $e['VALUE']));
				if ($code === 'INDEX') {
					if (in_array($xml, ['n', 'no', '0'], true) || in_array($val, ['n', 'нет', 'no', '0'], true)) {
						$preferred = (int) $e['ID'];
						break;
					}
					continue;
				}
				if (
					strpos($xml, 'noindex') !== false
					|| in_array($xml, ['y', 'yes', '1', 'true'], true)
					|| in_array($val, ['y', 'yes', '1', 'да'], true)
					|| strpos($val, 'noindex') !== false
					|| strpos($val, 'скрыт') !== false
				) {
					$preferred = (int) $e['ID'];
					break;
				}
			}
			// fail closed: без явного enum не пишем list-property
			$enumId = $preferred !== null ? (int) $preferred : 0;
		}
		return $cache[$iblockId] = [
			'id' => (int) $prop['ID'],
			'code' => (string) $prop['CODE'],
			'type' => (string) $prop['PROPERTY_TYPE'],
			'enum_id' => $enumId,
		];
	}

	/**
	 * @return array{ok:bool,channels:string[],error?:string}
	 */
	public static function setNoindex(int $elementId, bool $on): array
	{
		if ($elementId <= 0) {
			return ['ok' => false, 'channels' => [], 'error' => 'bad id'];
		}
		if (!Config::elementBelongsToCatalog($elementId)) {
			return ['ok' => false, 'channels' => [], 'error' => 'element not in catalog iblock'];
		}
		self::ensureFields();
		$channels = [];
		$iblockId = Config::iblockId();

		self::setUfNoindex($elementId, $on);
		$channels[] = 'uf:' . self::UF_NOINDEX;

		if (self::useSeoRobots()) {
			self::setSeoRobots($elementId, $iblockId, $on);
			$channels[] = 'seo:ELEMENT_META_ROBOTS';
		}

		$prop = self::resolveProperty($iblockId);
		if ($prop) {
			self::setPropertyNoindex($elementId, $iblockId, $prop, $on);
			$channels[] = 'property:' . $prop['code'];
		}

		\CIBlock::clearIblockTagCache($iblockId);

		return ['ok' => true, 'channels' => $channels];
	}

	public static function isNoindex(int $elementId): bool
	{
		if ($elementId <= 0) {
			return false;
		}
		$iblockId = Config::iblockId();

		if (self::readUfNoindex($elementId)) {
			return true;
		}

		if (self::useSeoRobots() && self::readSeoIsNoindex($elementId, $iblockId)) {
			return true;
		}

		$prop = self::resolveProperty($iblockId);
		if ($prop && self::readPropertyIsNoindex($elementId, $prop)) {
			return true;
		}

		return false;
	}

	/**
	 * Карта elementId => noindex для списка (один SQL по свойству + UF).
	 *
	 * @param int[] $ids
	 * @return array<int, bool>
	 */
	public static function mapNoindex(array $ids): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids)));
		$out = [];
		foreach ($ids as $id) {
			$out[$id] = false;
		}
		if ($ids === []) {
			return $out;
		}

		global $DB;
		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_element';
		$idList = implode(',', $ids);

		if (self::hasUfColumn(self::UF_NOINDEX)) {
			$res = $DB->Query('
				SELECT VALUE_ID, ' . self::UF_NOINDEX . ' AS V
				FROM ' . $uts . '
				WHERE VALUE_ID IN (' . $idList . ')
			');
			while ($row = $res->Fetch()) {
				$v = $row['V'];
				if ($v === '1' || $v === 1 || $v === 'Y' || $v === true) {
					$out[(int) $row['VALUE_ID']] = true;
				}
			}
		}

		$prop = self::resolveProperty($iblockId);
		if ($prop) {
			$res = $DB->Query('
				SELECT IBLOCK_ELEMENT_ID, VALUE, VALUE_ENUM, VALUE_NUM
				FROM b_iblock_element_property
				WHERE IBLOCK_PROPERTY_ID = ' . (int) $prop['id'] . '
				  AND IBLOCK_ELEMENT_ID IN (' . $idList . ')
			');
			while ($row = $res->Fetch()) {
				$eid = (int) $row['IBLOCK_ELEMENT_ID'];
				if ($prop['type'] === 'L') {
					if ($prop['enum_id'] > 0 && (int) $row['VALUE_ENUM'] === $prop['enum_id']) {
						$out[$eid] = true;
					}
				} else {
					$val = trim((string) ($row['VALUE'] ?? ''));
					if (in_array(mb_strtolower($val), ['1', 'y', 'yes', 'true', 'noindex'], true)) {
						$out[$eid] = true;
					}
				}
			}
		}

		if (self::useSeoRobots()) {
			$res = $DB->Query("
				SELECT ENTITY_ID, TEMPLATE
				FROM b_iblock_iproperty
				WHERE IBLOCK_ID = " . (int) $iblockId . "
				  AND ENTITY_TYPE = 'E'
				  AND CODE = 'ELEMENT_META_ROBOTS'
				  AND ENTITY_ID IN (" . $idList . ")
			");
			while ($row = $res->Fetch()) {
				$tpl = mb_strtolower((string) $row['TEMPLATE']);
				if (strpos($tpl, 'noindex') !== false) {
					$out[(int) $row['ENTITY_ID']] = true;
				}
			}
		}

		return $out;
	}

	protected static function setUfNoindex(int $elementId, bool $on): void
	{
		self::setUfNoindexEntity('ELEMENT', $elementId, $on);
	}

	protected static function readUfNoindex(int $elementId): bool
	{
		return self::readUfNoindexEntity('ELEMENT', $elementId);
	}

	protected static function hasUfColumn(string $col): bool
	{
		return self::hasUfColumnEntity('ELEMENT', $col);
	}

	protected static function setSeoRobots(int $elementId, int $iblockId, bool $on): void
	{
		if (!class_exists('\Bitrix\Iblock\InheritedProperty\ElementTemplates')) {
			return;
		}
		try {
			$templates = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($iblockId, $elementId);
			$templates->set([
				'ELEMENT_META_ROBOTS' => $on ? 'noindex, nofollow' : '',
			]);
		} catch (\Throwable $e) {
			// канал опциональный
		}
	}

	protected static function readSeoIsNoindex(int $elementId, int $iblockId): bool
	{
		global $DB;
		$row = $DB->Query("
			SELECT TEMPLATE FROM b_iblock_iproperty
			WHERE IBLOCK_ID = " . (int) $iblockId . "
			  AND ENTITY_TYPE = 'E'
			  AND ENTITY_ID = " . (int) $elementId . "
			  AND CODE = 'ELEMENT_META_ROBOTS'
			LIMIT 1
		")->Fetch();
		if (!$row) {
			return false;
		}
		return strpos(mb_strtolower((string) $row['TEMPLATE']), 'noindex') !== false;
	}

	/**
	 * @param array{id:int,code:string,type:string,enum_id:int} $prop
	 */
	protected static function setPropertyNoindex(int $elementId, int $iblockId, array $prop, bool $on): void
	{
		if ($prop['type'] === 'L') {
			if ($prop['enum_id'] <= 0) {
				return;
			}
			\CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
				$prop['code'] => $on ? $prop['enum_id'] : false,
			]);
			return;
		}
		// S / N / etc.
		\CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
			$prop['code'] => $on ? 'Y' : false,
		]);
	}

	/**
	 * @param array{id:int,code:string,type:string,enum_id:int} $prop
	 */
	protected static function readPropertyIsNoindex(int $elementId, array $prop): bool
	{
		$res = \CIBlockElement::GetProperty(
			Config::iblockId(),
			$elementId,
			[],
			['CODE' => $prop['code']]
		);
		while ($row = $res->Fetch()) {
			if ($prop['type'] === 'L') {
				if ($prop['enum_id'] > 0 && (int) $row['VALUE_ENUM'] === $prop['enum_id']) {
					return true;
				}
			} else {
				$val = mb_strtolower(trim((string) ($row['VALUE'] ?? '')));
				if (in_array($val, ['1', 'y', 'yes', 'true', 'noindex'], true)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return array{ok:bool,channels:string[],error?:string}
	 */
	public static function setSectionNoindex(int $sectionId, bool $on): array
	{
		if ($sectionId <= 0) {
			return ['ok' => false, 'channels' => [], 'error' => 'bad id'];
		}
		if (!Config::sectionBelongsToCatalog($sectionId)) {
			return ['ok' => false, 'channels' => [], 'error' => 'section not in catalog iblock'];
		}
		self::ensureSectionFields();
		$channels = [];
		$iblockId = Config::iblockId();

		self::setUfNoindexEntity('SECTION', $sectionId, $on);
		$channels[] = 'uf:' . self::UF_NOINDEX;

		// Нативное UF магазина (часто UF_ROBOTS_NOINDEX) — если колонка есть
		if (self::syncShopSectionRobotsUf($sectionId, $on)) {
			$channels[] = 'uf:UF_ROBOTS_NOINDEX';
		}

		if (self::useSeoRobots()) {
			self::setSectionSeoRobots($sectionId, $iblockId, $on);
			$channels[] = 'seo:SECTION_META_ROBOTS';
		}

		\CIBlock::clearIblockTagCache($iblockId);

		return ['ok' => true, 'channels' => $channels];
	}

	/**
	 * Синхронизация UF_ROBOTS_NOINDEX раздела (витрины, где шаблон читает это поле).
	 */
	protected static function syncShopSectionRobotsUf(int $sectionId, bool $on): bool
	{
		$col = 'UF_ROBOTS_NOINDEX';
		if (!self::hasUfColumnEntity('SECTION', $col)) {
			return false;
		}
		global $DB;
		$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_section';
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $sectionId)->Fetch();
		$val = $on ? '1' : '0';
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . $col . '=' . $val . ' WHERE VALUE_ID=' . (int) $sectionId);
		} else {
			$DB->Query('INSERT INTO ' . $uts . ' (VALUE_ID, ' . $col . ') VALUES (' . (int) $sectionId . ', ' . $val . ')');
		}
		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update('IBLOCK_' . Config::iblockId() . '_SECTION', $sectionId, [
				$col => $on ? 1 : 0,
			]);
		}
		return true;
	}

	public static function isSectionNoindex(int $sectionId): bool
	{
		if ($sectionId <= 0) {
			return false;
		}
		if (self::readUfNoindexEntity('SECTION', $sectionId)) {
			return true;
		}
		if (self::hasUfColumnEntity('SECTION', 'UF_ROBOTS_NOINDEX')) {
			global $DB;
			$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_section';
			$row = $DB->Query('SELECT UF_ROBOTS_NOINDEX AS V FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $sectionId)->Fetch();
			$v = $row['V'] ?? null;
			if ($v === '1' || $v === 1 || $v === 'Y') {
				return true;
			}
		}
		if (self::useSeoRobots() && self::readSectionSeoIsNoindex($sectionId, Config::iblockId())) {
			return true;
		}
		return false;
	}

	/**
	 * @param int[] $ids
	 * @return array<int, bool>
	 */
	public static function mapSectionNoindex(array $ids): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids)));
		$out = [];
		foreach ($ids as $id) {
			$out[$id] = false;
		}
		if ($ids === []) {
			return $out;
		}

		global $DB;
		$iblockId = Config::iblockId();
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_section';
		$idList = implode(',', $ids);

		if (self::hasUfColumnEntity('SECTION', self::UF_NOINDEX)) {
			$res = $DB->Query('
				SELECT VALUE_ID, ' . self::UF_NOINDEX . ' AS V
				FROM ' . $uts . '
				WHERE VALUE_ID IN (' . $idList . ')
			');
			while ($row = $res->Fetch()) {
				$v = $row['V'];
				if ($v === '1' || $v === 1 || $v === 'Y' || $v === true) {
					$out[(int) $row['VALUE_ID']] = true;
				}
			}
		}

		if (self::useSeoRobots()) {
			$res = $DB->Query("
				SELECT ENTITY_ID, TEMPLATE
				FROM b_iblock_iproperty
				WHERE IBLOCK_ID = " . (int) $iblockId . "
				  AND ENTITY_TYPE = 'S'
				  AND CODE = 'SECTION_META_ROBOTS'
				  AND ENTITY_ID IN (" . $idList . ")
			");
			while ($row = $res->Fetch()) {
				$tpl = mb_strtolower((string) $row['TEMPLATE']);
				if (strpos($tpl, 'noindex') !== false) {
					$out[(int) $row['ENTITY_ID']] = true;
				}
			}
		}

		if (self::hasUfColumnEntity('SECTION', 'UF_ROBOTS_NOINDEX')) {
			$res = $DB->Query('
				SELECT VALUE_ID, UF_ROBOTS_NOINDEX AS V
				FROM ' . $uts . '
				WHERE VALUE_ID IN (' . $idList . ')
			');
			while ($row = $res->Fetch()) {
				$v = $row['V'];
				if ($v === '1' || $v === 1 || $v === 'Y' || $v === true) {
					$out[(int) $row['VALUE_ID']] = true;
				}
			}
		}

		return $out;
	}

	protected static function setUfNoindexEntity(string $kind, int $id, bool $on): void
	{
		$kind = $kind === 'SECTION' ? 'SECTION' : 'ELEMENT';
		$iblockId = Config::iblockId();
		$entityId = 'IBLOCK_' . $iblockId . '_' . $kind;
		global $USER_FIELD_MANAGER;
		if (is_object($USER_FIELD_MANAGER)) {
			$USER_FIELD_MANAGER->Update($entityId, $id, [
				self::UF_NOINDEX => $on ? 1 : 0,
			]);
		}
		global $DB;
		$uts = 'b_uts_iblock_' . (int) $iblockId . '_' . ($kind === 'SECTION' ? 'section' : 'element');
		if (!self::hasUfColumnEntity($kind, self::UF_NOINDEX)) {
			return;
		}
		$row = $DB->Query('SELECT VALUE_ID FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $id)->Fetch();
		$val = $on ? '1' : '0';
		if ($row) {
			$DB->Query('UPDATE ' . $uts . ' SET ' . self::UF_NOINDEX . '=' . $val . ' WHERE VALUE_ID=' . (int) $id);
		} else {
			$DB->Query('INSERT INTO ' . $uts . ' (VALUE_ID, ' . self::UF_NOINDEX . ') VALUES (' . (int) $id . ', ' . $val . ')');
		}
	}

	protected static function readUfNoindexEntity(string $kind, int $id): bool
	{
		global $DB;
		$kind = $kind === 'SECTION' ? 'SECTION' : 'ELEMENT';
		$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_' . ($kind === 'SECTION' ? 'section' : 'element');
		if (!self::hasUfColumnEntity($kind, self::UF_NOINDEX)) {
			return false;
		}
		$row = $DB->Query('SELECT ' . self::UF_NOINDEX . ' AS V FROM ' . $uts . ' WHERE VALUE_ID=' . (int) $id)->Fetch();
		if (!$row) {
			return false;
		}
		$v = $row['V'];
		return $v === '1' || $v === 1 || $v === 'Y';
	}

	protected static function hasUfColumnEntity(string $kind, string $col): bool
	{
		global $DB;
		$kind = $kind === 'SECTION' ? 'section' : 'element';
		$key = $kind . ':' . $col;
		static $cache = [];
		if (isset($cache[$key])) {
			return $cache[$key];
		}
		$uts = 'b_uts_iblock_' . (int) Config::iblockId() . '_' . $kind;
		$res = $DB->Query("SHOW TABLES LIKE '" . $DB->ForSql($uts) . "'");
		if (!$res->Fetch()) {
			return $cache[$key] = false;
		}
		$res = $DB->Query("SHOW COLUMNS FROM {$uts} LIKE '" . $DB->ForSql($col) . "'");
		return $cache[$key] = (bool) $res->Fetch();
	}

	protected static function setSectionSeoRobots(int $sectionId, int $iblockId, bool $on): void
	{
		if (!class_exists('\Bitrix\Iblock\InheritedProperty\SectionTemplates')) {
			return;
		}
		try {
			$templates = new \Bitrix\Iblock\InheritedProperty\SectionTemplates($iblockId, $sectionId);
			$templates->set([
				'SECTION_META_ROBOTS' => $on ? 'noindex, nofollow' : '',
			]);
		} catch (\Throwable $e) {
			// канал опциональный
		}
	}

	protected static function readSectionSeoIsNoindex(int $sectionId, int $iblockId): bool
	{
		global $DB;
		$row = $DB->Query("
			SELECT TEMPLATE FROM b_iblock_iproperty
			WHERE IBLOCK_ID = " . (int) $iblockId . "
			  AND ENTITY_TYPE = 'S'
			  AND ENTITY_ID = " . (int) $sectionId . "
			  AND CODE = 'SECTION_META_ROBOTS'
			LIMIT 1
		")->Fetch();
		if (!$row) {
			return false;
		}
		return strpos(mb_strtolower((string) $row['TEMPLATE']), 'noindex') !== false;
	}

	/**
	 * ID элемента на витрине: CODE из path/роутера, либо REQUEST ID только если
	 * DETAIL_PAGE_URL совпадает с текущим URI (защита от ?ID= spoof → чужой noindex).
	 */
	public static function guessCurrentElementId(): int
	{
		if (!Loader::includeModule('iblock')) {
			return 0;
		}
		$code = self::resolveElementCodeFromRequest();
		$idFromCode = 0;
		if ($code !== '') {
			$res = \CIBlockElement::GetList(
				[],
				['IBLOCK_ID' => Config::iblockId(), '=CODE' => $code, 'ACTIVE' => 'Y'],
				false,
				['nTopCount' => 1],
				['ID']
			);
			if ($row = $res->Fetch()) {
				$idFromCode = (int) $row['ID'];
			}
		}

		$reqId = 0;
		foreach (['ELEMENT_ID', 'ID', 'el_id'] as $k) {
			if (!empty($_REQUEST[$k]) && ctype_digit((string) $_REQUEST[$k])) {
				$reqId = (int) $_REQUEST[$k];
				break;
			}
		}

		if ($idFromCode > 0) {
			// REQUEST ID на чужой странице игнорируем; совпадение с CODE — ок
			if ($reqId > 0 && $reqId !== $idFromCode) {
				return $idFromCode;
			}
			return $idFromCode;
		}

		if ($reqId > 0 && Config::elementBelongsToCatalog($reqId) && self::currentUriMatchesElementDetail($reqId)) {
			return $reqId;
		}

		return 0;
	}

	public static function guessCurrentSectionId(): int
	{
		if (!Loader::includeModule('iblock')) {
			return 0;
		}
		$code = trim((string) ($_REQUEST['SECTION_CODE'] ?? ''));
		if ($code === '' && !empty($GLOBALS['APPLICATION'])) {
			$page = (string) $GLOBALS['APPLICATION']->GetCurPage(false);
			$base = basename(rtrim($page, '/'));
			if ($base !== '' && $base !== 'index.php' && !preg_match('/\.(php|html?)$/i', $base)) {
				// только если нет ELEMENT_CODE — иначе это карточка товара
				if (empty($_REQUEST['ELEMENT_CODE']) && empty($_REQUEST['CODE'])) {
					$code = urldecode($base);
				}
			}
		}
		$idFromCode = 0;
		if ($code !== '') {
			$res = \CIBlockSection::GetList(
				[],
				['IBLOCK_ID' => Config::iblockId(), '=CODE' => $code, 'ACTIVE' => 'Y'],
				false,
				['ID'],
				['nTopCount' => 1]
			);
			if ($row = $res->Fetch()) {
				$idFromCode = (int) $row['ID'];
			}
		}

		$reqId = 0;
		foreach (['SECTION_ID', 'SID'] as $k) {
			if (!empty($_REQUEST[$k]) && ctype_digit((string) $_REQUEST[$k])) {
				$reqId = (int) $_REQUEST[$k];
				break;
			}
		}

		if ($idFromCode > 0) {
			return $idFromCode;
		}

		if ($reqId > 0 && Config::sectionBelongsToCatalog($reqId) && self::currentUriMatchesSection($reqId)) {
			return $reqId;
		}

		return 0;
	}

	protected static function resolveElementCodeFromRequest(): string
	{
		$code = trim((string) ($_REQUEST['ELEMENT_CODE'] ?? ''));
		if ($code !== '') {
			return $code;
		}
		// CODE часто = секция; ELEMENT_CODE надёжнее. CODE берём только вместе с признаком карточки.
		if (!empty($_REQUEST['CODE']) && (isset($_REQUEST['ELEMENT_ID']) || isset($_REQUEST['ID']) || self::looksLikeElementDetailPath())) {
			return trim((string) $_REQUEST['CODE']);
		}
		if (!empty($GLOBALS['APPLICATION']) && self::looksLikeElementDetailPath()) {
			$page = (string) $GLOBALS['APPLICATION']->GetCurPage(false);
			$base = basename(rtrim($page, '/'));
			if ($base !== '' && $base !== 'index.php' && !preg_match('/\.(php|html?)$/i', $base)) {
				return urldecode($base);
			}
		}
		return '';
	}

	protected static function looksLikeElementDetailPath(): bool
	{
		$path = '';
		if (!empty($GLOBALS['APPLICATION'])) {
			$path = (string) $GLOBALS['APPLICATION']->GetCurPage(false);
		} elseif (!empty($_SERVER['REQUEST_URI'])) {
			$path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
		}
		// эвристика: глубокий path каталога (не корень /catalog/)
		$parts = array_values(array_filter(explode('/', trim($path, '/'))));
		return count($parts) >= 2;
	}

	protected static function currentUriMatchesElementDetail(int $elementId): bool
	{
		$res = \CIBlockElement::GetList(
			[],
			['IBLOCK_ID' => Config::iblockId(), 'ID' => $elementId, 'CHECK_PERMISSIONS' => 'N'],
			false,
			['nTopCount' => 1],
			['ID', 'DETAIL_PAGE_URL', 'CODE']
		);
		$row = $res->GetNext();
		if (!$row) {
			return false;
		}
		$detail = (string) ($row['DETAIL_PAGE_URL'] ?? '');
		if ($detail === '') {
			return false;
		}
		$cur = self::currentRequestPath();
		$detailPath = (string) parse_url($detail, PHP_URL_PATH);
		if ($detailPath === '') {
			$detailPath = $detail;
		}
		return $cur !== '' && rtrim($cur, '/') === rtrim($detailPath, '/');
	}

	protected static function currentUriMatchesSection(int $sectionId): bool
	{
		$res = \CIBlockSection::GetList(
			[],
			['IBLOCK_ID' => Config::iblockId(), 'ID' => $sectionId, 'CHECK_PERMISSIONS' => 'N'],
			false,
			['ID', 'SECTION_PAGE_URL', 'CODE'],
			['nTopCount' => 1]
		);
		$row = $res->GetNext();
		if (!$row) {
			return false;
		}
		$pageUrl = (string) ($row['SECTION_PAGE_URL'] ?? '');
		if ($pageUrl === '') {
			return false;
		}
		$cur = self::currentRequestPath();
		$path = (string) parse_url($pageUrl, PHP_URL_PATH);
		if ($path === '') {
			$path = $pageUrl;
		}
		return $cur !== '' && rtrim($cur, '/') === rtrim($path, '/');
	}

	protected static function currentRequestPath(): string
	{
		if (!empty($GLOBALS['APPLICATION'])) {
			return (string) $GLOBALS['APPLICATION']->GetCurPage(false);
		}
		if (!empty($_SERVER['REQUEST_URI'])) {
			return (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
		}
		return '';
	}

	public static function applyRobotsOnEpilog(): void
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return;
		}
		global $APPLICATION;
		if (!is_object($APPLICATION)) {
			return;
		}
		$existing = (string) $APPLICATION->GetPageProperty('robots');
		if ($existing !== '' && stripos($existing, 'noindex') !== false) {
			return;
		}
		if (!Loader::includeModule('titlo.relevance') || !Loader::includeModule('iblock')) {
			return;
		}
		$eid = self::guessCurrentElementId();
		if ($eid > 0 && self::isNoindex($eid)) {
			$APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
			return;
		}
		$sid = self::guessCurrentSectionId();
		if ($sid > 0 && self::isSectionNoindex($sid)) {
			$APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
		}
	}

	/**
	 * Страховка: шаблон мог уже вывести index,follow или игнорировать SEO.
	 * robots.txt НЕ трогаем — закрытие страницы = meta robots.
	 *
	 * @param string $content
	 */
	public static function applyRobotsOnBuffer(&$content): void
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
			return;
		}
		if (!is_string($content) || $content === '' || stripos($content, '<html') === false) {
			return;
		}
		if (!Loader::includeModule('titlo.relevance') || !Loader::includeModule('iblock')) {
			return;
		}
		$need = false;
		$eid = self::guessCurrentElementId();
		if ($eid > 0 && self::isNoindex($eid)) {
			$need = true;
		}
		if (!$need) {
			$sid = self::guessCurrentSectionId();
			if ($sid > 0 && self::isSectionNoindex($sid)) {
				$need = true;
			}
		}
		if (!$need) {
			return;
		}

		$meta = '<meta name="robots" content="noindex, nofollow" />';
		$replaced = preg_replace(
			'/<meta\s[^>]*name\s*=\s*["\']robots["\'][^>]*>/i',
			$meta,
			$content,
			1,
			$count
		);
		if (is_string($replaced) && $count > 0) {
			$content = $replaced;
			return;
		}
		$injected = preg_replace('/<\/head>/i', $meta . "\n</head>", $content, 1, $count2);
		if (is_string($injected) && $count2 > 0) {
			$content = $injected;
		}
	}
}
