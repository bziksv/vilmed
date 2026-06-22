<?php
namespace Yandex\Market\Api\Business\OfferCards;

use Yandex\Market\Api\Reference\Collection;

class RecommendationCollection extends Collection
{
	public static function getItemReference()
	{
		return Recommendation::class;
	}
}