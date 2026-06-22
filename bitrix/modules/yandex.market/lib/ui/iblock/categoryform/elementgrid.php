<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryValue;
use Yandex\Market\Ui\Iblock\CategoryProvider;

class ElementGrid implements ElementForm
{
	private $property;
	private $elementId;

	public function __construct(array $property, $elementId)
	{
		$this->property = $property;
		$this->elementId = (int)$elementId;
	}

	public function type()
	{
		return Factory::ELEMENT_GRID;
	}

	public function elementId()
	{
		return $this->elementId;
	}

	public function payload()
	{
		return [
			'elementId' => $this->elementId,
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
		return CategoryValue\MemoPool::get(new CategoryValue\ElementValue($this->property['IBLOCK_ID'], $this->elementId))
             ->parent();
	}
}