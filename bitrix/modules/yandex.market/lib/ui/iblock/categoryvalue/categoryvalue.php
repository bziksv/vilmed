<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

interface CategoryValue
{
	/** @return array{CATEGORY: string, PARAMETERS: array}|null */
	public function value();

	/** @param array{CATEGORY: string, PARAMETERS: array}|null $value */
	public function save(array $value = null);

	/** @return CategoryValue|null */
	public function parent();
}