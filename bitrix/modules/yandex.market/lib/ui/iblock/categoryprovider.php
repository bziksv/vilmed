<?php
namespace Yandex\Market\Ui\Iblock;

use Yandex\Market\Config;
use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Utils;
use Yandex\Market\Utils\JsonSerializer;
use Yandex\Market\Utils\Value;

class CategoryProvider
{
	use Concerns\HasMessage;

	const THEME_FORM = 'form';
	const THEME_GRID = 'grid';
	const THEME_MASSIVE_EDIT = 'massive-edit';
	const THEME_TAB = 'tab';

	const PARAMETERS_SHOW_LIMIT = 1;

	public static function isCreatedDefault($iblockId)
	{
		$option = (string)\CUserOptions::GetOption(Config::getLangPrefix() . 'EXPORT_MARKET_PROPERTY', "autoCreateCategory{$iblockId}", 'N', 0);

		return ($option === 'Y');
	}

	private static function markCreatedDefault($iblockId)
	{
		\CUserOptions::SetOption(Config::getLangPrefix() . 'EXPORT_MARKET_PROPERTY', "autoCreateCategory{$iblockId}", 'Y', true);
	}

	public static function createDefault($iblockId)
	{
		$iblockContext = Entity\Iblock\Provider::getContext($iblockId);

		self::markCreatedDefault($iblockId);

		$created = [];

		if ($iblockContext['HAS_OFFER'])
		{
			$created[] = [
				Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY,
				CategoryValue\PropertyRepository::propertyId($iblockContext['OFFER_IBLOCK_ID']) ?: CategoryProperty::createDefault($iblockContext['OFFER_IBLOCK_ID']),
			];
		}

		$created[] = [
			Entity\Manager::TYPE_IBLOCK_ELEMENT_PROPERTY,
			CategoryValue\PropertyRepository::propertyId($iblockContext['IBLOCK_ID']) ?: CategoryProperty::createDefault($iblockContext['IBLOCK_ID']),
		];

		$created[] = [
			Entity\Manager::TYPE_IBLOCK_SECTION,
			CategoryValue\FieldRepository::fieldName($iblockContext['IBLOCK_ID']) ?: CategoryField::createDefault($iblockContext['IBLOCK_ID']),
		];

		return $created;
	}

	public static function encodeValue($value)
    {
        $value = self::castValue($value);

        if ($value === null) { return null; }
        if (empty($value['CATEGORY']) && empty($value['PARAMETERS'])) { return null; }

        return JsonSerializer::encode($value);
    }

	public static function decodeFieldValue($value)
	{
		if (is_string($value))
		{
			$value = htmlspecialcharsback($value);
		}

		return self::decodeValue($value);
	}

    public static function decodeValue($value)
    {
        if (is_string($value))
        {
            if ($value === '') { return null; }

            if (!JsonSerializer::isEncodedObject($value))
            {
                return [
                    'CATEGORY' => $value,
                    'PARAMETERS' => [],
                ];
            }

            $value = JsonSerializer::decode($value);
        }

        return self::castValue($value);
    }

    private static function castValue($value)
    {
        if (!is_array($value)) { return null; }

        return [
            'CATEGORY' => isset($value['CATEGORY']) ? (string)$value['CATEGORY'] : '',
            'PARAMETERS' => isset($value['PARAMETERS']) && is_array($value['PARAMETERS'])
                ? $value['PARAMETERS']
                : [],
        ];
    }

	public static function sanitizeValue($value)
	{
		$value = self::decodeValue($value);

		if ($value === null) { return null; }

		// unset empty multiple values
		$value['PARAMETERS'] = array_map(static function($parameter) {
			if (!isset($parameter['VALUE']) || !is_array($parameter['VALUE'])) { return $parameter; }

			$values = [];

			foreach ($parameter['VALUE'] as $value)
			{
				if (!is_string($value)) { continue; }

				$value = trim($value);

				if ($value === '') { continue; }

				$values[] = $value;
			}

			$parameter['VALUE'] = $values;

			return $parameter;
		}, $value['PARAMETERS']);

		// unset parameters with empty value
		$value['PARAMETERS'] = array_values(array_filter($value['PARAMETERS'], static function($parameter) {
			return (isset($parameter['VALUE']) && !Value::isEmpty($parameter['VALUE']));
		}));

		if (empty($value['CATEGORY']) && empty($value['PARAMETERS'])) { return null; }

		return $value;
	}

    public static function editComponent(array $componentParameters)
    {
        global $APPLICATION;

        return (string)$APPLICATION->IncludeComponent(
            'yandex.market:admin.property.category',
            '',
            $componentParameters,
            false,
            [ 'HIDE_ICONS' => 'Y' ]
        );
    }

    public static function skuStatusComponent(array $componentParameters)
    {
        global $APPLICATION;

        return (string)$APPLICATION->IncludeComponent(
            'yandex.market:admin.sku.status',
            '',
            $componentParameters,
            false,
            [ 'HIDE_ICONS' => 'Y' ]
        );
    }

	public static function mergeValue(array $result = null, array $value = null)
	{
		if ($result === null) { return $value; }
		if ($value === null || !empty($result['CATEGORY'])) { return $result; }

		if (!empty($value['CATEGORY']))
		{
			$result['CATEGORY'] = $value['CATEGORY'];
		}

		if (!empty($value['PARAMETERS']))
		{
			array_unshift($result['PARAMETERS'], ...$value['PARAMETERS']);
		}

		return $result;
	}

	public static function displayValue(array $value = null)
	{
		if (empty($value['CATEGORY']) && empty($value['PARAMETERS'])) { return ''; }

		$partials = [];

		if (!empty($value['CATEGORY']))
		{
			$partials[] = (string)$value['CATEGORY'];
		}

		$parameters = array_filter(array_map(
			static function($parameter) { return self::parameterDisplayValue($parameter); },
			$value['PARAMETERS']
		));

		$partials[] = self::glueParametersDisplayValue($parameters);

		$content = implode('<br />', $partials);

		if ($content === '') { return $content; }

		return '<div style="min-width: 300px;">' . $content . '</div>';
	}

	private static function parameterDisplayValue($parameter)
	{
		if (!isset($parameter['NAME'], $parameter['VALUE'])) { return null; }

		$label = (string)$parameter['NAME'];
		$values = is_array($parameter['VALUE']) ? $parameter['VALUE'] : [ $parameter['VALUE'] ];
		$values = array_map(static function($value) {
			if (!is_string($value)) { return null; }

			if ($value === 'Y' || $value === 'N')
			{
				return self::getMessage('BOOLEAN_' . $value, null, $value);
			}

			if (preg_match('/^(.*)\s\[\d+]$/', $value, $matches))
			{
				return $matches[1];
			}

			return $value;
		}, $values);

		if (isset($rowValue['UNIT']) && preg_match('/^(.*)\s\[\d+]$/', $rowValue['UNIT'], $unitMatches))
		{
			$label .= ", {$unitMatches[1]}";
		}

		return sprintf('<small>%s: %s</small>', $label, implode(', ', $values));
	}

	private static function glueParametersDisplayValue(array $partials)
	{
		$count = count($partials);

		if ($count <= self::PARAMETERS_SHOW_LIMIT + 1)
		{
			return implode('<br />', $partials);
		}

		$before = implode('<br />', array_slice($partials, 0, self::PARAMETERS_SHOW_LIMIT));
		$after = implode('<br />', array_slice($partials, self::PARAMETERS_SHOW_LIMIT));
		$afterCount = $count - self::PARAMETERS_SHOW_LIMIT;
		$summary = self::getMessage('PARAMETERS_DISPLAY_MORE', [
			'#COUNT#' => $afterCount,
			'#UNIT#' => Utils::sklon($afterCount, [
				self::getMessage('PARAMETERS_DISPLAY_MORE_UNIT_1'),
				self::getMessage('PARAMETERS_DISPLAY_MORE_UNIT_2'),
				self::getMessage('PARAMETERS_DISPLAY_MORE_UNIT_5'),
			]),
		]);

		return <<<HTML
			{$before}
			<details>
				<summary><small>{$summary}</small></summary>
				{$after}
			</details>
HTML;
	}
}