<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Storage;

/**
 * @property Model[] $collection
 */
class Collection extends Storage\Collection
{
	public static function getItemReference()
	{
		return Model::class;
	}

	public function initChildren()
	{
		foreach ($this->collection as $item)
		{
			$item->initChildren();
		}
	}

	public function preloadReference()
	{
		foreach ($this->collection as $item)
		{
			$item->getValueCollection();
		}
	}

    /** @return TagMap */
    public function getTagMap()
    {
        return new TagMap($this->describeTagMap($this));
    }

    private function describeTagMap(Collection $paramCollection)
    {
        $result = [];

        /** @var Model $param */
        foreach ($paramCollection as $param)
        {
            $tagResult = [
                'TAG' => $param->getField('XML_TAG'),
                'VALUE' => null,
                'CHILDREN' => $this->describeTagMap($param->getChildren()),
                'ATTRIBUTES' => [],
                'SETTINGS' => $param->getSettings()
            ];

            /** @var \Yandex\Market\Export\ParamValue\Model $paramValue */
            foreach ($param->getValueCollection() as $paramValue)
            {
                $sourceType = $paramValue->getSourceType();
                $sourceField = $paramValue->getSourceField();
                $sourceMap = (
                    $sourceType === Entity\Manager::TYPE_TEXT
                        ? [ 'VALUE' => $sourceField ]
                        : [ 'TYPE' => $sourceType, 'FIELD' => $sourceField ]
                );

                if ($paramValue->isAttribute())
                {
                    $attributeName = $paramValue->getAttributeName();

                    $tagResult['ATTRIBUTES'][$attributeName] = $sourceMap;
                }
                else
                {
                    $tagResult['VALUE'] = $sourceMap;
                }
            }

            $result[] = $tagResult;
        }

        return $result;
    }
}