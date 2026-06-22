<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Ui\Iblock\CategoryValue;

class MassiveEdit implements Form
{
	/** @var ?array */
	private $parentValue;

	public function __construct(array $parentValue = null)
	{
		$this->parentValue = $parentValue;
	}

	public function type()
	{
		return Factory::MASSIVE_EDIT;
	}

	public function payload()
	{
		return [
			'parentValue' => $this->parentValue,
		];
	}

	public function fields()
	{
		return [];
	}

	public function theme()
	{
		return CategoryProvider::THEME_MASSIVE_EDIT;
	}

	public function parentValue(array $fields = null)
	{
		return new CategoryValue\FixedValue($this->parentValue);
	}
}