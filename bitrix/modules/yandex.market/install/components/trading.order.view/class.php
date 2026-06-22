<?php
namespace Yandex\Market\Components;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Entity as TradingEntity;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

/** @noinspection PhpUnused */
class TradingOrderView extends \CBitrixComponent
{
	protected $campaign;

	public function onPrepareComponentParams($arParams)
	{
		$arParams['CHECK_ACCESS'] = !isset($arParams['CHECK_ACCESS']) || $arParams['CHECK_ACCESS'];
		$arParams['EXTERNAL_ID'] = trim($arParams['EXTERNAL_ID']);
		$arParams['CAMPAIGN_ID'] = !empty($arParams['CAMPAIGN_ID']) ? (int)$arParams['CAMPAIGN_ID'] : null;
		$arParams['SITE_ID'] = !empty($arParams['SITE_ID']) ? trim($arParams['SITE_ID']) : null;

		return $arParams;
	}

	protected function listKeysSignedParameters()
	{
		return [
			'CHECK_ACCESS',
			'EXTERNAL_ID',
			'CAMPAIGN_ID',
			'SITE_ID',
		];
	}

	public function executeComponent()
	{
		try
		{
			$this->loadModules();

			$orderExternalId = $this->getOrderExternalId();
			$orderNum = $this->getOrderNum($orderExternalId);
			$orderInternalId = $this->getOrderNum($orderExternalId, false);
			$response = $this->runAction($orderExternalId, $orderNum);

			$this->buildResult($response);
			$this->extendResult($orderExternalId, $orderNum, $orderInternalId);

			$templatePage = $this->resolveTemplatePage();
		}
		catch (Main\SystemException $exception)
		{
			$this->arResult['ERROR'] = $exception->getMessage();
			$templatePage = 'exception';
		}

		$this->includeComponentTemplate($templatePage);

		return isset($this->arResult['RETURN']) ? $this->arResult['RETURN'] : null;
	}

	protected function resolveTemplatePage()
	{
		$mode = isset($this->arParams['MODE']) ? $this->arParams['MODE'] : '';

		if ($mode === 'RELOAD')
		{
			$result = 'reload';
		}
		else
		{
			$result = '';
		}

		return $result;
	}

	protected function getCampaign()
	{
		if ($this->campaign === null)
		{
			$campaignId = $this->getRequiredParameter('CAMPAIGN_ID');

			$this->campaign = Market\Trading\Campaign\Model::loadById($campaignId);
		}

		return $this->campaign;
	}

	protected function loadModules()
	{
		$requiredModules = $this->getRequiredModules();

		foreach ($requiredModules as $requiredModule)
		{
			if (!Main\Loader::includeModule($requiredModule))
			{
				$message = $this->getLang('MODULE_NOT_INSTALLED', [ '#MODULE_ID#' => $requiredModule ]);

				throw new Main\SystemException($message);
			}
		}
	}

	protected function getRequiredModules()
	{
		return [
			'yandex.market',
		];
	}

	protected function getRequiredParameter($key)
	{
		$result = $this->getParameter($key);

		if ($result === '')
		{
			$message = $this->getLang('PARAMETER_' . $key . '_REQUIRED');
			throw new Main\ArgumentException($message);
		}

		return $result;
	}

	protected function getParameter($key)
	{
		return isset($this->arParams[$key]) ? (string)$this->arParams[$key] : '';
	}

	protected function getLang($code, $replace = null, $language = null)
	{
		return Main\Localization\Loc::getMessage('YANDEX_MARKET_TRADING_ORDER_VIEW_' . $code, $replace, $language) ?: $code;
	}

	protected function getOrderExternalId()
	{
		return (int)$this->getParameter('EXTERNAL_ID');
	}

	protected function getOrderNum($externalId, $useAccountNumber = null)
	{
		$campaign = $this->getCampaign();
		$platform = $campaign->getTrading()->getPlatform();
		$registry = $campaign->getTrading()->getEnvironment()->getOrderRegistry();
		$result = $registry->search($externalId, $platform, $useAccountNumber);

		if ($result === null)
		{
			$message = $this->getLang('ORDER_NOT_REGISTERED', [ '#EXTERNAL_ID#' => $externalId ]);
			throw new Main\SystemException($message);
		}

		return $result;
	}

	protected function runAction($externalId, $orderNum)
	{
		$procedure = new Market\Trading\Procedure\Runner(
			Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER,
			$orderNum
		);

		$parameters = [
			'id' => $externalId,
			'flushCache' => true,
			'useCache' => true,
		];
		$parameters += $this->getEnvironmentFetchParameters();

		return $procedure->run($this->getCampaign()->getTrading(), 'admin/view', $parameters);
	}

	protected function getEnvironmentFetchParameters()
	{
		global $USER;

		return [
			'userId' => $USER->GetID(),
			'checkAccess' => (bool)$this->arParams['CHECK_ACCESS'],
		];
	}

	protected function buildResult(Market\Trading\Service\Reference\Action\Response $response)
	{
		$this->arResult = [
			'ORDER' => $response->getField('order'),
			'WARNINGS' => $response->getField('warnings'),
			'PROPERTIES' => $response->getField('properties'),
			'BASKET' => [
				'COLUMNS' => $response->getField('basket.columns'),
				'ITEMS' => $response->getField('basket.items'),
				'SUMMARY' => $response->getField('basket.summary'),
			],
			'DELIVERY' => $response->getField('delivery'),
			'COURIER' => $response->getField('courier'),
			'BUYER' => $response->getField('buyer'),
			'SHIPMENT' => $response->getField('shipments'),
			'BOX' => $response->getField('boxes'),
			'ORDER_ACTIONS' => (array)$response->getField('orderActions'),
			'PRINT_READY' => (bool)$response->getField('printReady'),
		];
	}

	protected function extendResult($orderExternalId, $orderNum, $orderInternalId)
	{
		$this->fillCommonData($orderExternalId, $orderNum, $orderInternalId);
		$this->fillBasketItemsIndex();
		$this->fillBoxNumber();
		$this->resolveBasketCisColumn();
		$this->resolveBasketDigitalColumn();
		$this->filterOrderActions();
		$this->fillItemsChangeReason();
		$this->fillPrintDocuments();
		$this->extendActivities();
	}

	protected function fillCommonData($orderExternalId, $orderNum, $orderInternalId)
	{
		$campaign = $this->getCampaign();

		$this->arResult['CAMPAIGN_ID'] = $campaign->getId();
		$this->arResult['SERVICE_NAME'] = $campaign->getTrading()->getService()->getInfo()->getTitle('SHORT');
		$this->arResult['ORDER_INTERNAL_ID'] = $orderInternalId;
		$this->arResult['ORDER_EXTERNAL_ID'] = $orderExternalId;
		$this->arResult['ORDER_ACCOUNT_NUMBER'] = $orderNum;
	}

	protected function fillBoxNumber()
	{
		if (empty($this->arResult['BOX'])) { return; }

		$boxNumber = 1;

		foreach ($this->arResult['BOX'] as &$box)
		{
			$box['NUMBER'] = $boxNumber;
			++$boxNumber;
		}
		unset($box);
	}

	protected function fillBasketItemsIndex()
	{
		if (empty($this->arResult['BASKET']['ITEMS'])) { return; }

		$basketItemIndex = 0;

		foreach ($this->arResult['BASKET']['ITEMS'] as &$basketItem)
		{
			if (isset($basketItem['INDEX']))
			{
				$basketItemIndex = max($basketItemIndex, $basketItem['INDEX'] + 1);
			}
			else
			{
				++$basketItemIndex;
				$basketItem['INDEX'] = $basketItemIndex;
			}
		}
		unset($basketItem);
	}

	protected function resolveBasketCisColumn()
	{
		if (!isset($this->arResult['BASKET']['COLUMNS']['CIS'])) { return; }

		$isMarkingGroupUsed = false;

		foreach ($this->arResult['BASKET']['ITEMS'] as $item)
		{
			if (!empty($item['INSTANCE_TYPES']) || !empty($item['MARKING_GROUP']))
			{
				$isMarkingGroupUsed = true;
				break;
			}
		}

		if (!$isMarkingGroupUsed)
		{
			unset($this->arResult['BASKET']['COLUMNS']['CIS']);
		}
	}

	protected function resolveBasketDigitalColumn()
	{
		if (
			isset($this->arResult['BASKET']['COLUMNS']['DIGITAL'])
			&& empty($this->arResult['ORDER_ACTIONS'][TradingEntity\Operation\Order::DIGITAL])
		)
		{
			unset($this->arResult['BASKET']['COLUMNS']['DIGITAL']);
		}
	}

	protected function filterOrderActions()
	{
		if (empty($this->arResult['ORDER_ACTIONS'])) { return; }

		$this->arResult['ORDER_ACTIONS'] = array_intersect_key(
			$this->arResult['ORDER_ACTIONS'],
			$this->getReadyActions()
		);
	}

	protected function getReadyActions()
	{
		return array_filter([
			TradingEntity\Operation\Order::ITEM => !empty($this->arResult['BASKET']['ITEMS']),
			TradingEntity\Operation\Order::BOX => !empty($this->arResult['BOX']),
			TradingEntity\Operation\Order::CIS => isset($this->arResult['BASKET']['COLUMNS']['CIS']),
			TradingEntity\Operation\Order::DIGITAL => isset($this->arResult['BASKET']['COLUMNS']['DIGITAL']),
		]);
	}

	protected function fillItemsChangeReason()
	{
		if (!isset($this->arResult['ORDER_ACTIONS'][TradingEntity\Operation\Order::ITEM])) { return; }

		$service = $this->getCampaign()->getTrading()->getService();

		if (!($service instanceof Market\Trading\Service\Reference\HasItemsChangeReason)) { return; }

		$reasonService = $service->getItemsChangeReason();
		$enum = [];

		foreach ($reasonService->getVariants() as $variant)
		{
			$enum[] = [
				'ID' => $variant,
				'VALUE' => $reasonService->getTitle($variant),
			];
		}

		$this->arResult['ITEMS_CHANGE_REASON'] = $enum;
	}

	protected function fillPrintDocuments()
	{
		$printer = $this->getCampaign()->getTrading()->getService()->getPrinter();
		$documents = [];

		foreach ($printer->getTypes() as $type)
		{
			$document = $printer->getDocument($type);

			if ($document->getSourceType() !== Market\Trading\Entity\Registry::ENTITY_TYPE_ORDER) { continue; }
			if (!$this->matchActivityFilter($document->getFilter())) { continue; }

			$documents[] = [
				'TYPE' => $type,
				'TITLE' => $document->getTitle(),
			];
		}

		$this->arResult['PRINT_DOCUMENTS'] = $documents;
	}

	protected function extendActivities()
	{
		$resultKeys = [
			'PROPERTIES',
			'DELIVERY',
			'BUYER',
		];

		foreach ($resultKeys as $resultKey)
		{
			if (!isset($this->arResult[$resultKey])) { continue; }

			foreach ($this->arResult[$resultKey] as $propertyKey => &$property)
			{
				if (!isset($property['ACTIVITY'])) { continue; }

				$activity = $this->getActivity($property['ACTIVITY']);
				$activityAction = $this->makeActivityAction($property['ACTIVITY'], $activity);

				if ($this->matchActivityFilter($activityAction['FILTER']))
				{
					$property['ACTIVITY_ACTION'] = $activityAction;
				}
				else if (Market\Utils\Value::isEmpty($property['VALUE']))
				{
					unset($this->arResult[$resultKey][$propertyKey]);
				}
			}
			unset($property);

			if (empty($this->arResult[$resultKey]))
			{
				unset($this->arResult[$resultKey]);
			}
		}
	}

	protected function makeActivityAction($path, Market\Trading\Service\Reference\Action\AbstractActivity $activity, $chain = '')
	{
		$type = $path . ($chain !== '' ? (mb_strpos($path, '|') === false ? '|' : '.') . $chain : '');
		$result = [
			'TYPE' => $path,
			'TEXT' => $activity->getTitle(),
			'FILTER' => $activity->getFilter(),
		];

		if ($activity instanceof Market\Trading\Service\Reference\Action\ComplexActivity)
		{
			$result['MENU'] = [];

			foreach ($activity->getActivities() as $key => $child)
			{
				$childChain = ($chain !== '' ? $chain . '.' . $key : $key);

				$childActivity = $this->makeActivityAction($path, $child, $childChain);

				if (!$this->matchActivityFilter($childActivity['FILTER'])) { continue; }

				$result['MENU'][] = $childActivity;
			}

			if (empty($result['MENU']))
			{
				$result['FILTER'] = [ 'UNKNOWN' => true ];
 			}
		}
		else if ($activity instanceof Market\Trading\Service\Reference\Action\CommandActivity)
		{
			$result['METHOD'] = sprintf(
				'BX.YandexMarket.OrderView.activity.executeCommand("%s")',
				$type
			);

			$result += $activity->getParameters(); // confirm and etc
		}
		else if ($activity instanceof Market\Trading\Service\Reference\Action\FormActivity)
		{
			$result['METHOD'] = sprintf(
				'BX.YandexMarket.OrderView.activity.openForm("%s", %s)',
				$type,
				Main\Web\Json::encode([
					'TITLE' => $result['TEXT'],
				])
			);
		}
		else if ($activity instanceof Market\Trading\Service\Reference\Action\ViewActivity)
		{
			$result['METHOD'] = sprintf(
				'BX.YandexMarket.OrderView.activity.openView("%s", %s)',
				$type,
				Main\Web\Json::encode([
					'TITLE' => $result['TEXT'],
				])
			);
		}

		return $result;
	}

	protected function getActivity($path)
	{
		$setup = $this->getCampaign()->getTrading();
		$router = $setup->wakeupService()->getRouter();

		return $router->getActivity($path, $setup->getEnvironment());
	}

	protected function matchActivityFilter($filter)
	{
		return Market\Utils\ActionFilter::isMatch($filter, $this->arResult['ORDER']);
	}
}