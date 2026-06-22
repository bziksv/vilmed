<?php

namespace Yandex\Market\Trading\Service\Common\Concerns\Action;

use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

/**
 * trait HasTasks
 * @property TradingService\Common\Provider $provider
 * @property TradingEntity\Reference\Environment $environment
 * @property TradingEntity\Reference\Order $order
 * @property TradingService\Common\Action\OrderAccept\Request|TradingService\Common\Action\OrderStatus\Request $request
 */
trait HasTasks
{
	protected $tasks = [];

	protected function addTask($path, $data)
	{
		$this->tasks[] = [
			'PATH' => $path,
			'DATA' => $data,
		];
	}

	protected function registerTasks()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getId();
		$commonData = [
			'internalId' => $this->order->getId(),
			'orderId' => $this->request->getOrder()->getId(),
			'orderNum' => $this->order->getAccountNumber(),
		];

		foreach ($this->tasks as $payload)
		{
			$task = new Market\Trading\Procedure\Task(TradingEntity\Registry::ENTITY_TYPE_ORDER, $this->order->getAccountNumber());

			$task->clear($campaignId, $payload['PATH']);
			$task->schedule($campaignId, $payload['PATH'], $payload['DATA'] + $commonData);
		}
	}
}