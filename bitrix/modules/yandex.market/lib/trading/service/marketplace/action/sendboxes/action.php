<?php

namespace Yandex\Market\Trading\Service\Marketplace\Action\SendBoxes;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

/** @property TradingService\Marketplace\Provider $provider */
/** @property Request $request */
class Action extends TradingService\Reference\Action\DataAction
{
	use Market\Reference\Concerns\HasLang;

	protected static function includeMessages()
	{
		Main\Localization\Loc::loadMessages(__FILE__);
	}

	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	public function getAudit()
	{
		return Market\Logger\Trading\Audit::SEND_BOXES;
	}

	public function process()
	{
		$this->sendBoxes();
		$this->logBoxes();
	}

	protected function sendBoxes()
	{
		$request = $this->buildSendRequest();

		$sendResult = $request->send();

		if (!$sendResult->isSuccess())
		{
			$message = static::getLang('TRADING_ACTION_SEND_BOXES_RESPONSE_FAIL', [
				'#MESSAGE#' => implode(PHP_EOL, $sendResult->getErrorMessages())
			]);
			throw new Market\Exceptions\Api\Request($message);
		}
	}

	protected function buildSendRequest()
	{
        /** @var TradingService\Marketplace\Api\SendBoxes\Request $result */
		$result = $this->provider->getRequestFactory()->create(TradingService\Marketplace\Api\SendBoxes\Request::class);
		$result->setOrderId($this->request->getOrderId());
		$result->setShipmentId($this->request->getShipmentId());
		$result->setBoxes($this->request->getBoxes());

		return $result;
	}

	protected function logBoxes()
	{
		$logger = $this->provider->getLogger();
		$message = static::getLang('TRADING_ACTION_SEND_BOXES_SEND_LOG', [
			'#SHIPMENT_ID#' => $this->request->getShipmentId() ?: '',
			'#BOX_COUNT#' => count($this->request->getBoxes()),
		]);

		$logger->info($message, [
			'AUDIT' => $this->getAudit(),
			'ENTITY_TYPE' => TradingEntity\Registry::ENTITY_TYPE_ORDER,
			'ENTITY_ID' => $this->request->getOrderNumber(),
		]);
	}
}