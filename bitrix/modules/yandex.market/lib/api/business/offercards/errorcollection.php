<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Collection;

class ErrorCollection extends Collection
{
	public static function getItemReference()
	{
		return Error::class;
	}
}