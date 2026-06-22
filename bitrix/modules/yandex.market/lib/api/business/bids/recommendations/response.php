<?php
namespace Yandex\Market\Api\Business\Bids\Recommendations;

use Yandex\Market\Api;

class Response extends Api\Reference\ResponseWithResult
{
	public function getRecommendations()
	{
		return $this->requireCollection('result.recommendations', Model\RecommendationCollection::class);
	}
}