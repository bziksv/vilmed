<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryProvider;

class NullForm implements Form
{
	public function type()
	{
		return Factory::NULL_FORM;
	}

	public function payload()
	{
		return [];
	}

	public function fields()
	{
		return [];
	}

	public function theme()
	{
		return CategoryProvider::THEME_FORM;
	}

	public function parentValue(array $fields = null)
	{
		return null;
	}
}