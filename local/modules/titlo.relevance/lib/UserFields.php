<?php

namespace Titlo\Relevance;

use Bitrix\Main\Loader;

class UserFields
{
	public const PHRASE_FIELD = 'UF_TITLO_PHRASE';
	public const SKIP_FIELD = 'UF_TITLO_PHRASE_SKIP';
	public const PHRASE_AT_FIELD = 'UF_TITLO_PHRASE_AT';
	/** Дата автопроработки карточки (анализ → генерация → сохранение). */
	public const AUTO_AT_FIELD = 'UF_TITLO_AUTO_AT';

	/**
	 * Ensure UF fields for Titlo name-check on catalog elements.
	 */
	public static function ensurePhraseField(int $iblockId = 0): bool
	{
		if (!Loader::includeModule('iblock')) {
			return false;
		}

		$iblockId = $iblockId > 0 ? $iblockId : Config::iblockId();
		if ($iblockId <= 0) {
			return false;
		}

		$okElement = self::ensurePhraseFieldsForEntity('IBLOCK_' . $iblockId . '_ELEMENT');
		$okSection = self::ensurePhraseFieldsForEntity('IBLOCK_' . $iblockId . '_SECTION');
		$okAutoEl = self::ensureAutoAtField('IBLOCK_' . $iblockId . '_ELEMENT');
		$okAutoSec = self::ensureAutoAtField('IBLOCK_' . $iblockId . '_SECTION');

		return $okElement && $okSection && $okAutoEl && $okAutoSec;
	}

	/**
	 * UF фраз для секций (категорий).
	 */
	public static function ensureSectionPhraseField(int $iblockId = 0): bool
	{
		if (!Loader::includeModule('iblock')) {
			return false;
		}
		$iblockId = $iblockId > 0 ? $iblockId : Config::iblockId();
		if ($iblockId <= 0) {
			return false;
		}
		return self::ensurePhraseFieldsForEntity('IBLOCK_' . $iblockId . '_SECTION');
	}

	protected static function ensurePhraseFieldsForEntity(string $entityId): bool
	{
		$okPhrase = self::ensureStringField($entityId);
		$okSkip = self::ensureSkipField($entityId);
		$okAt = self::ensurePhraseAtField($entityId);

		return $okPhrase && $okSkip && $okAt;
	}

	protected static function ensureStringField(string $entityId): bool
	{
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::PHRASE_FIELD,
		])->Fetch();

		if ($exists) {
			return true;
		}

		$oUserType = new \CUserTypeEntity();
		$id = $oUserType->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::PHRASE_FIELD,
			'USER_TYPE_ID' => 'string',
			'XML_ID' => self::PHRASE_FIELD,
			'SORT' => 100,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'S',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => [
				'SIZE' => 50,
				'ROWS' => 1,
				'MIN_LENGTH' => 0,
				'MAX_LENGTH' => 50,
				'DEFAULT_VALUE' => '',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: фраза для релевантности', 'en' => 'Titlo relevance phrase'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Titlo-фраза', 'en' => 'Titlo phrase'],
			'LIST_FILTER_LABEL' => ['ru' => 'Titlo-фраза', 'en' => 'Titlo phrase'],
			'ERROR_MESSAGE' => ['ru' => '', 'en' => ''],
			'HELP_MESSAGE' => [
				'ru' => 'Укороченное название (до 50 символов) для анализатора релевантности Titlo',
				'en' => 'Short phrase (max 50) for Titlo relevance analyzer',
			],
		]);

		return (bool) $id;
	}

	protected static function ensureSkipField(string $entityId): bool
	{
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::SKIP_FIELD,
		])->Fetch();

		if ($exists) {
			return true;
		}

		$oUserType = new \CUserTypeEntity();
		$id = $oUserType->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::SKIP_FIELD,
			'USER_TYPE_ID' => 'boolean',
			'XML_ID' => self::SKIP_FIELD,
			'SORT' => 110,
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
				'LABEL_CHECKBOX' => 'Не прорабатывать название',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: не прорабатывать название', 'en' => 'Titlo: skip name'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Titlo: skip', 'en' => 'Titlo skip'],
			'LIST_FILTER_LABEL' => ['ru' => 'Не прорабатывать', 'en' => 'Skip'],
			'ERROR_MESSAGE' => ['ru' => '', 'en' => ''],
			'HELP_MESSAGE' => [
				'ru' => 'Исключается из очереди проверки названий',
				'en' => 'Exclude from name-check queue',
			],
		]);

		return (bool) $id;
	}

	protected static function ensurePhraseAtField(string $entityId): bool
	{
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::PHRASE_AT_FIELD,
		])->Fetch();

		if ($exists) {
			return true;
		}

		$oUserType = new \CUserTypeEntity();
		$id = $oUserType->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::PHRASE_AT_FIELD,
			'USER_TYPE_ID' => 'datetime',
			'XML_ID' => self::PHRASE_AT_FIELD,
			'SORT' => 105,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'N',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => [
				'DEFAULT_VALUE' => [
					'TYPE' => 'NONE',
					'VALUE' => '',
				],
				'USE_SECOND' => 'Y',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: дата проработки названия', 'en' => 'Titlo: phrase processed at'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Titlo: проработано', 'en' => 'Titlo phrase at'],
			'LIST_FILTER_LABEL' => ['ru' => 'Проработано', 'en' => 'Processed at'],
			'ERROR_MESSAGE' => ['ru' => '', 'en' => ''],
			'HELP_MESSAGE' => [
				'ru' => 'Когда последний раз сохранили короткую фразу (генерация или вручную)',
				'en' => 'When the short phrase was last saved',
			],
		]);

		return (bool) $id;
	}

	protected static function ensureAutoAtField(string $entityId): bool
	{
		$exists = \CUserTypeEntity::GetList([], [
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::AUTO_AT_FIELD,
		])->Fetch();

		if ($exists) {
			return true;
		}

		$oUserType = new \CUserTypeEntity();
		$id = $oUserType->Add([
			'ENTITY_ID' => $entityId,
			'FIELD_NAME' => self::AUTO_AT_FIELD,
			'USER_TYPE_ID' => 'datetime',
			'XML_ID' => self::AUTO_AT_FIELD,
			'SORT' => 120,
			'MULTIPLE' => 'N',
			'MANDATORY' => 'N',
			'SHOW_FILTER' => 'N',
			'SHOW_IN_LIST' => 'Y',
			'EDIT_IN_LIST' => 'Y',
			'IS_SEARCHABLE' => 'N',
			'SETTINGS' => [
				'DEFAULT_VALUE' => [
					'TYPE' => 'NONE',
					'VALUE' => '',
				],
				'USE_SECOND' => 'Y',
			],
			'EDIT_FORM_LABEL' => ['ru' => 'Titlo: дата автопроработки', 'en' => 'Titlo: auto-processed at'],
			'LIST_COLUMN_LABEL' => ['ru' => 'Titlo: авто', 'en' => 'Titlo auto'],
			'LIST_FILTER_LABEL' => ['ru' => 'Автопроработка', 'en' => 'Auto at'],
			'ERROR_MESSAGE' => ['ru' => '', 'en' => ''],
			'HELP_MESSAGE' => [
				'ru' => 'Когда последний раз прошла автоматическая проработка (анализ + описания)',
				'en' => 'When auto relevance + text generation last finished',
			],
		]);

		return (bool) $id;
	}

	/**
	 * Нормализация datetime UF → 'd.m.Y H:i' или ''.
	 *
	 * @param mixed $raw
	 */
	public static function formatPhraseAt($raw): string
	{
		if ($raw === null || $raw === '' || $raw === false) {
			return '';
		}
		if (is_array($raw)) {
			$raw = $raw['VALUE'] ?? reset($raw);
		}
		$s = trim((string) $raw);
		if ($s === '' || $s === '0000-00-00 00:00:00' || $s === '0000-00-00') {
			return '';
		}
		$ts = strtotime($s);
		if ($ts === false) {
			// Bitrix culture: 15.09.2026 22:30:00
			$ts = MakeTimeStamp($s);
		}
		if (!$ts) {
			return $s;
		}
		return date('d.m.Y H:i', (int) $ts);
	}

	public static function isSkipValue($raw): bool
	{
		if (is_bool($raw)) {
			return $raw;
		}
		$v = is_array($raw) ? ($raw['VALUE'] ?? reset($raw)) : $raw;
		return $v === 1 || $v === '1' || $v === true || $v === 'Y' || $v === 'y';
	}
}
