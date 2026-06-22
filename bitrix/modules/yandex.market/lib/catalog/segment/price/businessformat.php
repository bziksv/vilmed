<?php
namespace Yandex\Market\Catalog\Segment\Price;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;

class BusinessFormat implements Param\Format
{
	use Concerns\HasMessage;

	private $onlyDefaultPrice;
	private $forSubmit;

	public function __construct($onlyDefaultPrice, $forSubmit = false)
	{
		$this->onlyDefaultPrice = (bool)$onlyDefaultPrice;
		$this->forSubmit = (bool)$forSubmit;
	}

	public function getDocumentationUrl()
	{
		return $this->onlyDefaultPrice
			? 'https://yandex.ru/dev/market/partner-api/doc/ru/reference/business-assortment/updateBusinessPrices'
			: 'https://yandex.ru/dev/market/partner-api/doc/ru/reference/assortment/updatePrices';
	}

	public function getTag()
	{
		self::includeSelfMessages();

		return new Xml\Tag\Base([
			'name' => 'businessPrice',
			'children' => [
				new Tag\Price([ 'name' => 'basicPrice', 'required' => $this->onlyDefaultPrice || $this->forSubmit, 'preselect' => true ]),
				new Xml\Tag\OldPrice([ 'name' => 'discountBase', 'preselect' => true, 'price_name' => 'basicPrice', 'value_precision' => 0 ]),
				new Xml\Tag\PurchasePrice([ 'name' => 'purchasePrice', 'preselect' => true, 'value_precision' => 0 ]),
				new Xml\Tag\PurchasePrice([ 'name' => 'cofinancePrice', 'preselect' => false, 'visible' => true, 'value_precision' => 0 ]),
				new Tag\AdditionalExpenses([ 'visible' => true ]),
				new Tag\CurrencyId([ 'required' => true, 'indirect' => true ]),
			],
		]);
	}
}