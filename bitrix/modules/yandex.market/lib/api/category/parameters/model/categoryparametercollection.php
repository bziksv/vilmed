<?php
namespace Yandex\Market\Api\Category\Parameters\Model;

use Yandex\Market\Api\Reference\Collection;

/** @property CategoryParameter[] $collection */
class CategoryParameterCollection extends Collection
{
	public static function getItemReference()
	{
		return CategoryParameter::class;
	}

    public function getItemById($id)
    {
        $id = (int)$id;

        foreach ($this->collection as $item)
        {
            if ($item->getId() === $id)
            {
                return $item;
            }
        }

        return null;
    }
}