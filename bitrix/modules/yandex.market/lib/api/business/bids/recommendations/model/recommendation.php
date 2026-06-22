<?php
namespace Yandex\Market\Api\Business\Bids\Recommendations\Model;

use Yandex\Market\Api\Reference\Model;

class Recommendation extends Model
{
	public function getSku()
	{
		return (string)$this->requireField('sku');
	}

	public function getBid()
	{
		return (int)$this->requireField('bid');
	}

	public function getBidRecommendations()
	{
		return $this->getCollection('bidRecommendations', BidCollection::class);
	}

	public function getPriceRecommendations()
	{
		return $this->getCollection('priceRecommendations', PriceCollection::class);
	}
}