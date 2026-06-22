<?php
namespace Yandex\Market\Component\Molecules;

use Yandex\Market\Config;
use Yandex\Market\Ui\UserField\Helper\Field;

class CalculatedFields
{
	private $fields;
	private $messagePrefix;

	public function __construct(array $fields, $messagePrefix)
	{
		$this->fields = $fields;
		$this->messagePrefix = $messagePrefix;
	}

	public function getFields()
	{
		$result = [];

		foreach ($this->fields as $name => $field)
		{
			$field += [
				'NAME' => Config::getLang($this->messagePrefix . 'FIELD_' . $name),
				'SORTABLE' => false,
				'FILTERABLE' => !empty($field['QUERY_FILTER']),
				'SELECTABLE' => !empty($field['BATCH_LOADER']) || !empty($field['ITEM_LOADER']),
			];
			$field = Field::extend($field, $name);

			$result[$name] = $field;
		}

		return $result;
	}

	public function queryParameters(array $queryParameters)
	{
		list($common, $select) = $this->splitQuerySelect($queryParameters);
		$common = $this->extendQueryFilter($common);
		$common = $this->removeQueryOrder($common);

		return [ $common, $select ];
	}

	private function splitQuerySelect(array $common)
	{
		if (empty($common['select']))
		{
			return [ $common, array_keys($this->fields) ];
		}

		$calculated = [];

		foreach ($this->fields as $name => $field)
		{
			$index = array_search($name, $common['select'], true);

			if ($index === false) { continue; }

			$calculated[] = $name;
			unset($common['select'][$index]);

			if (!empty($field['USES']))
			{
				foreach ($field['USES'] as $dependencyName)
				{
					if (in_array($dependencyName, $common['select'], true)) { continue; }

					$common['select'][] = $dependencyName;
				}
			}
		}

		return [ $common, $calculated ];
	}

	private function extendQueryFilter(array $queryParameters)
	{
		if (empty($queryParameters['filter'])) { return $queryParameters; }

		$operationCompiler = new \CSQLWhere();

		foreach ($queryParameters['filter'] as $name => $filter)
		{
			if (is_numeric($name)) { continue; }

			$operation = $operationCompiler->MakeOperation($name);

			if (!isset($this->fields[$operation['FIELD']])) { continue; }

			unset($queryParameters['filter'][$name]);

			$field = $this->fields[$operation['FIELD']];

			if (!isset($field['QUERY_FILTER']) || !is_callable($field['QUERY_FILTER'])) { continue; }

			$compare = \CSQLWhere::getOperationByCode($operation['OPERATION']);
			$queryParameters['filter'][] = call_user_func($field['QUERY_FILTER'], $filter, $compare);
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

	public function extendRows(array $rows, array $select)
	{
		if (empty($select)) { return $rows; }

		foreach ($this->fields as $name => $field)
		{
			if (!in_array($name, $select, true)) { continue; }

			if (isset($field['BATCH_LOADER']))
			{
				$rows = call_user_func($field['BATCH_LOADER'], $rows);
				continue;
			}

			if (isset($field['ITEM_LOADER']))
			{
				foreach ($rows as &$row)
				{
					$row[$name] = call_user_func($field['ITEM_LOADER'], $row);
				}
				unset($row);
			}
		}

		return $rows;
	}
}