<?php
namespace Yandex\Market\Component\Molecules;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Export;
use Yandex\Market\Utils;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Reference\Storage;

class ProductFilter
{
	use Concerns\HasMessage;

	private $filterFields;

	public function __construct(array $filterFields)
	{
		$this->filterFields = $filterFields;
	}

	public function sanitizeFilter(array $request, array $fields)
	{
		foreach ($this->filterFields as $name)
		{
			foreach ($this->groupFields($fields, $name) as $field)
			{
				$nameChain = Utils\Field::splitKey($field['FIELD_NAME'], Utils\Field::GLUE_BRACKET);
				$filterName = array_pop($nameChain);

				if ($filterName === 'ID' || empty($nameChain)) { continue; }

				$parentValue = Utils\Field::getChainValue($request, $nameChain);

				if (!is_array($parentValue) || (isset($parentValue[$filterName]) && is_array($parentValue[$filterName]))) { continue; }

				$parentValue[$filterName] = [];
				Utils\Field::setChainValue($request, $nameChain, $parentValue);
			}
		}

		return $request;
	}

	public function validate(Main\Entity\Result $result, array $data, array $fields)
	{
		foreach ($this->filterFields as $name)
		{
			$filterFields = $this->groupFields($fields, $name);
			$hasFilter = false;

			if (empty($filterFields)) { continue; }

			foreach ($filterFields as $filterField)
			{
				$filters = Utils\Field::getChainValue($data, $filterField['FIELD_NAME'], Utils\Field::GLUE_BRACKET);

				if (empty($filters) || !is_array($filters)) { continue; }

				foreach ($filters as $filter)
				{
					$hasFilter = true;
					$hasCondition = false;

					if (!empty($filter['FILTER_CONDITION']))
					{
						foreach ($filter['FILTER_CONDITION'] as $filterCondition)
						{
							if (Export\FilterCondition\Table::isValidData($filterCondition))
							{
								$hasCondition = true;
								break;
							}
						}
					}

					if (!$hasCondition)
					{
						$result->addError(new Market\Error\EntityError(self::getMessage('ERROR_CONDITION_EMPTY', [
							'#FIELD_NAME#' => $filterField['LIST_COLUMN_LABEL'],
						])));
						break;
					}
				}
			}

			if ($hasFilter) { return; }

			$exportAllFields = $this->groupFields($fields, $this->exportAllName($name));

			foreach ($exportAllFields as $exportAllField)
			{
				$exportAll = Utils\Field::getChainValue($data, $exportAllField['FIELD_NAME'], Utils\Field::GLUE_BRACKET);

				if ((string)$exportAll === Storage\Table::BOOLEAN_Y)
				{
					$hasFilter = true;
					break;
				}
			}

			if (!$hasFilter)
			{
				$filterField = reset($filterFields);
				$errorCode = !empty($exportAllFields) ? 'ERROR_FILTER_EMPTY_WITH_ALL' : 'ERROR_FILTER_EMPTY';

				$result->addError(new Market\Error\EntityError(self::getMessage($errorCode, [
					'#FIELD_NAME#' => $filterField['LIST_COLUMN_LABEL'],
				])));
			}
		}
	}

	private function groupFields(array $fields, $name)
	{
		$result = [];

		foreach ($fields as $field)
		{
			if (empty($field['FIELD_GROUP']) || $field['FIELD_GROUP'] !== $name) { continue; }

			$result[] = $field;
		}

		return $result;
	}

	private function exportAllName($name)
	{
		$namePartials = explode('.', $name);
		array_pop($namePartials);
		$namePartials[] = 'EXPORT_ALL';

		return implode('.', $namePartials);
	}
}