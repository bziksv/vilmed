<?php
namespace Yandex\Market\Export\Xml\Tag;

use Bitrix\Main;
use Yandex\Market;
use Bitrix\Catalog;

class CargoTypes extends Base
{
	use Market\Reference\Concerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'name' => 'cargo-types',
			'value_type' => Market\Type\Manager::TYPE_BOOLEAN,
			'overrides' => [
                false => null,
				true => 'CIS_REQUIRED',
			],
		];
	}

	/** @noinspection PhpDeprecationInspection */
	public function getSourceRecommendation(array $context = [])
	{
        if (!$context['HAS_CATALOG']) { return []; }
        if (!Main\Loader::includeModule('catalog') || !class_exists(Catalog\Product\SystemField::class)) { return []; }

		return [
            [
                'TYPE' => Market\Export\Entity\Manager::TYPE_CATALOG_PRODUCT,
                'FIELD' => Catalog\Product\SystemField::CODE_MARKING_CODE_GROUP,
            ],
        ];
	}
}