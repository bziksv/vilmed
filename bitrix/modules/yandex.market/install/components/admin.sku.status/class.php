<?php
namespace Yandex\Market\Components;

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Bitrix\Iblock;
use Yandex\Market\Utils as MarketUtils;
use Yandex\Market\Api;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Service as TradingService;

Loc::loadMessages(__FILE__);

/** @noinspection PhpUnused */
class AdminSkuStatus extends \CBitrixComponent
{
	const LIMIT_FULL = 50;
	const LIMIT_PRODUCT = 200;

	private static $iblockLimitCache = [];

	public function onPrepareComponentParams($arParams)
	{
		$arParams['IBLOCK_ID'] = (int)$arParams['IBLOCK_ID'];
		$arParams['ELEMENT_ID'] = (int)$arParams['ELEMENT_ID'];

		return $arParams;
	}

	public function executeComponent()
	{
		if ($this->arParams['ELEMENT_ID'] <= 0) { return ''; }

		$this->arResult['LIMIT'] = self::iblockLimit($this->arParams['IBLOCK_ID']);

		$this->includeComponentTemplate();

		return $this->arResult['HTML'];
	}

	private static function iblockLimit($iblockId)
	{
		$iblockId = (int)$iblockId;

		if ($iblockId <= 0 || !Main\Loader::includeModule('catalog'))
		{
			return self::LIMIT_PRODUCT;
		}

		if (!isset(self::$iblockLimitCache[$iblockId]))
		{
			$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

			self::$iblockLimitCache[$iblockId] = (
				$catalog !== false && defined('CCatalogSku::TYPE_FULL') && $catalog['CATALOG_TYPE'] === \CCatalogSku::TYPE_FULL
					? self::LIMIT_FULL
					: self::LIMIT_PRODUCT
			);
		}

		return self::$iblockLimitCache[$iblockId];
	}

	public function loadAction($iblockId, array $elementIds)
	{
		if (empty($elementIds)) { return []; }

		$this->checkIblockReadAccess($iblockId);

		$businesses = $this->businesses($iblockId);
		$elementStatuses = $this->collectElementStatuses($businesses, $iblockId, $elementIds);
		$elementStatuses = $this->calculateAverageRating($elementStatuses);

		return [
			'businesses' => $this->compileBusinessPayload($businesses),
			'elements' => $elementStatuses,
		];
	}

	private function checkIblockReadAccess($iblockId)
	{
		if (
			!Main\Loader::includeModule('iblock')
			|| !\CIBlockRights::UserHasRightTo($iblockId, $iblockId, "iblock_admin_display")
		)
		{
			throw new Main\AccessDeniedException(Loc::getMessage('YANDEX_MARKET_SKU_STATUS_READ_ACCESS_DENIED'));
		}
	}

	private function businesses($iblockId)
	{
		$businesses = [];

		foreach (Business\Model::loadList() as $business)
		{
			if (!$this->canUseSkuMapForIblock($business, $iblockId) || !$this->isActiveBusiness($business)) { continue; }

			$businesses[] = $business;
		}

		return $businesses;
	}

	private function mapElementIdsToOfferIds($iblockId, array $elementIds)
	{
		if (empty($elementIds) || !Main\Loader::includeModule('catalog'))
		{
			return array_combine($elementIds, $elementIds);
		}

		$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

		if (
			$catalog === false
			|| !defined('CCatalogSku::TYPE_FULL')
			|| $catalog['CATALOG_TYPE'] !== \CCatalogSku::TYPE_FULL
			|| empty($catalog['SKU_PROPERTY_ID'])
			|| !Main\Loader::includeModule('iblock')
		)
		{
			return array_combine($elementIds, $elementIds);
		}

		$parents = array_fill_keys($elementIds, true);
		$offerMap = [];

		foreach (array_chunk($elementIds, 500) as $elementsChunk)
		{
			$query = \CIBlockElement::GetPropertyValues(
				$catalog['IBLOCK_ID'],
				[ '=PROPERTY_' . $catalog['SKU_PROPERTY_ID'] => $elementsChunk ],
				false,
				[ 'ID' => $catalog['SKU_PROPERTY_ID'] ]
			);

			while ($propertyRow = $query->Fetch())
			{
				$offerId = (int)$propertyRow['IBLOCK_ELEMENT_ID'];
				$parentId = (int)$propertyRow[$catalog['SKU_PROPERTY_ID']];

				if (!isset($parents[$parentId])) { continue; }

				$parents[$parentId] = false;
				$offerMap[$offerId] = $parentId;
			}
		}

		$standaloneParents = array_keys(array_filter($parents));

		return $offerMap + array_combine($standaloneParents, $standaloneParents);
	}

	private function collectElementStatuses(array $businesses, $iblockId, $elementIds)
	{
		$offerMap = $this->mapElementIdsToOfferIds($iblockId, $elementIds);
		$result = $this->skeletonElementStatuses($elementIds);

		/** @var Business\Model $business */
		foreach ($businesses as $business)
		{
			try
			{
				$skuMap = $this->mapOfferIdsToSku($business, array_keys($offerMap));
				$skuMap = $this->limitSkuCount($skuMap, $offerMap);
				$skuStatuses = $this->fetchSkuStatuses($business, array_values($skuMap));
				$skuStatuses = $this->fulfilSkuName($skuStatuses, $skuMap);

				$result = $this->combineElementStatuses($business, $skuStatuses, $skuMap, $offerMap, $result);
				$result = $this->groupElementStatusesOffers($result);
			}
			catch (\Exception $exception)
			{
				if (!($exception instanceof Main\SystemException))
				{
					Main\Application::getInstance()->getExceptionHandler()->writeToLog($exception);
				}

				$result = $this->writeElementStatusesError($business, $elementIds, $exception->getMessage(), $result);
			}
				/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */
			catch (\Throwable $exception)
			{
				Main\Application::getInstance()->getExceptionHandler()->writeToLog($exception);

				$result = $this->writeElementStatusesError($business, $elementIds, $exception->getMessage(), $result);
			}
		}

		return $result;
	}

	private function skeletonElementStatuses(array $elementIds)
	{
		$result = [];

		foreach ($elementIds as $elementId)
		{
			$result[$elementId] = [
				'rating' => null,
				'businesses' => [],
			];
		}

		return $result;
	}

	private function isActiveBusiness(Business\Model $business)
	{
		if ($business->getTradingCollection()->getActive() !== null)
		{
			return true;
		}

		$catalog = $business->getCatalog();

		return ($catalog !== null && $catalog->wasSubmitted() && $catalog->isActive());
	}

	private function canUseSkuMapForIblock(Business\Model $business, $iblockId)
	{
		$iblockIds = $business->getOptions()->getSkuMap()->getIblockIds();

		if (empty($iblockIds) || in_array($iblockId, $iblockIds, true)) { return true; }

		if (!Main\Loader::includeModule('catalog')) { return false; }

		$catalog = \CCatalogSku::GetInfoByIBlock($iblockId);

		if ($catalog === false) { return false; }

		return (
			in_array((int)$catalog['IBLOCK_ID'], $iblockIds, true)
			|| (
				!empty($catalog['PRODUCT_IBLOCK_ID'])
				&& in_array((int)$catalog['PRODUCT_IBLOCK_ID'], $iblockIds, true)
			)
		);
	}

	private function mapOfferIdsToSku(Business\Model $business, array $offerIds)
	{
		if (empty($offerIds)) { return []; }

		/** @var TradingService\Common\Command\SkuMap $command */
		$trading = $business->getPrimaryTrading();
		$command = $trading->wakeupService()->getContainer()->get(TradingService\Common\Command\SkuMap::class, [
			'environment' => $trading->getEnvironment(),
		]);
		$skuMap = $command->make($offerIds);

		if ($skuMap === null)
		{
			return array_combine($offerIds, $offerIds);
		}

		return $skuMap;
	}

	private function limitSkuCount(array $skuMap, array $offerMap)
	{
		$limit = Api\Business\OfferCards\Request::OFFER_IDS_LIMIT;
		$overhead = count($skuMap) - $limit;

		if ($overhead <= 0) { return $skuMap; }

		$groups = [];

		foreach ($skuMap as $offerId => $sku)
		{
			$elementId = $offerMap[$offerId];

			if (!isset($groups[$elementId]))
			{
				$groups[$elementId] = [];
			}

			$groups[$elementId][] = $offerId;
		}

		$groupsCount = count($groups);

		if ($groupsCount === 0) { return $skuMap; }

		$elementLimit = max(1, floor($limit / $groupsCount));

		do
		{
			$hasHugeGroup = false;

			foreach ($groups as &$group)
			{
				if (count($group) <= $elementLimit) { continue; }

				$hasHugeGroup = true;
				$offerId = array_pop($group);

				unset($skuMap[$offerId]);

				if (--$overhead <= 0) { break; }
			}
			unset($group);
		}
		while ($overhead > 0 && $hasHugeGroup);

		return $skuMap;
	}

	private function fetchSkuStatuses(Business\Model $business, array $skus)
	{
		$skuStatuses = [];

		foreach (array_chunk($skus, Api\Business\OfferCards\Request::OFFER_IDS_LIMIT) as $skusChunk)
		{
			$request = new Api\Business\OfferCards\Request($business->getId(), $business->getOptions()->getApiAuth(), $business->createLogger());
			$request->setOfferIds($skusChunk);

			/** @var Api\Business\OfferCards\OfferCard $offerCard */
			foreach ($request->execute()->getOfferCards() as $offerCard)
			{
				$messageGroups = $offerCard->groupMessages();
				$messageGroups = $this->uniqueMessages($messageGroups);

				$skuStatuses[] = [
					'sku' => $offerCard->getOfferId(),
					'name' => $offerCard->getMapping()->getMarketSkuName(),
					'rating' => $offerCard->getContentRating(),
					'caption' => $this->captionMessages($messageGroups),
					'messages' => $messageGroups,
					'messagesHash' => $this->hashMessages($messageGroups),
				];
			}
		}

		return $skuStatuses;
	}

	private function uniqueMessages(array $messageGroups)
	{
		foreach ($messageGroups as &$messageGroup)
		{
			$unique = [];
			$changed = false;

			foreach ($messageGroup as $messageKey => $message)
			{
				$text = ($message['comment'] ?: $message['message']);

				if (isset($unique[$text]))
				{
					$changed = true;
					unset($messageGroup[$messageKey]);
					continue;
				}

				$unique[$text] = true;
			}

			if ($changed)
			{
				$messageGroup = array_values($messageGroup);
			}
		}
		unset($messageGroup);

		return $messageGroups;
	}

	private function hashMessages(array $groups)
	{
		$partials = [];

		foreach ($groups as $type => $group)
		{
			foreach ($group as $message)
			{
				$partials[] = $type . ':' . ($message['comment'] ?: $message['message']);
			}
		}

		$partials = array_unique($partials);
		sort($partials);

		$text = strip_tags(implode(PHP_EOL, $partials));
		$text = preg_replace('/\(SKU: .*?\)/', '', $text);

		return md5($text);
	}

	private function captionMessages(array $groups)
	{
		$caption = null;
		$morePartials = [];

		foreach ($groups as $type => $messages)
		{
			if ($caption === null)
			{
				$message = array_shift($messages);

				if ($message === null) { continue; }

				$caption = $message['message'] ?: $message['comment'];
			}

			$messagesCount = count($messages);

			if ($messagesCount === 0) { continue; }

			$typeUpper = mb_strtoupper($type);
			$typeTitle = MarketUtils::sklon($messagesCount, [
				Loc::getMessage('YANDEX_MARKET_SKU_STATUS_' . $typeUpper . '_1'),
				Loc::getMessage('YANDEX_MARKET_SKU_STATUS_' . $typeUpper . '_2'),
				Loc::getMessage('YANDEX_MARKET_SKU_STATUS_' . $typeUpper . '_5'),
			]);

			$morePartials[] = $messagesCount . '&nbsp;' . mb_strtolower($typeTitle);
		}

		if ($caption === null) { return null; }

		return [
			'text' => $caption,
			'more' => $this->mergeCaptionMorePartials($morePartials),
		];
	}

	private function mergeCaptionMorePartials(array $morePartials)
	{
		if (empty($morePartials)) { return ''; }

		if (count($morePartials) === 1)
		{
			return Loc::getMessage('YANDEX_MARKET_SKU_STATUS_AND') . ' ' . $morePartials[0];
		}

		$lastMore = array_pop($morePartials);

		return (
			Loc::getMessage('YANDEX_MARKET_SKU_STATUS_MORE') . ' '
			. implode(', ', $morePartials)
			. ' ' . Loc::getMessage('YANDEX_MARKET_SKU_STATUS_AND') . ' '
			. $lastMore
		);
	}

	private function fulfilSkuName(array $skuStatuses, array $skuMap)
	{
		$skusWithoutName = array_column(array_filter(
			$skuStatuses,
			static function(array $skuStatus) { return ((string)$skuStatus['name'] === ''); }
		), 'sku');
		$skuMap = array_intersect($skuMap, $skusWithoutName);

		if (empty($skuMap) || !Main\Loader::includeModule('iblock')) { return $skuStatuses; }

		$query = Iblock\ElementTable::getList([
			'filter' => [ '=ID' => array_keys($skuMap) ],
			'select' => [ 'ID', 'NAME' ],
		]);

		while ($row = $query->fetch())
		{
			if (!isset($skuMap[$row['ID']])) { continue; }

			$sku = (string)$skuMap[$row['ID']];

			foreach ($skuStatuses as &$skuStatus)
			{
				if ($skuStatus['sku'] !== $sku || (string)$skuStatus['name'] !== '') { continue; }

				$skuStatus['name'] = $row['NAME'];
			}
			unset($skuStatus);
		}

		return $skuStatuses;
	}

	private function combineElementStatuses(Business\Model $business, array $skuStatuses, array $skuMap, array $offerMap, array $ready = [])
	{
		$skuToOfferMap = array_flip($skuMap);
		$businessId = $business->getId();

		foreach ($skuStatuses as $skuStatus)
		{
			if (!isset($skuToOfferMap[$skuStatus['sku']])) { continue; }

			$offerId = $skuToOfferMap[$skuStatus['sku']];

			if (!isset($offerMap[$offerId])) { continue; }

			$elementId = $offerMap[$offerId];

			if (!isset($ready[$elementId]['businesses'][$businessId]))
			{
				$ready[$elementId]['businesses'][$businessId] = [
					'rating' => null,
					'offers' => [],
				];
			}

			$ready[$elementId]['businesses'][$businessId]['offers'][] = $skuStatus + [
				'id' => $offerId,
			];
		}

		return $ready;
	}

	private function groupElementStatusesOffers(array $elementStatuses)
	{
		foreach ($elementStatuses as &$elementStatus)
		{
			foreach ($elementStatus['businesses'] as &$businessStatus)
			{
				if (empty($businessStatus['offers'])) { continue; }

				$hashMap = [];
				$changed = false;

				foreach ($businessStatus['offers'] as $offerKey => $offer)
				{
					if (!isset($hashMap[$offer['messagesHash']]))
					{
						$hashMap[$offer['messagesHash']] = $offerKey;
						continue;
					}

					$baseKey = $hashMap[$offer['messagesHash']];
					$baseOffer = &$businessStatus['offers'][$baseKey];

					if (!isset($baseOffer['siblings'])) { $baseOffer['siblings'] = []; }

					$baseOffer['siblings'][] = array_intersect_key($offer, [
						'sku' => true,
						'name' => true,
					]);

					unset($businessStatus['offers'][$offerKey], $baseOffer);
					$changed = true;
				}

				if ($changed)
				{
					$businessStatus['offers'] = array_values($businessStatus['offers']);
				}
			}
			unset($businessStatus);
		}
		unset($elementStatus);

		return $elementStatuses;
	}

	private function writeElementStatusesError(Business\Model $business, array $elementIds, $errorMessage, array $ready)
	{
		$businessId = $business->getId();

		foreach ($elementIds as $elementId)
		{
			$ready[$elementId]['businesses'][$businessId]['error'] = $errorMessage;
		}

		return $ready;
	}

	private function compileBusinessPayload(array $businesses)
	{
		$payload = [];

		/** @var Business\Model $business */
		foreach ($businesses as $business)
		{
			$payload[] = [
				'id' => $business->getId(),
				'name' => $business->getName(),
			];
		}

		return $payload;
	}

	private function calculateAverageRating(array $elementStatuses)
	{
		foreach ($elementStatuses as &$elementStatus)
		{
			$elementRatings = [];

			foreach ($elementStatus['businesses'] as &$businessStatus)
			{
				if (empty($businessStatus['offers'])) { continue; }

				$businessRatings = [];

				foreach ($businessStatus['offers'] as $offer)
				{
					if ($offer['rating'] === null) { continue; }

					$businessRatings[] = $offer['rating'];
					$elementRatings[] = $offer['rating'];
				}

				$businessStatus['rating'] = !empty($businessRatings)
					? floor(array_sum($businessRatings) / count($businessRatings))
					: null;
			}
			unset($businessStatus);

			$elementStatus['rating'] = !empty($elementRatings)
				? floor(array_sum($elementRatings) / count($elementRatings))
				: null;
		}
		unset($elementStatus);

		return $elementStatuses;
	}
}