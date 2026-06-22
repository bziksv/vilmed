<?php
namespace Yandex\Market\Trading\Facade\Action;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Procedure;

class SendOrderBoxes
{
	use Concerns\HasMessage;

	protected $orderContext;
	protected $items;
	protected $allowInstances = true;
	protected $allowRemove = false;

	public function __construct($order)
	{
		$this->orderContext = OrderContext::fromOrder($order);
	}

	public function allowInstances($allow = true)
	{
		$this->allowInstances = (bool)$allow;

		return $this;
	}

	public function allowRemove($allow = true)
	{
		$this->allowRemove = (bool)$allow;

		return $this;
	}

	public function submit()
	{
		$result = new Market\Result\Base();

		try
		{
			if (!$this->orderContext->isOur())
			{
				$result->addWarning(new Market\Error\Base(self::getMessage('NOT_OUR_ORDER', [ '#ORDER_ID#' => $this->orderContext->order()->getId() ])));
				return $result;
			}

			$tradingOrder = $this->orderContext->order();
			$body = $this->makeItems($tradingOrder);

			if (empty($body['items']) && $body['allowRemove'] !== true)
			{
				$result->addWarning(new Market\Error\Base(self::getMessage('NOTHING_TO_SUBMIT', [ '#ORDER_ID#' => $this->orderContext->order()->getId() ])));
				return $result;
			}

			$procedure = new Procedure\Runner(TradingEntity\Registry::ENTITY_TYPE_ORDER, $tradingOrder->getAccountNumber());
			$procedure->run($this->orderContext->setup(), 'send/order/boxes', $body);
		}
		catch (Main\SystemException $exception)
		{
			$result->addError(new Market\Error\Base($exception->getMessage()));
		}

		return $result;
	}

	protected function makeItems(TradingEntity\Reference\Order $tradingOrder)
	{
		$result = [
			'items' => [],
			'allowRemove' => $this->allowRemove,
		];
		$result += $this->tradingPayload($tradingOrder);

		foreach ($tradingOrder->getExistsBasketItemCodes() as $basketCode)
		{
			$basketData = $tradingOrder->getBasketItemData($basketCode)->getData();

			if (!isset($basketData['PRODUCT_ID'], $basketData['XML_ID'])) { continue; }

			$resultItem = [
				'productId' => $basketData['PRODUCT_ID'],
				'xmlId' => $basketData['XML_ID'],
			];

			if ($this->allowRemove)
			{
				$resultItem['count'] = (float)$basketData['QUANTITY'];
			}

			if ($this->allowInstances && !empty($basketData['INSTANCES']))
			{
				$resultItem['instances'] = [];

				foreach ($basketData['INSTANCES'] as $instance)
				{
					$resultInstance = [];

					foreach ($instance as $type => $value)
					{
						$value = trim($value);

						if ($value === '') { continue; }

						$resultInstance[mb_strtolower($type)] = $value;
					}

					if (empty($resultInstance)) { continue; }

					$resultItem['instances'][] = $resultInstance;
				}
			}

			if (!$this->allowRemove && empty($resultItem['instances']))
			{
				continue;
			}

			$result['items'][] = $resultItem;
		}

		return !empty($result['items']) ? $result : null;
	}

	protected function tradingPayload(TradingEntity\Reference\Order $tradingOrder)
	{
		$tradingInfo = $this->orderContext->tradingInfo();

		return [
			'internalId' => $tradingOrder->getId(),
			'orderId' => $tradingInfo['ORDER_ID'],
			'orderNum' => $tradingOrder->getAccountNumber(),
			'immediate' => false,
		];
	}
}