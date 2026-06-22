<?php
namespace Yandex\Market\Api\Delivery\Services;

use Yandex\Market;

class Response extends Market\Api\Reference\Response
{
	public function getDeliveryServices()
	{
		return $this->requireCollection('result.deliveryService', Model\DeliveryServiceCollection::class);
	}
}