<?php
namespace Yandex\Market\Ui\UserField;

use Bitrix\Main;

class ReferenceType extends EnumerationType
{
	public static function GetList($arUserField)
	{
		static $cache = [];

		$dataClass = static::getDataClass($arUserField);

		if ($dataClass === null)
		{
			$values = [];
		}
		else if (isset($cache[$dataClass]))
		{
			$values = $cache[$dataClass];
		}
		else
		{
			$values = static::fetchList($dataClass, $arUserField);
			$cache[$dataClass] = $values;
		}

		if (isset($arUserField['SETTINGS']['INCLUDE_VALUES']))
		{
			$values = static::applyValuesIncludeFilter(
				$values,
				$arUserField['SETTINGS']['INCLUDE_VALUES'],
				!empty($arUserField['SETTINGS']['INCLUDE_INVERSE'])
			);
		}

		$result = new \CDBResult();
		$result->InitFromArray($values);

		return $result;
	}

	protected static function fetchList($dataClass, array $userField)
	{
		$values = [];
		$select = [ 'ID' ];
		$nameField = isset($userField['SETTINGS']['NAME']) ? $userField['SETTINGS']['NAME'] : 'NAME';
		$nameTemplate = null;

		if (is_array($nameField))
		{
			$nameTemplate = (string)array_shift($nameField);
			array_push($select, ...$nameField);
		}
		else
		{
			$select[] = $nameField;
		}

		/** @var Main\Entity\DataManager $dataClass */
		$query = $dataClass::getList([
			'filter' => static::fetchFilter($userField),
			'select' => $select,
		]);

		while ($row = $query->fetch())
		{
			if ($nameTemplate !== null)
			{
				$nameVariables = [];

				foreach ($nameField as $fieldName)
				{
					$nameVariables[] = isset($row[$fieldName]) ? (string)$row[$fieldName] : '';
				}

				$name = sprintf($nameTemplate, ...$nameVariables);
			}
			else
			{
				$name = sprintf('[%s] %s', $row['ID'], $row[$nameField]);
			}

			$values[] = [
				'ID' => $row['ID'],
				'VALUE' => $name,
			];
		}

		return $values;
	}

	protected static function fetchFilter(array $userField)
	{
		return !empty($userField['SETTINGS']['FILTER']) ? $userField['SETTINGS']['FILTER'] : [];
	}

	protected static function getDataClass($userField)
	{
		$result = null;

		if (isset($userField['SETTINGS']['DATA_CLASS']))
		{
			$result = Main\Entity\Base::normalizeEntityClass($userField['SETTINGS']['DATA_CLASS']);
		}

		return $result;
	}

	protected static function applyValuesIncludeFilter($values, $include, $inverse = false)
	{
		$includeMap = array_flip($include);

		foreach ($values as $valueKey => $value)
		{
			$isIncluded = isset($includeMap[$value['ID']]);

			if ($isIncluded === $inverse)
			{
				unset($values[$valueKey]);
			}
		}

		return $values;
	}
}