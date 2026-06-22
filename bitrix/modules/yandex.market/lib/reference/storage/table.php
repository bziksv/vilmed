<?php
namespace  Yandex\Market\Reference\Storage;

use Bitrix\Main;
use Yandex\Market;

abstract class Table extends Main\Entity\DataManager
{
	const BOOLEAN_Y = '1';
	const BOOLEAN_N = '0';

	public static function getClassName()
	{
		return '\\' . static::class;
	}

	public static function createIndexes(Main\DB\Connection $connection)
	{
		// nothing by default
	}

	public static function isValidData($data)
	{
		return true;
	}

	public static function addBatch(array $dataList, $updateOnDuplicate = false)
	{
		(new Batch\InsertBatch(static::class))->run($dataList, $updateOnDuplicate);

		return new Main\Entity\AddResult();
	}

	public static function add(array $data)
	{
		return self::addExtended($data);
	}

	public static function addExtended(array $data)
	{
		$reference = static::saveExtractReference($data);
		$data = static::convertNullForSave($data, true);
		$addResult = parent::add($data);

		if ($addResult->isSuccess())
		{
			static::saveApplyReference($addResult->getId(), $reference);
			static::onAfterSave($addResult->getId());
		}

		return $addResult;
	}

	public static function updateBatch(array $parameters, array $data)
	{
		(new Batch\UpdateBatch(static::class))->run($parameters, $data);

		return new Main\Entity\UpdateResult();
	}

	public static function update($primary, array $data)
	{
		return static::updateExtended($primary, $data);
	}

	public static function updateExtended($primary, array $data)
	{
		$reference = static::saveExtractReference($data);

		static::onBeforeSave($primary);

		if (!empty($data))
		{
			$data = static::convertNullForSave($data);
			$updateResult = parent::update($primary, $data);
		}
		else
		{
			$updateResult = new Main\Entity\UpdateResult();

			static::normalizePrimary($primary, $data);
			$updateResult->setPrimary($primary);
		}

		if ($updateResult->isSuccess())
		{
			static::saveApplyReference($updateResult->getId(), $reference);
			static::onAfterSave($updateResult->getId());
		}

		return $updateResult;
	}

	public static function deleteBatch(array $parameters = [])
	{
		(new Batch\DeleteBatch(static::class))->run($parameters);

		return new Main\Entity\DeleteResult();
	}

	public static function delete($primary)
	{
		return self::deleteExtended($primary);
	}

	public static function deleteExtended($primary)
	{
		$id = static::getPrimaryId($primary);

		static::onBeforeRemove($id);

		$delResult = parent::delete($primary);

		// delete connected data
		if ($delResult->isSuccess())
		{
			static::deleteReference($id);
		}

		return $delResult;
	}
	
	public static function loadExternalReference($primaryList, $select = null, $isCopy = false)
	{
		$primaryList = (array)$primaryList;
		$result = [];

		if (!empty($primaryList))
		{
			$referenceList = static::getReference($primaryList);

			foreach ($referenceList as $field => $referenceConfig)
			{
				if (empty($select) || in_array($field, $select, true))
				{
					/** @var Table $referenceTable */
					$referenceTable = $referenceConfig['TABLE'];
					$referenceLinkField = $referenceConfig['LINK_FIELD'];
					$referenceEntity = $referenceTable::getEntity();
					$referencePrimaries = $referenceEntity->getPrimaryArray();
					$referencePrimariesWithoutLink = array_diff($referencePrimaries, (array)$referenceLinkField);
					$referencePrimaryField = count($referencePrimariesWithoutLink) === 1 ? reset($referencePrimariesWithoutLink) : 'ID';
					$isReferenceLinkFieldMultiple = is_array($referenceLinkField);
					$referenceFilter = static::makeReferenceLinkFilter($referenceConfig['LINK']);
					$rowList = [];

					$queryParams = [
						'filter' => $referenceFilter,
						'select' => [ '*' ]
					];

					if (isset($referenceConfig['ORDER']))
					{
						$queryParams['order'] = $referenceConfig['ORDER'];
					}

					if ($isReferenceLinkFieldMultiple)
					{
						$queryParams['select'] = array_merge($queryParams['select'], $referenceLinkField);
					}
					else
					{
						$queryParams['select'][] = $referenceLinkField;
					}

					$queryRows = $referenceTable::getList($queryParams);

					while ($row = $queryRows->fetch())
					{
						if ($isReferenceLinkFieldMultiple || isset($row[$referenceLinkField]))
						{
							$rowList[$row[$referencePrimaryField]] = $row;
						}
					}

					// load reference values

					if (!empty($rowList))
					{
						$externalDataList = $referenceTable::loadExternalReference(array_keys($rowList), null, $isCopy);

						foreach ($externalDataList as $rowId => $externalData)
						{
							$rowList[$rowId] += $externalData;
						}
					}

					// build result

					foreach ($rowList as $row)
					{
						$parentPrimary = '';

						if ($isReferenceLinkFieldMultiple)
						{
							foreach ($referenceLinkField as $referenceLinkFieldPart)
							{
								$parentPrimary .= ($parentPrimary === '' ? '' : ':') . $row[$referenceLinkFieldPart];

								if ($isCopy) { unset($row[$referenceLinkFieldPart]); }
							}
						}
						else
						{
							$parentPrimary = $row[$referenceLinkField];

							if ($isCopy) { unset($row[$referenceLinkField]); }
						}

						if (!isset($result[$parentPrimary]))
						{
							$result[$parentPrimary][$field] = [];
						}

						if ($isCopy) { unset($row['ID']); }

						$result[$parentPrimary][$field][] = $row;
					}
				}
			}
		}

		return $result;
	}

	public static function saveExtractReference(array &$data)
	{
		$referenceList = static::getReference();
		$result = [];

		foreach ($referenceList as $referenceField => $reference)
		{
			if (array_key_exists($referenceField, $data))
			{
				$result[$referenceField] = $data[$referenceField];
				unset($data[$referenceField]);
			}
		}

		return $result;
	}

	protected static function saveApplyReference($primary, $fields)
	{
		if (empty($fields)) { return; }

		foreach (static::getReference($primary) as $referenceField => $referenceConfig)
		{
			if (isset($referenceConfig['UPDATABLE']) && $referenceConfig['UPDATABLE'] === false) { continue; }
			if (!array_key_exists($referenceField, $fields)) { continue; }

			/** @var class-string<Table> $referenceTable */
			$referenceTable = $referenceConfig['TABLE'];
			$referenceEntity = $referenceTable::getEntity();
			$referencePrimaries = $referenceEntity->getPrimaryArray();
			$referencePrimariesWithoutLink = array_diff($referencePrimaries, (array)$referenceConfig['LINK_FIELD']);
			$referencePrimaryField = count($referencePrimariesWithoutLink) === 1 ? reset($referencePrimariesWithoutLink) : 'ID';
			$dataList = is_array($fields[$referenceField]) ? $fields[$referenceField] : [];
			$foundRowIds = [];

			// update exist and delete not present

			$idToDataKeyMap = [];

			foreach ($dataList as $dataKey => $data)
			{
				if (!empty($data[$referencePrimaryField]) && $referenceTable::isValidData($data))
				{
					$rowId = $data[$referencePrimaryField];
					$idToDataKeyMap[$rowId] = $dataKey;
				}
			}

			$linkFilter = static::makeReferenceLinkFilter($referenceConfig['LINK']);

			if (!empty($idToDataKeyMap) && count($referencePrimaries) === 1)
			{
				$linkFilter = [
					'LOGIC' => 'OR',
					$linkFilter,
					[ '=' . $referencePrimaryField => array_keys($idToDataKeyMap) ],
				];
			}

			$queryExistRows = $referenceTable::getList([
				'filter' => $linkFilter,
				'select' => [ $referencePrimaryField ],
			]);

			while ($existRow = $queryExistRows->fetch())
			{
				$existRowFullPrimary = [];

				foreach ($referencePrimaries as $fieldName)
				{
					if (isset($existRow[$fieldName]))
					{
						$existRowFullPrimary[$fieldName] = $existRow[$fieldName];
					}
					else if (isset($referenceConfig['LINK'][$fieldName]))
					{
						$existRowFullPrimary[$fieldName] = $referenceConfig['LINK'][$fieldName];
					}
				}

				if (isset($idToDataKeyMap[$existRow[$referencePrimaryField]]))
				{
					$foundRowIds[$existRow[$referencePrimaryField]] = true;

					$dataKey = $idToDataKeyMap[$existRow[$referencePrimaryField]];
					$data = $dataList[$dataKey] + $referenceConfig['LINK'];

					unset($data[$referencePrimaryField]);

					$referenceTable::update($existRowFullPrimary, $data);
				}
				else
				{
					$referenceTable::delete($existRowFullPrimary);
				}
			}

			// add new

			foreach ($dataList as $data)
			{
				if (!$referenceTable::isValidData($data)) { continue; }

				if (isset($data[$referencePrimaryField]))
				{
					if (isset($foundRowIds[$data[$referencePrimaryField]])) { continue; }

					if ($referencePrimaryField === $referenceEntity->getAutoIncrement())
					{
						unset($data[$referencePrimaryField]);
					}
				}

				if (isset($referenceConfig['LINK']))
				{
					$data += $referenceConfig['LINK'];
				}

				$referenceTable::add($data);
			}
		}
	}

	protected static function deleteReference($primary)
	{
		$referenceList = static::getReference($primary);

		foreach ($referenceList as $referenceConfig)
		{
			if (isset($referenceConfig['DELETABLE']) && $referenceConfig['DELETABLE'] === false) { continue; }

			/** @var Table $referenceTable */
			$referenceTable = $referenceConfig['TABLE'];
			$referenceEntity = $referenceTable::getEntity();
			$referencePrimaries = $referenceEntity->getPrimaryArray();

			$queryExistRows = $referenceTable::getList([
				'filter' => static::makeReferenceLinkFilter($referenceConfig['LINK']),
				'select' => $referencePrimaries,
			]);

			while ($existRow = $queryExistRows->fetch())
			{
				$existPrimary = [];

				foreach ($referencePrimaries as $fieldName)
				{
					$existPrimary[$fieldName] = $existRow[$fieldName];
				}

				$referenceTable::delete($existPrimary);
			}
		}
	}

	protected static function onBeforeSave($primary)
	{
		// nothing
	}

	protected static function onAfterSave($primary)
	{
		// nothing
	}

	protected static function onBeforeRemove($primary)
    {
        // nothing
    }

	/**
	 * Ключ = Поле связи
	 * Значение = Массив LINK_FIELD => Указатель на текущую сущность, LINK => Поля для связи, TABLE => Table::class
	 *
	 * @param int|int[]|null $primary
	 *
	 * @return array
	 */
	public static function getReference($primary = null)
	{
		return [];
	}

	public static function makeReferenceLinkFilter($link)
	{
		$result = [];

		foreach ($link as $field => $value)
		{
			if ($field === 'LOGIC')
			{
				$result[$field] = $value;
			}
			else if (!is_numeric($field))
			{
				$result['=' . $field] = $value;
			}
			else if (is_array($value))
			{
				$result[$field] = static::makeReferenceLinkFilter($value);
			}
			else
			{
				$result[$field] = $value;
			}
		}

		return $result;
	}

	/**
	 * Описание полей сущности в формате USER_FIELD_MANAGER
	 *
	 * @return array
	 */
	public static function getMapDescription()
	{
		Market\Utils\MessageRegistry::getModuleInstance()->load(static::class);

		$entity = static::getEntity();
		$referenceList = static::getReference();
		$result = [];

		foreach ($entity->getFields() as $field)
		{
			$fieldName = $field->getName();
			$userField = [];
			$userType = null;

			if (isset($result[$fieldName])) { continue; } // reference one to one conflict

			switch (true)
			{
				case ($field instanceof Main\Entity\EnumField): // enum

					$userType = 'enumeration';
					$userField['VALUES'] = [];
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

					foreach ($field->getValues() as $option)
					{
						$userField['VALUES'][] = [
							'ID' => $option,
							'VALUE' => static::getFieldEnumTitle($fieldName, $option, $field)
						];
					}

				break;

				case ($field instanceof Main\Entity\DatetimeField): // datetime

					$userType = 'datetime';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\DateField): // date

					$userType = 'date';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\IntegerField): // int

					$userType = 'integer';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\FloatField): // double

					$userType = 'double';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\StringField): // string
				case ($field instanceof Main\Entity\ExpressionField): // expression

					$userType = 'string';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\BooleanField): // boolean

					$userType = 'boolean';
					$userField['SETTINGS'] = [
						'DEFAULT_VALUE' => $field->getDefaultValue()
					];

				break;

				case ($field instanceof Main\Entity\ReferenceField):

					$userType = 'reference';
					$userField['MULTIPLE'] = isset($referenceList[$fieldName]) ? 'Y' : 'N';
					$userField['SETTINGS']  = [
						'DATA_CLASS' => $field->getRefEntityName()
					];

				break;
			}

			if (!isset($userType)) { continue; }

			$userField += [
				'USER_TYPE' => Market\Ui\UserField\Manager::getUserType($userType),
				'FIELD_NAME' => $fieldName,
				'LIST_COLUMN_LABEL' => $field->getTitle(),
				'HELP_MESSAGE' => Main\Localization\Loc::getMessage($field->getLangCode() . '_HELP_MESSAGE'),
				'MANDATORY' => (method_exists($field, 'isRequired') && $field->isRequired() ? 'Y' : 'N'),
				'MULTIPLE' => 'N',
				'EDIT_IN_LIST' => (method_exists($field, 'isAutocomplete') && $field->isAutocomplete() ? 'N' : 'Y')
			];

			$result[$fieldName] = $userField;
		}

		return $result;
	}

	public static function migrate(Main\DB\Connection $connection)
	{
		$entity = static::getEntity();

		Market\Migration\StorageFacade::addNewFields($connection, $entity);
	}

	public static function getScalarMap()
	{
		$result = [];
		$map = static::getMap();

		foreach ($map as $field)
		{
			if ($field instanceof Main\Entity\ScalarField || $field instanceof Main\Entity\ExpressionField)
			{
				$result[] = $field->getName();
			}
		}

		return $result;
	}

	public static function getName()
	{
		$langKey = static::getLangKey();

		return Market\Config::getLang($langKey);
	}

	public static function getLangKey()
	{
		return 'UNKNOWN';
	}

	public static function getFieldEnumTitle($fieldName, $optionValue, Main\Entity\Field $field = null)
	{
		$result = null;

		if ($field === null)
		{
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$field = static::getEntity()->getField($fieldName);
		}

		if ($field)
		{
			$fieldEnumLangKey = $field->getLangCode() . '_ENUM_';
			$optionValueLangKey = str_replace(['.', ' ', '-'], '_', $optionValue);
			$optionValueLangKey = Market\Data\TextString::toUpper($optionValueLangKey);

			$result = Main\Localization\Loc::getMessage($fieldEnumLangKey . $optionValueLangKey);
		}

		if ($result === null)
		{
			$result = $optionValue;
		}

		return $result;
	}

	protected static function convertNullForSave($data, $isAdd = false)
	{
		$result = $data;

		foreach ($data as $fieldName => $fieldValue)
		{
			if ($fieldValue !== null)
			{
				// nothing
			}
			else if ($isAdd)
			{
				unset($result[$fieldName]);
			}
			else
			{
				$result[$fieldName] = '';
			}
		}

		return $result;
	}

	protected static function getPrimaryId($primary)
	{
		if (is_array($primary) && count($primary) === 1)
		{
			return end($primary);
		}

		return $primary;
	}
}