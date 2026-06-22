<?php
namespace Yandex\Market\Component\CatalogCategory;

use Bitrix\Main;
use Yandex\Market\Component;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Ui\Iblock\CategoryValue;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Ui\UserField;

class EditForm extends Component\Base\EditForm
{
	protected $business;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		parent::__construct($component, $componentParameters);

		$this->business = new Component\Molecules\Business();
	}

	public function getFields(array $select = [], array $item = null)
	{
		$result = [];
		$usedIblocks = $this->business->usedIblocks((array)$item, $this->getComponentParam('SKU_MAP_FIELD'));

		foreach ($usedIblocks as $iblockId)
		{
			$fieldName = "CATEGORY_{$iblockId}";

			$result[$fieldName] = UserField\Helper\Field::extend([
				'NAME' => "CATEGORY_{$iblockId}",
				'TYPE' => 'catalogCategory',
				'CONTEXT' => Entity\Iblock\Provider::getContext($iblockId),
				'API_KEY_FIELD' => $this->getComponentParam('API_KEY_FIELD'),
			], $fieldName);
		}

		return $result;
	}

	public function validate(array $data, array $fields)
	{
		return new Main\Entity\Result();
	}

	public function load($primary, array $select = [], $isCopy = false)
	{
		return [];
	}

	public function extend(array $data, array $fields)
	{
		foreach ($fields as $name => $field)
		{
			if (isset($data[$name])) { continue; } // filled in request

			$iblockId = $this->fieldIblockId($name);

			if ($iblockId === null) { continue; }

			$data[$name] = $this->storedIblock($iblockId);
		}

		return $data;
	}

	public function add(array $data)
	{
		return $this->save(new Main\Entity\AddResult(), $data);
	}

	public function update($primary, array $data)
	{
		return $this->save(new Main\Entity\UpdateResult(), $data);
	}

	private function save(Main\Entity\Result $saveResult, array $data)
	{
		foreach ($data as $name => $values)
		{
			try
			{
				$iblockId = $this->fieldIblockId($name);

				if ($iblockId === null) { continue; }

				if (!is_array($values)) { $values = []; }

				$this->saveIblock($iblockId, $values);
			}
			catch (Main\SystemException $exception)
			{
				$saveResult->addError(new Main\Entity\EntityError($exception->getMessage()));
			}
		}

		if ($saveResult instanceof Main\Entity\AddResult && $saveResult->isSuccess())
		{
			$saveResult->setId(1);
		}

		return $saveResult;
	}

	private function saveIblock($iblockId, array $incoming)
	{
		$stored = $this->storedIblock($iblockId);
		$changed = $this->onlyChanged($incoming, $stored);

		if (empty($changed)) { return; }

		$this->checkIblockField($iblockId);

		foreach ($changed as $sectionId => $sectionIncoming)
		{
			if ($sectionId === 0)
			{
				$this->saveProperty($iblockId, $sectionIncoming);
			}
			else
			{
				$this->saveSection($iblockId, $sectionId, $sectionIncoming);
			}
		}
	}

	private function storedIblock($iblockId)
	{
		$fieldName = CategoryValue\FieldRepository::fieldName($iblockId);

		$values = [
			0 => CategoryValue\Facade::compile(new CategoryValue\PropertyDefault($iblockId)),
		];

		if ($fieldName === null || !Main\Loader::includeModule('iblock')) { return $values; }

		$query = \CIBlockSection::GetList(
			['LEFT_MARGIN' => 'ASC'],
			['IBLOCK_ID' => $iblockId, 'GLOBAL_ACTIVE' => 'Y'],
			false,
			['ID', $fieldName]
		);

		while ($section = $query->Fetch())
		{
			$values[$section['ID']] = CategoryProvider::decodeValue($section[$fieldName]);
		}

		return $values;
	}

	private function onlyChanged(array $incoming, array $stored)
	{
		$result = [];

		foreach ($incoming as $sectionId => $incomingValue)
		{
			$incomingValue = isset($incoming[$sectionId]) ? CategoryProvider::sanitizeValue($incoming[$sectionId]) : null;
			$sectionValue = isset($stored[$sectionId]) ? $stored[$sectionId] : null;

			if (!$this->isChangedValue($sectionValue, $incomingValue)) { continue; }

			$result[$sectionId] = $incomingValue;
		}

		return $result;
	}

	private function checkIblockField($iblockId)
	{
		$fieldName = CategoryValue\FieldRepository::fieldName($iblockId);

		if ($fieldName !== null) { return; }

		CategoryProvider::createDefault($iblockId);
	}

	/** @noinspection PhpRedundantOptionalArgumentInspection */
	private function saveProperty($iblockId, array $value = null)
	{
		$propertyLoader = new CategoryValue\PropertyDefault($iblockId);
		$fieldLoader = $propertyLoader->parent();

		Assert::notNull($fieldLoader, 'fieldLoader');

		$propertyValue = $propertyLoader->value();

		if (empty($value['CATEGORY']))
		{
			$propertyLoader->save(null);
			$fieldLoader->save(null);
			return;
		}

		if (!empty($propertyValue['CATEGORY']))
		{
			$propertyLoader->save($value);
			return;
		}

		$fieldValue = $fieldLoader !== null ? $fieldLoader->value() : null;

		if (!empty($fieldValue['CATEGORY']))
		{
			$fieldLoader->save($value);
			$propertyLoader->save(null);

			return;
		}

		$propertyLoader->save($value);
	}

	private function saveSection($iblockId, $sectionId, array $value = null)
	{
		$valueLoader = new CategoryValue\SectionValue($iblockId, $sectionId);
		$valueLoader->save($value);
	}

	private function fieldIblockId($fieldName)
	{
		if (!preg_match('#^CATEGORY_(\d+)$#', $fieldName, $matches)) { return null; }

		return (int)$matches[1];
	}

	private function isChangedValue(array $sectionStored = null, array $sectionIncoming = null)
	{
		$storedCategory = $sectionStored !== null ? $sectionStored['CATEGORY'] : null;
		$incomingCategory = $sectionIncoming !== null ? $sectionIncoming['CATEGORY'] : null;

		if ($storedCategory !== $incomingCategory) { return true; }

		$storedParameters = $sectionStored !== null ? array_column($sectionStored['PARAMETERS'], 'VALUE', 'ID') : [];
		$incomingParameters = $sectionStored !== null ? array_column($sectionIncoming['PARAMETERS'], 'VALUE', 'ID') : [];

		if (
			count($storedParameters) !== count($incomingParameters)
			|| count(array_diff_key($incomingParameters, $storedParameters)) > 0
		)
		{
			return true;
		}

		foreach ($incomingParameters as $parameterId => $parameterValue)
		{
			if (!$this->isSameParameterValue($parameterValue, $storedParameters[$parameterId]))
			{
				return true;
			}
		}

		return false;
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
}