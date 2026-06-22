<?php
namespace Yandex\Market\Ui\Iblock;

use Bitrix\Main\Application;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Exceptions;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui\Extension;
use Yandex\Market\Ui\UserField\Helper\Renderer;

class CategoryField
{
    use Concerns\HasMessage;

    const USER_TYPE = 'ym_category';
    const DEFAULT_FIELD_NAME = 'UF_YAMARKET_CATEGORY';

    public static function getUserTypeDescription()
    {
        return [
            'BASE_TYPE' => 'string',
            'USER_TYPE_ID' => static::USER_TYPE,
            'DESCRIPTION' => self::getMessage('DESCRIPTION', null, 'Yandex.Market: Category'),
            'CLASS_NAME' => static::class,
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function getDbColumnType($userField)
    {
        return 'text';
    }

    public static function createDefault($iblockId)
    {
        Assert::positiveInteger($iblockId, 'iblockId');

        $userType = static::getUserTypeDescription();

        $newField = [
            'ENTITY_ID' => "IBLOCK_{$iblockId}_SECTION",
            'FIELD_NAME' => static::DEFAULT_FIELD_NAME,
            'USER_TYPE_ID' => $userType['USER_TYPE_ID'],
            'SORT' => 90,
        ];

        foreach (['EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL'] as $message)
        {
            $newField[$message] = [ LANGUAGE_ID => $userType['DESCRIPTION'] ];
        }

        $added = (new \CUserTypeEntity())->Add($newField);

        if (!$added)
        {
            throw Exceptions\Facade::fromApplication();
        }

	    CategoryValue\FieldRepository::resetFieldCache();

        return static::DEFAULT_FIELD_NAME;
    }

	/**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
	public static function OnBeforeSave($arUserField, $value)
	{
		return CategoryProvider::encodeValue(
			CategoryProvider::sanitizeValue($value)
		);
	}

    /** @noinspection PhpUnusedParameterInspection */
    public static function getAdminListViewHTML($userField, $htmlControl)
    {
        Extension::load('@Ui.AssetsLoader');

	    return Renderer::failSafe(static function() use ($userField, $htmlControl) {
		    GridClickPrevent::disableColumn($userField['FIELD_NAME']);

		    $value = CategoryProvider::decodeFieldValue($htmlControl['VALUE']);

			if (empty($value['CATEGORY']))
			{
				$form = CategoryForm\Factory::makeSection($userField, $htmlControl);
				$value = CategoryProvider::mergeValue(
					$value,
					CategoryValue\Facade::compile($form->parentValue())
				);
			}

		    return CategoryProvider::displayValue($value);
	    });
    }

	public static function getAdminListEditHtml($userField, $htmlControl)
	{
		return self::getEditFormHTML($userField, $htmlControl);
	}

    /** @noinspection PhpUnused */
    public static function getEditFormHTML($userField, $htmlControl)
    {
		return Renderer::failSafe(static function() use ($userField, $htmlControl) {
			$form = CategoryForm\Factory::makeSection($userField, $htmlControl);
			$value = CategoryProvider::decodeFieldValue($htmlControl['VALUE']);

            return CategoryProvider::editComponent([
                'PROPERTY_TYPE' => 'section',
                'PROPERTY_ID' => $userField['ID'],
                'DELAYED' => 'Y',
                'VALUE' => $value,
                'PARENT_VALUE' => empty($value['CATEGORY']) ? CategoryValue\Facade::compile($form->parentValue()) : null,
                'CONTROL_NAME' => $htmlControl['NAME'],
				'FORM_TYPE' => $form->type(),
                'FORM_FIELDS' => $form->fields(),
                'FORM_PAYLOAD' => $form->payload(),
	            'THEME' => $form->theme(),
            ]);
		});
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     */
    public static function getEditFormHtmlMulty($userField, $htmlControl)
    {
	    return self::getMessage('MULTIPLE_NOT_SUPPORTED', null, 'multiple not supported');
    }

    public static function getSettingsHtml($userField, $htmlControl, $varsFromForm)
    {
        $settings = $varsFromForm ? $GLOBALS[$htmlControl['NAME']] : $userField['SETTINGS'];

        if (!is_array($settings)) { $settings = []; }

        $apiKeyLabel = self::getMessage('API_KEY');
        $apiKeyValue = htmlspecialcharsbx($settings['API_KEY']);

        $defaultValueLabel = self::getMessage('DEFAULT_VALUE');
	    $defaultValueControl = Renderer::failSafe(static function() use ($userField, $htmlControl, $settings) {
		    if (isset($userField['ENTITY_ID']))
		    {
				$iblockId = CategoryValue\FieldRepository::iblockId($userField['ENTITY_ID']);
			    $propertyParameters = [
				    'PROPERTY_TYPE' => 'userField',
				    'PROPERTY_ID' => (int)$userField['ID'],
			    ];
		    }
			else
			{
				$entityId = (string)Application::getInstance()->getContext()->getRequest()->get('ENTITY_ID');
				$iblockId = $entityId !== '' ? CategoryValue\FieldRepository::iblockId($entityId) : null;

				if ($iblockId !== null)
				{
					$propertyParameters = [
						'PROPERTY_TYPE' => 'element',
						'PROPERTY_ID' => 0,
						'PROPERTY_IBLOCK' => $iblockId,
					];
				}
				else
				{
					$propertyParameters = [
						'PROPERTY_TYPE' => 'userField',
						'PROPERTY_ID' => 0,
					];
				}
			}

            $html = CategoryProvider::editComponent($propertyParameters + [
                'VALUE' => CategoryProvider::decodeValue($settings['DEFAULT_VALUE']),
                'CONTROL_NAME' => $htmlControl['NAME'] . '[DEFAULT_VALUE]',
	            'API_KEY_FIELD' => $htmlControl['NAME'] . '[API_KEY]',
	            'THEME' => CategoryProvider::THEME_FORM,
            ]);

			if ($iblockId !== null)
			{
				$propertyDefault = (new CategoryValue\PropertyDefault($iblockId))->value();

				if (!empty($propertyDefault['CATEGORY']))
				{
					$message = new \CAdminMessage([
						'TYPE' => 'ERROR',
						'MESSAGE' => self::getMessage('PROPERTY_REDEFINED', [
							'#PROPERTY_URL#' => Admin\Path::getPageUrl('iblock_edit_property', [
								'ID' => CategoryValue\PropertyRepository::propertyId($iblockId),
								'IBLOCK_ID' => $iblockId,
								'admin' => 'Y',
								'lang' => LANGUAGE_ID,
							]),
						]),
						'HTML' => true,
					]);
					$html .= $message->Show();
				}
			}

			return $html;
        });

        return <<<HTML
            <tr>
                <td>{$apiKeyLabel}</td>    
                <td><input type="text" name="{$htmlControl['NAME']}[API_KEY]" value="{$apiKeyValue}" /></td>    
            </tr>
            <tr>
				<td>{$defaultValueLabel}</td>
				<td>{$defaultValueControl}</td>
			</tr>
HTML;
    }

    public static function prepareSettings($userField = [])
    {
        return array_filter([
            'API_KEY' => isset($userField['SETTINGS']['API_KEY']) ? trim($userField['SETTINGS']['API_KEY']) : '',
            'DEFAULT_VALUE' => CategoryProvider::encodeValue(CategoryProvider::sanitizeValue($userField['SETTINGS']['DEFAULT_VALUE'])),
        ]);
    }
}