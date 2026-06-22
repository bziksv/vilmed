<?php
namespace Yandex\Market\Trading\Facade\Action;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Setup as TradingSetup;

class OrderContext
{
	use Concerns\HasMessage;
	use Concerns\HasOnce;

	/** @var TradingEntity\Reference\Order */
	protected $order;

	/**
	 * @param Sale\Order|TradingEntity\Reference\Order|int $order
	 * @return static
	 */
	public static function fromOrder($order)
	{
		if ($order instanceof self)
		{
			return $order;
		}

		if ($order instanceof TradingEntity\Reference\Order)
		{
			return new static($order);
		}

		if ($order instanceof Sale\Order)
		{
			return static::fromSaleOrder($order);
		}

		if (is_numeric($order))
		{
			return static::fromOrderId($order);
		}

		throw new Main\ArgumentException(sprintf(
			'unknown order argument format %s',
			is_scalar($order) ? $order : gettype($order)
		));
	}

	public static function fromOrderId($id)
	{
		$order = TradingEntity\Manager::createEnvironment()->getOrderRegistry()->loadOrder($id);

		return new static($order);
	}

	public static function fromSaleOrder(Sale\Order $saleOrder)
	{
		$order = TradingEntity\Manager::createEnvironment()->getOrderRegistry()->wakeUpOrder($saleOrder);

		return new static($order);
	}

	public function __construct(TradingEntity\Reference\Order $order)
	{
		$this->order = $order;
	}

	public function isOur()
	{
		return $this->order->getTradingInfo() !== null;
	}

	public function order()
	{
		return $this->order;
	}

	public function setup()
	{
		$tradingInfo = $this->tradingInfo();

		if ($tradingInfo === null)
		{
			throw new Main\SystemException(self::getMessage('ORDER_TRADING_INFO_NOT_FOUND', [
				'#ORDER_ID#' => $this->order->getId(),
			]));
		}

		return TradingSetup\ModelPool::getByTradingInfo($tradingInfo);
	}

	public function tradingInfo()
	{
		return $this->once('tradingInfo', null, function() {
			return $this->order->getTradingInfo();
		});
	}
}