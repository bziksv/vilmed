<?php
namespace Yandex\Market\Catalog\Segment\Stocks;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;

class Format implements Param\Format
{
	use Concerns\HasMessage;

    private $forSubmit;

    public function __construct($forSubmit = false)
    {
        $this->forSubmit = (bool)$forSubmit;
    }

	public function getDocumentationUrl()
	{
		return 'https://yandex.ru/dev/market/partner-api/doc/ru/reference/stocks/updateStocks';
	}

	public function getTag()
	{
		self::includeSelfMessages();

		return new Xml\Tag\Base([
			'name' => 'campaignStocks',
			'children' => [
				new Tag\Count([ 'required' => $this->forSubmit, 'preselect' => true ]),
			],
		]);
	}
}