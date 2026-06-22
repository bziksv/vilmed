<?php

namespace Yandex\Market\Export\Xml\Format\Marketplace;

use Yandex\Market\Export\Xml;

class Catalog extends Xml\Format\YandexMarket\Simple
	implements Xml\Format\Reference\FormatDeprecated
{
	use Concerns\TagRules;

	public function getSupportedFields()
	{
		return [
			'SHOP_DATA',
		];
	}

	public function getRoot()
	{
		$result = parent::getRoot();

		$this->sanitizeRoot($result);

		return $result;
	}

	public function getOffer()
	{
		$result = parent::getOffer();

		$this->extendOffer($result);
		$this->sanitizeOffer($result);
		$this->overrideTags($result->getChildren(), [
			'vendor' => [ 'required' => true ],
			'delivery' => [ 'deprecated' => true ],
			'pickup' => [ 'deprecated' => true ],
			'leadtime' => [ 'deprecated' => true ],
			'delivery-weekday' => [ 'deprecated' => true ],
			'availability' => [ 'deprecated' => true ],
			'transport-unit' => [ 'deprecated' => true ],
			'min-delivery-pieces' => [ 'deprecated' => true ],
			'quantum' => [ 'deprecated' => true ],
		]);

		return $result;
	}
}
