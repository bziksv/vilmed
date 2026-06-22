<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryValue\CategoryValue;

interface Form
{
	/** @return string */
	public function type();

	/** @return array<string, int|string> */
	public function payload();

	/** @return array<string, string[]|string> */
	public function fields();

	/** @return string */
	public function theme();

	/** @return CategoryValue|null */
	public function parentValue(array $fields = null);
}