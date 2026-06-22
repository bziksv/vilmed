<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs\Action\AdminList;

use Yandex\Market;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @property TradingService\MarketplaceDbs\Provider $provider
 * @property Request $request
 */
class Action extends TradingService\Marketplace\Action\AdminList\Action
{
	use Market\Reference\Concerns\HasMessage;

	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	protected function getOrderRow(Market\Api\Model\Order $order, TradingEntity\Reference\Order $bitrixOrder = null)
	{
		$serviceUniqueKey = $this->provider->getUniqueKey();
		$storedCancellationAccept = Market\Trading\State\OrderData::getValue($serviceUniqueKey, $order->getId(), 'CANCELLATION_ACCEPT');

		$result = parent::getOrderRow($order, $bitrixOrder);
		$result['DISPATCH_TYPE'] = $this->getDispatchType($order);
		$result['OUTLET_STORAGE_LIMIT_DATE'] = $this->getOutletStorageLimitDate($order);

		if ($result['TOTAL'] !== null)
		{
			$result['TOTAL'] += $order->getDeliveryTotal() + $order->getSubsidies()->getDeliverySum();
			$result['SUBSIDY'] += $order->getSubsidies()->getDeliverySum();
		}

		if ($storedCancellationAccept !== null)
		{
			$result['CANCELLATION_ACCEPT'] = $storedCancellationAccept;
			$result['STATUS_LANG'] .= self::getMessage(
				sprintf('CANCELLATION_ACCEPT_%s_STATUS_SUFFIX', $storedCancellationAccept),
				null,
				' (' . $storedCancellationAccept . ')'
			);
		}
		else if ($this->request->onlyWaitingForCancellationApprove())
		{
			$result['CANCELLATION_ACCEPT'] = Market\Data\Trading\CancellationAccept::WAIT;
			$result['STATUS_LANG'] .= self::getMessage('CANCELLATION_ACCEPT_WAIT_STATUS_SUFFIX');
		}
		else
		{
			$result['CANCELLATION_ACCEPT'] = null;
		}

		return $result;
	}

	protected function getDispatchType(Market\Api\Model\Order $order)
	{
		if (!$order->hasDelivery()) { return null; }

		$delivery = $order->getDelivery();

		if (!($delivery instanceof TradingService\MarketplaceDbs\Model\Order\Delivery)) { return null; }

		return $delivery->getDispatchType();
	}

	protected function getOutletStorageLimitDate(Market\Api\Model\Order $order)
	{
		$delivery = $order->hasDelivery() ? $order->getDelivery() : null;

		if (!($delivery instanceof TradingService\MarketplaceDbs\Model\Order\Delivery)) { return null; }

		return $delivery->getOutletStorageLimitDate();
	}

	protected function isCancelAllowed(Market\Api\Model\Order $order)
	{
		$status = $order->getStatus();

		if ($status === TradingService\MarketplaceDbs\Status::STATUS_UNPAID) { return false; }

		$statusOrder = $this->provider->getStatus()->getProcessOrder();
		$currentOrder = isset($statusOrder[$status]) ? $statusOrder[$status] : null;
		$cancelOrder = $statusOrder[TradingService\MarketplaceDbs\Status::STATUS_CANCELLED];

		return ($currentOrder === null || $cancelOrder > $currentOrder);
	}
}