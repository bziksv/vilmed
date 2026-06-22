<?php
namespace Yandex\Market\Migration\V283;

use Yandex\Market;

class MoveShopSkuToId
{
	public static function apply()
	{
		$shopSkuTags = self::shopSkuTags();
		$offerTags = self::offerTags(array_column($shopSkuTags, 'IBLOCK_LINK_ID'));
		$idAttributes = self::idAttributes(array_column($offerTags, 'ID'));

		foreach ($shopSkuTags as $shopSkuTag)
		{
			$iblockLinkId = (int)$shopSkuTag['IBLOCK_LINK_ID'];

			if (!isset($offerTags[$iblockLinkId])) { continue; }

			$offerTag = $offerTags[$iblockLinkId];

			if (isset($idAttributes[$offerTag['ID']]))
			{
				Market\Export\ParamValue\Table::delete($idAttributes[$offerTag['ID']]['ID']);
			}

			Market\Export\ParamValue\Table::update($shopSkuTag['ID'], [
				'PARAM_ID' => $offerTag['ID'],
				'XML_TYPE' => Market\Export\ParamValue\Table::XML_TYPE_ATTRIBUTE,
				'XML_ATTRIBUTE_NAME' => 'id',
			]);

			Market\Export\Param\Table::delete($shopSkuTag['PARAM_ID']);
		}
	}

	private static function shopSkuTags()
	{
		$query = Market\Export\ParamValue\Table::getList([
			'filter' => [ '=PARAM.XML_TAG' => 'shop-sku' ],
			'select' => [
				'ID',
				'PARAM_ID',
				'IBLOCK_LINK_ID' => 'PARAM.IBLOCK_LINK_ID',
			],
		]);

		return $query->fetchAll();
	}

	private static function offerTags(array $iblockLinkIds)
	{
		if (empty($iblockLinkIds)) { return []; }

		$query = Market\Export\Param\Table::getList([
			'filter' => [
				'=IBLOCK_LINK_ID' => $iblockLinkIds,
				'=XML_TAG' => 'offer',
			],
			'select' => [ 'IBLOCK_LINK_ID', 'ID' ],
		]);

		return Market\Utils\ArrayHelper::columnToKey($query->fetchAll(), 'IBLOCK_LINK_ID');
	}

	private static function idAttributes(array $paramIds)
	{
		if (empty($paramIds)) { return []; }

		$query = Market\Export\ParamValue\Table::getList([
			'filter' => [
				'=PARAM_ID' => $paramIds,
				'=XML_TYPE' => Market\Export\ParamValue\Table::XML_TYPE_ATTRIBUTE,
				'=XML_ATTRIBUTE_NAME' => 'id',
			],
		]);

		return Market\Utils\ArrayHelper::columnToKey($query->fetchAll(), 'PARAM_ID');
	}
}