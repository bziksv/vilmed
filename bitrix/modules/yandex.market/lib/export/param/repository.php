<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market\Reference\Storage;

class Repository
{
    public static function loadCollection(Storage\Model $parent, array $queryParams)
    {
        $queryParams['order'] = [ 'PARENT_ID' => 'asc', 'ID' => 'ASC' ];
        $queryParams['filter'] = array_diff_key($queryParams['filter'], [
            '=PARENT_ID' => true,
        ]);

        $flatCollection = Collection::load($parent, $queryParams);
        $flatCollection->initChildren();
        $flatCollection->preloadReference();

        return self::makeCollectionTree($flatCollection);
    }

    private static function makeCollectionTree(Collection $flatCollection)
    {
        $valueTreeMap = [];
        $result = new Collection;

        /** @var Model $param */
        foreach ($flatCollection as $param)
        {
            $valueId = (int)$param->getId();
            $parentId = (int)$param->getField('PARENT_ID');
            $parentLevel = $result;

            if (empty($parentId)) // is root
            {
                $parentTree = [];
            }
            else
            {
                if (!isset($valueTreeMap[$parentId])) { continue; }

                $parentTree = $valueTreeMap[$parentId];
            }

            foreach ($parentTree as $parentId)
            {
                /** @var Model $child */
                $child = $parentLevel->getItemById($parentId);

                if ($child === null)
                {
                    $parentLevel = null;
                    break;
                }

                $parentLevel = $child->initChildren();
            }

            if ($parentLevel === null) { continue; }

            $param->setParentCollection($parentLevel);
            $parentLevel->addItem($param);

            $selfTree = $parentTree;
            $selfTree[] = $valueId;

            $valueTreeMap[$valueId] = $selfTree;
        }

        return $result;
    }
    
	/* optimized load for reference PARAM */
	public static function loadByReference($entityType, $primaries, $isCopy = false)
	{
		if (empty($primaries)) { return []; }

		// load rows

		$query = Table::getList([
			'filter' => [ '=ENTITY_TYPE' => $entityType, '=ENTITY_ID' => $primaries ],
			'order' => [ 'PARENT_ID' => 'asc', 'ID' => 'asc' ],
		]);

		$rows = $query->fetchAll();

		if (empty($rows)) { return []; }

		$ids = array_column($rows, 'ID');
		$rows = array_combine($ids, $rows);

		// load external

		$externalDataList = Table::loadExternalReference($ids, [ 'PARAM_VALUE' ], $isCopy);

		foreach ($externalDataList as $id => $externalData)
		{
			$rows[$id] += $externalData;
		}

		// group by parent

		$parentValues = [];

		foreach ($rows as $row)
		{
			$parentId = $row['ENTITY_ID'];

			if (!isset($parentValues[$parentId]))
			{
				$parentValues[$parentId] = [
					'PARAM' => [],
				];
			}

			$parentValues[$parentId]['PARAM'][] = $row;
		}

		// convert to tree

		foreach ($parentValues as &$parentValue)
		{
			$parentValue['PARAM'] = self::toTree($parentValue['PARAM']);

			if ($isCopy)
			{
				$parentValue['PARAM'] = self::clearCopy($parentValue['PARAM']);
			}
		}
		unset($parentValue);

		return $parentValues;
	}

	private static function toTree(array $paramRows)
	{
		$valueTreeMap = [];
		$result = [];

		foreach ($paramRows as $paramRow)
		{
			$valueId = (int)$paramRow['ID'];
			$parentLevel = &$result;

			if (empty($paramRow['PARENT_ID'])) // is root
			{
				$parentTree = [];
			}
			else
			{
				if (!isset($valueTreeMap[$paramRow['PARENT_ID']])) { continue; }

				$parentTree = $valueTreeMap[$paramRow['PARENT_ID']];
			}

			foreach ($parentTree as $parentId)
			{
				if (!isset($parentLevel[$parentId]))
				{
					$parentLevel = null;
					break;
				}

				if (!isset($parentLevel[$parentId]['CHILDREN']))
				{
					$parentLevel[$parentId]['CHILDREN'] = [];
				}

				$parentLevel = &$parentLevel[$parentId]['CHILDREN'];
			}

			if ($parentLevel === null) { continue; }

			$parentLevel[$valueId] = $paramRow;

			$selfTree = $parentTree;
			$selfTree[] = $valueId;

			$valueTreeMap[$valueId] = $selfTree;

			unset($parentLevel);
		}

		return $result;
	}

	private static function clearCopy(array $paramRows)
	{
		foreach ($paramRows as &$paramRow)
		{
			if (!empty($paramRow['CHILDREN']))
			{
				$paramRow['CHILDREN'] = self::clearCopy($paramRow['CHILDREN']);
			}

			$paramRow = array_diff_key($paramRow, [
				'ENTITY_ID' => true,
				'ID' => true,
				'PARENT_ID' => true,
			]);
		}
		unset($paramRow);

		return $paramRows;
	}
}