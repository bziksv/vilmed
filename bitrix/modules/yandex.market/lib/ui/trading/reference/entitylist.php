<?php

namespace Yandex\Market\Ui\Trading\Reference;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Service as TradingService;

abstract class EntityList extends Market\Ui\Reference\Page
{
	use Market\Reference\Concerns\HasMessage;

	protected function getReadRights()
	{
		return Market\Ui\Access::RIGHTS_PROCESS_TRADING;
	}

	/** @return string */
	abstract protected function getTargetEntity();

	public function show()
	{
		$businessId = Market\Ui\Trading\Menu::extractBusinessId();
		$campaignCollection = $this->getCampaignCollection($businessId);
		$campaignId = $this->getRequestCampaignId() ?: $this->getStoredCampaignId($businessId);

		try
		{
			$campaign = $this->resolveCampaign($campaignCollection, $campaignId);

			$this->showCampaignSelector($campaignCollection, $campaign->getId());
			$this->showGrid($campaign);

			$this->setStoredCampaignId($businessId, $campaign->getId());
		}
		catch (Main\ObjectException $exception)
		{
			$this->showCampaignSelector($campaignCollection, $campaignId, true);
			$this->showError($exception->getMessage());
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			if ($this->getRequestCampaignId() === null && $this->getStoredCampaignId($businessId) === $campaignId)
			{
				$this->resetStoredCampaignId($businessId);
			}

			$this->showCampaignSelector($campaignCollection, $campaignId, true);
			$this->showError($exception->getMessage());
		}
	}

	protected function showError($message)
	{
		\CAdminMessage::ShowMessage([
			'TYPE' => 'ERROR',
			'MESSAGE' => $message,
		]);
	}

	protected function showCampaignSelector(Market\Trading\Campaign\Collection $campaignCollection, $selectedId = null, $force = false)
	{
		$options = $this->buildRoleOptions($campaignCollection);
		$showLimit = $force ? 0 : 1;

		if (count($options) <= $showLimit) { return; }

		$options = array_map(static function(array $option) use ($selectedId) {
			global $APPLICATION;

			return $option + [
				'SELECTED' => $option['ID'] === (int)$selectedId,
				'URL' => $APPLICATION->GetCurPageParam(http_build_query([ 'campaign' => $option['ID'] ]), [ 'campaign' ]),
			];
		}, $options);

		if (Market\Utils\BitrixTemplate::isBitrix24())
		{
			$this->renderCrmCampaignSelector($options);
		}
		else
		{
			$this->renderAdminCampaignSelector($options);
		}
	}

	protected function renderCrmCampaignSelector(array $options)
	{
		global $APPLICATION;

		$selectedOptions = array_filter($options, static function(array $option) { return $option['SELECTED']; });
		$selectedOption = reset($selectedOptions);
		$dropdownItems = array_map(static function(array $option) {
			return [
				'text' => $option['VALUE'],
				'link' => $option['URL'],
				'selected' => $option['SELECTED'],
			];
		}, $options);
		$dropdownItems = array_filter($dropdownItems, static function(array $item) { return !$item['selected']; });
		$dropdownItems = array_values($dropdownItems);

		$html = sprintf(
			'<div class="crm-interface-toolbar-button-container">
				<button class="ui-btn ui-btn-dropdown ui-btn-light-border" type="button" id="yamarket-campaign-selector">
					%s
				</button>
			</div>',
			$selectedOption !== false ? $selectedOption['VALUE'] : 'TRADING BEHAVIOR'
		);
		/** @noinspection JSUnresolvedReference */
		$html .= sprintf(
			'<script>
				BX.ready(function() {
					const button = BX("yamarket-campaign-selector");
					const items = JSON.parse(\'%s\');
					
					if (!button || !items) { return; }
					
					items.forEach(function(item) {
						item.onclick = function() { window.location.href = item.link; };
					});
					
					const menu = new BX.PopupMenuWindow({
						bindElement: button,
						items: items,
					});
			
					button.addEventListener("click", function() { menu.show(); });
				});
			</script>',
			Main\Web\Json::encode($dropdownItems)
		);

		$APPLICATION->AddViewContent('inside_pagetitle', $html);
	}

	protected function renderAdminCampaignSelector(array $options)
	{
		echo '<div style="margin-bottom: 10px;">';

		foreach ($options as $option)
		{
			if ($option['SELECTED'])
			{
				echo sprintf(
					' <span class="adm-btn adm-btn-active">%s</span>',
					htmlspecialcharsbx($option['VALUE'])
				);
			}
			else
			{
				/** @noinspection HtmlUnknownTarget */
				echo sprintf(
					' <a class="adm-btn" href="%s">%s</a>',
					htmlspecialcharsbx($option['URL']),
					htmlspecialcharsbx($option['VALUE'])
				);
			}
		}

		echo '</div>';
	}

	protected function buildRoleOptions(Market\Trading\Campaign\Collection $campaignCollection)
	{
		$result = [];

		/** @var Market\Trading\Campaign\Model $campaign */
		foreach ($campaignCollection as $campaign)
		{
			if ($campaign->getTradingId() === 0) { continue; }

			$result[] = [
				'ID' => (int)$campaign->getId(),
				'VALUE' => $campaign->getTitle(),
			];
		}

		return $result;
	}

	abstract protected function showGrid(Market\Trading\Campaign\Model $campaign);

	abstract protected function getGridId();

	protected function gridActionsParameters(Market\Trading\Campaign\Model $campaign, $documents, $activities)
	{
		return [
			'ROW_ACTIONS' => $this->getOrderListRowActions($campaign, $documents, $activities),
			'ROW_ACTIONS_PERSISTENT' => 'Y',
			'GROUP_ACTIONS' => $this->getOrderListGroupActions($campaign, $documents, $activities),
			'GROUP_ACTIONS_PARAMS' => $this->getOrderListGroupActionsParams($activities),
			'UI_GROUP_ACTIONS' => $this->getOrderListUiGroupActions($campaign, $documents, $activities),
			'UI_GROUP_ACTIONS_PARAMS' => [
				'disable_action_target' => true,
			],
		];
	}

	protected function initializePrintActions(Market\Trading\Campaign\Model $campaign, $documents)
	{
		if (empty($documents)) { return; }

		self::includeSelfMessages();

		Market\Ui\Library::load('jquery');

		Market\Ui\Assets::loadPluginCore();
		Market\Ui\Assets::loadPlugins([
			'lib.dialog',
			'lib.printdialog',
			'OrderList.DialogAction',
			'OrderList.Print',
		]);

		Market\Ui\Assets::loadMessages([
			'PRINT_DIALOG_SUBMIT',
			'PRINT_DIALOG_WINDOW_BLOCKED',
		]);

		$this->addDialogActionsScript('Print', [
			'url' => Market\Ui\Admin\Path::getModuleUrl('trading_order_print', [
				'view' => 'dialog',
				'campaign' => $campaign->getId(),
				'alone' => 'Y',
			]),
			'items' => $this->getPrintItems($documents),
			'lang' => [
				'REQUIRE_SELECT_ORDERS' => static::getMessage('PRINT_REQUIRE_SELECT_ORDERS'),
			],
		]);
	}

	protected function initializeActivityActions(Market\Trading\Campaign\Model $campaign, $activities)
	{
		if (empty($activities)) { return; }

		Market\Ui\Library::load('jquery');

		Market\Ui\Assets::loadPluginCore();
		Market\Ui\Assets::loadPlugins([
			'lib.dialog',
			'Ui.ModalForm',
			'OrderList.DialogAction',
			'OrderList.Activity',
		]);

		$this->addDialogActionsScript('Activity', [
			'url' => Market\Ui\Admin\Path::getModuleUrl('trading_order_activity', [
				'view' => 'dialog',
				'campaign' => $campaign->getId(),
				'alone' => 'Y',
			]),
			'items' => $this->getActivityItems($activities),
			'lang' => [
				'ACTIVITY_SUBMIT' => static::getMessage('ACTIVITY_SUBMIT'),
				'ACTIVITY_CHOOSE_DROPDOWN' => static::getMessage('ACTIVITY_CHOOSE_DROPDOWN'),
			],
		]);
	}

	protected function addDialogActionsScript($type, array $parameters)
	{
		$pageAssets = Main\Page\Asset::getInstance();
		/** @noinspection JSUnresolvedReference */
		$contents = sprintf(
			'<script>
				BX.YandexMarket.OrderList["%s"] = new BX.YandexMarket.OrderList.%s(null, ' . Main\Web\Json::encode($parameters) . ')
			</script>',
			Market\Data\TextString::toLower($type),
			$type
		);

		$pageAssets->addString($contents, false, Main\Page\AssetLocation::AFTER_JS);
	}

	/**
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 *
	 * @return array
	 */
	protected function getPrintItems($documents)
	{
		$result = [];

		foreach ($documents as $type => $document)
		{
			$result[] = [
				'TYPE' => $type,
				'TITLE' => $document->getTitle(),
			];
		}

		return $result;
	}

	protected function getActivityItems($activities)
	{
		$result = [];

		foreach ($activities as $path => $activity)
		{
			$items = $this->makeActivityItems($path, $activity);

			if (empty($items)) { continue; }

			array_push($result, ...$items);
		}

		return $result;
	}

	protected function makeActivityItems($path, Market\Trading\Service\Reference\Action\AbstractActivity $activity, $chain = '')
	{
		$result = [];

		if ($activity instanceof Market\Trading\Service\Reference\Action\ComplexActivity)
		{
			foreach ($activity->getActivities() as $key => $child)
			{
				$childChain = ($chain !== '' ? $chain . '.' . $key : $key);
				$childItems = $this->makeActivityItems($path, $child, $childChain);

				if (empty($childItems)) { continue; }

				array_push($result, ...$childItems);
			}
		}
		else
		{
			$result[] = [
				'TYPE' => $path . ($chain !== '' ? '|' . $chain : ''),
				'TITLE' => $activity->getTitle(),
				'BEHAVIOR' => $this->resolveActivityBehavior($activity),
			];
		}

		return $result;
	}

	protected function resolveActivityBehavior(Market\Trading\Service\Reference\Action\AbstractActivity $activity)
	{
		if ($activity instanceof TradingService\Reference\Action\CommandActivity)
		{
			$result = 'command';
		}
		else if ($activity instanceof TradingService\Reference\Action\FormActivity)
		{
			$result = 'form';
		}
		else if ($activity instanceof TradingService\Reference\Action\ViewActivity)
		{
			$result = 'view';
		}
		else
		{
			throw new Main\SystemException(sprintf('unknown activity type for %s', get_class($activity)));
		}

		return $result;
	}

	/**
	 * @param Market\Trading\Campaign\Model                                $campaign
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[]   $activities
	 *
	 * @return array
	 */
	protected function getOrderListRowActions(Market\Trading\Campaign\Model $campaign, $documents, $activities)
	{
		return
			$this->getOrderListRowActivityActions($activities)
			+ $this->getOrderListRowPrintActions($documents);
	}

	/**
	 * @param Market\Trading\Campaign\Model                                $campaign
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[]   $activities
	 *
	 * @return array
	 */
	protected function getOrderListGroupActions(Market\Trading\Campaign\Model $campaign, $documents, $activities)
	{
		return
			$this->getOrderListGroupPrintActions($documents)
			+ $this->getOrderListGroupActivitiesActions($activities);
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return array
	 */
	protected function getOrderListGroupActionsParams($activities)
	{
		return [
			'select_onchange' => $this->onChangeOrderListGroupActivities($activities),
			'disable_action_target' => true,
		];
	}

	/**
	 * @param Market\Trading\Campaign\Model                                $campaign
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[]   $activities
	 *
	 * @return array
	 */
	protected function getOrderListUiGroupActions(Market\Trading\Campaign\Model $campaign, array $documents, array $activities)
	{
		return
			$this->getOrderListGroupPrintActions($documents)
			+ $this->getOrderListUiGroupActivitiesActions($activities);
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return array
	 */
	protected function getOrderListRowActivityActions(array $activities)
	{
		$result = [];

		foreach ($activities as $path => $activity)
		{
			$code = 'ACTIVITY_' . Market\Data\TextString::toUpper(str_replace('/', '_', $path));

			$item = $this->makeOrderListRowActivityAction($path, $activity);

			if (isset($item['UNPACK']))
			{
				$result += $item['UNPACK'];
			}
			else
			{
				$result[$code] = $item;
			}
		}

		uasort($result, static function($a, $b) {
			$aSort = isset($a['SORT']) ? $a['SORT'] : 500;
			$bSort = isset($b['SORT']) ? $b['SORT'] : 500;

			if ($aSort === $bSort) { return 0; }

			return ($aSort < $bSort ? -1 : 1);
		});

		return $result;
	}

	protected function makeOrderListRowActivityAction($path, Market\Trading\Service\Reference\Action\AbstractActivity $activity, $chain = '')
	{
		$result = [
			'TEXT' => $activity->getTitle(),
			'FILTER' => $activity->getFilter(),
			'SORT' => $activity->getSort(),
		];

		if ($activity instanceof Market\Trading\Service\Reference\Action\ComplexActivity)
		{
			$items = [];

			foreach ($activity->getActivities() as $key => $child)
			{
				$childChain = ($chain !== '' ? $chain . '.' . $key : $key);

				$items[$childChain] = $this->makeOrderListRowActivityAction($path, $child, $childChain);
			}

			if ($activity->onlyContents())
			{
				if (!empty($result['FILTER']))
				{
					foreach ($items as &$item)
					{
						$item['FILTER'] = isset($item['FILTER']) ? $item['FILTER'] + $result['FILTER'] : $result['FILTER'];
					}
					unset($item);
				}

				$result['UNPACK'] = $items;
			}
			else
			{
				$result['MENU'] = array_values($items);
			}
		}
		else
		{
			$type = $path . ($chain !== '' ? '|' . $chain : '');

			$result['METHOD'] = sprintf(
				'BX.YandexMarket.OrderList.activity.action("%s", "#ID#", %s)',
				$type,
				$this->getGridId()
			);

			if ($activity instanceof TradingService\Reference\Action\CommandActivity)
			{
				$result += array_intersect_key($activity->getParameters(), [
					'CONFIRM' => true,
					'CONFIRM_MESSAGE' => true,
				]);
			}
		}

		return $result;
	}

	/**
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 *
	 * @return array
	 */
	protected function getOrderListRowPrintActions(array $documents)
	{
		$menu = [];

		foreach ($documents as $type => $document)
		{
			$key = 'PRINT_' . Market\Data\TextString::toUpper($type);

			$menu[$key] = [
				'FILTER' => $document->getFilter(),
				'TEXT' => $document->getTitle('PRINT'),
				'METHOD' => 'BX.YandexMarket.OrderList.print.openDialog("' .  $type .  '", "#ID#")',
			];
		}

		return [
			'PRINT' => [
				'TEXT' => self::getMessage('ACTION_PRINT'),
				'MENU' => $menu,
			],
		];
	}

	/**
	 * @param Market\Trading\Service\Reference\Document\AbstractDocument[] $documents
	 *
	 * @return array
	 */
	protected function getOrderListGroupPrintActions($documents)
	{
		$result = [];

		foreach ($documents as $type => $document)
		{
			$key = 'PRINT_' . Market\Data\TextString::toUpper($type);
			$needSelectOrders = $document->getEntityType() !== Market\Trading\Entity\Registry::ENTITY_TYPE_NONE;

			if ($needSelectOrders)
			{
				$action = sprintf(
					'BX.YandexMarket.OrderList.print.openGroupDialog("%s", %s)',
					$type,
					$this->getGridId()
				);
			}
			else
			{
				$action = sprintf('BX.YandexMarket.OrderList.print.openDialog("%s")', $type);
			}

			$result[$key] = [
				'type' => 'button',
				'value' => $key,
				'name' => $document->getTitle('PRINT'),
				'action' => $action,
			];
		}

		return $result;
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return array
	 */
	protected function getOrderListGroupActivitiesActions($activities)
	{
		$result = [];
		$gridId = $this->getGridId();

		foreach ($this->filterGroupActivities($activities) as $name => $activity)
		{
			$controlName = preg_replace('/[^A-Z]+/i', '_', $name);

			if ($activity instanceof TradingService\Reference\Action\ComplexActivity)
			{
				$result[$controlName] = [
					'name' => $activity->getTitle(),
					'value' => $name,
					'action' => sprintf('BX.YandexMarket.OrderList.activity.groupAction("%s", %s, "%s")', $name, $gridId, $controlName),
				];
				$result[$controlName . '_chooser'] = [
					'type' => 'html',
					'value' => $this->makeGroupActivitySelectHtml($name, $activity),
				];
			}
			else
			{
				$result[$controlName] = [
					'name' => $activity->getTitle(),
					'value' => $name,
					'action' => sprintf('BX.YandexMarket.OrderList.activity.groupAction("%s", %s)', $name, $gridId),
				];
			}
		}

		return $result;
	}

	protected function makeGroupActivitySelectHtml($name, TradingService\Reference\Action\ComplexActivity $activity)
	{
		$controlName = preg_replace('/[^A-Z]+/i', '_', $name);
		$html = sprintf('<div id="%s_chooser" style="display: none;">', $controlName);
		$html .= sprintf('<select name="%s">', $controlName);

		foreach ($activity->getActivities() as $childName => $child)
		{
			if (!$child->useGroup()) { continue; }

			$childGlue = (Market\Data\TextString::getPosition($name, '|') === false ? '|' : '.');
			$childPath = $name . $childGlue . $childName;

			$html .= sprintf(
				'<option value="%s">%s</option>',
				$childPath,
				$child->getTitle()
			);
		}

		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return string
	 */
	protected function onChangeOrderListGroupActivities($activities)
	{
		$result = '';

		foreach ($this->filterGroupActivities($activities) as $name => $activity)
		{
			if (!($activity instanceof TradingService\Reference\Action\ComplexActivity)) { continue; }

			$controlName = preg_replace('/[^A-Z]+/i', '_', $name);

			$result .= sprintf(
				'BX(\'%1$s_chooser\') && (BX(\'%1$s_chooser\').style.display = (this.value == \'%2$s\' ? \'block\' : \'none\'));',
				$controlName,
				$name
			);
		}

		return $result;
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return array
	 */
	protected function getOrderListUiGroupActivitiesActions($activities)
	{
		if (!class_exists(Main\Grid\Panel\Snippet::class) || !class_exists(Main\Grid\Panel\Actions::class)) { return []; }

		$result = [];
		$snippets = new Main\Grid\Panel\Snippet();
		$gridId = $this->getGridId();

		foreach ($this->filterGroupActivities($activities) as $name => $activity)
		{
			$controlName = preg_replace('/[^A-Z]+/i', '_', $name);
			$action = [
				'type' => 'multicontrol',
				'controlId' => $controlName,
				'controlName' => $controlName,
				'name' => $activity->getTitle(),
				'action' => [
					[ 'ACTION' => Main\Grid\Panel\Actions::RESET_CONTROLS ],
				],
			];

			if ($activity instanceof TradingService\Reference\Action\ComplexActivity)
			{
				$applyCallback = sprintf('BX.YandexMarket.OrderList.activity.groupAction("%s", %s, "%s")', $name, $gridId, $controlName);
				$items = [];

				foreach ($activity->getActivities() as $childName => $child)
				{
					if (!$child->useGroup()) { continue; }

					$childGlue = (Market\Data\TextString::getPosition($name, '|') === false ? '|' : '.');
					$childPath = $name . $childGlue . $childName;

					$items[] = [
						'NAME' => $child->getTitle(),
						'VALUE' => $childPath,
					];
				}

				$action['action'][] = [
					'ACTION' => Main\Grid\Panel\Actions::CREATE,
					'DATA' => [
						[
							'TYPE' => Main\Grid\Panel\Types::DROPDOWN,
							'ID' => 'selected_action_' . $gridId . '_' . $controlName,
							'NAME' => $controlName,
							'ITEMS' => $items,
						],
						$snippets->getApplyButton([
							'ONCHANGE' => [
								[
									'ACTION' => Main\Grid\Panel\Actions::CALLBACK,
									'DATA' => [
										[ 'JS' => $applyCallback ],
									],
								],
							],
						]),
					]
				];
			}
			else
			{
				$applyCallback = sprintf('BX.YandexMarket.OrderList.activity.groupAction("%s", %s)', $name, $gridId);

				$action['action'][] = [
					'ACTION' => Main\Grid\Panel\Actions::CREATE,
					'DATA' => [
						$snippets->getApplyButton([
							'ONCHANGE' => [
								[
									'ACTION' => Main\Grid\Panel\Actions::CALLBACK,
									'DATA' => [
										[ 'JS' => $applyCallback ],
									],
								],
							],
						]),
					]
				];
			}

			$result[] = $action;
		}

		return $result;
	}

	/**
	 * @param Market\Trading\Service\Reference\Action\AbstractActivity[] $activities
	 *
	 * @return Market\Trading\Service\Reference\Action\AbstractActivity[]
	 */
	protected function filterGroupActivities($activities)
	{
		$result = [];

		foreach ($activities as $name => $activity)
		{
			if (!$activity->useGroup()) { continue; }

			if ($activity instanceof TradingService\Reference\Action\ComplexActivity && $activity->onlyContents())
			{
				$children = $activity->getActivities();
				$children = $this->filterGroupActivities($children);
				$glue = (Market\Data\TextString::getPosition($name, '|') === false ? '|' : '.');

				foreach ($children as $childName => $child)
				{
					$result[$name . $glue . $childName] = $child;
				}
			}
			else
			{
				$result[$name] = $activity;
			}
		}

		return $result;
	}

	protected function getServiceActivities(Market\Trading\Campaign\Model $campaign)
	{
		$router = $campaign->getTrading()->getService()->getRouter();
		$environment = $campaign->getTrading()->getEnvironment();
		$pageTargetEntity = $this->getTargetEntity();
		$result = [];

		foreach ($router->getMap() as $path => $actionClass)
		{
			if (!$router->hasDataAction($path)) { continue; }

			$action = $router->getDataAction($path, $environment);

			if (!($action instanceof Market\Trading\Service\Reference\Action\HasActivity)) { continue; }

			$activity = $action->getActivity();

			if ($activity->getSourceType() !== $pageTargetEntity) { continue; }

			$result[$path] = $activity;
		}

		return $result;
	}

	protected function getPrintDocuments(Market\Trading\Campaign\Model $campaign)
	{
		$printer = $campaign->getTrading()->getService()->getPrinter();
		$pageTargetEntity = $this->getTargetEntity();
		$result = [];

		foreach ($printer->getTypes() as $type)
		{
			$document = $printer->getDocument($type);

			if ($document->getSourceType() !== $pageTargetEntity) { continue; }

			$result[$type] = $document;
		}

		return $result;
	}

	protected function getComponentBaseUrl(Market\Trading\Campaign\Model $campaign)
	{
		global $APPLICATION;

		$queryParameters = array_filter([
			'lang' => LANGUAGE_ID,
			'business' => Market\Ui\Trading\Menu::castQueryBusiness($campaign->getBusinessId()),
			'campaign' => $campaign->getId(),
		]);

		return $APPLICATION->GetCurPage() . '?' . http_build_query($queryParameters);
	}

	protected function getCampaignCollection($businessId)
	{
		return Market\Trading\Campaign\Collection::loadByFilter([
			'filter' => Market\Ui\Trading\Menu::businessFilter($businessId),
			'order' => [ 'ID' => 'ASC' ],
		]);
	}

	protected function getRequestCampaignId()
	{
		return $this->request->get('campaign');
	}

	protected function getStoredCampaignId($businessId)
	{
		$businessId = (int)$businessId;
		$category = $this->getUserOptionCategory();
		$option = (string)\CUserOptions::GetOption($category, 'campaign_' . $businessId, null);

		return $option !== '' ? (int)$option : null;
	}

	protected function setStoredCampaignId($businessId, $campaignId)
	{
		$category = $this->getUserOptionCategory();

		\CUserOptions::SetOption($category, 'campaign_' . $businessId, $campaignId);
	}

	protected function resetStoredCampaignId($businessId)
	{
		$category = $this->getUserOptionCategory();

		\CUserOptions::DeleteOption($category, 'campaign_' . $businessId);
	}

	/** @return string */
	abstract protected function getUserOptionCategory();

	protected function resolveCampaign(Market\Trading\Campaign\Collection $campaignCollection, $campaignId = null)
	{
		if ($campaignId !== null)
		{
			$campaign = $campaignCollection->getItemById($campaignId);

			if ($campaign === null)
			{
				throw new Main\ObjectNotFoundException(self::getMessage('CAMPAIGN_NOT_FOUND', [
					'#ID#' => $campaignId,
				]));
			}

			if ($campaign->getTradingId() === 0)
			{
				throw new Main\ObjectException(self::getMessage('CAMPAIGN_INACTIVE', [
					'#ID#' => $campaignId,
				]));
			}
		}
		else
		{
			$campaign = $campaignCollection->getFirstWithTrading();

			if ($campaign === null)
			{
				throw new Main\ObjectNotFoundException(self::getMessage('CAMPAIGN_NOT_EXISTS'));
			}
		}

		return $campaign;
	}
}