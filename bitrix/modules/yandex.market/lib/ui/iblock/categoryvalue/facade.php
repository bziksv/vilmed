<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Yandex\Market\Ui\Iblock\CategoryProvider;

class Facade
{
	public static function compile(CategoryValue $valueLoader = null)
	{
		$result = null;

		while ($valueLoader !== null)
		{
			$value = $valueLoader->value();
			$result = CategoryProvider::mergeValue($result, $value);

			if (!empty($result['CATEGORY']))
			{
				return $result;
			}

			$valueLoader = $valueLoader->parent();
		}

		return $result;
	}
}