<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

class ElementValue implements CategoryValue
{
	protected $iblockId;
	protected $elementId;

	public function __construct($iblockId, $elementId)
	{
		$this->iblockId = (int)$iblockId;
		$this->elementId = (int)$elementId;
	}

	public function value()
	{
		return PropertyRepository::elementValue($this->iblockId, $this->elementId);
	}

	public function save(array $value = null)
	{
		PropertyRepository::saveElement($this->iblockId, $this->elementId, $value);
	}

	public function parent()
	{
		if ($this->elementId === 0)
		{
			return new PropertyDefault($this->iblockId);
		}

		$element = ElementFetcher::element($this->elementId, [ 'IBLOCK_SECTION_ID' ]);

		if (empty($element['IBLOCK_SECTION_ID']))
		{
			return new PropertyDefault($this->iblockId);
		}

		return new SectionValue($this->iblockId, (int)$element['IBLOCK_SECTION_ID']);
	}
}