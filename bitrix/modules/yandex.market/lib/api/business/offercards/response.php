<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api;

class Response extends Api\Partner\Reference\Response
{
	public function getOfferCards()
	{
		return $this->requireCollection('result.offerCards', OfferCardCollection::class);
	}
}
