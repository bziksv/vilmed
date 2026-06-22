<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Model;
use Yandex\Market\Exceptions\Api\ObjectPropertyException;

class Unit extends Model
{
    public function getDefaultUnitId()
    {
        return (int)$this->requireField('defaultUnitId');
    }

	public function getDefaultUnit()
	{
		$defaultUnitId = $this->getDefaultUnitId();

		foreach ($this->getUnits() as $unit)
		{
			if ($unit['id'] === $defaultUnitId)
			{
				return $unit;
			}
		}

		throw new ObjectPropertyException($this->relativePath . 'defaultUnitNotFound');
	}

    /** @return array{id: int, name: string, fullName: string}[]*/
    public function getUnits()
    {
        return (array)$this->requireField('units');
    }
}