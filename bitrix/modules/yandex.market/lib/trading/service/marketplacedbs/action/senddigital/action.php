<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\SendDigital;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @property Request $request
*/
class Action extends TradingService\Reference\Action\DataAction
{
	use Market\Reference\Concerns\HasMessage;
	use TradingService\Common\Concerns\Action\HasOrder;
	use TradingService\Common\Concerns\Action\HasOrderMarker;

	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	public function process()
	{
		try
		{
			$orderId = $this->request->getOrderId();
			$items = $this->request->getItems();

			$this->sendDigitalGoods($orderId, $items);

			$this->resolveOrderMarker(true);
		}
		catch (Market\Exceptions\Api\Request $exception)
		{
			$sendResult = new Main\Result();
			$sendResult->addError(new Main\Error(
				$exception->getMessage(),
				$exception->getCode()
			));

			$this->resolveOrderMarker(false, $sendResult);
			throw $exception;
		}
	}

	protected function sendDigitalGoods($orderId, $items)
	{
        /** @var TradingService\MarketplaceDbs\Api\DeliverDigitalGoods\Request $request */
        $request = $this->provider->getRequestFactory()->create(TradingService\MarketplaceDbs\Api\DeliverDigitalGoods\Request::class);
        $request->setOrderId($orderId);
        $request->setItems($this->sanitizeDigitalGoodsItems($items));

        $request->execute();
	}

	protected function sanitizeDigitalGoodsItems($items)
	{
		foreach ($items as &$item)
		{
			if (isset($item['activate_till']))
			{
				$activateTill = Market\Data\Date::sanitize($item['activate_till']);

				if ($activateTill === null) { continue; }

				$item['activate_till'] = Market\Data\Date::convertForService($activateTill, 'Y-m-d');
			}
		}
		unset($item);

		return $items;
	}

	protected function getMarkerCode()
	{
		return $this->provider->getDictionary()->getErrorCode('SEND_DIGITAL_ERROR');
	}
}