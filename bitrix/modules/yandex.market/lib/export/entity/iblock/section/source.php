<?php

namespace Yandex\Market\Export\Entity\Iblock\Section;

use Bitrix\Main;
use Bitrix\Iblock;
use Yandex\Market;
use Yandex\Market\Export\Entity\Fetcher;

Main\Localization\Loc::loadMessages(__FILE__);

class Source extends Market\Export\Entity\Reference\Source
	implements Market\Export\Entity\Reference\HasSectionValues
{
	const CHAIN_SUFFIX = 'chain';

	protected $cacheSectionValues = [];
	protected $cacheSectionValuesSetupId;

	public function getLangPrefix()
	{
		return 'IBLOCK_SECTION_';
	}

	public function initializeQueryContext($select, &$queryContext, &$sourceSelect)
	{
		$fieldSource = Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD;

		if (!isset($sourceSelect[$fieldSource]))
		{
			$sourceSelect[$fieldSource] = [];
		}

		if (!in_array('IBLOCK_SECTION_ID', $sourceSelect[$fieldSource], true))
		{
			$sourceSelect[$fieldSource][] = 'IBLOCK_SECTION_ID';
		}
	}

	public function releaseQueryContext($select, $queryContext, $sourceSelect)
	{
		$this->cacheSectionValues = [];
		$this->cacheSectionValuesSetupId = null;
	}

	public function getElementListValues($elementList, $parentList, $selectFields, $queryContext, $sourceValues)
	{
		$result = [];

		if (!empty($queryContext['IBLOCK_ID']))
		{
			$sectionToElementMap = [];
			$fieldSource = Market\Export\Entity\Manager::TYPE_IBLOCK_ELEMENT_FIELD;

			foreach ($elementList as $elementId => $element)
			{
				if (!isset($sourceValues[$elementId][$fieldSource]['IBLOCK_SECTION_ID'])) { continue; }

				$sectionId = $sourceValues[$elementId][$fieldSource]['IBLOCK_SECTION_ID'];

				if (!isset($sectionToElementMap[$sectionId]))
				{
					$sectionToElementMap[$sectionId] = [];
				}

				$sectionToElementMap[$sectionId][] = $elementId;
			}

			if (!empty($sectionToElementMap))
			{
				$sectionIds = array_keys($sectionToElementMap);
				$sectionValuesList = $this->getSectionValues($queryContext['IBLOCK_ID'], $sectionIds, $selectFields, $queryContext['SETUP_ID']);

				foreach ($sectionValuesList as $sectionId => $sectionValues)
				{
					if (isset($sectionToElementMap[$sectionId]))
					{
						foreach ($sectionToElementMap[$sectionId] as $elementId)
						{
							$result[$elementId] = $sectionValues;
						}
					}
				}
			}
		}

		return $result;
	}

	public function getSectionListValues($sectionList, $select, $context)
	{
		$sectionIds = array_keys($sectionList);
		$disallowChain = !empty($context['DISALLOW_SECTION_CHAIN']);

		return $this->getSectionValues($context['IBLOCK_ID'], $sectionIds, $select, $context['SETUP_ID'], $disallowChain);
	}

	protected function getSectionValues($iblockId, $sectionIds, $selectFields, $setupId = null, $disallowChain = false)
	{
		$result = [];
		$iblockId = (int)$iblockId;
		$sectionIds = (array)$sectionIds;

		// get from cache

		if ($setupId !== null)
		{
			$cacheSectionValuesList = $this->getCacheSectionValues($setupId);
			$newSectionIds = [];

			foreach ($sectionIds as $sectionId)
			{
				if (!isset($cacheSectionValuesList[$sectionId]))
				{
					$newSectionIds[] = $sectionId;
				}
				else if ($cacheSectionValuesList[$sectionId] === false)
				{
					// nothing
				}
				else
				{
					$result[$sectionId] = $cacheSectionValuesList[$sectionId];
				}
			}
		}
		else
		{
			$newSectionIds = $sectionIds;
		}

		// query from db

		if ($iblockId > 0 && !empty($newSectionIds) && Main\Loader::includeModule('iblock'))
		{
			$nextSections = $newSectionIds;
			$nextSectionExportMap = [];
			$sectionList = [];
			$userFieldFetcher = $this->getUserFieldFetcher($iblockId);
			$querySelect = $this->makeIblockSectionSelect($selectFields);

			while (!empty($nextSections))
			{
				$filterSections = $nextSections;
				$sectionExportMap = $nextSectionExportMap;

				$nextSections = [];
				$nextSectionExportMap = [];

				$query = \CIBlockSection::GetList(
					[ 'DEPTH_LEVEL' => 'DESC' ],
					[ 'IBLOCK_ID' => $iblockId, 'ID' => $filterSections, 'CHECK_PERMISSIONS' => 'N' ],
					false,
					array_merge(
						[ 'IBLOCK_ID', 'ID', 'IBLOCK_SECTION_ID' ],
						$querySelect
					)
				);

				while ($section = $query->Fetch())
				{
					$sectionList[$section['ID']] = $section;
					$hasAllFields = true;
					$exportIdList = isset($sectionExportMap[$section['ID']]) ? $sectionExportMap[$section['ID']] : [ $section['ID'] ];
					$lastParentId = null;
					$ipropData = null;

					foreach ($selectFields as $selectName)
					{
						list($rawName, $innerName, $chainSuffix) = $this->splitName($selectName);
						$fieldName = ($innerName !== null ? "{$rawName}.{$innerName}" : $rawName);
						$isFoundValue = false;
						$fieldValue = $this->getSectionRawValue($section, $rawName, $userFieldFetcher);
						$searchParentId = null;

						if (!Market\Utils\Value::isEmpty($fieldValue))
						{
							$isFoundValue = true;
							$this->pushSectionValue($result, $fieldName, $fieldValue, $exportIdList, $chainSuffix, $userFieldFetcher);
						}
						else if (mb_strpos($rawName, 'SEO_') === 0)
						{
							if ($ipropData === null)
							{
								$ipropValues = new Iblock\InheritedProperty\SectionValues($iblockId, $section['ID']);
								$ipropData = $ipropValues->getValues();
							}

							$seoFieldName = str_replace('SEO_', 'SECTION_', $fieldName);

							if (isset($ipropData[$seoFieldName]))
							{
								$isFoundValue = true;
								$this->pushSectionValue($result, $fieldName, $ipropData[$seoFieldName], $exportIdList, $chainSuffix, $userFieldFetcher);
							}
						}

						if ((!$isFoundValue || $chainSuffix !== null) && !$disallowChain)
						{
							$searchParentId = (int)$section['IBLOCK_SECTION_ID'];

							while ($searchParentId > 0 && isset($sectionList[$searchParentId]))
							{
								$parentSection = $sectionList[$searchParentId];
								$parentValue = $this->getSectionRawValue($parentSection, $rawName, $userFieldFetcher);

								if (!Market\Utils\Value::isEmpty($parentValue))
								{
									$isFoundValue = true;
									$this->pushSectionValue($result, $fieldName, $parentValue, $exportIdList, $chainSuffix, $userFieldFetcher);

									if ($chainSuffix === null) { break; }
								}

								$searchParentId = (int)$parentSection['IBLOCK_SECTION_ID'];
							}
						}

						if ($chainSuffix !== null || !$isFoundValue)
						{
							$hasAllFields = false;
							$lastParentId = $searchParentId;
						}
					}

					if (!$hasAllFields && $lastParentId > 0)
					{
						if (!isset($nextSectionExportMap[$lastParentId]))
						{
							$nextSectionExportMap[$lastParentId] = $exportIdList;
						}
						else
						{
							foreach ($exportIdList as $exportId)
							{
								$nextSectionExportMap[$lastParentId][] = $exportId;
							}
						}

						$nextSections[] = $lastParentId;
					}
				}
			}

			$result = $userFieldFetcher->extendResult($result);

			if ($setupId !== null)
			{
				$this->setCacheSectionValues($setupId, $newSectionIds, $result);
			}
		}

		return $result;
	}

	protected function makeIblockSectionSelect($select)
	{
		$result = [];

		foreach ($select as $name)
		{
			list($name) = $this->splitName($name);

			$result[] = $name;
		}

		return $result;
	}

	protected function getSectionRawValue($section, $name, Fetcher\UserField $userFieldFetcher)
	{
		if ($userFieldFetcher->hasField($name))
		{
			$result = $userFieldFetcher->sanitizeValue($name, $section[$name]);
		}
		else if (isset($section[$name]))
		{
			$result = $section[$name];
		}
		else
		{
			$result = null;
		}

		return $result;
	}

	protected function splitName($name)
	{
		list($field, $inner) = explode('.', $name, 2);

		if ($inner === self::CHAIN_SUFFIX)
		{
			$chain = $inner;
			$inner = null;
		}
		else if ($inner !== null && preg_match('/^(.+)\.' . self::CHAIN_SUFFIX . '$/', $inner, $matches))
		{
			$inner = $matches[1];
			$chain = self::CHAIN_SUFFIX;
		}
		else
		{
			$chain = null;
		}

		return [ $field, $inner, $chain ];
	}

	protected function pushSectionValue(array &$result, $fieldName, $fieldValue, array $exportIdList, $chainSuffix, Fetcher\UserField $userFieldFetcher)
	{
		if ($userFieldFetcher->hasField($fieldName))
		{
			$userFieldFetcher->requestValue($fieldName, $fieldValue, $exportIdList, $chainSuffix);
		}
		else
		{
			$fieldValue = $this->convertFieldValue($fieldName, $fieldValue);

			foreach ($exportIdList as $exportId)
			{
				if (!isset($result[$exportId])) { $result[$exportId] = []; }

				if ($chainSuffix !== null)
				{
					$exportName = "{$fieldName}.{$chainSuffix}";

					if (!isset($result[$exportId][$exportName]))
					{
						$result[$exportId][$exportName] = [];
					}

					$result[$exportId][$exportName][] = $fieldValue;
				}
				else if (!isset($result[$exportId][$fieldName]))
				{
					$result[$exportId][$fieldName] = $fieldValue;
				}
			}
		}
	}

	protected function getCacheSectionValues($setupId)
	{
		if ($this->cacheSectionValuesSetupId === $setupId)
		{
			$result = $this->cacheSectionValues;
		}
		else
		{
			$result = [];
		}

		return $result;
	}

	protected function setCacheSectionValues($setupId, $sectionIds, $sectionValuesList)
	{
		if ($this->cacheSectionValuesSetupId !== $setupId)
		{
			$this->cacheSectionValuesSetupId = $setupId;
			$this->cacheSectionValues = [];
		}

		foreach ($sectionIds as $sectionId)
		{
			$this->cacheSectionValues[$sectionId] = (
				isset($sectionValuesList[$sectionId])
					? $sectionValuesList[$sectionId]
					: false
			);
		}

		if (count($this->cacheSectionValues) > 100)
		{
			$this->cacheSectionValues = array_slice($this->cacheSectionValues, -500, 500, true);
		}
	}

	public function getSectionFields(array $context = [])
	{
		return $this->getFields($context);
	}

	public function getFields(array $context = [])
	{
		$result = $this->buildFieldsDescription([
			'NAME' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'CODE' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'XML_ID' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
            'PICTURE' => [
                'TYPE' => Market\Export\Entity\Data::TYPE_FILE,
                'FILTERABLE' => false,
                'SELECTABLE' => true
            ],
            'DETAIL_PICTURE' => [
                'TYPE' => Market\Export\Entity\Data::TYPE_FILE,
                'FILTERABLE' => false,
                'SELECTABLE' => true
            ],
			'DESCRIPTION' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'SEO_META_TITLE' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'SEO_META_KEYWORDS' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'SEO_META_DESCRIPTION' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
			'SEO_PAGE_TITLE' => [
				'TYPE' => Market\Export\Entity\Data::TYPE_STRING,
				'FILTERABLE' => false,
				'SELECTABLE' => true
			],
		]);

		if (!empty($context['IBLOCK_ID']))
		{
			foreach ($this->getUserFieldFetcher($context['IBLOCK_ID'])->getFields() as $field)
			{
				$result[] = $field + [
					'FILTERABLE' => false,
					'SELECTABLE' => true,
				];
			}
		}

		return $result;
	}

	protected function convertFieldValue($fieldName, $fieldValue)
    {
        switch ($fieldName)
        {
            case 'PICTURE':
            case 'DETAIL_PICTURE':
                $result = \CFile::GetPath($fieldValue);
            break;

            default:
                $result = $fieldValue;
            break;
        }

        return $result;
    }

	protected function getUserFieldFetcher($iblockId)
	{
		return new Fetcher\UserField('IBLOCK_' . $iblockId . '_SECTION');
	}
}