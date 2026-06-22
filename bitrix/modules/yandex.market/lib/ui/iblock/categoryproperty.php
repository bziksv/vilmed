<?php
namespace Yandex\Market\Ui\Iblock;

use Bitrix\Main;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Extension;
use Yandex\Market\Ui\UserField\Helper\Renderer;

class CategoryProperty
{
    use Concerns\HasMessage;

    const USER_TYPE = 'ym_category';

    public static function getUserTypeDescription()
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => static::USER_TYPE,
            'DESCRIPTION' => self::getMessage('DESCRIPTION', null, 'Yandex.Market: Category'),
            'GetAdminListViewHTML' => [static::class, 'getAdminListViewHTML'],
            'GetPropertyFieldHtml' => [static::class, 'getPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [static::class, 'getPropertyFieldHtmlMulty'],
            'PrepareSettings' => [static::class, 'prepareSettings'],
            'GetSettingsHTML' => [static::class, 'getSettingsHtml'],
	        'GetLength' => [static::class, 'getLength'],
	        'ConvertToDB' => [static::class, 'convertToDB'],
	        'ConvertFromDB' => [static::class, 'convertFromDB'],
        ];
    }

    public static function createDefault($iblockId)
    {
        Assert::positiveInteger($iblockId, 'iblockId');

        $userType = static::getUserTypeDescription();

        $newProperty = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            'PROPERTY_TYPE' => $userType['PROPERTY_TYPE'],
            'USER_TYPE' => $userType['USER_TYPE'],
            'NAME' => $userType['DESCRIPTION'],
            'SORT' => 90,
        ];

        $provider = new \CIBlockProperty;
        $added = $provider->Add($newProperty);

        if (!$added)
        {
            throw new Main\SystemException($provider->LAST_ERROR ?: 'cant create category property');
        }

	    CategoryValue\PropertyRepository::resetFieldCache();

        return (int)$added;
    }

	/** @noinspection PhpUnusedParameterInspection */
	public static function getLength($property, $value)
	{
		$sanitized = CategoryProvider::sanitizeValue($value['VALUE']);

		if ($sanitized === null) { return 0; }

		return mb_strlen(CategoryProvider::encodeValue($sanitized));
	}

    /** @noinspection PhpUnusedParameterInspection */
    public static function convertFromDb($property, $value)
	{
		return [ 'VALUE' => CategoryProvider::decodeFieldValue($value['VALUE']) ];
	}

    /** @noinspection PhpUnusedParameterInspection */
    public static function convertToDB($property, $value)
	{
        $value['VALUE'] = CategoryProvider::encodeValue(
			CategoryProvider::sanitizeValue($value['VALUE'])
        );

		return $value;
	}

	/** @noinspection PhpUnusedParameterInspection */
	public static function getAdminListViewHTML($property, $value, $htmlControl)
	{
		Extension::load('@Ui.AssetsLoader');

		return Renderer::failSafe(static function() use ($property, $value, $htmlControl) {
			GridClickPrevent::disableColumn('PROPERTY_' . $property['ID']);

			$form = CategoryForm\Factory::makeElement($property, $htmlControl);
			$elementId = $form instanceof CategoryForm\ElementForm ? $form->elementId() : null;

			if (empty($value['VALUE']['CATEGORY']))
			{
				$value['VALUE'] = CategoryProvider::mergeValue(
					$value['VALUE'],
					CategoryValue\Facade::compile($form->parentValue())
				);
			}

			$html = CategoryProvider::displayValue($value['VALUE']);

			if ($elementId > 0)
			{
				$html .= CategoryProvider::skuStatusComponent([
					'IBLOCK_ID' => $property['IBLOCK_ID'],
					'ELEMENT_ID' => $elementId,
					'DELAYED' => 'Y',
					'THEME' => $form->theme(),
					'ALONE' => empty($value['VALUE']),
				]);
			}

			return $html;
		});
	}

    public static function getPropertyFieldHtml($property, $value, $htmlControl)
    {
		return Renderer::failSafe(static function() use ($property, $value, $htmlControl) {
			$form = CategoryForm\Factory::makeElement($property, $htmlControl);
			$elementId = $form instanceof CategoryForm\ElementForm ? $form->elementId() : null;
			$value = CategoryProvider::decodeValue($value['VALUE']);

			$html = CategoryProvider::editComponent([
                'PROPERTY_TYPE' => 'element',
                'PROPERTY_ID' => $property['ID'],
                'MULTIPLE' => 'N',
                'DELAYED' => 'Y',
                'VALUE' => $value,
				'PARENT_VALUE' => empty($value['CATEGORY']) ? CategoryValue\Facade::compile($form->parentValue()) : null,
                'CONTROL_NAME' => $htmlControl['VALUE'],
				'FORM_TYPE' => $form->type(),
				'FORM_FIELDS' => $form->fields(),
				'FORM_PAYLOAD' => $form->payload(),
				'THEME' => $form->theme(),
			]);

			if ($elementId > 0)
			{
				$html .= CategoryProvider::skuStatusComponent([
					'IBLOCK_ID' => $property['IBLOCK_ID'],
					'ELEMENT_ID' => $elementId,
					'DELAYED' => 'Y',
					'THEME' => $form->theme(),
				]);
			}

			return $html;
		});
    }

	/** @noinspection PhpUnusedParameterInspection */
	public static function getPropertyFieldHtmlMulty($property, $values, $htmlControl)
    {
	    return self::getMessage('MULTIPLE_NOT_SUPPORTED', null, 'multiple not supported');
    }

    public static function getSettingsHtml($property, $htmlControl, &$fields)
    {
        $fields = [
            'HIDE' => ['ROW_COUNT', 'COL_COUNT', 'WITH_DESCRIPTION', 'FILTER_HINT', 'DISPLAY_TYPE', 'MULTIPLE_CNT', 'FILTRABLE', 'SMART_FILTER', 'SEARCHABLE', 'DEFAULT_VALUE'],
        ];

		$iblockId = (int)$property['IBLOCK_ID'];
        $settings = static::prepareSettings($property);

        $apiKeyLabel = self::getMessage('API_KEY');
        $apiKeyValue = htmlspecialcharsbx($settings['API_KEY']);

		$html = <<<HTML
            <tr>
                <td>{$apiKeyLabel}</td>    
                <td><input type="text" name="{$htmlControl['NAME']}[API_KEY]" value="{$apiKeyValue}" /></td>    
            </tr>
HTML;

		if (Entity\Iblock\Provider::getCatalogIblockId($iblockId) === null)
		{
		    $defaultValueLabel = self::getMessage('DEFAULT_VALUE');
		    $defaultValueControl = Renderer::failSafe(static function() use ($property, $htmlControl, $settings) {
				$iblockId = (int)$property['IBLOCK_ID'];
				$value = CategoryProvider::decodeValue($settings['DEFAULT_VALUE']);

			    return CategoryProvider::editComponent([
				    'PROPERTY_TYPE' => 'element',
				    'PROPERTY_ID' => (int)$property['ID'],
					'PROPERTY_IBLOCK' => $iblockId,
				    'VALUE' => $value,
				    'PARENT_VALUE' => empty($value['CATEGORY'])
					    ? (new CategoryValue\FieldDefault($iblockId))->value()
					    : null,
				    'CONTROL_NAME' => $htmlControl['NAME'] . '[DEFAULT_VALUE]',
				    'API_KEY_FIELD' => $htmlControl['NAME'] . '[API_KEY]',
				    'THEME' => CategoryProvider::THEME_FORM,
			    ]);
		    });

			$html .= <<<HTML
	            <tr>
	                <td>{$defaultValueLabel}</td>    
	                <td>{$defaultValueControl}</td>    
	            </tr>
HTML;
		}

		return $html;
    }

    public static function prepareSettings($property)
    {
        return array_filter([
            'API_KEY' => isset($property['USER_TYPE_SETTINGS']['API_KEY']) ? trim($property['USER_TYPE_SETTINGS']['API_KEY']) : null,
	        'DEFAULT_VALUE' => isset($property['USER_TYPE_SETTINGS']['DEFAULT_VALUE'])
		        ? CategoryProvider::encodeValue(CategoryProvider::sanitizeValue($property['USER_TYPE_SETTINGS']['DEFAULT_VALUE']))
		        : null,
        ]);
    }
}