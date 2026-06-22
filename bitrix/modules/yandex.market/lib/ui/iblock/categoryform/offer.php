<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Bitrix\Main;
use Yandex\Market\Export\Entity;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Ui\Iblock\CategoryValue;

class Offer implements ElementForm
{
	private $property;
	private $offerId;
	private $skuPropertyId;
	private $productId;

	public function __construct(array $property, $offerId, $skuPropertyId, $productId = null)
	{
		$this->property = $property;
		$this->offerId = (int)$offerId;
		$this->skuPropertyId = $skuPropertyId;
		$this->productId = $productId;
	}

	public function type()
	{
		return Factory::OFFER;
	}

	public function elementId()
	{
		return $this->offerId;
	}

	public function payload()
	{
		$result = [
			'offerId' => $this->offerId,
			'skuPropertyId' => $this->skuPropertyId,
		];

		if ($this->productId !== null)
		{
			$result['productId'] = $this->productId;
		}

		return $result;
	}

	public function fields()
	{
		return [
			'sku' => [ "div[id^='layout_PROPx{$this->skuPropertyId}xx']", 'input[type="hidden"]', 'onLookupInputChange' ],
		];
	}

	public function theme()
	{
		return CategoryProvider::THEME_FORM;
	}

	public function parentValue(array $fields = null)
	{
		$productId = isset($fields['sku']) ? (int)$fields['sku'] : $this->productId;

		if ($productId !== null)
		{
			return new CategoryValue\ElementValue($this->skuIblockId(), (int)$productId);
		}

		return (new CategoryValue\OfferValue($this->property['IBLOCK_ID'], $this->offerId))->parent();
	}

	private function skuIblockId()
	{
		$result = Entity\Iblock\Provider::getCatalogIblockId($this->property['IBLOCK_ID']);

		if ($result === null)
		{
			throw new Main\SystemException("cant find sku iblock for {$this->property['IBLOCK_ID']}");
		}

		return $result;
	}
}