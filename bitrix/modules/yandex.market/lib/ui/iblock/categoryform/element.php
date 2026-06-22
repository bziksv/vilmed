<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Ui\Iblock\CategoryValue;

class Element implements ElementForm
{
	private $property;
	private $elementId;
	private $sectionId;
	private $sections;

	public function __construct(array $property, $elementId, $sectionId = null, array $sections = null)
	{
		$this->property = $property;
		$this->elementId = (int)$elementId;
		$this->sectionId = $sectionId;
		$this->sections = $sections;
	}

	public function type()
	{
		return Factory::ELEMENT;
	}

	public function elementId()
	{
		return $this->elementId;
	}

	public function payload()
	{
		return [
			'elementId' => $this->elementId,
		];
	}

	public function fields()
	{
		return [
			'sectionId' => [ '#RESULT_IBLOCK_ELEMENT_SECTION_ID', 'select' ],
			'sections' => 'IBLOCK_SECTION[]',
		];
	}

	public function theme()
	{
		return CategoryProvider::THEME_FORM;
	}

	public function parentValue(array $fields = null)
	{
		return $this->sectionParent($fields) ?: $this->elementParent();
	}

	private function sectionParent(array $fields = null)
	{
		if ($fields !== null)
		{
			$sectionId = isset($fields['sectionId']) && $fields['sectionId'] !== '' ? (int)$fields['sectionId'] : null;
			$sections = isset($fields['sections']) && is_array($fields['sections']) ? $fields['sections'] : null;
		}
		else
		{

			$sectionId = $this->elementId === 0 ? $this->sectionId : null;
			$sections = $this->sections;
		}

		$primarySectionId = $this->primarySection($sectionId, $sections);

		if ($primarySectionId === null) { return null; }

		return new CategoryValue\SectionValue($this->property['IBLOCK_ID'], $primarySectionId);
	}

	private function elementParent()
	{
		return (new CategoryValue\ElementValue($this->property['IBLOCK_ID'], $this->elementId))->parent();
	}

	private function primarySection($sectionId, array $sections = null)
	{
		if ($sections === null) { return $sectionId; }

		if (!empty($sections) && !in_array($sectionId, $sections, true))
		{
			return (int)min($sections);
		}

		if ($sectionId > 0)
		{
			return (int)$sectionId;
		}

		return null;
	}
}