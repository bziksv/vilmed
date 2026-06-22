<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Collection;

class OfferCardCollection extends Collection
{
	public static function getItemReference()
	{
		return OfferCard::class;
	}
}
