<?php
namespace Yandex\Market\Component\Molecules;

use Yandex\Market\Config;
use Yandex\Market\Ui\UserField\Helper\Field;
use Yandex\Market\Utils\ArrayHelper;

class CompoundFields
{
	private $fields;
	private $messagePrefix;

	public function __construct(array $fields, $messagePrefix)
	{
		$this->fields = $fields;
		$this->messagePrefix = $messagePrefix;
	}

	public function getFields(array $fields)
	{
		$result = [];

		foreach ($this->fields as $name => $field)
		{
			$field = Field::extend($field + [
				'TYPE' => 'compound',
				'NAME' => Config::getLang($this->messagePrefix . 'FIELD_' . $name),
				'FILTERABLE' => false,
				'SORTABLE' => false,
				'SELECTABLE' => true,
			], $name);
			$field['FIELDS'] = $this->compoundFields($field['FIELDS'], $fields + $result);

			$result[$name] = $field;
		}

		return $result;
	}

	private function compoundFields(array $children, array $fields)
	{
		$result = [];

		foreach ($children as $name => $field)
		{
			if (is_string($field))
			{
				if (!isset($fields[$field])) { continue; }

				$result[$field] = $fields[$field];
				continue;
			}

			$result[$name] = Field::extend($field, $name);
		}

		return $result;
	}

	public function queryParameters(array $queryParameters)
	{
		$queryParameters = $this->querySelect($queryParameters);
		$queryParameters = $this->queryFilter($queryParameters);
		$queryParameters = $this->removeQueryOrder($queryParameters);

		return $queryParameters;
	}

	private function querySelect(array $queryParameters)
	{
		if (empty($queryParameters['select'])) { return $queryParameters; }

		foreach ($this->fields as $name => $field)
		{
			$selectIndex = array_search($name, $queryParameters['select'], true);

			if ($selectIndex === false) { continue; }

			unset($queryParameters['select'][$selectIndex]);

			foreach ($field['FIELDS'] as $childField)
			{
				if (!is_array($childField))
				{
					$childName = $childField;
				}
				else if (isset($childField['LINK_FIELD']))
				{
					$childName = $childField['LINK_FIELD'];
				}
				else
				{
					continue;
				}

				if (in_array($childName, $queryParameters['select'], true)) { continue; }

				$queryParameters['select'][] = $childName;
			}

			if (!empty($field['FILTER']))
			{
				foreach ($field['FILTER'] as $filter)
				{
					if (!is_array($filter)) { continue; }

					foreach ($filter as $filterField => $filterValue)
					{
						if (in_array($filterField, $queryParameters['select'], true)) { continue; }

						$queryParameters['select'][] = $filterField;
					}
				}
			}
		}

		return $queryParameters;
	}

	private function queryFilter(array $queryParameters)
	{
		if (empty($queryParameters['filter'])) { return $queryParameters; }

		$operationCompiler = new \CSQLWhere();

		foreach ($queryParameters['filter'] as $name => $filter)
		{
			if (is_numeric($name)) { continue; }

			$operation = $operationCompiler->MakeOperation($name);

			if (!isset($this->fields[$operation['FIELD']])) { continue; }

			unset($queryParameters['filter'][$name]);

			$operationCompare = \CSQLWhere::getOperationByCode($operation['OPERATION']);
			$field = $this->fields[$operation['FIELD']];
			$newFilter = [];

			foreach ((array)$filter as $filterItem)
			{
				if (!is_string($filterItem)) { continue; }

				list($filterType, $filterId) = explode(':', $filterItem, 2);

				if (!isset($field['FIELDS'][$filterType])) { continue; }

				$childName = isset($field['FIELDS'][$filterType]['LINK_FIELD']) ? $field['FIELDS'][$filterType]['LINK_FIELD'] : $filterType;

				if (!isset($newFilter[$filterType]))
				{
					$newFilter[$filterType] = isset($field['FILTER'][$filterType]) && is_array($field['FILTER'][$filterType])
						? ArrayHelper::prefixKeys($field['FILTER'][$filterType], $operationCompare)
						: [];
					$newFilter[$filterType][$operationCompare . $childName] = [];
				}

				$newFilter[$filterType][$operationCompare . $childName][] = $filterId;
			}

			if (empty($newFilter)) { continue; }

			if (count($newFilter) > 1)
			{
				$queryParameters['filter'][] = [ 'LOGIC' => 'OR' ] + array_values($newFilter);
			}
			else
			{
				$queryParameters['filter'][] = reset($newFilter);
			}
		}

		return $queryParameters;
	}

	private function removeQueryOrder(array $queryParameters)
	{
		if (!empty($queryParameters['order']))
		{
			$queryParameters['order'] = array_diff_key($queryParameters['order'], $this->fields);
		}

		return $queryParameters;
	}
}