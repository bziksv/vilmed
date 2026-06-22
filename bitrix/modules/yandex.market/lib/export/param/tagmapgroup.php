<?php
namespace Yandex\Market\Export\Param;

class TagMapGroup
{
    private $tagMaps;

    /** @param TagMap[] $tagMaps */
    public function __construct(array $tagMaps)
    {
        $this->tagMaps = $tagMaps;
    }

    public function getSourceSelect()
    {
        $result = [];

        foreach ($this->tagMaps as $tagMap)
        {
            $result = $tagMap->getSourceSelect($result);
        }

        return $result;
    }

    public function extractGroup(array $groupValues)
    {
        $result = [];

        foreach ($groupValues as $elementId => $elementValues)
        {
            $result[$elementId] = $this->extract($elementValues);
        }

        return $result;
    }

    public function extract(array $elementValues)
    {
        $result = [];

        foreach ($this->tagMaps as $key => $tagMap)
        {
            $result[$key] = $tagMap->extract($elementValues);
        }

        return $result;
    }
}