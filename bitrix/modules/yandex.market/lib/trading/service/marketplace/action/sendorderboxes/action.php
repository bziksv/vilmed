<?php
namespace Yandex\Market\Trading\Service\Marketplace\Action\SendOrderBoxes;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

/**
 * @property TradingService\Marketplace\Provider $provider
 * @property Request $request
 */
class Action extends TradingService\Reference\Action\DataAction
{
	use Market\Reference\Concerns\HasMessage;
	use Market\Reference\Concerns\HasOnce;
	use TradingService\Common\Concerns\Action\HasOrder;
	use TradingService\Common\Concerns\Action\HasOrderMarker;
	use TradingService\Common\Concerns\Action\HasItemIdMatch;

	public function __construct(
		TradingService\Marketplace\Provider $provider,
		TradingEntity\Reference\Environment $environment,
		array $data
	)
	{
		parent::__construct($provider, $environment, $data);
	}

	public function getAudit()
	{
		return Market\Logger\Trading\Audit::SEND_BOXES;
	}

	protected function createRequest(array $data)
	{
		return new Request($data);
	}

	public function process()
	{
		try
		{
			$boxes = $this->makeBoxes();

			if (
				$this->request->isAutoSubmit()
				&& (
					!$this->isChanged($boxes)
					|| ($this->getItemsTotalCount($boxes) === 0 && $this->isOrderFinished())
				)
			)
			{
				return;
			}

			$this->validateBoxes($boxes);
			$this->sendBoxes($boxes, $this->request->isAllowRemove());
			$this->logBoxes($boxes);
			$this->saveData($boxes);

			$this->resolveOrderMarker(true);
		}
		catch (Market\Exceptions\Api\Request $exception)
		{
			$this->handleException($exception);
		}
		catch (Market\Exceptions\Trading\Validation $exception)
		{
			$this->handleException($exception);
		}
	}

	protected function handleException($exception)
	{
		$result = new Main\Result();
		$result->addError(new Main\Error($exception->getMessage(), $exception->getCode()));

		$this->resolveOrderMarker(false, $result);
		throw $exception;
	}

	protected function isOrderFinished()
	{
		$statusValue = $this->getOrderStatus();
		$statusService = $this->provider->getStatus();

		return $statusService->isOrderDelivered($statusValue) || $statusService->isCanceled($statusValue);
	}

	protected function getOrderStatus()
	{
		$result = $this->getStoredOrderStatus();

		if ($result === null)
		{
			$result = $this->getExternalOrderStatus();
		}

		return $result;
	}

	protected function getStoredOrderStatus()
	{
		$orderId = $this->request->getOrderId();
		$stored = (string)$this->provider->getStatus()->getStored($orderId);
		$result = null;

		if ($stored !== '')
		{
			list($result) = explode(':', $stored, 2);
		}

		return $result;
	}

	protected function getExternalOrderStatus()
	{
		return $this->getExternalOrder()->getStatus();
	}

	protected function makeBoxes()
	{
		$boxes = $this->request->getBoxes();

		if ($boxes !== null) { return $boxes; }

		$items = $this->request->getItems();

		if ($items !== null)
		{
			$items = $this->extendItems($items);

			return $this->buildBoxes($items);
		}

		throw new Market\Exceptions\Api\ObjectPropertyException('boxes');
	}

	protected function extendItems(array $items)
	{
		$items = $this->extendItemsId($items);
		$items = $this->extendItemsCount($items);
		$items = $this->extendItemsInstances($items);
		$items = $this->extendItemsRatio($items);

		return $items;
	}

	protected function extendItemsCount(array $items)
	{
		$hasItemWithCount = array_reduce($items, static function($carry, array $item) { return $carry || isset($item['count']); }, false);

		if ($hasItemWithCount) { return $items; }

		$basketItems = $this->collectBasketItems();
		$basketItems = $this->extendItemsId($basketItems);
		$basketItems = Market\Utils\ArrayHelper::columnToKey($basketItems, 'id');

		foreach ($items as &$item)
		{
			if (!isset($basketItems[$item['id']])) { continue; }

			$item['count'] = $basketItems[$item['id']]['count'];
			unset($basketItems[$item['id']]);
		}
		unset($item);

		if (!$this->request->isAllowRemove())
		{
			foreach ($basketItems as $basketItem)
			{
				$items[] = $basketItem;
			}
		}

		return $items;
	}

	protected function collectBasketItems()
	{
		$order = $this->getOrder();
		$result = [];

		foreach ($order->getExistsBasketItemCodes() as $code)
		{
			$basketData = $order->getBasketItemData($code)->getData();

			if (!isset($basketData['QUANTITY'])) { continue; }

			$result[] = [
				'xmlId' => $basketData['XML_ID'],
				'productId' => $basketData['PRODUCT_ID'],
				'count' => $basketData['QUANTITY'],
			];
		}

		return $result;
	}

	protected function extendItemsRatio(array $items)
	{
		$command = new TradingService\Common\Command\OfferPackRatio($this->provider, $this->environment);
		$productIds = array_column($items, 'productId');
		$productIds = array_filter($productIds);

		$packRatio = $command->make($productIds);

		foreach ($items as &$item)
		{
			if (!isset($item['productId'], $packRatio[$item['productId']])) { continue; }

			$ratio = $packRatio[$item['productId']];
			$count = $item['count'] / $ratio;

			$item['ratio'] = $ratio;
			$item['count'] = (int)Market\Data\Quantity::floor($count, 0);
			$item['countExact'] = $count;
		}
		unset($item);

		return $items;
	}

	protected function extendItemsId(array $items)
	{
		foreach ($items as $key => &$item)
		{
			if (isset($item['id'])) { continue; }

			$id = $this->getItemId($item);

			if ($id === null)
			{
				unset($items[$key]);
			}
			else
			{
				$item['id'] = $id;
			}
		}
		unset($item);

		return $items;
	}

	protected function hasItemsInstances(array $items)
	{
		return array_reduce($items, static function($carry, array $item) { return $carry || isset($item['instances']); }, false);
	}

	protected function extendItemsInstances(array $items)
	{
		if ($this->hasItemsInstances($items) || $this->hasStoredBoxes()) { return $items; }

		$instances = $this->collectBasketInstances($items);

		foreach ($items as &$item)
		{
			if (!isset($instances[$item['id']])) { continue; }

			$item['instances'] = $instances[$item['id']];
		}
		unset($item);

		return $items;
	}

	protected function collectBasketInstances(array $items)
	{
		$order = $this->getOrder();
		$result = [];

		foreach ($items as $item)
		{
			$basketCode = $this->getItemBasketCode($item);

			if ($basketCode === null) { continue; }

			$basketResult = $order->getBasketItemData($basketCode);
			$basketData = $basketResult->getData();

			if (!isset($basketData['MARKING_GROUP']) || (string)$basketData['MARKING_GROUP'] === '') { continue; }
			if (!isset($basketData['INSTANCES']) || !is_array($basketData['INSTANCES'])) { continue; }

			$instances = [];

			foreach ($basketData['INSTANCES'] as $instance)
			{
				$instanceFormatted = [];

				if (isset($instance['CIS']))
				{
					$instanceFormatted['cis'] = Market\Data\Trading\Cis::formatMarkingCode($instance['CIS']);
				}

				if (isset($instance['UIN']))
				{
					$instanceFormatted['uin'] = Market\Data\Trading\Uin::formatMarkingCode($instance['UIN']);
				}

				if (empty($instanceFormatted)) { continue; }

				$instances[] = $instanceFormatted;
			}

			$result[$item['id']] = $instances;
		}

		return $result;
	}

	protected function buildBoxes(array $items)
	{
		$boxes = (array)$this->storedBoxes();
		$hasInstances = $this->hasItemsInstances($items);

		list($boxes, $items, $found) = $this->splitItemsByBoxesWithInstances($boxes, $items);
		list(, $items, $found) = $this->splitItemsByBoxesWithId($boxes, $items, $hasInstances, $found);
		list($found) = $this->splitItemsByBoxesWithUnallocated($items, $found);

		return $this->sortBuiltBoxes($found);
	}

	protected function hasStoredBoxes()
	{
		$boxes = $this->storedBoxes();

		return !empty($boxes);
	}

	protected function storedBoxes()
	{
		return $this->once('storedBoxes', function() {
			$serviceKey = $this->provider->getUniqueKey();
			$orderId = $this->request->getOrderId();
			$boxes = Market\Trading\State\OrderData::getValue($serviceKey, $orderId, 'ORDER_BOXES');

			return is_array($boxes) ? $boxes : null;
		});
	}

	protected function splitItemsByBoxesWithInstances(array $boxes, array $items, array $found = [])
	{
		$itemsEmptyKeys = [];

		foreach ($items as $itemKey => &$item)
		{
			if (empty($item['instances'])) { continue; }

			foreach ($boxes as $boxKey => &$box)
			{
				if (empty($box['items'])) { continue; }

				$boxEmptyKeys = [];

				foreach ($box['items'] as $boxItemKey => &$boxItem)
				{
					if (empty($boxItem['instances']) || (string)$boxItem['id'] !== (string)$item['id']) { continue; }

					$sameMap = Market\Data\Trading\ItemInstance::map($item['instances'], $boxItem['instances']);

					if (empty($sameMap)) { continue; }

					$foundItem = [
						'id' => $item['id'],
						'instances'	=> Market\Data\Trading\ItemInstance::merge(
							array_intersect_key($item['instances'], $sameMap),
							$boxItem['instances'],
							$sameMap
						),
					];
					$foundItem += array_intersect_key($item, [
						'ratio' => true,
						'countExact' => true,
						'productId' => true,
						'xmlId' => true,
					]);

					if (isset($boxItem['partialCount']['current'], $boxItem['partialCount']['total']))
					{
						$count = 1;
						$foundItem['partialCount'] = $boxItem['partialCount'];

						$isFinalBoxItem = ((int)$boxItem['partialCount']['current'] === (int)$boxItem['partialCount']['total']);
						$boxEmptyKeys[$boxItemKey] = true;
					}
					else
					{
						$count = count($sameMap);

						$isFinalBoxItem = true;
						$foundItem['fullCount'] = $count;
						$boxItem['fullCount'] -= $count;

						if ($boxItem['fullCount'] <= 0)
						{
							$boxEmptyKeys[$boxItemKey] = true;
						}
						else
						{
							$boxItem['instances'] = array_values(array_diff_key($boxItem['instances'], array_flip($sameMap)));
						}
					}

					if (!isset($found[$boxKey])) { $found[$boxKey] = [ 'items' => [] ]; }

					$found[$boxKey]['items'] = $this->pushBoxItem($found[$boxKey]['items'], $boxItemKey, $foundItem);

					if ($isFinalBoxItem)
					{
						$item['count'] -= $count;
						$item['instances'] = array_values(array_diff_key($item['instances'], $sameMap));

						if ($item['count'] <= 0)
						{
							$itemsEmptyKeys[$itemKey] = true;
							break;
						}
					}
				}
				unset($boxItem);

				$box['items'] = array_diff_key($box['items'], $boxEmptyKeys);

				if ($item['count'] <= 0) { break; }
 			}
			unset($box);
		}
		unset($item);

		$items = array_diff_key($items, $itemsEmptyKeys);

		return [$boxes, $items, $found];
	}

	protected function splitItemsByBoxesWithId(array $boxes, array $items, $hasItemsInstances, array $found = [])
	{
		$itemsEmptyKeys = [];

		foreach ($items as $itemKey => &$item)
		{
			if (!isset($item['id']) || $item['count'] <= 0) { continue; }

			foreach ($boxes as $boxKey => &$box)
			{
				if (empty($box['items'])) { continue; }

				$boxEmptyKeys = [];

				foreach ($box['items'] as $boxItemKey => &$boxItem)
				{
					if ((string)$boxItem['id'] !== (string)$item['id']) { continue; }

					$foundItem = [
						'id' => $item['id'],
					];
					$foundItem += array_intersect_key($item, [
						'ratio' => true,
						'countExact' => true,
						'productId' => true,
						'xmlId' => true,
					]);

					if (isset($boxItem['partialCount']['current'], $boxItem['partialCount']['total']))
					{
						$count = 1;
						$foundItem['partialCount'] = $boxItem['partialCount'];

						$isFinalBoxItem = ((int)$boxItem['partialCount']['current'] === (int)$boxItem['partialCount']['total']);
						$boxEmptyKeys[$boxItemKey] = true;
					}
					else
					{
						$count = min($item['count'], $boxItem['fullCount']);
						$foundItem['fullCount'] = $count;

						$isFinalBoxItem = true;
						$boxItem['fullCount'] -= $count;

						if ($boxItem['fullCount'] <= 0)
						{
							$boxEmptyKeys[$boxItemKey] = true;
						}
					}

					if (isset($item['instances']))
					{
						$foundItem['instances'] = array_slice($item['instances'], 0, $count);

						if ($isFinalBoxItem)
						{
							$item['instances'] = array_slice($item['instances'], $count);
						}
					}
					else if (!$hasItemsInstances && !empty($boxItem['instances']))
					{
						$foundItem['instances'] = array_slice($boxItem['instances'], 0, $count);
						$boxItem['instances'] = array_slice($boxItem['instances'], $count);
					}

					if (!isset($found[$boxKey])) { $found[$boxKey] = [ 'items' => [] ]; }

					$found[$boxKey]['items'] = $this->pushBoxItem($found[$boxKey]['items'], $boxItemKey, $foundItem);

					if ($isFinalBoxItem)
					{
						$item['count'] -= $count;

						if ($item['count'] <= 0)
						{
							$itemsEmptyKeys[$itemKey] = true;
							break;
						}
					}
				}
				unset($boxItem);

				$box['items'] = array_diff_key($box['items'], $boxEmptyKeys);

				if ($item['count'] <= 0) { break; }
			}
			unset($box);
		}
		unset($item);

		$items = array_diff_key($items, $itemsEmptyKeys);

		return [$boxes, $items, $found];
	}

	protected function splitItemsByBoxesWithUnallocated(array $items, array $found = [])
	{
		if (empty($items)) { return [ $found ]; }

		$newBox = [ 'items' => [] ];

		foreach ($items as $item)
		{
			$boxItem = [
				'id' => $item['id'],
				'fullCount' => $item['count'],
			];
			$boxItem += array_intersect_key($item, [
				'ratio' => true,
				'countExact' => true,
				'productId' => true,
				'xmlId' => true,
			]);

			if (isset($item['instances']))
			{
				$boxItem['instances'] = $item['instances'];
			}

			$newBox['items'][] = $boxItem;
		}

		$found[] = $newBox;

		return [ $found ];
	}

	protected function pushBoxItem(array $boxItems, $key, array $item)
	{
		if (!isset($boxItems[$key]))
		{
			$boxItems[$key] = $item;
			return $boxItems;
		}

		$boxItems[$key]['fullCount'] += $item['fullCount'];

		if (!empty($item['instances']))
		{
			if (!empty($boxItems[$key]['instances']))
			{
				array_push($boxItems[$key]['instances'], ...array_values($item['instances']));
			}
			else
			{
				$boxItems[$key]['instances'] = $item['instances'];
			}
		}

		return $boxItems;
	}

	protected function sortBuiltBoxes(array $boxes)
	{
		foreach ($boxes as &$box)
		{
			if (empty($box['items'])) { continue; }

			ksort($box['items'], SORT_NUMERIC);
		}
		unset($box);

		ksort($boxes, SORT_NUMERIC);

		return $boxes;
	}

	protected function sanitizeBoxes(array $boxes)
	{
		$result = [];

		foreach ($boxes as $box)
		{
			if (!isset($box['items'])) { continue; }

			foreach ($box['items'] as &$item)
			{
				$item = array_intersect_key($item, [
					'id' => true,
					'partialCount' => true,
					'fullCount' => true,
					'instances' => true,
				]);

				// count

				if (isset($item['fullCount']))
				{
					$item['fullCount'] = (int)$item['fullCount'];
				}

				if (isset($item['partialCount']['current']))
				{
					$item['partialCount']['current'] = (int)$item['partialCount']['current'];
				}

				if (isset($item['partialCount']['total']))
				{
					$item['partialCount']['total'] = (int)$item['partialCount']['total'];
				}

				// instances

				if (isset($item['instances']))
				{
					if (empty($item['instances']) || $item['count'] === 0)
					{
						unset($item['instances']);
					}
					else if (count($item['instances']) > $item['count'])
					{
						$item['instances'] = array_slice($item['instances'], 0, $item['count']);
					}
					else
					{
						$item['instances'] = array_values($item['instances']);
					}
				}
			}
			unset($item);

			$box['items'] = array_values($box['items']);

			$result[] = $box;
		}

		return $result;
	}

	protected function validateBoxes(array $boxes)
	{
		if (!$this->request->isAutoSubmit()) { return; }

		$this->validateItemsPack($boxes);
		$this->validateItemsTotalCount($boxes);
	}

	protected function validateItemsPack(array $boxes)
	{
		foreach ($boxes as $box)
		{
			foreach ($box['items'] as $item)
			{
				if (!isset($item['ratio'])) { continue; }

				$count = isset($item['fullCount']) ? (int)$item['fullCount'] : 1;

				if (!Market\Data\Quantity::equal($item['countExact'], $count))
				{
					throw new Market\Exceptions\Trading\Validation(self::getMessage('VALIDATE_PACK_ITEM_COUNT', [
						'#PRODUCT_ID#' => $item['productId'],
					]));
				}
			}
		}
	}

	protected function validateItemsTotalCount(array $boxes)
	{
		$requested = $this->getItemsTotalCount($boxes);
		$stored = $this->getTotalCount();

		if ($requested < $stored && $this->hasMissingBasketItems($boxes))
		{
			throw new Market\Exceptions\Trading\Validation(self::getMessage('VALIDATE_NEW_PRODUCT_ADDITION'));
		}
	}

	protected function hasMissingBasketItems(array $boxes)
	{
		$order = $this->getOrder();
		$existsCodes = $order->getExistsBasketItemCodes();
		$foundCodes = $this->getItemsBasketCodes($this->flatItems($boxes));
		$missingCodes = array_diff($existsCodes, $foundCodes);
		$result = false;

		foreach ($missingCodes as $basketCode)
		{
			$basketData = $order->getBasketItemData($basketCode)->getData();

			if (isset($basketData['PRICE']) && (int)$basketData['PRICE'] > 0)
			{
				$result = true;
			}
		}

		return $result;
	}

	protected function sendBoxes(array $boxes, $allowRemove = false)
	{
		$request = $this->createSendBoxesRequest($boxes, $allowRemove);
		$sendResult = $request->send();

		if (!$sendResult->isSuccess())
		{
			$errorMessage = implode(PHP_EOL, $sendResult->getErrorMessages());
			$exceptionMessage = static::getMessage('SEND_ERROR', ['#MESSAGE#' => $errorMessage], $errorMessage);

			throw new Market\Exceptions\Api\Request($exceptionMessage);
		}
	}

	protected function createSendBoxesRequest(array $boxes, $allowRemove = false)
	{
		$result = $this->provider->getRequestFactory()->create(TradingService\Marketplace\Api\SendOrderBoxes\Request::class);
		$result->setOrderId($this->request->getOrderId());
		$result->setBoxes($this->sanitizeBoxes($boxes));
		$result->setAllowRemove($allowRemove);

		return $result;
	}

	protected function logBoxes(array $boxes)
	{
		$logger = $this->provider->getLogger();
		$instancesCount = $this->getInstancesTotalCount($boxes);
		$message = static::getMessage('SEND_LOG', [
			'#EXTERNAL_ID#' => $this->request->getOrderId(),
			'#BOX_COUNT#' => count($boxes),
			'#ITEMS_COUNT#' => $this->getItemsTotalCount($boxes),
		]);

		if ($instancesCount > 0)
		{
			$message .= static::getMessage('SEND_LOG_INSTANCES', [
				'#COUNT#' => $instancesCount,
			]);
		}

		$logger->info($message, [
			'AUDIT' => $this->getAudit(),
			'ENTITY_TYPE' => TradingEntity\Registry::ENTITY_TYPE_ORDER,
			'ENTITY_ID' => $this->request->getOrderNumber(),
		]);
	}

	protected function isChanged(array $boxes)
	{
		$stored = $this->storedBoxes();

		if ($stored === null || count($boxes) !== count($stored)) { return true; }

		$boxes = $this->sanitizeBoxes($boxes);

		foreach ($boxes as $boxKey => $newBox)
		{
			if (!isset($stored[$boxKey])) { return true; }

			$storedBox = $stored[$boxKey];

			if (count($newBox['items']) !== count($storedBox['items'])) { return true; }

			foreach ($newBox['items'] as $itemKey => $newItem)
			{
				if (!isset($storedBox['items'][$itemKey])) { return true; }

				$storedItem = $storedBox['items'][$itemKey];

				if (
					(string)$newItem['id'] !== (string)$storedItem['id']
					|| !empty($newItem['instances']) !== !empty($storedItem['instances'])
				)
				{
					return true;
				}

				if (
					isset($newItem['fullCount'])
					&& (
						!isset($storedItem['fullCount'])
						|| (int)$newItem['fullCount'] !== (int)$storedItem['fullCount']
					)
				)
				{
					return true;
				}

				if (
					isset($newItem['partialCount'])
					&& (
						!isset($storedItem['partialCount'])
						|| (int)$newItem['partialCount']['current'] !== (int)$storedItem['partialCount']['current']
						|| (int)$newItem['partialCount']['total'] !== (int)$storedItem['partialCount']['total']
					)
				)
				{
					return true;
				}

				if (isset($newItem['instances'], $storedItem['instances']))
				{
					if (count($newItem['instances']) !== count($storedItem['instances'])) { return true; }

					foreach ($newItem['instances'] as $instanceKey => $newInstance)
					{
						if (!isset($storedItem['instances'][$instanceKey])) { return true; }

						$storedInstance = $storedItem['instances'][$instanceKey];

						if (count($newInstance) !== count($storedInstance)) { return true; }

						$diffInstance = array_diff_assoc($newInstance, $storedInstance);

						if (!empty($diffInstance)) { return true; }
					}
				}
			}
		}

		return false;
	}

	protected function getTotalCount()
	{
		$result = $this->getStoredTotalCount();

		if ($result === null)
		{
			$result = $this->getExternalTotalCount();
		}

		return $result;
	}

	protected function getStoredTotalCount()
	{
		$serviceKey = $this->provider->getUniqueKey();
		$orderId = $this->request->getOrderId();
		$stored = Market\Trading\State\OrderData::getValue($serviceKey, $orderId, 'ITEMS_TOTAL');

		return $stored !== null ? (int)$stored : null;
	}

	protected function getExternalTotalCount()
	{
		return (int)$this->getExternalOrder()->getItems()->getTotalCount();
	}

	protected function saveData(array $boxes)
	{
		$serviceKey = $this->provider->getUniqueKey();
		$orderId = $this->request->getOrderId();
		$boxes = $this->sanitizeBoxes($boxes);

		Market\Trading\State\OrderData::setValues($serviceKey, $orderId, [
			'ITEMS_TOTAL' => $this->getItemsTotalCount($boxes),
			'ORDER_BOXES' => $boxes,
		]);
	}

	protected function getItemsTotalCount(array $boxes)
	{
		$result = 0;

		foreach ($boxes as $box)
		{
			if (!isset($box['items'])) { continue; }

			foreach ($box['items'] as $item)
			{
				if (
					isset($item['partialCount']['current'], $item['partialCount']['total'])
					&& (int)$item['partialCount']['current'] === (int)$item['partialCount']['total']
				)
				{
					++$result;
				}
				else if (isset($item['fullCount']))
				{
					$result += (int)$item['fullCount'];
				}
			}
		}

		return $result;
	}

	protected function getInstancesTotalCount(array $boxes)
	{
		$result = 0;

		foreach ($boxes as $box)
		{
			if (!isset($box['items'])) { continue; }

			foreach ($box['items'] as $item)
			{
				if (empty($item['instances'])) { continue; }

				$result += count($item['instances']);
			}
		}

		return $result;
	}

	protected function flatItems(array $boxes)
	{
		$itemGroups = array_column($boxes, 'items');

		if (empty($itemGroups)) { return []; }

		return array_merge(...array_values($itemGroups));
	}

	protected function getMarkerCode()
	{
		return $this->provider->getDictionary()->getErrorCode('SEND_BOXES_ERROR');
	}
}