<?php

namespace Yandex\Market\Export\Xml\Format\Turbo;

use Yandex\Market\Export\Xml;

class Simple extends Xml\Format\YandexMarket\Simple
	implements Xml\Format\Reference\FormatDeprecated
{
	public function getPublishNote()
	{
		return Data\Info::getPublishNote();
	}

	public function getRoot()
	{
		$result = parent::getRoot();
		$shop = $result->getChild('shop');

		if ($shop !== null)
		{
			$this->removeChildTags($shop, [
				'cpa',
				'enable_auto_discounts',
			]);
		}

		return $result;
	}

	public function getOffer()
	{
		$result = parent::getOffer();

		$this->overrideTags($result->getChildren(), [
			'url' => [ 'required' => true ],
			'description' => [ 'required' => true, 'value_tags' => '<h3><br><ul><ol><li><p>' ],
		]);

		$this->removeChildTags($result, [
			'cpa',
			'enable_auto_discounts',
			'count',
			'cargo-types',
			'market_category_id',
		]);

		return $result;
	}
}