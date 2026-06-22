<?php
namespace Yandex\Market\Trading\Settings;

use Yandex\Market;

class Collection extends Market\Reference\Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}

	public function getValue($name)
	{
		/** @var Market\Trading\Settings\Model $model */
		foreach ($this->collection as $model)
		{
			if ($model->getName() === $name)
			{
				return $model->getValue();
			}
		}

		return null;
	}

	public function getValues()
	{
		$result = [];

		/** @var Market\Trading\Settings\Model $model */
		foreach ($this->collection as $model)
		{
			$result[$model->getName()] = $model->getValue();
		}

		return $result;
	}
}