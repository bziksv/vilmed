<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\OrderAccept;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;

/** @property TradingService\Marketplace\Provider $provider */
class Action extends TradingService\Common\Action\OrderAccept\Action
{
	use TradingService\Marketplace\Concerns\Action\HasBasketStoreData;

	/** @var Request */
	protected $request;

	protected function createRequest(Main\HttpRequest $request, Main\Server $server)
	{
		return new Request($request, $server);
	}

	protected function getOrderNum()
	{
		if ($this->provider->getOptions()->useAccountNumberTemplate())
		{
			return $this->order->getId();
		}

		return parent::getOrderNum();
	}

	protected function fillOrder()
	{
		parent::fillOrder();
		$this->fillContact();
	}

	protected function fillProperties()
	{
		parent::fillProperties();
		$this->fillBuyerProperties();
	}

	protected function fillBuyerProperties()
	{
		$buyer = $this->request->getOrder()->getBuyer();

		if ($buyer === null) { return null; }

		$this->setMeaningfulPropertyValues($buyer->getMeaningfulValues());
	}

	protected function fillDelivery()
	{
		$deliveryId = $this->provider->getOptions()->getDeliveryId();

		if ($deliveryId !== '')
		{
			$this->order->createShipment($deliveryId);
		}
	}

	protected function fillBasketStore()
	{
		$this->order->setBasketStore($this->provider->getOptions()->getProductSelfStores());
	}

	protected function fillPaySystem()
	{
		$this->fillPaySystemSubsidy();
		$this->fillPaySystemCommon();
	}

	protected function fillPaySystemSubsidy()
	{
		$options = $this->provider->getOptions();
		$subsidySystemId = $options->getSubsidyPaySystemId();

		if ($subsidySystemId !== '' && $options->includeBasketSubsidy())
		{
			$subsidySum = $this->calculateSubsidySum();

			if ($subsidySum > 0)
			{
				$this->order->createPayment($subsidySystemId, $subsidySum, [
					'SUBSIDY' => true,
					'ORDER_ID' => $this->request->getOrder()->getId(),
				]);
			}
		}
	}

	protected function calculateSubsidySum()
	{
		return $this->request->getOrder()->getItems()->getSubsidySum();
	}

	protected function fillPaySystemCommon()
	{
		$paySystemId = $this->resolvePaySystem();

		if ($paySystemId !== '')
		{
			$this->order->createPayment($paySystemId);
		}
	}

	protected function resolvePaySystem()
	{
		$paymentType = $this->request->getOrder()->getPaymentType();

		return $this->provider->getOptions()->getPaySystemId($paymentType);
	}

	protected function getItemPrice(Market\Api\Model\Order\Item $item)
	{
		if ($this->provider->getOptions()->includeBasketSubsidy())
		{
			$result = $item->getPrice() + $item->getSubsidy();
		}
		else
		{
			$result = $item->getPrice();
		}

		return $result;
	}

	protected function fillContact()
	{
		try
		{
			$command = new TradingService\Common\Command\AnonymousContact($this->provider, $this->environment);
			$contacts = $command->execute();

			$this->order->fillContacts($contacts);
		}
		catch (Main\SystemException $exception)
		{
			$this->provider->getLogger()->warning($exception);
		}
	}

	protected function isAllowModifyBasket()
	{
		return (
			parent::isAllowModifyBasket()
			|| (
				$this->request->isDownload()
				&& $this->provider->getOptions()->getYandexMode() === TradingService\Marketplace\Options::YANDEX_MODE_PULL
			)
		);
	}

	protected function makeData()
	{
		return
			$this->makeFakeData()
			+ $this->makeShipmentData();
	}

	protected function makeFakeData()
	{
		if (!$this->request->getOrder()->isFake()) { return []; }

		return [
			'FAKE' => 'Y',
		];
	}

	protected function makeShipmentData()
	{
		$shipmentDates = $this->request->getOrder()->getMeaningfulShipmentDates();

		if (empty($shipmentDates)) { return []; }

		$shipmentDate = reset($shipmentDates);

		if (!($shipmentDate instanceof Main\Type\DateTime)) { return []; }

		return [
			'SHIPMENT_DATE' => $shipmentDate->format(Market\Data\DateTime::FORMAT_DEFAULT_FULL),
		];
	}
}