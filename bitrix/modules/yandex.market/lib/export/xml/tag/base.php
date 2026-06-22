<?php

namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Xml;
use Yandex\Market;
use Bitrix\Main;

Main\Localization\Loc::loadMessages(__FILE__);

class Base extends Xml\Reference\Node
{
	/** @var Xml\Attribute\Base[] */
	protected $attributes;
	/** @var Market\Export\Xml\Attribute\Base|null */
	protected $primaryAttribute;
	/** @var Xml\Tag\Base[] */
	protected $children;
	/** @var bool */
	protected $hasEmptyValue;
	/** @var bool */
	protected $isMultiple;
	/** @var bool */
	protected $isUnion;
	/** @var int|null */
	protected $maxCount;
	/** @var string|null */
	protected $wrapperName;
	/** @var string|null */
	protected $itemName;

	protected function refreshParameters()
	{
		parent::refreshParameters();

		$parameters = $this->parameters;

		$this->children = isset($parameters['children']) ? (array)$parameters['children'] : [];
		$this->attributes = isset($parameters['attributes']) ? (array)$parameters['attributes'] : [];
		$this->hasEmptyValue = !empty($this->children) || !empty($parameters['empty_value']);
		$this->isMultiple = !empty($parameters['multiple']);
		$this->isUnion = !empty($parameters['union']);
		$this->maxCount = isset($parameters['max_count']) ? (int)$parameters['max_count'] : null;
        $this->wrapperName = isset($parameters['wrapper_name']) ? (string)$parameters['wrapper_name'] : null;
        $this->itemName = isset($parameters['item_name']) ? (string)$parameters['item_name'] : null;

        if ($this->itemName !== null && $this->wrapperName === null)
        {
            $this->wrapperName = $this->name;
        }
	}

    public function cloneWithout(array $children, array $attributes = [])
    {
        $parameters = $this->parameters;
        $parameters['children'] = array_filter($this->children, static function(Xml\Tag\Base $tag) use ($children) {
            return !in_array($tag->getId(), $children, true);
        });
        $parameters['attributes'] = array_filter($this->attributes, static function(Xml\Attribute\Base $attribute) use ($attributes) {
            return !in_array($attribute->getId(), $attributes, true);
        });

        return new static($parameters);
    }

	public function cloneOnly(array $children, array $attributes = [])
	{
		$parameters = $this->parameters;
		$parameters['children'] = array_filter($this->children, static function(Xml\Tag\Base $tag) use ($children) {
			return in_array($tag->getId(), $children, true);
		});
		$parameters['attributes'] = array_filter($this->attributes, static function(Xml\Attribute\Base $attribute) use ($attributes) {
			return in_array($attribute->getId(), $attributes, true);
		});

		return new static($parameters);
	}

	public function isUnion()
	{
		return $this->isUnion;
	}

	public function isMultiple()
	{
		return $this->isMultiple;
	}

	public function isDeprecated()
	{
		return (bool)$this->getParameter('deprecated');
	}

	public function isSelfClosed()
	{
		return $this->hasEmptyValue && empty($this->children);
	}

	public function getChild($tagName)
	{
		$result = null;

		foreach ($this->children as $child)
		{
			if ($child->getName() === $tagName)
			{
				$result = $child;
				break;
			}
		}

		return $result;
	}

	public function hasChild($tagName)
	{
		return ($this->getChild($tagName) !== null);
	}

	public function hasChildren()
	{
		return !empty($this->children);
	}

	/** @return Base[] */
	public function getChildren()
	{
		return $this->children;
	}

	public function addChildren(array $tags, $position = null, $after = false)
	{
		if (empty($tags)) { return; }

		$offset = null;

		if (is_numeric($position))
		{
			$offset = (int)$position;
		}
		else if (is_string($position))
		{
			$positionChild = $this->getChild($position);
			$positionOffset = $positionChild !== null
				? array_search($positionChild, $this->children, true)
				: false;

			if ($positionOffset !== false)
			{
				$offset = $positionOffset;
			}
		}

		if ($offset !== null && $after)
		{
			++$offset;
		}

		if ($offset !== null)
		{
			array_splice($this->children, $offset, 0, $tags);
		}
		else
		{
			array_push($this->children, ...$tags);
		}

		$this->hasEmptyValue = true;
	}

	public function addChild(Base $tag, $position = null, $after = false)
	{
		$this->addChildren([ $tag ], $position, $after);
	}

	public function removeChild(Base $tag)
	{
		$tagIndex = array_search($tag, $this->children);

		if ($tagIndex !== false)
		{
			array_splice($this->children, $tagIndex, 1);
			$this->hasEmptyValue = !empty($this->children) || !empty($parameters['empty_value']);
		}
	}

	public function getLangKey()
	{
		$nameLang = $this->getParameter('lang_key');

		if ($nameLang === null)
		{
			$nameLang = str_replace(['.', ' ', '-'], '_', $this->id);
			$nameLang = mb_strtoupper($nameLang);
		}

		return 'EXPORT_TAG_' . $nameLang;
	}

	public function getPrimary()
	{
		if ($this->primaryAttribute === null)
		{
			$this->primaryAttribute = $this->resolvePrimary();
		}

		return $this->primaryAttribute;
	}

	protected function resolvePrimary()
	{
		$result = null;

		foreach ($this->attributes as $attribute)
		{
			if ($attribute->isPrimary())
			{
				$result = $attribute;
				break;
			}
		}

		return $result;
	}

	public function getAttribute($attributeName)
	{
		$result = null;

		foreach ($this->attributes as $attribute)
		{
			if ($attribute->getName() === $attributeName)
			{
				$result = $attribute;
				break;
			}
		}

		return $result;
	}

	public function hasAttribute($attributeName)
	{
		return ($this->getAttribute($attributeName) !== null);
	}

	public function hasAttributes()
	{
		return !empty($this->attributes);
	}

	/**
	 * @return Xml\Attribute\Base[]
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	public function addAttribute(Xml\Attribute\Base $attribute, $position = null)
	{
		if ($position !== null)
		{
			array_splice($this->attributes, $position, 0, [ $attribute ]);
		}
		else
		{
			$this->attributes[] = $attribute;
		}
	}

	/** @return bool */
	public function hasEmptyValue()
	{
		return $this->hasEmptyValue;
	}

	/** @return int|null */
	public function getMaxCount()
	{
		return $this->maxCount;
	}

	public function extendTagDescriptionList(&$tagDescriptionList, array $context)
	{
		if (!isset($context['TAG_LEVEL'])) { $context['TAG_LEVEL'] = 0; }

		$foundTags = [];

		// search exists

		foreach ($tagDescriptionList as &$existDescription)
		{
			if ($existDescription['TAG'] === $this->id)
			{
				$existDescription = $this->extendTagDescription($existDescription, $context);

				$foundTags[] = &$existDescription;
			}
		}
		unset($existDescription);

		// create default

		if (empty($foundTags))
		{
			$newDescription = $this->extendTagDescription([], $context);

			if (!empty($newDescription))
			{
				$newDescription['TAG'] = $this->id;

				$foundTags[] = &$newDescription;
				$tagDescriptionList[] = &$newDescription;
			}
			else if ($context['TAG_LEVEL'] === 0)
			{
				$this->extendChildrenDescription($tagDescriptionList, null, $context);
			}
		}

		// apply

		foreach ($foundTags as &$foundDescription)
		{
			$foundDescription = $this->extendChildrenDescription($tagDescriptionList, $foundDescription, $context);
		}
		unset($foundDescription);
	}

	public function extendTagDescription($tagDescription, array $context)
	{
		$result = $tagDescription;

		if (empty($result['VALUE']) || $this->isDefined())
		{
			$definedSource = $this->getDefinedSource($context);

			if ($definedSource !== null)
			{
				$result['VALUE'] = $definedSource;
			}
		}

		foreach ($this->getAttributes() as $attribute)
		{
			$attributeId = $attribute->getId();

			if (empty($result['ATTRIBUTES'][$attributeId]) || $attribute->isDefined())
			{
				$definedSource = $attribute->getDefinedSource($context);

				if ($definedSource !== null)
				{
					if (!isset($result['ATTRIBUTES']))
					{
						$result['ATTRIBUTES'] = [];
					}

					$result['ATTRIBUTES'][$attributeId] = $definedSource;
				}
			}
		}

		return $result;
	}

	protected function extendChildrenDescription(&$tagDescriptionList, $tagDescription, array $context)
	{
		if ($context['TAG_LEVEL'] > 0)
		{
			if (!isset($tagDescription['CHILDREN']) || !is_array($tagDescription['CHILDREN']))
			{
				$tagDescription['CHILDREN'] = [];
			}

			$childrenValues = &$tagDescription['CHILDREN'];
			$childrenContext = $context;

			if (!isset($childrenContext['TAG_CHAIN'])) { $childrenContext['TAG_CHAIN'] = []; }

			$childrenContext['TAG_CHAIN'][] = $tagDescriptionList;
		}
		else
		{
			$childrenValues = &$tagDescriptionList;
			$childrenContext = $context;
		}

		++$childrenContext['TAG_LEVEL'];

		foreach ($this->getChildren() as $child)
		{
			$child->extendTagDescriptionList($childrenValues, $childrenContext);
		}

		return $tagDescription;
	}

	/**  @return array|null */
	public function getSettingsDescription(array $context = [])
	{
		return null;
	}

	/** @return Market\Result\XmlNode */
	public function exportTag(array $groupValues, array $context)
	{
        return (new Xml\TagExporter($context))->export($this, $groupValues, new Xml\Data\XmlElement('root'));
	}

    /** @return Market\Result\XmlNode */
    public function exportJson(array $groupValues, array $context)
    {
        return (new Xml\TagExporter($context))->export($this, $groupValues, new Xml\Data\ArrayElement('root'));
    }

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
        $name = $this->name;

		if ($this->wrapperName !== null)
		{
			$parent = $parent->getChild($this->wrapperName)[0] ?: $parent->addChild($this->wrapperName);
            $name = $this->itemName !== null ? $this->itemName : $name;
		}

		if ($this->hasEmptyValue)
		{
			return $parent->addChild($name, null, $this->isMultiple);
		}

		return $parent->addChild($name, $value, $this->isMultiple);
	}

    public function appendNode($value, Xml\Data\ExportElement $node)
	{
		if ($this->hasEmptyValue || (string)$value === '') { return; }

		$node->appendValue($value, $this->getParameter('glue', ', '));
	}

	public function removeNode(Xml\Data\ExportElement $node, Xml\Data\ExportElement $parent)
	{
		if ($this->wrapperName !== null)
		{
			$wrapper = $parent->getChild($this->wrapperName)[0];

			if ($wrapper === null) { return; }

			$wrapper->removeChild($node);

			if ($wrapper->hasChildren()) { return; }

			$parent->removeChild($wrapper);
			return;
		}

		$parent->removeChild($node);
	}

	protected function getTagValues($tagValuesList, $tagId, $isMultiple = false)
	{
		$result = null;

		if (isset($tagValuesList[$tagId]))
		{
			$tagValues = $tagValuesList[$tagId];
			$isSingleValue = array_key_exists('VALUE', $tagValues);

			if ($isMultiple)
			{
				$result = $isSingleValue ? [ $tagValues ] : $tagValues;
			}
			else
			{
				$result = $isSingleValue ? $tagValues : reset($tagValues);
			}
		}
		else if ($isMultiple)
		{
			$result = [];
		}

		return $result;
	}
}
