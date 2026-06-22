<?php
namespace Yandex\Market\Export\IblockLink;

use Yandex\Market;
use Yandex\Market\Export\Glossary;
use Yandex\Market\Watcher;

/**
 * @method Market\Export\Setup\Model getParent()
 */
class Model extends Market\Reference\Storage\Model
{
	protected $iblockContext;
	protected $tagDescriptionList;

	public function getTagDescription($tagName)
	{
		$result = null;
		$tagDescriptionList = $this->getTagDescriptionList();

		foreach ($tagDescriptionList as $tagDescription)
		{
			if ($tagDescription['TAG'] === $tagName)
			{
				$result = $tagDescription;
				break;
			}
		}

		return $result;
	}

	public function getTagDescriptionList()
	{
		return $this->getParamCollection()->getTagMap()->getRaw();
	}

	public function getSourceSelect()
	{
		$result = $this->getParamCollection()->getTagMap()->getSourceSelect();

		return $this->extendSourceSelect($result);
	}

	protected function extendSourceSelect($sourceSelect)
	{
		$context = $this->getContext();

		foreach ($sourceSelect as $sourceType => $sourceFields)
		{
			$source = Market\Export\Entity\Manager::getSource($sourceType);

			$source->initializeQueryContext($sourceFields, $context, $sourceSelect);
			$source->releaseQueryContext($sourceFields, $context, $sourceSelect);
		}

		return $sourceSelect;
	}

	public function getUsedSources()
	{
		$result = $this->getSourceSelect();

		foreach ($this->getFilterCollection() as $filterModel)
		{
			$filterUserSources = $filterModel->getUsedSources();

			foreach ($filterUserSources as $sourceType)
			{
				if (!isset($result[$sourceType]))
				{
					$result[$sourceType] = true;
				}
			}
		}

		return array_keys($result);
	}

	public function getTrackSourceList()
	{
		$sourceList = $this->getUsedSources();
		$context = $this->getContext();
		$result = [];

		foreach ($sourceList as $sourceType)
		{
			$eventHandler = Market\Export\Entity\Manager::getEvent($sourceType);

            $result[] = [
                'SOURCE_TYPE' => $sourceType,
                'SOURCE_PARAMS' => $eventHandler->getSourceParams($context)
            ];
		}

		return $result;
	}

	public function getSetupBindEntities()
	{
		$context = $this->getIblockContext();
		$result = [
			new Watcher\Track\BindEntity(Glossary::ENTITY_OFFER, $context['IBLOCK_ID']),
		];

		if ($context['HAS_OFFER'])
		{
			$result[] = new Watcher\Track\BindEntity(Glossary::ENTITY_OFFER, $context['OFFER_IBLOCK_ID']);
		}

		if ($this->hasCurrencyConversion())
		{
			$result[] = new Market\Watcher\Track\BindEntity(Glossary::ENTITY_CURRENCY);
		}

		return $result;
	}

	protected function hasCurrencyConversion()
	{
		$result = false;
		$tags = [
			'price',
			'oldprice',
			'currencyId',
		];

		foreach ($tags as $tagName)
		{
			$tagDescription = $this->getTagDescription($tagName);

			if (!isset($tagDescription['VALUE']['TYPE'], $tagDescription['VALUE']['FIELD'])) { continue; }

			$source = Market\Export\Entity\Manager::getSource($tagDescription['VALUE']['TYPE']);

			if (
				method_exists($source, 'hasCurrencyConversion')
				&& $source->hasCurrencyConversion($tagDescription['VALUE']['FIELD'], $tagDescription['SETTINGS'])
			)
			{
				$result = true;
				break;
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function getContext()
	{
		$result = [
			'IBLOCK_LINK_ID' => $this->getId(),
		];

		$result += $this->getIblockContext();
		$result += $this->getTagContext();

		// sales notes

		$salesNotes = $this->getSalesNotes();

		if ($salesNotes !== '')
		{
			$result['SALES_NOTES'] = $salesNotes;
		}

		// delivery options

		$deliveryOptions = $this->getDeliveryOptions();

		if (!empty($deliveryOptions))
		{
			$result['DELIVERY_OPTIONS'] = $deliveryOptions;
		}

		$result = $this->mergeParentContext($result);

		return $result;
	}

	protected function mergeParentContext($selfContext)
	{
		$setup = $this->getParent();

		if ($setup === null) { return $selfContext; }

		$result = $selfContext + $setup->getContext();

		if (isset($result['DELIVERY_OPTIONS']) && !isset($selfContext['DELIVERY_OPTIONS']))
		{
			unset($result['DELIVERY_OPTIONS']);
		}

		return $result;
	}

	protected function getIblockContext()
	{
		if ($this->iblockContext === null)
		{
			$iblockId = $this->getIblockId();
			$iblockContext = Market\Export\Entity\Iblock\Provider::getContext($iblockId);

			if (count($iblockContext['SITE_LIST']) > 1)
			{
				$setup = $this->getParent();

				if ($setup instanceof Market\Export\Setup\Model)
				{
					$domain = $setup->getDomain();
					$path = $setup->getDomainPath();
					$domainSiteId = Market\Data\SiteDomain::getSite($domain, $path);

					if ($domainSiteId !== null && in_array($domainSiteId, $iblockContext['SITE_LIST'], true))
					{
						$iblockContext['SITE_ID'] = $domainSiteId;
					}
				}
			}

			$this->iblockContext = $iblockContext;
		}

		return $this->iblockContext;
	}

	protected function getTagContext()
	{
		$result = [];
		$priceTag = $this->getTagDescription('price');

		if (isset($priceTag['SETTINGS']['USER_GROUP']))
		{
			$selectedGroup = (int)$priceTag['SETTINGS']['USER_GROUP'];
			$groups = Market\Data\UserGroup::getDefaults();

			if (!in_array($selectedGroup, $groups, true))
			{
				$groups[] = $selectedGroup;
			}

			$result['USER_GROUPS'] = $groups;
		}

		return $result;
	}

	public function getDeliveryOptions()
	{
		$deliveryCollection = $this->getDeliveryCollection();

		return $deliveryCollection->getDeliveryOptions();
	}

	public function getSalesNotes()
	{
		return trim($this->getField('SALES_NOTES'));
	}

	public function getIblockId()
	{
		return (int)$this->getField('IBLOCK_ID');
	}

	public function getOfferIblockId()
	{
		$iblockContext = $this->getIblockContext();

		return (isset($iblockContext['OFFER_IBLOCK_ID']) ? $iblockContext['OFFER_IBLOCK_ID'] : null);
	}

	/** @noinspection PhpUnused */
	public function getOfferPropertyId()
	{
		$iblockContext = $this->getIblockContext();

		return (isset($iblockContext['OFFER_PROPERTY_ID']) ? $iblockContext['OFFER_PROPERTY_ID'] : null);
	}

	public function isExportAll()
	{
		return $this->getField('EXPORT_ALL') === '1';
	}

	public function getSiteId()
	{
		$iblockContext = $this->getIblockContext();

		return $iblockContext['SITE_ID'];
	}

	/** @noinspection PhpUnused */
	public function hasIblockCatalog()
	{
		$iblockContext = $this->getIblockContext();

		return $iblockContext['HAS_CATALOG'];
	}

	/** @noinspection PhpUnused */
	public function isIblockCatalogOnlyOffers()
	{
		$iblockContext = $this->getIblockContext();

		return !empty($iblockContext['OFFER_ONLY']);
	}

	/** @noinspection PhpUnused */
	public function hasIblockOffers()
	{
		$iblockContext = $this->getIblockContext();

		return $iblockContext['HAS_OFFER'];
	}

	public static function getDataClass()
	{
		return Table::class;
	}

	public function getFilterCollection()
	{
		return $this->getCollection('FILTER', Market\Export\Filter\Collection::class);
	}

	public function getParamCollection()
	{
		return $this->getCollection('PARAM', Market\Export\Param\Collection::class);
	}

	public function getDeliveryCollection()
	{
		return $this->getCollection('DELIVERY', Market\Export\Delivery\Collection::class);
	}

	protected function queryChildCollection($collectionClassName, $fieldKey)
	{
		if ($fieldKey === 'PARAM')
		{
			$queryParams = $this->getChildCollectionQueryParameters($fieldKey);

			if ($queryParams === null) { return null; }

			return Market\Export\Param\Repository::loadCollection($this, $queryParams);
        }

		return parent::queryChildCollection($collectionClassName, $fieldKey);
	}
}