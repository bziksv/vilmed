<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Bitrix\Iblock;
use Bitrix\Main\Loader;

class SectionValue implements CategoryValue
{
	private $iblockId;
	private $sectionId;
	private $fieldName;

	public function __construct($iblockId, $sectionId, $fieldName = null)
	{
		$this->iblockId = (int)$iblockId;
		$this->sectionId = (int)$sectionId;
		$this->fieldName = $fieldName;
	}

	public function value()
	{
		return FieldRepository::sectionValue($this->iblockId, $this->sectionId, $this->fieldName);
	}

	public function save(array $value = null)
	{
		FieldRepository::saveSection($this->iblockId, $this->sectionId, $value);
	}

	public function parent()
	{
		if ($this->sectionId === 0)
		{
			return new PropertyDefault($this->iblockId);
		}

		if (!Loader::includeModule('iblock')) { return null; }

		$section = Iblock\SectionTable::getRow([
			'filter' => [ '=ID' => $this->sectionId ],
			'select' => [ 'IBLOCK_SECTION_ID' ],
		]);

		if (empty($section['IBLOCK_SECTION_ID']))
		{
			return new PropertyDefault($this->iblockId);
		}

		return new SectionValue($this->iblockId, (int)$section['IBLOCK_SECTION_ID'], $this->fieldName);
	}

	public function sectionId()
	{
		return $this->sectionId;
	}
}