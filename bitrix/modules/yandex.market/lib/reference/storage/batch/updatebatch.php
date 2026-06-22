<?php
namespace Yandex\Market\Reference\Storage\Batch;

use Bitrix\Main;

class UpdateBatch
{
	/** @var class-string<Main\Entity\DataManager> */
	private $dataClass;

	public function __construct($dataClass)
	{
		$this->dataClass = $dataClass;
	}

	public function run(array $parameters, array $data)
	{
		$dataClass = $this->dataClass;
		$query = $this->createQuery($dataClass, $parameters);
		$selectSql = $query->getQuery();

		if (!preg_match('/^SELECT\s.*?\sFROM(\s.*?)(\s(?:LEFT |RIGHT |INNER )?JOIN\s.*?)?(\sWHERE\s.*?)?$/si', $selectSql, $match))
		{
			throw new Main\SystemException('invalid updateBatch query');
		}

		$entity = $dataClass::getEntity();
		$connection = $entity->getConnection();
		$helper = $connection->getSqlHelper();

		$tableName = $entity->getDBTableName();
		$tableAlias = $helper->quote($query->getInitAlias());

		$dataReplacedColumn = $this->replaceFieldName($entity, $data);
		$update = $helper->prepareUpdate($tableName, $dataReplacedColumn);
		$update[0] = $tableAlias . '.' . str_replace(', ', ', ' . $tableAlias . '.', $update[0]);

		$sql = 'UPDATE ' . $match[1] . $match[2] . ' SET ' . $update[0] . $match[3];

		$connection->queryExecute($sql, $update[1]);
	}

	private function createQuery($dataClass, array $parameters)
	{
		/** @var class-string<Main\Entity\DataManager> $dataClass */
		$query = $dataClass::query();

		foreach ($parameters as $name => $value)
		{
			if ($name === 'filter')
			{
				$query->setFilter($value);
			}
			else if ($name === 'runtime')
			{
				foreach ($value as $referenceName => $referenceInfo)
				{
					$query->registerRuntimeField($referenceName, $referenceInfo);
				}
			}
			else
			{
				throw new Main\ArgumentException("Unknown parameter: {$name}", $name);
			}
		}

		return $query;
	}

	private function replaceFieldName(Main\Entity\Base $entity, array $data)
	{
		$newData = [];

		foreach ($data as $fieldName => $value)
		{
			$newData[$entity->getField($fieldName)->getColumnName()] = $value;
		}

		return $newData;
	}
}