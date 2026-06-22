<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

class OfferValue implements CategoryValue
{
	protected $iblockId;
	protected $elementId;

	public function __construct($iblockId, $elementId)
	{
		$this->iblockId = (int)$iblockId;
		$this->elementId = (int)$elementId;
	}

	public function value()
	{
		return PropertyRepository::elementValue($this->iblockId, $this->elementId);
	}

	public function save(array $value = null)
	{
		PropertyRepository::saveElement($this->iblockId, $this->elementId, $value);
	}

	public function parent()
	{
		$product = ElementFetcher::sku($this->iblockId, $this->elementId);

		if ($product === null) { return null; }

		return new ElementValue((int)$product['IBLOCK_ID'], (int)$product['ID']);
	}
}