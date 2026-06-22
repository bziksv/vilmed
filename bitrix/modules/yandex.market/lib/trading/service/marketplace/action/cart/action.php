<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\Cart;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;

/** @property TradingService\Marketplace\Provider $provider */
class Action extends TradingService\Common\Action\Cart\Action
{
	use TradingService\Marketplace\Concerns\Action\HasBasketStoreData;

	protected function createRequest(Main\HttpRequest $request, Main\Server $server)
	{
		return new Request($request, $server);
	}

	public function checkAuthorization()
	{
		try
		{
			parent::checkAuthorization();
		}
		catch (Market\Exceptions\Trading\AccessDenied $exception)
		{
			throw new Market\Exceptions\Trading\PingDenied($exception->getMessage());
		}
	}

	protected function getPriceCalculationMode()
	{
		return null;
	}

	protected function collectResponse()
	{
		$this->collectItems();
	}

	protected function collectItems()
	{
		$items = $this->request->getCart()->getItems();
		$hasValidItems = false;

		/** @var TradingService\Marketplace\Model\Cart\Item $item */
		foreach ($items as $itemIndex => $item)
		{
			$feedId = $item->getFeedId();
			$offerId = $item->getOfferId();
			$responseItem = [
				'feedId' => $feedId,
				'offerId' => $offerId,
				'count' => 0,
				'delivery' => false,
			];

			if (isset($this->basketMap[$itemIndex]))
			{
				$basketCode = $this->basketMap[$itemIndex];
				$basketResult = $this->order->getBasketItemData($basketCode);
				$basketData = $basketResult->getData();
				$basketQuantity = isset($basketData['QUANTITY']) ? (float)$basketData['QUANTITY'] : null;

				if ($basketQuantity > 0 && $basketResult->isSuccess())
				{
					$hasValidItems = true;
					$ratio = isset($this->basketPackRatio[$itemIndex]) ? $this->basketPackRatio[$itemIndex] : 1;

					$responseItem['count'] = (int)floor($basketQuantity / $ratio);
					$responseItem['delivery'] = true;
				}
			}

			$this->response->pushField('cart.items', $responseItem);
		}

		if (!$hasValidItems)
		{
			$this->response->setField('cart.items', []);
		}
	}
}