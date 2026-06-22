<?php
namespace Yandex\Market\Ui\UserField;

use Yandex\Market\Catalog\Segment;

/** @noinspection PhpUnused */
class CatalogCardType extends CatalogSegmentType
{
    protected static function mapCampaignValues(array $values)
    {
        $result = [];

        foreach ($values as $value)
        {
            if (!isset($value['CATEGORY_ID'])) { continue; }

            $result[(int)$value['CATEGORY_ID']] = $value;
        }

        return $result;
    }

    protected static function businessGroups($baseName, array $valuesMap, Segment\BusinessConfig $businessConfig = null)
    {
        if ($businessConfig === null) { return []; }

        $businessName = "{$baseName}[0]";

        return [
            [
                'INPUT_NAME' => "{$businessName}[PARAM]",
                'FORMAT' => $businessConfig->format(),
                'VALUE' => isset($valuesMap[0]['PARAM']) ? $valuesMap[0]['PARAM'] : null,
                'HIDDEN' => [
                    "{$businessName}[ID]" => !empty($valuesMap[0]['ID']) ? (int)$valuesMap[0]['ID'] : null,
                    "{$businessName}[CATEGORY_ID]" => 0,
                ],
            ]
        ];
    }
}