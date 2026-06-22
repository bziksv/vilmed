<?php
namespace Yandex\Market\Reference\Storage\Batch;

use Bitrix\Main;

class DeleteBatch
{
	/** @var class-string<Main\Entity\DataManager> */
	private $dataClass;

	public function __construct($dataClass)
	{
		$this->dataClass = $dataClass;
	}

	public function run(array $parameters)
	{
		$dataClass = $this->dataClass;
		$query = $this->createQuery($dataClass, $parameters);

		$selectSql = $query->getQuery();

		if (!preg_match('/^SELECT\s.*?\s(FROM\s.*)$/si', $selectSql, $match))
		{
			throw new Main\SystemException('invalid deleteBatch query');
		}

		$entity = $dataClass::getEntity();
		$connection = $entity->getConnection();
		$helper = $connection->getSqlHelper();
		$sql = 'DELETE ' . $helper->quote($query->getInitAlias()) . ' ' . $match[1];

		$connection->queryExecute($sql);
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
}