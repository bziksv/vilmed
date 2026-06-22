<?php
namespace Yandex\Market\Catalog\Segment\Price;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Type;

class CampaignPriceFormat implements Param\Format
{
	use Concerns\HasMessage;

	private $forSubmit;

	public function __construct($forSubmit = false)
	{
		$this->forSubmit = (bool)$forSubmit;
	}

	public function getDocumentationUrl()
	{
		return null;
	}

	public function getTag()
	{
		self::includeSelfMessages();

		return new Xml\Tag\Base([
			'name' => 'campaignPrice',
			'children' => array_filter([
				new Tag\Price([ 'name' => 'price', 'required' => $this->forSubmit ]),
				new Xml\Tag\OldPrice([ 'name' => 'discountBase', 'value_precision' => 0 ]),
				new Xml\Tag\Vat([ 'value_format' => Type\VatType::FORMAT_NUMERIC ]),
				$this->forSubmit ? new Tag\CurrencyId([ 'required' => true ]) : null,
			]),
		]);
	}
}