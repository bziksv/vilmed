<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryValue;
use Yandex\Market\Ui\Iblock\CategoryProvider;

class OfferGrid implements ElementForm
{
	private $property;
	private $offerId;

	public function __construct(array $property, $offerId)
	{
		$this->property = $property;
		$this->offerId = (int)$offerId;
	}

	public function type()
	{
		return Factory::OFFER_GRID;
	}

	public function elementId()
	{
		return $this->offerId;
	}

	public function payload()
	{
		return [
			'offerId' => $this->offerId,
		];
	}

	public function fields()
	{
		return [];
	}

	public function theme()
	{
		return CategoryProvider::THEME_GRID;
	}

	public function parentValue(array $fields = null)
	{
		$parent = (new CategoryValue\OfferValue($this->property['IBLOCK_ID'], $this->offerId))->parent();

		if ($parent === null) { return null; }

		return CategoryValue\MemoPool::get($parent);
	}
}