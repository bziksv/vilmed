<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market;

class Model extends Market\Reference\Storage\Model
{
	public static function getDataClass()
	{
		return Table::class;
	}

	public function getSettings()
	{
		$fieldValue = $this->getField('SETTINGS');

		return is_array($fieldValue) ? $fieldValue : null;
	}

	public function getValueCollection()
	{
		return $this->getCollection('PARAM_VALUE', Market\Export\ParamValue\Collection::class);
	}

	public function initChildren()
	{
		if (!$this->hasField('CHILDREN'))
		{
			$this->setField('CHILDREN', []);
		}

		return $this->getChildren();
	}

	public function getChildren()
	{
		return $this->getCollection('CHILDREN', Market\Export\Param\Collection::class);
	}
}