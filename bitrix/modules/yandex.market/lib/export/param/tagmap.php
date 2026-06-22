<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market\Export\Entity;
use Yandex\Market\Export\Xml\Tag;
use Yandex\Market\Result;
use Yandex\Market\Utils\Value;

class TagMap
{
    private $tagsMap;

    public function __construct(array $tagsMap)
    {
        $this->tagsMap = $tagsMap;
    }

    public function getRaw()
    {
        return $this->tagsMap;
    }

	public function setRaw(array $tagsMap)
	{
		$this->tagsMap = $tagsMap;
	}

	public function isEmpty()
	{
		return empty($this->tagsMap);
	}

	public function hasAny(array $names)
	{
		$namesMap = array_flip($names);

		foreach ($this->tagsMap as $tag)
		{
			if (isset($namesMap[$tag['TAG']]))
			{
				return true;
			}
		}

		return false;
	}

    public function get($name)
    {
        $result = [];

        foreach ($this->tagsMap as $tag)
        {
            if ($tag['TAG'] === $name)
            {
                $result[] = $tag;
            }
        }

        return $result;
    }

	public function has($name)
	{
		foreach ($this->tagsMap as $tag)
		{
			if ($tag['TAG'] === $name)
			{
				return true;
			}
		}

		return false;
	}

    public function cloneWithout(array $names)
    {
        $tags = [];
        $namesMap = array_flip($names);

        foreach ($this->tagsMap as $tag)
        {
            if (!isset($namesMap[$tag['TAG']]))
            {
                $tags[] = $tag;
            }
        }

        return new static($tags);
    }

	public function cloneOnly(array $names)
	{
		$tags = [];
		$namesMap = array_flip($names);

		foreach ($this->tagsMap as $tag)
		{
			if (isset($namesMap[$tag['TAG']]))
			{
				$tags[] = $tag;
			}
		}

		return new static($tags);
	}

	public function merge(TagMap ...$partials)
	{
		$tagPartials = [
			$this->getRaw(),
		];

		foreach ($partials as $partial)
		{
			$tagPartials[] = $partial->getRaw();
		}

		return new static(array_merge(...$tagPartials));
	}

    public function getSourceSelect(array $initial = [])
    {
        return $this->compileSourceSelect($this->tagsMap, $initial);
    }

    private function compileSourceSelect(array $tagsMap, array $initial = [])
    {
        $result = $initial;

        foreach ($tagsMap as $tagMap)
        {
            if (isset($tagMap['VALUE']))
            {
                $result = $this->pushSourceSelect($result, $tagMap['VALUE']);
            }

            foreach (['ATTRIBUTES', 'SETTINGS'] as $childKey)
            {
                if (!isset($tagMap[$childKey]) || !is_array($tagMap[$childKey])) { continue; }

                foreach ($tagMap[$childKey] as $attribute)
                {
                    if (!is_array($attribute)) { continue; }

                    $result = $this->pushSourceSelect($result, $attribute);
                }
            }

            if (!empty($tagMap['CHILDREN']))
            {
                $result = $this->compileSourceSelect($tagMap['CHILDREN'], $result);
            }
        }

        return $result;
    }

    private function pushSourceSelect(array $sourceSelect, array $sourceMap)
    {
        if (
            !isset($sourceMap['TYPE'], $sourceMap['FIELD'])
            || $sourceMap['TYPE'] === Entity\Manager::TYPE_TEXT
            || mb_strpos($sourceMap['TYPE'], 'VIRTUAL_') === 0
        )
        {
            return $sourceSelect;
        }

        if (!isset($sourceSelect[$sourceMap['TYPE']]))
        {
            $sourceSelect[$sourceMap['TYPE']] = [];
        }

        if (!in_array($sourceMap['FIELD'], $sourceSelect[$sourceMap['TYPE']], true))
        {
            $sourceSelect[$sourceMap['TYPE']][] = $sourceMap['FIELD'];
        }

        return $sourceSelect;
    }

    /** @noinspection PhpUnused */
    public function extractGroup(array $groupValues, Tag\Base $tag = null, array $context = [])
    {
        $result = [];

        foreach ($groupValues as $elementId => $elementValues)
        {
            $result[$elementId] = $this->extract($elementValues, $tag, $context);
        }

        return $result;
    }

    public function extract(array $sourceValues, Tag\Base $tag = null, array $context = [])
    {
        return $this->compile($this->tagsMap, $sourceValues, $tag, $context);
    }

    private function compile(array $tagsMap, array $sourceValues, Tag\Base $root = null, array $context = [])
    {
        $result = new Result\XmlValue();

        foreach ($tagsMap as $tagMap)
        {
			$name = $tagMap['TAG'];
	        $tag = null;

			if ($root !== null)
			{
				$tag = $root->getId() === $tagMap['TAG'] ? $root : $root->getChild($tagsMap['TAG']);
			}

	        $values = $this->compileValues($tagMap, $sourceValues, $context);
	        $keys = array_fill_keys(array_keys($values), true);
	        list($attributes, $keys) = $this->compileAttributes($tagMap, $sourceValues, $keys, $context);
			list($children, $keys) = $this->compileChildren($tagMap, $sourceValues, $tag, $keys, $context);
	        $settings = $this->compileSettings($tagMap, $sourceValues, $context);

			$this->pushTags($result, $name, $keys, $values, $attributes, $settings, $children);
        }

        return $result;
    }

	private function compileValues(array $tagMap, array $sourceValues, array $context)
	{
		if (isset($tagMap['VALUE']))
		{
			$value = $this->sourceValue($tagMap['VALUE'], $sourceValues, $context);

			return is_array($value) ? $value : [ $value ];
		}

		return [ null ];
	}

	private function compileAttributes(array $tagMap, array $sourceValues, array $valueKeys, array $context)
	{
		if (empty($tagMap['ATTRIBUTES'])) { return [ [], $valueKeys ]; }

		$attributes = [];

		foreach ($tagMap['ATTRIBUTES'] as $name => $fieldMap)
		{
			$value = $this->sourceValue($fieldMap, $sourceValues, $context);

			if (is_array($value))
			{
				foreach ($value as $partKey => $partValue)
				{
					$valueKeys[$partKey] = true;
				}
			}

			$attributes[$name] = $value;
		}

		return [ $attributes, $valueKeys ];
	}

	private function compileSettings(array $tagMap, array $sourceValues, array $context)
	{
		if (!isset($tagMap['SETTINGS'])) { return null; }
		if (!is_array($tagMap['SETTINGS'])) { return $tagMap['SETTINGS']; }

		$settings = $tagMap['SETTINGS'];

		foreach ($settings as $name => $map)
		{
			if (!isset($map['TYPE'], $map['FIELD'])) { continue; }

			$settings[$name] = $this->sourceValue($map, $sourceValues, $context);
		}

		return $settings;
	}

	private function compileChildren(array $tagMap, array $sourceValues, Tag\Base $tag = null, array $valueKeys = [], array $context = [])
	{
		if (empty($tagMap['CHILDREN'])) { return [ [], $valueKeys ]; }

		$childrenTag = $this->compile($tagMap['CHILDREN'], $sourceValues, $tag, $context);
		$childrenValues = [];

		if ($tag !== null && $childrenTag->hasMultipleTags() && ($tag->isMultiple() || $tag->isUnion()))
		{
			$childrenValueKeys = $childrenTag->getMultipleKeys();

			foreach ($childrenValueKeys as $childrenValueKey)
			{
				$childrenValues[$childrenValueKey] = $childrenTag->getMultipleData($childrenValueKey);
			}

			$valueKeys += array_flip(array_keys($childrenValues));
		}
		else if (!empty($valueKeys))
		{
			reset($valueKeys);
			$childrenValues[key($valueKeys)] = $childrenTag->getTagData();
		}

		return [ $childrenValues, $valueKeys ];
	}

	private function pushTags(Result\XmlValue $tagValue, $name, array $valueKeys, array $values, array $attributes, $settings, array $children)
	{
		foreach ($valueKeys as $valueKey => $dummy)
		{
			$itemValue = isset($values[$valueKey]) ? $values[$valueKey] : null;
			$itemChildren = isset($children[$valueKey]) ? $children[$valueKey] : null;
			$itemAttributes = [];
			$empty = empty($itemChildren) && Value::isEmpty($itemValue);

			foreach ($attributes as $attributeName => $attributeValue)
			{
				if (is_array($attributeValue))
				{
					$attributeValue = isset($attributeValue[$valueKey]) ? $attributeValue[$valueKey] : null;
				}

				$itemAttributes[$attributeName] = $attributeValue;

				if ($empty && !Value::isEmpty($attributeValue))
				{
					$empty = false;
				}
			}

			if ($empty) { continue; }

			if (!$tagValue->hasTag($name, $itemValue, $itemAttributes, $itemChildren))
			{
				$tagValue->addTag($name, $itemValue, $itemAttributes, $settings, $itemChildren);
			}
		}
	}

    private function sourceValue(array $fieldMap, array $values, array $context)
    {
        if (isset($fieldMap['VALUE']))
        {
            return $fieldMap['VALUE'];
        }

        if ($fieldMap['TYPE'] === Entity\Manager::TYPE_TEXT)
        {
            return $fieldMap['FIELD'];
        }

		$field = isset($context['SELECT_MAP'][$fieldMap['TYPE']][$fieldMap['FIELD']])
			? $context['SELECT_MAP'][$fieldMap['TYPE']][$fieldMap['FIELD']]
			: $fieldMap['FIELD'];

        if (isset($values[$fieldMap['TYPE']][$field]))
        {
            return $values[$fieldMap['TYPE']][$field];
        }

        return null;
    }
}