<?php
namespace Yandex\Market\Migration\V2110;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;
use Yandex\Market\Config;
use Yandex\Market\Ui\Iblock as IblockUi;

/** @noinspection PhpUnused */
class OfferCategoryProperty
{
	public function apply()
	{
		if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) { return; }
		if (!$this->wasCreated()) { return; }

		$propertyIblockIds = $this->propertyIblockIds();

		foreach ($propertyIblockIds as $iblockId)
		{
			$iblockId = (int)$iblockId;
			$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

			if ($catalog === false || isset($propertyIblockIds[$catalog['IBLOCK_ID']])) { continue; }

			IblockUi\CategoryProperty::createDefault($catalog['IBLOCK_ID']);
		}

		$this->clearOldOption();
	}

	private function wasCreated()
	{
		$option = (string)\CUserOptions::GetOption(Config::getLangPrefix() . 'EXPORT_MARKET_PROPERTY', 'autoCreateCategory', 'N', 0);

		return ($option === 'Y');
	}

	private function clearOldOption()
	{
		\CUserOptions::DeleteOption(Config::getLangPrefix() . 'EXPORT_MARKET_PROPERTY', 'autoCreateCategory', true);
	}

	private function propertyIblockIds()
	{
		$query = PropertyTable::getList([
			'filter' => [
				'=ACTIVE' => 'Y',
				'=USER_TYPE' => IblockUi\CategoryProperty::USER_TYPE,
			],
			'select' => [ 'IBLOCK_ID' ],
		]);

		return array_column($query->fetchAll(), 'IBLOCK_ID', 'IBLOCK_ID');
	}
}