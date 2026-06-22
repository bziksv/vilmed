<?php
namespace Yandex\Market\Component\Group;

use Yandex\Market;

class EditForm extends Market\Component\Data\EditForm
{
	use Market\Component\Concerns\HasGroup;

	public function getFields(array $select = [], array $item = null)
	{
		$result = parent::getFields($select, $item);

		if (isset($result['PARENT_ID']))
		{
			$result['PARENT_ID'] = $this->extendParentIdField($result['PARENT_ID']);
		}

		return $result;
	}

	protected function extendParentIdField($field)
	{
		if (!isset($field['SETTINGS'])) { $field['SETTINGS'] = []; }

		$field['USER_TYPE'] = Market\Ui\UserField\Manager::getUserType('enumeration');
		$field['SETTINGS']['ALLOW_NO_VALUE'] = 'N';
		$field['SETTINGS']['DEFAULT_VALUE'] = (int)$this->getComponentParam('PARENT_GROUP');
		$field['VALUES'] = $this->getGroupTreeEnum();

		return $field;
	}

	protected function getGroupDataClass()
	{
		return Market\Export\Setup\Internals\GroupTable::class;
	}
}