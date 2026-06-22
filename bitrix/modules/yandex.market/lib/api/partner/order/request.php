<?php
namespace Yandex\Market\Api\Partner\Order;

use Yandex\Market;
use Yandex\Market\Reference\Assert;

/**
 * @method Response execute()
 */
class Request extends Market\Api\Partner\Reference\Request
{
	protected $orderId;

	public function getPath()
	{
		return '/v2/campaigns/' . $this->getCampaignId() . '/orders/' . $this->getOrderId() .'.json';
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}

	public function setOrderId($orderId)
	{
		$this->orderId = $orderId;
	}

	public function getOrderId()
	{
		Assert::notNull($this->orderId, 'orderId');

		return (string)$this->orderId;
	}

	protected function validationQueue()
	{
		$queue = parent::validationQueue();
		$queue->add(new Market\Api\Reference\Validator\RequiredKeys([
			'order',
		]));

		return $queue;
	}
}