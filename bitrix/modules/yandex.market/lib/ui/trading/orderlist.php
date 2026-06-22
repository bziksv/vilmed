<?php
namespace Yandex\Market\Ui\Trading;

use Yandex\Market;

class OrderList extends Reference\EntityList
{
	use Market\Reference\Concerns\HasMessage;

	protected function getTargetEntity()
	{
		return Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER;
	}

	protected function getUserOptionCategory()
	{
		return 'yamarket_order_grid';
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
				'PROVIDER' => Market\Component\TradingOrder\GridList::class,
				'CONTEXT_MENU_EXCEL' => 'Y',
				'SETUP' => $campaign->getTrading(),
				'BASE_URL' => $this->getComponentBaseUrl($campaign),
				'PAGER_LIMIT' => 50,
				'DEFAULT_FILTER_FIELDS' => [
					'STATUS',
					'DATE_CREATE',
					'DATE_SHIPMENT',
					'FAKE',
				],
				'DEFAULT_LIST_FIELDS' => [
					'ID',
					'ACCOUNT_NUMBER',
					'DATE_CREATE',
					'BASKET',
					'TOTAL',
					'SUBSIDY',
					'STATUS_LANG',
				],
				'CHECK_ACCESS' => !Market\Ui\Access::isWriteAllowed(),
				'RELOAD_EVENTS' => [
					'yamarketShipmentSubmitEnd',
					'yamarketFormSave',
				],
			]
		);
	}

	protected function getGridId()
	{
		return 'YANDEX_MARKET_ADMIN_TRADING_ORDER_LIST';
	}

	protected function getOrderListRowActions(Market\Trading\Campaign\Model $campaign, $documents, $activities)
	{
		return
			$this->getOrderListRowCommonActions($campaign)
			+ $this->getOrderListRowActivityActions($activities)
			+ $this->getOrderListRowPrintActions($documents)
			+ $this->getOrderListRowAdditionalActions();
	}

	protected function getOrderListRowCommonActions(Market\Trading\Campaign\Model $campaign)
	{
		return [
		    'ACCEPT' => [
		        'ACTION' => 'accept',
                'TEXT' => self::getMessage('ACTION_ORDER_ACCEPT'),
            ],
			'EDIT' => [
				'ICON' => 'view',
				'TEXT' =>
					$campaign->getTrading()->getService()->getInfo()->getMessage('ORDER_VIEW_TAB')
					?: self::getMessage('ACTION_ORDER_VIEW'),
				'MODAL' => 'Y',
				'MODAL_TITLE' => self::getMessage('ACTION_ORDER_VIEW_MODAL_TITLE'),
				'MODAL_PARAMETERS' => [
					'width' => 1024,
					'height' => 750,
				],
				'URL' => Market\Ui\Admin\Path::getModuleUrl('trading_order_view', [
					'lang' => LANGUAGE_ID,
					'view' => 'popup',
					'campaign' => $campaign->getId(),
					'site' => $campaign->getTrading()->getSiteId(),
				]) . '&id=#ID#',
				'DEFAULT' => true,
			],
		];
	}

	protected function getOrderListRowAdditionalActions()
	{
		return [
			'STATUS' => [
				'ACTION' => 'status',
				'TEXT' => self::getMessage('ACTION_ORDER_STATUS'),
			],
		];
	}

	protected function getOrderListGroupActions(Market\Trading\Campaign\Model $campaign, $documents, $activities)
	{
		return
			parent::getOrderListGroupActions($campaign, $documents, $activities)
			+ $this->getOrderListGroupBoxActions($campaign);
	}

	protected function getOrderListGroupActionsParams($activities)
	{
		$result = parent::getOrderListGroupActionsParams($activities);
		$chooses = [
			'boxes',
		];

		foreach ($chooses as $choose)
		{
			$result['select_onchange'] .= sprintf(
				'BX(\'%1$s_chooser\') && (BX(\'%1$s_chooser\').style.display = (this.value == \'%1$s\' ? \'block\' : \'none\'));',
				$choose
			);
		}

		return $result;
	}

	protected function getOrderListUiGroupActions(Market\Trading\Campaign\Model $campaign, array $documents, array $activities)
	{
		return
			parent::getOrderListUiGroupActions($campaign, $documents, $activities)
			+ $this->getOrderListUiGroupBoxActions($campaign)
			+ $this->getOrderListUiGroupAcceptActions();
	}

	protected function getOrderListGroupBoxActions(Market\Trading\Campaign\Model $campaign)
	{
		if (!$this->isSupportBoxes($campaign)) { return []; }

		$variants = $this->getBoxesVariants();

		return [
			'boxes' => self::getMessage('ACTION_SEND_BOXES'),
			'boxes_chooser' => [
				'type' => 'html',
				'value' => $this->makeGroupActionSelectHtml('boxes', $variants),
			],
		];
	}

	protected function getOrderListUiGroupAcceptActions()
	{
		return [
			'accept' => self::getMessage('ACTION_ORDER_ACCEPT'),
		];
	}

	protected function getOrderListUiGroupBoxActions(Market\Trading\Campaign\Model $campaign)
	{
		if (!$this->isSupportBoxes($campaign)) { return []; }

		return [
			'boxes' => [
				'type' => 'select',
				'name' => 'boxes',
				'label' => self::getMessage('ACTION_SEND_BOXES'),
				'items' => $this->getBoxesVariants(),
			],
		];
	}

	protected function isSupportBoxes(Market\Trading\Campaign\Model $campaign)
	{
		$service = $campaign->getTrading()->getService();
		$feature = $service->getFeature();

		return (
			$service->getRouter()->hasAction('send/boxes')
			&& (
				!($feature instanceof Market\Trading\Service\Marketplace\Feature)
				|| $feature->supportBoxesWithoutItems()
			)
		);
	}

	protected function getBoxesVariants()
	{
		$variants = [];
		$plural = [
			self::getMessage('ACTION_SEND_BOXES_COUNT_1'),
			self::getMessage('ACTION_SEND_BOXES_COUNT_2'),
			self::getMessage('ACTION_SEND_BOXES_COUNT_5'),
		];

		for ($count = 1; $count <= 10; ++$count)
		{
			$variants[] = [
				'VALUE' => $count,
				'NAME' => $count . ' ' . Market\Utils::sklon($count, $plural),
			];
		}

		return $variants;
	}

	protected function makeGroupActionSelectHtml($name, $variants)
	{
		$html = sprintf('<div id="%s_chooser" style="display: none;">', $name);
		$html .= sprintf('<select name="%s">', $name);

		foreach ($variants as $outgoingVariant)
		{
			$html .= sprintf(
				'<option value="%s">%s</option>',
				$outgoingVariant['VALUE'],
				$outgoingVariant['NAME']
			);
		}

		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}
}