<?php

namespace Yandex\Market\Ui\Trading;

use Yandex\Market;
use Bitrix\Main;

class ShipmentList extends Reference\EntityList
{
	use Market\Reference\Concerns\HasMessage;

	protected function getTargetEntity()
	{
		return Market\Trading\Entity\Registry::ENTITY_TYPE_LOGISTIC_SHIPMENT;
	}

	protected function getUserOptionCategory()
	{
		return 'yamarket_shipment_grid';
	}

	public function setTitle()
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('TITLE'));
	}

	protected function showGrid(Market\Trading\Campaign\Model $campaign)
	{
		global $APPLICATION;

		$documents = $this->getPrintDocuments($campaign);
		$activities = $this->getServiceActivities($campaign);

		$this->initializePrintActions($campaign, $documents);
		$this->initializeActivityActions($campaign, $activities);

		$APPLICATION->IncludeComponent(
			'yandex.market:admin.grid.list',
			'',
			$this->gridActionsParameters($campaign, $documents, $activities)
			+ [
				'GRID_ID' => $this->getGridId(),
				'PROVIDER' => Market\Component\TradingShipment\GridList::class,
				'CONTEXT_MENU_EXCEL' => 'Y',
				'SETUP' => $campaign->getTrading(),
				'BASE_URL' => $this->getComponentBaseUrl($campaign),
				'PAGER_FIXED' => Market\Component\TradingShipment\GridList::PAGE_SIZE,
				'DEFAULT_FILTER_FIELDS' => [
					'DATE',
					'STATUS',
					'ORDER_ID',
				],
				'DEFAULT_LIST_FIELDS' => [
					'ID',
					'EXTERNAL_ID',
					'DATE',
					'SHIPMENT_TYPE',
					'STATUS',
					'DELIVERY_SERVICE',
					'DRAFT_COUNT',
					'PLANNED_COUNT',
					'FACT_COUNT',
				],
				'CHECK_ACCESS' => !Market\Ui\Access::isWriteAllowed(),
				'RELOAD_EVENTS' => [
					'yamarketFormSave',
				],
			]
		);
	}

	protected function getGridId()
	{
		return 'YANDEX_MARKET_ADMIN_TRADING_SHIPMENT_LIST';
	}

	protected function getCampaignCollection($businessId)
	{
		$collection = parent::getCampaignCollection($businessId);

		return $collection->filter(function(Market\Trading\Campaign\Model $campaign) {
			return (
				$campaign->getTradingId() > 0
				&& $campaign->getTrading()->getService()->getRouter()->hasDataAction('admin/shipments')
			);
		});
	}
}