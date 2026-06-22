<?php
namespace Yandex\Market\Component\Molecules;

use Yandex\Market\Export\Entity;

class ProductLink
{
	private $productFields;
	private $sanitizedIblockKeys = [];

	public function __construct(array $productFields)
	{
		$this->productFields = $productFields;
	}

	public function inSelect(array $select)
	{
		if (empty($select)) { return true; }

		foreach ($this->productFields as $productField)
		{
			if (in_array($productField, $select, true)) { return true; }

			$parentName = $productField . '.';

			foreach ($select as $name)
			{
				if (mb_strpos($name, $parentName) === 0)
				{
					return true;
				}
			}
		}

		return false;
	}

	public function sanitizeIblock(array $request, array $setupIblockList, array $iblockFieldMap = [])
	{
		$result = $request;

		foreach ($this->productFields as $productFieldKey)
		{
			if (isset($iblockFieldMap[$productFieldKey]))
			{
				$iblockFieldKey = $iblockFieldMap[$productFieldKey];
				$giftIblockId = isset($result[$iblockFieldKey]) ? (int)$result[$iblockFieldKey] : null;

				if ($giftIblockId === null || $giftIblockId <= 0)
				{
					$giftIblockId = (int)reset($setupIblockList);
				}

				$iblockIdList = $giftIblockId > 0 ? [ $giftIblockId ] : [];
			}
			else
			{
				$iblockIdList = $setupIblockList;
			}

			$iblockIdMap = array_flip($iblockIdList);
			$result[$productFieldKey] = isset($request[$productFieldKey]) ? (array)$request[$productFieldKey] : [];
			$found = [];

			foreach ($result[$productFieldKey] as $collectionProductKey => $collectionProduct)
			{
				$iblockId = isset($collectionProduct['IBLOCK_ID']) ? (int)$collectionProduct['IBLOCK_ID'] : null;

				if ($iblockId > 0 && isset($iblockIdMap[$iblockId]))
				{
					$found[$iblockId] = $collectionProductKey;
				}
				else
				{
					unset($result[$productFieldKey][$collectionProductKey]);
				}
			}

			foreach ($iblockIdList as $iblockId)
			{
				if ($iblockId <= 0 || isset($found[$iblockId])) { continue; }

				if (isset($this->sanitizedIblockKeys[$productFieldKey][$iblockId]))
				{
					$storedKey = $this->sanitizedIblockKeys[$productFieldKey][$iblockId];

					if (!isset($result[$productFieldKey][$storedKey]))
					{
						$result[$productFieldKey][$storedKey] = [
							'IBLOCK_ID' => $iblockId,
						];

						$found[$iblockId] = $storedKey;
						continue;
					}
				}

				$result[$productFieldKey][] = [
					'IBLOCK_ID' => $iblockId,
				];

				end($result[$productFieldKey]);
				$found[$iblockId] = key($result[$productFieldKey]);
			}

			$this->sanitizedIblockKeys[$productFieldKey] = $found;
		}

		return $result;
	}

	public function extend(array $data)
	{
		$result = $data;

		foreach ($this->productFields as $productFieldKey)
		{
			if (empty($result[$productFieldKey])) { continue; }

			foreach ($result[$productFieldKey] as &$product)
			{
				$product['CONTEXT'] = Entity\Iblock\Provider::getContext($product['IBLOCK_ID']);
			}
			unset($product);
		}

		return $result;
	}
}