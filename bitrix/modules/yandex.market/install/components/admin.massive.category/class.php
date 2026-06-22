<?php
namespace Yandex\Market\Components;

use Bitrix\Main;
use Bitrix\Iblock;
use Yandex\Market\Config;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Ui\Iblock\CategoryForm;
use Yandex\Market\Ui\Iblock\CategoryValue;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Utils;
use Yandex\Market\Utils\ArrayHelper;

Main\Localization\Loc::loadMessages(__FILE__);

class AdminMassiveCategory extends \CBitrixComponent
{
	const SHOW_DETAILS_LIMIT = 50;

	public function requiredModules()
	{
		return [ 'iblock' ];
	}

	public function executeComponent()
	{
		ShowError('include component unsupported');
	}

	/** @noinspection PhpUnused */
	public function formAction()
	{
		$selectedIds = $this->request->get('SELECTED');
		$iblockId = $this->request->get('IBLOCK_ID');

		Assert::isArray($selectedIds, '$_POST[SELECTED]');
		Assert::positiveInteger($iblockId, '$_POST[IBLOCK_ID]');

		$iblockId = (int)$iblockId;

		$this->checkIblockReadAccess($iblockId);

		list($propertyType, $propertyId) = $this->primaryProperty($iblockId);
		$selectedLimit = (int)Config::getOption('massive_edit_limit', 20000);
		$selected = $this->selected($selectedIds, $iblockId, $selectedLimit);
		$selectedLimited = false;

		if (count($selected) > $selectedLimit)
		{
			$selected = array_slice($selected, 0, $selectedLimit, true);
			$selectedLimited = true;
		}

		CategoryValue\ElementFetcher::preload($iblockId, array_keys($selected));

		$elementLoaders = $this->valueLoaders($iblockId, array_keys($selected));
		$selectedLoaders = $this->climbUpUntilSelected($elementLoaders, $selected);
		$parentLoaders = $this->climbUpUntilParent($selectedLoaders);

		$groups = $this->groupSelected($selectedLoaders, $elementLoaders);
		$groups = $this->splitGroupParents($groups, $parentLoaders);

		$this->formCommonResult($iblockId, $selectedLimited, $selectedLimit, $propertyType, $propertyId);
		$this->formGroupResult($groups, $elementLoaders, $selectedLoaders);

		$this->includeComponentTemplate();
	}

	private function formCommonResult($iblockId, $selectedLimited, $selectedLimit, $propertyType, $propertyId)
	{
		$this->arResult['IBLOCK_ID'] = $iblockId;
		$this->arResult['SELECTED_LIMITED'] = $selectedLimited;
		$this->arResult['SELECTED_LIMIT'] = $selectedLimit;
		$this->arResult['PROPERTY_TYPE'] = $propertyType;
		$this->arResult['PROPERTY_ID'] = $propertyId;
	}

	/**
	 * @param int[][] $groups
	 * @param array<int, CategoryValue\MemoProxy> $elementLoaders
	 * @param array<int, CategoryValue\MemoProxy> $selectedLoaders
	 */
	private function formGroupResult(array $groups, array $elementLoaders, array $selectedLoaders)
	{
		$this->arResult['GROUPS'] = [];

		foreach ($groups as $group)
		{
			$this->arResult['GROUPS'][] = $this->compileFormGroup(
				$group,
				array_intersect_key($elementLoaders, array_flip($group)),
				array_intersect_key($selectedLoaders, array_flip($group))
			);
		}
	}

	/**
	 * @param int[] $elementIds
	 * @param array<int, CategoryValue\MemoProxy> $elementLoaders
	 * @param array<int, CategoryValue\MemoProxy> $selectedLoaders
	 */
	private function compileFormGroup(array $elementIds, array $elementLoaders, array $selectedLoaders)
	{
		$elementNames = $this->elementNames($elementIds);
		$selectedLoader = reset($selectedLoaders);
		$decoratedLoader = $selectedLoader->decorated();
		$elementValues = $this->compileValues($elementLoaders, $selectedLoaders);
		$firstElementValue = reset($elementValues);
		$groupValue = [
			'CATEGORY' => isset($firstElementValue['CATEGORY']) ? $firstElementValue['CATEGORY'] : '',
			'PARAMETERS' => $this->commonParameters($elementValues),
		];
		$parentValue = CategoryValue\Facade::compile($selectedLoader->parent());
		$form = new CategoryForm\MassiveEdit($parentValue);

		if ($decoratedLoader instanceof CategoryValue\SectionValue)
		{
			$sectionId = $decoratedLoader->sectionId();

			$sectionTitle = $this->sectionName($sectionId) ?: $this->getMessage('GROUP_SECTION_UNKNOWN', [ '#ID#' => $sectionId ]);
			$sectionExpand = $this->getMessage('GROUP_SECTION_EXPAND');
		}
		else
		{
			$sectionId = 0;
			$firstElementId = reset($elementIds);
			$siblingsCount = count($elementIds) - 1;

			$sectionTitle = isset($elementNames[$firstElementId])
				? $elementNames[$firstElementId]
				: $this->getMessage('GROUP_ELEMENT_UNKNOWN', [ '#ID#' => $firstElementId ]);
			$sectionExpand = $siblingsCount > 0
				? $this->getMessage('GROUP_ELEMENT_EXPAND', [
					'#COUNT#' => $siblingsCount,
					'#UNIT#' => Utils::sklon($siblingsCount, [
						$this->getMessage('GROUP_ELEMENT_SIBLING_1'),
						$this->getMessage('GROUP_ELEMENT_SIBLING_2'),
						$this->getMessage('GROUP_ELEMENT_SIBLING_5'),
					]),
				])
				: null;
		}

		return [
			'ELEMENT_ID' => $elementIds,
			'ELEMENT_NAME' => $elementNames,
			'SECTION_ID' => $sectionId,
			'SECTION_TITLE' => $sectionTitle,
			'SECTION_EXPAND' => $sectionExpand,
			'VALUE' => $groupValue,
			'PARENT_VALUE' => $parentValue,
			'FORM_TYPE' => $form->type(),
			'FORM_FIELDS' => $form->fields(),
			'FORM_PAYLOAD' => $form->payload(),
			'THEME' => $form->theme(),
		];
	}

	private function checkIblockReadAccess($iblockId)
	{
		if (!\CIBlockRights::UserHasRightTo($iblockId, $iblockId, "iblock_admin_display"))
		{
			throw new Main\AccessDeniedException($this->getMessage('READ_ACCESS_DENIED'));
		}
	}

	private function checkElementsWriteAccess($iblockId, array $elementIds)
	{
		if (\CIBlock::GetArrayByID($iblockId, 'RIGHTS_MODE') === 'E')
		{
			foreach ($elementIds as $elementId)
			{
				if (!\CIBlockElementRights::UserHasRightTo($iblockId, $elementId, "element_edit"))
				{
					throw new Main\AccessDeniedException($this->getMessage('WRITE_ACCESS_DENIED'));
				}
			}
		}
		else if (\CIBlock::GetPermission($iblockId) < 'W')
		{
			throw new Main\AccessDeniedException($this->getMessage('WRITE_ACCESS_DENIED'));
		}
	}

	private function selected(array $ids, $iblockId, $limit)
	{
		$result = [];

		foreach ($ids as $id)
		{
			if (is_numeric($id))
			{
				$elementId = (int)$id;

				$result[$elementId] = 0;
			}
			else if (mb_strpos($id, 'E') === 0)
			{
				$elementId = (int)mb_substr($id, 1);

				$result[$elementId] = 0;
			}
			else if (mb_strpos($id, 'S') === 0)
			{
				$sectionId = (int)mb_substr($id, 1);

				$result += array_fill_keys(
					$this->sectionElements($sectionId, $iblockId, $limit),
					$sectionId
				);
			}
		}

		if (empty($result))
		{
			throw new Main\ArgumentException($this->getMessage('ELEMENTS_NOT_FOUND'));
		}

		return $result;
	}

	private function sectionElements($sectionId, $iblockId, $limit)
	{
		$sectionId = (int)$sectionId;

		if ($sectionId <= 0) { return []; }

		$result = [];
		$sections = array_merge(
			[$sectionId],
			$this->childrenSections($sectionId)
		);
		$sectionFilterName = version_compare(Main\ModuleManager::getVersion('iblock'), '20.0') !== -1
			? 'IBLOCK_SECTION_ID'
			: 'SECTION_ID';

		$queryElements = \CIBlockElement::GetList(
			[],
			[
				'IBLOCK_ID' => $iblockId,
				$sectionFilterName => $sections,
			],
			false,
			[ 'nTopCount' => $limit + 1 ],
			[ 'ID', 'IBLOCK_SECTION_ID' ]
		);

		while ($row = $queryElements->Fetch())
		{
			if (!in_array((int)$row['IBLOCK_SECTION_ID'], $sections, true))
			{
				continue;
			}

			$result[] = (int)$row['ID'];
		}

		return $result;
	}
	
	private function childrenSections($sectionId)
	{
		$section = Iblock\SectionTable::getRow([
			'filter' => [ '=ID' => $sectionId ],
			'select' => [ 'IBLOCK_ID', 'LEFT_MARGIN', 'RIGHT_MARGIN' ],
		]);

		if ($section === null) { return []; }

		return array_map('intval', array_column(Iblock\SectionTable::getList([
			'filter' => [
				'=IBLOCK_ID' => $section['IBLOCK_ID'],
				'>LEFT_MARGIN' => $section['LEFT_MARGIN'],
				'<RIGHT_MARGIN' => $section['RIGHT_MARGIN'],
			],
			'select' => [ 'ID' ],
		])->fetchAll(), 'ID'));
	}

	private function primaryProperty($iblockId)
	{
		$property = CategoryValue\PropertyRepository::property($iblockId);

		if ($property !== null)
		{
			if ($property['MULTIPLE'] === 'Y')
			{
				throw new Main\SystemException($this->getMessage('MULTIPLE_NOT_SUPPORTED'));
			}

			return [ 'element', $property['ID'] ];
		}

		$field = CategoryValue\FieldRepository::field($iblockId);

		if ($field !== null)
		{
			if ($field['MULTIPLE'] === 'Y')
			{
				throw new Main\SystemException($this->getMessage('MULTIPLE_NOT_SUPPORTED'));
			}

			return [ 'section', $field['ID'] ];
		}

		throw new Main\ObjectNotFoundException($this->getMessage('PROPERTY_NOT_FOUND'));
	}

	/** @return CategoryValue\MemoProxy[] */
	private function valueLoaders($iblockId, array $elementIds)
	{
		$result = [];
		$isOffer = $this->isOffersIblock($iblockId);

		foreach ($elementIds as $id)
		{
			$valueLoader = $isOffer
				? new CategoryValue\OfferValue($iblockId, $id)
				: new CategoryValue\ElementValue($iblockId, $id);

			$result[$id] = new CategoryValue\MemoProxy($valueLoader);
		}

		return $result;
	}

	private function isOffersIblock($iblockId)
	{
		if (Main\Loader::includeModule('catalog'))
		{
			$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

			return $catalog !== false && $catalog['CATALOG_TYPE'] === \CCatalogSku::TYPE_OFFERS;
		}

		return false;
	}

	/**
	 * @param array<int, CategoryValue\MemoProxy> $valueLoaders
	 * @param array<int, int> $selected
	 *
	 * @return array<int, CategoryValue\MemoProxy>
	 */
	private function climbUpUntilSelected(array $valueLoaders, array $selected)
	{
		$result = [];

		foreach ($valueLoaders as $elementId => $valueLoader)
		{
			if ($selected[$elementId] === 0)
			{
				$result[$elementId] = $valueLoader;
				continue;
			}

			$selectedLoader = $valueLoader;
			$level = 0;

			do
			{
				$decoratedLoader = $valueLoader->decorated();

				if (
					$decoratedLoader instanceof CategoryValue\PropertyDefault
					|| $decoratedLoader instanceof CategoryValue\FieldDefault
				) // treat as parent
				{
					break;
				}

				if (
					$decoratedLoader instanceof CategoryValue\SectionValue
					&& in_array($decoratedLoader->sectionId(), $selected, true)
				)
				{
					$selectedLoader = $valueLoader;
					break;
				}

				$value = $valueLoader->value();

				if (!empty($value['CATEGORY']) || (!empty($value['PARAMETERS']) && $level > 0))
				{
					if (
						$decoratedLoader instanceof CategoryValue\SectionValue
						&& !$this->sectionLoaderInSelectedRange($valueLoader, $selected)
					)
					{
						break;
					}

					$selectedLoader = $valueLoader;
					break;
				}

				$valueLoader = $valueLoader->parent();
				++$level;
			}
			while ($valueLoader !== null);

			$result[$elementId] = $selectedLoader;
		}

		return $result;
	}

	private function sectionLoaderInSelectedRange(CategoryValue\MemoProxy $valueLoader, array $selected)
	{
		$loopLoader = $valueLoader;

		/** @var CategoryValue\MemoProxy $loopLoader */
		while ($loopLoader = $loopLoader->parent())
		{
			$decorated = $loopLoader->decorated();

			if (!($decorated instanceof CategoryValue\SectionValue)) { break; }

			if (in_array($decorated->sectionId(), $selected, true))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, CategoryValue\MemoProxy> $selectedLoaders
	 *
	 * @return array<int, CategoryValue\MemoProxy>
	 */
	private function climbUpUntilParent(array $selectedLoaders)
	{
		$result = [];

		foreach ($selectedLoaders as $elementId => $valueLoader)
		{
			do
			{
				$valueLoader = $valueLoader->parent();

				if ($valueLoader === null) { break; }

				$value = $valueLoader->value();

				if (!empty($value['CATEGORY']) || !empty($value['PARAMETERS']))
				{
					$result[$elementId] = $valueLoader;
					break;
				}
			}
			while (true);
		}

		return $result;
	}

	/**
	 * @param array<int, CategoryValue\MemoProxy> $selectedLoaders
	 * @param array<int, CategoryValue\MemoProxy> $originLoaders
	 *
	 * @return int[][]
	 */
	private function groupSelected(array $selectedLoaders, array $originLoaders)
	{
		$result = [];
		$commonGroups = new \SplObjectStorage();
		$elementGroups = [];

		foreach ($selectedLoaders as $elementId => $valueLoader)
		{
			if ($valueLoader === $originLoaders[$elementId])
			{
				$value = $valueLoader->value();
				$category = !empty($value['CATEGORY']) ? (string)$value['CATEGORY'] : 0;

				if (!isset($elementGroups[$category])) { $elementGroups[$category] = []; }

				$elementGroups[$category][] = $elementId;
			}
			else
			{
				$group = $commonGroups->offsetExists($valueLoader) ? $commonGroups->offsetGet($valueLoader) : [];
				$group[] = $elementId;

				$commonGroups->offsetSet($valueLoader, $group);
			}
		}

		foreach ($commonGroups as $valueLoader)
		{
			$result[] = $commonGroups->offsetGet($valueLoader);
		}

		foreach ($elementGroups as $elementGroup)
		{
			$result[] = $elementGroup;
		}

		return $result;
	}

	/**
	 * @param int[][] $groups
	 * @param array<int, CategoryValue\MemoProxy> $parentLoaders
	 *
	 * @return int[][]
	 */
	private function splitGroupParents(array $groups, array $parentLoaders)
	{
		$result = [];

		foreach ($groups as $group)
		{
			$parentMap = new \SplObjectStorage();
			$withoutParents = [];

			foreach ($group as $elementId)
			{
				if (!isset($parentLoaders[$elementId]))
				{
					$withoutParents[] = $elementId;
					continue;
				}

				$parentLoader = $parentLoaders[$elementId];
				$group = $parentMap->offsetExists($parentLoader) ? $parentMap->offsetGet($parentLoader) : [];
				$group[] = $elementId;

				$parentMap->offsetSet($parentLoader, $group);
			}

			foreach ($parentMap as $parentLoader)
			{
				$result[] = $parentMap->offsetGet($parentLoader);
			}

			if (!empty($withoutParents))
			{
				$result[] = $withoutParents;
			}
		}

		return $result;
	}

	/**
	 * @param array<int, CategoryValue\CategoryValue> $valueLoaders
	 * @param array<int, CategoryValue\CategoryValue> $stopLoaders
	 *
	 * @return array
	 */
	private function compileValues(array $valueLoaders, array $stopLoaders)
	{
		$result = [];

		foreach ($valueLoaders as $elementId => $valueLoader)
		{
			$value = null;
			$stopLoader = $stopLoaders[$elementId];

			while ($valueLoader !== null)
			{
				$value = CategoryProvider::mergeValue($value, $valueLoader->value());

				if ($valueLoader === $stopLoader) { break; }

				if (!empty($value['CATEGORY']))
				{
					throw new Main\SystemException("Compile {$elementId} must be stopped before filled category");
				}

				$valueLoader = $valueLoader->parent();
			}

			$result[$elementId] = $value;
		}

		return $result;
	}

	private function commonParameters(array $values)
	{
		$sameMap = null;
		$parameters = null;

		foreach ($values as $value)
		{
			if (empty($value['PARAMETERS'])) { return []; }

			$parametersMap = array_column($value['PARAMETERS'], 'VALUE', 'ID');

			if ($parameters === null)
			{
				$parameters = $value['PARAMETERS'];
				$sameMap = $parametersMap;
				continue;
			}

			foreach ($sameMap as $parameterId => $parameterValue)
			{
				if (
					!isset($parametersMap[$parameterId])
					|| !$this->isSameParameterValue($parametersMap[$parameterId], $parameterValue)
				)
				{
					unset($sameMap[$parameterId]);
				}
			}

			if (empty($sameMap)) { break; }
		}

		if ($parameters === null) { return []; }

		return array_filter($parameters, static function(array $parameter) use ($sameMap) {
			return isset($sameMap[$parameter['ID']]);
		});
	}

	private function sectionName($sectionId)
	{
		$sectionId = (int)$sectionId;

		if ($sectionId <= 0) { return null; }

		$section = Iblock\SectionTable::getRow([
			'filter' => [ '=ID' => $sectionId ],
			'select' => [ 'NAME' ],
		]);

		return $section !== null ? $section['NAME'] : null;
	}

	private function elementNames(array $elementIds)
	{
		$result = [];

		foreach (array_chunk($elementIds, 500) as $elementIdsChunk)
		{
			$elements = Iblock\ElementTable::getList([
				'filter' => [ 'ID' => $elementIdsChunk ],
				'select' => [ 'ID', 'NAME' ],
			])->fetchAll();

			$result += array_column($elements, 'NAME', 'ID');
		}

		return $result;
	}

	public function saveAction()
	{
		global $APPLICATION;

		$iblockId = $this->request->get('IBLOCK_ID');
		$incomingGroups = $this->request->getPost('VALUES');

		Assert::positiveInteger($iblockId, '$_POST[IBLOCK_ID]');
		Assert::isArray($incomingGroups, '$_POST[VALUES]');

		$iblockId = (int)$iblockId;

		$this->checkIblockReadAccess($iblockId);

		foreach ($incomingGroups as $incomingGroup)
		{
			$elementIds = array_map('intval', explode(',', $incomingGroup['ELEMENT_ID']));
			$parameterIds = $incomingGroup['PARAMETER_ID'] === '' ? [] : array_map('intval', explode(',', $incomingGroup['PARAMETER_ID']));
			$sectionId = (int)$incomingGroup['SECTION_ID'];
			$value = $this->castValue($incomingGroup['VALUE']);

			CategoryValue\ElementFetcher::preload($iblockId, $elementIds);

			$this->checkElementsWriteAccess($iblockId, $elementIds);

			if ($sectionId > 0)
			{
				$this->saveSection($iblockId, $sectionId, $elementIds, $value, $parameterIds);
			}
			else
			{
				$this->saveElements($iblockId, $elementIds, $value, $parameterIds);
			}

			CategoryValue\ElementFetcher::release();
		}

		$APPLICATION->RestartBuffer();
		/** @noinspection JSUnresolvedReference */
		echo '<script> top.BX.onCustomEvent("onYandexMarketMassiveEditDone"); </script>';
		die();
	}

	private function saveSection($iblockId, $sectionId, array $elementIds, array $incoming, array $parameterIds)
	{
		$elementLoaders = $this->valueLoaders($iblockId, $elementIds);
		$sectionLoader = CategoryValue\MemoPool::get(new CategoryValue\SectionValue($iblockId, $sectionId));
		$tree = $this->treeUntilSection($elementLoaders, $sectionLoader);

		$this->updateTree(
			$sectionLoader,
			$incoming['CATEGORY'],
			ArrayHelper::columnToKey($incoming['PARAMETERS'], 'ID'),
			array_flip($parameterIds),
			$tree
		);
	}

	private function saveElements($iblockId, array $elementIds, array $incoming, array $parameterIds)
	{
		$parameters = ArrayHelper::columnToKey($incoming['PARAMETERS'], 'ID');
		$usedParametersMap = array_flip($parameterIds);

		foreach ($this->valueLoaders($iblockId, $elementIds) as $valueLoader)
		{
			$this->updateLevel($valueLoader, $incoming['CATEGORY'], $parameters, $usedParametersMap);
		}
	}

	private function updateTree(CategoryValue\CategoryValue $valueLoader, $category, array $parameters, array $usedParametersMap, \SplObjectStorage $children = null)
	{
		list($leftParameters, $usedParametersMap) = $this->updateLevel($valueLoader, $category, $parameters, $usedParametersMap);

		if ($children === null) { return; }

		foreach ($children as $child)
		{
			$this->updateTree($child, '', $leftParameters, $usedParametersMap, $children->offsetGet($child));
		}
	}

	private function updateLevel(CategoryValue\CategoryValue $valueLoader, $category, array $parameters, array $usedParametersMap)
	{
		$value = $this->castValue($valueLoader->value());
		$changed = false;

		if ($category !== $value['CATEGORY'])
		{
			$changed = true;
			$value['CATEGORY'] = $category;
		}

		foreach ($value['PARAMETERS'] as $key => $storedParameter)
		{
			if (isset($parameters[$storedParameter['ID']]))
			{
				$usedParametersMap[$storedParameter['ID']] = true;
				$parameter = $parameters[$storedParameter['ID']];

				if (!$this->isSameParameterValue($storedParameter['VALUE'], $parameter['VALUE']))
				{
					$changed = true;
					$value['PARAMETERS'][$key] = $parameter;
				}

				unset($parameters[$storedParameter['ID']]);
			}
			else if (isset($usedParametersMap[$storedParameter['ID']]))
			{
				$changed = true;
				unset($value['PARAMETERS'][$key]);
			}
		}

		$leftParameters = [];

		foreach ($parameters as $parameterId => $parameter)
		{
			if (isset($usedParametersMap[$parameterId]))
			{
				$leftParameters[$parameterId] = $parameter;
				continue;
			}

			$changed = true;
			$usedParametersMap[$parameterId] = true;
			$value['PARAMETERS'][] = $parameter;
		}

		if ($changed)
		{
			$valueLoader->save($value);
		}

		return [ $leftParameters, $usedParametersMap ];
	}

	private function treeUntilSection(array $elementLoaders, CategoryValue\CategoryValue $sectionLoader)
	{
		$tree = new \SplObjectStorage();
		$levelMap = new \SplObjectStorage();
		$levelMap->offsetSet($sectionLoader, $tree);

		foreach ($elementLoaders as $elementId => $elementLoader)
		{
			$parentChain = [];
			$levelLoader = $elementLoader->parent();
			$parent = null;

			while ($levelLoader !== null)
			{
				if ($levelMap->offsetExists($levelLoader))
				{
					$parent = $levelMap->offsetGet($levelLoader);
					break;
				}

				array_unshift($parentChain, $levelLoader);
				$levelLoader = $levelLoader->parent();
			}

			if ($parent === null)
			{
				throw new Main\SystemException("Element {$elementId} section link changed");
			}

			foreach ($parentChain as $levelLoader)
			{
				$level = new \SplObjectStorage();

				$parent->offsetSet($levelLoader, $level);
				$levelMap->offsetSet($levelLoader, $level);

				$parent = $level;
			}

			$parent->offsetSet($elementLoader);
		}

		return $tree;
	}

	private function castValue(array $value = null)
	{
		$value = CategoryProvider::sanitizeValue($value);

		if ($value === null)
		{
			return [
				'CATEGORY' => '',
				'PARAMETERS' => [],
			];
		}

		return $value;
	}

	private function isSameParameterValue($aValue, $bValue)
	{
		$aMultiple = is_array($aValue);
		$bMultiple = is_array($bValue);

		if ($aMultiple !== $bMultiple) { return false; }

		if ($aMultiple)
		{
			if (count($aValue) !== count($bValue)) { return false; }

			return count(array_diff_assoc($aValue, $bValue)) === 0;
		}

		return (string)$aValue === (string)$bValue;
	}
	
	public function getMessage($key, array $replaces = [])
	{
		$fullKey = 'YANDEX_MARKET_CATEGORY_MASSIVE_EDIT_' . $key;
		
		return Main\Localization\Loc::getMessage($fullKey, $replaces) ?: $key;
	}
}