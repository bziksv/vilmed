<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\SendCis;

use Yandex\Market\Trading\Service as TradingService;

/**
 * @deprecated
 * @property TradingService\Marketplace\Provider $provider
 * @property Request $request
 */
class Action extends TradingService\Marketplace\Action\SendIdentifiers\Action
{
	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	protected function buildRequest()
	{
		$result = $this->provider->getRequestFactory()->create(TradingService\Marketplace\Api\SendCis\Request::class);
		$items = $this->makeItems();

		$result->setOrderId($this->request->getOrderId());
		$result->setItems($items);

		$this->sentItems = $items;

		return $result;
	}
}