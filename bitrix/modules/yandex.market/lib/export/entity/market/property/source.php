<?php
namespace Yandex\Market\Export\Entity\Market\Property;

use Bitrix\Main;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui;

class Source extends Entity\Reference\Source
{
	use Concerns\HasMessage;

	const FIELD_CATEGORY_ID = 'CATEGORY_ID';
	const FIELD_PARAMETERS = 'PARAMETERS';

	protected $fieldMap;
	protected $defaultValue;

	public function getTitle()
	{
		return self::getMessage('TITLE');
	}

	public function getOrder()
	{
		return 510;
	}

	public function getFields(array $context = [])
	{
		return $this->buildFieldsDescription([
            static::FIELD_CATEGORY_ID => [
                'TYPE' => Entity\Data::TYPE_MARKET_CATEGORY,
                'SELECTABLE' => true,
                'FILTERABLE' => false,
            ],
            static::FIELD_PARAMETERS => [
                'TYPE' => Entity\Data::TYPE_MARKET_PARAMETERS,
                'SELECTABLE' => true,
                'FILTERABLE' => false,
            ],
        ]);
	}

	/** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
	public function initializeQueryContext($select, &$queryContext, &$sourceSelect)
	{
        if (empty($select)) { return; }

        $this->fieldMap = $this->categoryFields($queryContext);
		$defaultPartials = [];

        if (empty($this->fieldMap))
        {
            $this->fieldMap = $this->createCategoryFields($queryContext['IBLOCK_ID']);
        }

        foreach ($this->fieldMap as list($type, $field))
        {
			if ($type === Entity\Manager::TYPE_IBLOCK_ELEMENT_PROPERTY)
			{
				$defaultPartials[] = (new Ui\Iblock\CategoryValue\PropertyDefault($queryContext['IBLOCK_ID'], $field))->value();
			}

			if ($type === Entity\Manager::TYPE_IBLOCK_SECTION)
			{
				list($fieldName) = explode('.', $field, 2);
				$defaultPartials[] = (new Ui\Iblock\CategoryValue\FieldDefault($queryContext['IBLOCK_ID'], $fieldName))->value();
			}

            $sourceSelect[$type][] = $field;
        }

		$this->defaultValue = Ui\Iblock\CategoryProvider::mergeValue(...$defaultPartials);
	}

    public function releaseQueryContext($select, $queryContext, $sourceSelect)
    {
        $this->fieldMap = null;
    }

	public function getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues)
	{
		$result = [];

		foreach ($elementList as $id => $element)
		{
			$export = [];

			foreach ($this->collectElementPartials($id, $sourceValues) as $categoryValue)
			{
				foreach ($selectFields as $target)
				{
					$previous = isset($export[$target]) ? $export[$target] : null;

					$export[$target] = $this->obtainValue($target, $categoryValue, $previous);
				}
			}

			if (empty($export)) { continue; }

			$result[$id] = $export;
		}

		return $result;
	}

	protected function collectElementPartials($id, $sourceValues)
	{
		$partials = [];

		foreach ($this->fieldMap as list($type, $field))
		{
			if (empty($sourceValues[$id][$type][$field])) { continue; }

			$fieldValue = $sourceValues[$id][$type][$field];

			if ($type === Entity\Manager::TYPE_IBLOCK_SECTION)
			{
				if (!is_array($fieldValue)) { continue; }

				$values = $fieldValue;
			}
			else
			{
				$values = [ $fieldValue ];
			}

			foreach ($values as $value)
			{
				$categoryValue = Ui\Iblock\CategoryProvider::decodeValue($value);

				if ($categoryValue === null) { continue; }

				$partials[] = $categoryValue;

				if ($categoryValue['CATEGORY'] !== '')
				{
					return $partials;
				}
			}
		}

		if ($this->defaultValue !== null)
		{
			$partials[] = $this->defaultValue;
		}

		return $partials;
	}

	protected function obtainValue($target, array $categoryValue, $previous)
	{
		if ($target === static::FIELD_CATEGORY_ID)
		{
			return $this->obtainCategoryId($categoryValue['CATEGORY']);
		}

        if ($target === static::FIELD_PARAMETERS)
        {
            return $this->obtainParameters($categoryValue['PARAMETERS'], $previous);
        }

        throw new Main\ArgumentException(sprintf('unknown market property %s', $target));
	}

	protected function obtainCategoryId($category)
	{
		if (!is_string($category) || $category === '') { return null; }
		if (!preg_match('/\[(\d+)]\s*$/', $category, $matches)) { return null; }

		return (int)$matches[1];
	}

	protected function obtainParameters(array $parameters, array $previous = null)
	{
        $result = [];

        foreach ($parameters as $parameter)
        {
			if (!isset($parameter['ID'], $parameter['VALUE'])) { continue; }

            $parameterId = (int)$parameter['ID'];

			$values = is_array($parameter['VALUE']) ? $parameter['VALUE'] : [ $parameter['VALUE'] ];
			$defaults = [
				'parameterId' => $parameterId,
			];

			if (isset($parameter['UNIT']) && preg_match('/\s\[(\d+)]$/', $parameter['UNIT'], $unitMatches))
			{
				$defaults['unitId'] = (int)$unitMatches[1];
			}

	        foreach ($values as $value)
	        {
		        list($value, $valueId) = $this->obtainParameterValue($value);

		        if ($value === null) { continue; }

		        $export = $defaults + [
			        'value' => $value,
		        ];

		        if ($valueId !== null)
		        {
			        $export['valueId'] = $valueId;
		        }

		        $result[] = $export;
	        }
        }

		if (!empty($previous))
		{
			array_push($result, ...$previous);
		}

		return $result;
	}

    protected function obtainParameterValue($value)
    {
        if (!is_string($value)) { return null; }

	    if ($value === 'Y')
	    {
		    return [ true ];
	    }

	    if ($value === 'N')
	    {
		    return [ false ];
	    }

        if (preg_match('/^(.*)\s\[(\d+)]$/', $value, $matches))
        {
            return [ $matches[1], (int)$matches[2] ];
        }

        if ($value === '') { return null; }

        return [ $value ];
    }

	protected function categoryFields(array $context)
	{
		return array_filter([
			$this->categoryOfferProperty($context),
			$this->categoryElementProperty($context),
			$this->categorySectionField($context),
		]);
	}

    protected function createCategoryFields($iblockId)
    {
	    try
	    {
		    if (Ui\Iblock\CategoryProvider::isCreatedDefault($iblockId)) { return []; }

			$fields = Ui\Iblock\CategoryProvider::createDefault($iblockId);

			foreach ($fields as &$field)
			{
				if ($field[0] === Entity\Manager::TYPE_IBLOCK_SECTION)
				{
					$field[1] .= '.' . Entity\Iblock\Section\Source::CHAIN_SUFFIX;
				}
			}
			unset($field);

		    return $fields;
	    }
	    catch (Main\SystemException $exception)
	    {
		    trigger_error($exception->getMessage(), E_USER_WARNING);
		    return [];
	    }
    }

	protected function categoryOfferProperty(array $context)
	{
		if (empty($context['OFFER_IBLOCK_ID'])) { return null; }

		return $this->fetchCategoryProperty($context['OFFER_IBLOCK_ID'], Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY);
	}

	protected function categoryElementProperty(array $context)
	{
		return $this->fetchCategoryProperty($context['IBLOCK_ID'], Entity\Manager::TYPE_IBLOCK_ELEMENT_PROPERTY);
	}

	protected function fetchCategoryProperty($iblockId, $sourceType)
	{
		$propertyId = Ui\Iblock\CategoryValue\PropertyRepository::propertyId($iblockId);

		if ($propertyId === null) { return null; }

		return [ $sourceType, $propertyId ];
	}

	protected function categorySectionField(array $context)
	{
		$fieldName = Ui\Iblock\CategoryValue\FieldRepository::fieldName($context['IBLOCK_ID']);

		if ($fieldName === null) { return null; }

		return [
			Entity\Manager::TYPE_IBLOCK_SECTION,
			$fieldName . '.' . Entity\Iblock\Section\Source::CHAIN_SUFFIX
		];
	}

	protected function getLangPrefix()
	{
		self::includeSelfMessages();

		return 'EXPORT_ENTITY_MARKET_PROPERTY_SOURCE_';
	}
}