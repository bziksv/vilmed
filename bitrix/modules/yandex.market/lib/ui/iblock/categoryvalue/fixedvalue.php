<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Main;

class FixedValue implements CategoryValue
{
	/** @var ?array */
	protected $value;

	public function __construct(array $value = null)
	{
		$this->value = $value;
	}

	public function value()
	{
		return $this->value;
	}

	public function save(array $value = null)
	{
		throw new Main\NotSupportedException('Fixed value not supported saving');
	}

	public function parent()
	{
		return null;
	}
}