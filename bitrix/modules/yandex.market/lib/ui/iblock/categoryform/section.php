<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Ui\Iblock\CategoryValue;
use Yandex\Market\Ui\Iblock\CategoryValue\SectionValue;

class Section implements Form
{
	use Concerns\HasMessage;

	private $userField;
	private $sectionId;

	public function __construct(array $field, $sectionId)
	{
		$this->userField = $field;
		$this->sectionId = $sectionId;
	}

	public function type()
	{
		return Factory::SECTION;
	}

	public function payload()
	{
		return [
			'sectionId' => $this->sectionId,
		];
	}

	public function fields()
	{
		return [
			'parentId' => 'IBLOCK_SECTION_ID',
		];
	}

	public function theme()
	{
		return CategoryProvider::THEME_FORM;
	}

	public function parentValue(array $fields = null)
	{
		$iblockId = CategoryValue\FieldRepository::iblockId($this->userField['ENTITY_ID']);

		if (isset($fields['parentId']))
		{
			return new SectionValue($iblockId, (int)$fields['parentId'], $this->userField['FIELD_NAME']);
		}

		return (new SectionValue($iblockId, $this->sectionId, $this->userField['FIELD_NAME']))->parent();
	}
}