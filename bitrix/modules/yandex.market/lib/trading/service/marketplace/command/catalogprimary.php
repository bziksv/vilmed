<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command;

use Yandex\Market\Catalog\Run\Storage\OfferTable;

class CatalogPrimary
{
	public function exported(array $skus, $catalogId)
	{
		$catalogId = (int)$catalogId;

		if (empty($skus) || $catalogId <= 0) { return []; }

		$result = [];

		$query = OfferTable::getList([
			'filter' => [
				'=CATALOG_ID' => $catalogId,
				'=SKU' => $skus,
				'=STATUS' => OfferTable::STATUS_SUCCESS,
			],
			'select' => [ 'ELEMENT_ID', 'SKU' ],
		]);

		while ($row = $query->fetch())
		{
			$result[$row['SKU']][] = (int)$row['ELEMENT_ID'];
		}

		return $result;
	}
}