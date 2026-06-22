<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 * @noinspection PhpUndefinedClassInspection
 */
namespace Yandex\Market\Migration\V2100;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Yandex\Market\Export\Entity;
use Yandex\Market\Ui\Iblock as IblockUi;
use Yandex\Market\Ui\UserField\ServiceCategory;
use Yandex\Market\Export\ParamValue;

/** @noinspection PhpUnused */
class CategoryProperty
{
	public function apply()
	{
		$anyoneUsed = false;

        $this->installNewEvents();

		foreach ($this->oldFields() as $oldField)
		{
			if ($this->usedInFeed($oldField['FIELD_NAME'], [ Entity\Manager::TYPE_IBLOCK_SECTION ]))
			{
				$anyoneUsed = true;
				continue;
			}

			$this->replaceField($oldField);
		}

		foreach ($this->oldProperties() as $oldProperty)
		{
			if ($this->usedInFeed($oldProperty['ID'], [
				Entity\Manager::TYPE_IBLOCK_ELEMENT_PROPERTY,
				Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY,
			]))
			{
				$anyoneUsed = true;
				continue;
			}

			$this->replaceProperty($oldProperty);
		}

		if (!$anyoneUsed)
		{
			$this->uninstallOldEvents();
		}
	}

    private function installNewEvents()
    {
        global $USER_FIELD_MANAGER;

        foreach (IblockUi\FieldPublisher::getHandlers() as $handler)
        {
            IblockUi\FieldPublisher::register($handler);
        }

        $USER_FIELD_MANAGER->CleanCache();

        if (version_compare(ModuleManager::getVersion('main'), '20.0') === -1)
        {
            $USER_FIELD_MANAGER->arUserTypes = false;
        }
    }

    private function replaceField(array $oldField)
    {
		$newField = array_diff_key($oldField, [ 'ID' => true ]);
	    $newField['USER_TYPE_ID'] = IblockUi\CategoryField::USER_TYPE;

        foreach ([ 'EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL', 'ERROR_MESSAGE', 'HELP_MESSAGE' ] as $message)
        {
            if (isset($oldField[$message]))
            {
                $newField[$message] = [ LANGUAGE_ID => $oldField[$message] ];
            }
        }

        (new \CUserTypeEntity())->Delete($oldField['ID']);
	    (new \CUserTypeEntity())->Add($newField);
    }

    private function replaceProperty(array $oldProperty)
    {
		$newProperty = array_diff_key($oldProperty, [
			'ID' => true,
			'TIMESTAMP_X' => true,
			'DEFAULT_VALUE' => true,
		]);
	    $newProperty['PROPERTY_TYPE'] = 'S';
		$newProperty['USER_TYPE'] = IblockUi\CategoryProperty::USER_TYPE;

        \CIblockProperty::Delete($oldProperty['ID']);
        (new \CIBlockProperty)->Add($newProperty);
    }

    private function usedInFeed($field, array $types)
    {
        return (bool)ParamValue\Table::getRow([
			'select' => [ 'ID' ],
			'filter' => [
				'=XML_TYPE' => ParamValue\Table::XML_TYPE_VALUE,
				'=SOURCE_TYPE' => $types,
				'=SOURCE_FIELD' => $field,
				'=PARAM.XML_TAG' => 'categoryId',
            ],
        ]);
    }

	private function oldFields()
	{
        $result = [];

		$query = \CUserTypeEntity::GetList([], [
            'USER_TYPE_ID' => ServiceCategory\Provider::USER_TYPE,
            'LANG' => LANGUAGE_ID,
        ]);

        while ($row = $query->Fetch())
        {
            $result[] = $row;
        }

		return $result;
	}

    private function oldProperties()
    {
        if (!Loader::includeModule('iblock')) { return []; }

        $query = PropertyTable::getList([
            'filter' => [ '=USER_TYPE' => ServiceCategory\Provider::USER_TYPE ],
        ]);

        return $query->fetchAll();
    }

	private function uninstallOldEvents()
	{
		foreach (ServiceCategory\Event::getHandlers() as $handler)
		{
			ServiceCategory\Event::unregister($handler);
		}
	}
}