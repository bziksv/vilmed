<?php
namespace Yandex\Market\Export\PromoProduct;

use Yandex\Market;

class Model extends Market\Reference\Storage\Model
{
    /** @var array */
	protected $iblockContext;
	/** @var Market\Export\Promo\Discount\AbstractProvider|null*/
	protected $discount;

	/**
	 * Название класса таблицы
	 *
	 * @return class-string<Table>
	 */
	public static function getDataClass()
	{
		return Table::class;
	}

	public function getTagDescriptionList($offerPrimarySource = null)
	{
		return array_merge(
			$this->getCommonDescriptionList($offerPrimarySource),
			$this->getDiscountPriceDescriptionList()
		);
	}

	protected function getCommonDescriptionList($offerPrimarySource = null)
	{
		if (empty($offerPrimarySource))
		{
			$offerPrimarySource = [
				'TYPE' => Market\Export\Entity\Manager::TYPE_IBLOCK_OFFER_FIELD,
				'FIELD' => 'ID'
			];
		}

		return [
			[
				'TAG' => 'product',
				'VALUE' => null,
				'ATTRIBUTES' => [
					'offer-id' => $offerPrimarySource,
				],
				'SETTINGS' => null
			]
		];
	}

	protected function getDiscountPriceDescriptionList()
	{
		/** @var Market\Export\Promo\Model $promo */
		$promo = $this->getParent();
		$result = [];

		if ($this->discount === null || $promo === null || !$promo->hasProductDiscountPrice()) { return $result; }

		$context = $this->getIblockContext();
		$select = $this->discount->getProductPriceSelect($context);

		if (isset($select['PRICE']))
		{
			$attributes = [];

			if (isset($select['CURRENCY']))
			{
				$attributes['currency'] = $select['CURRENCY'];
			}

			$result[] = [
				'TAG' => 'discount-price',
				'VALUE' => $select['PRICE'],
				'ATTRIBUTES' => $attributes,
				'SETTINGS' => null,
			];
		}

		return $result;
	}

	public function getIblockId()
    {
        return (int)$this->getField('IBLOCK_ID');
    }

    public function setDiscount(Market\Export\Promo\Discount\AbstractProvider $discount = null)
    {
        $this->discount = $discount;
    }

    public function getDiscount()
    {
        return $this->discount;
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

    public function getSourceSelect()
    {
        return []; // nothing, all data from iblockLink
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

	public function getContext($isOnlySelf = false)
	{
		$result = $this->getIblockContext();
		$result['HAS_SETUP_IBLOCK'] = false;

		if ($iblockLink = $this->getIblockLink())
        {
        	$result['SITE_ID'] = $iblockLink->getSiteId();
            $result['HAS_SETUP_IBLOCK'] = true;
        }

		if ($this->discount !== null)
		{
			$result += $this->discount->getProductContext();
		}

		if (!$isOnlySelf)
		{
			$result = $this->mergeParentContext($result);
		}

		return $result;
	}

	protected function mergeParentContext(array $selfContext)
	{
		/** @var Market\Export\Promo\Model $parent */
		$parent = $this->getParent();

		if ($parent === null) { return $selfContext; }

		return $selfContext + $parent->getContext();
	}

	protected function getIblockContext()
	{
		if ($this->iblockContext === null)
		{
			$iblockId = $this->getIblockId();

			$this->iblockContext = Market\Export\Entity\Iblock\Provider::getContext($iblockId);
		}

		return $this->iblockContext;
	}

    public function getFilterCollection()
    {
        return $this->getCollection('FILTER', Market\Export\Filter\Collection::class);
    }

    protected function buildCollection($fieldKey, $className)
    {
        if ($this->discount !== null && $fieldKey === 'FILTER')
        {
            return $this->buildFilterCollection($className);
        }

        return parent::buildCollection($fieldKey, $className);
    }

	protected function supportsBatchCollectionLoading($fieldKey)
	{
		if ($this->discount !== null && $fieldKey === 'FILTER')
		{
			$result = false;
		}
		else
		{
			$result = parent::supportsBatchCollectionLoading($fieldKey);
		}

		return $result;
	}

    /**
     * Создаем коллекцию по инфоблокам профиля выгрузки (необходимо для скидок Битрикс)
     *
     * @param class-string<Market\Reference\Storage\Collection> $className
     *
     * @return Market\Reference\Storage\Collection
     */
    protected function buildFilterCollection($className)
    {
        $modelClassName = $className::getItemReference();

        $result = new $className();
        $result->setParent($this);

        if ($this->discount !== null)
        {
            $context = $this->getDiscountFilterContext();
            $filterList = $this->getDiscountProductFilterList($context);

            foreach ($filterList as $filter)
            {
	            /** @var Market\Export\Filter\Model $filterModel */
                $filterModel = $modelClassName::initialize([
                    'ENTITY_TYPE' => Market\Export\Filter\Table::ENTITY_TYPE_PROMO_PRODUCT,
                    'ENTITY_ID' => $this->getId()
                ]);

                $filterModel->setParentCollection($result);
                $filterModel->setPlainFilter((array)$filter['FILTER']);

                if (isset($filter['DATA']))
                {
                    $filterModel->setPlainData($filter['DATA']);
                }

                $result->addItem($filterModel);
            }
        }

        return $result;
    }

    protected function getDiscountFilterContext()
    {
        $result = $this->getIblockContext();
        $result['TAGS'] = [];

        $iblockLink = $this->getIblockLink();

        if ($iblockLink !== null)
        {
            $tags = [
                'oldprice' => [ 'oldprice', 'price' ]
            ];

            foreach ($tags as $tagName => $targetTagList)
            {
                $tagValue = null;

                foreach ($targetTagList as $targetTagName)
                {
                    $targetTagDescription = $iblockLink->getTagDescription($targetTagName);

                    if (isset($targetTagDescription['VALUE']['TYPE'], $targetTagDescription['VALUE']['FIELD']))
                    {
                        $tagValue = $targetTagDescription['VALUE'];
                        break;
                    }
                }

                if ($tagValue !== null)
                {
                    $result['TAGS'][$tagName] = $tagValue;
                }
            }
        }

        return $result;
    }

    /**
     * Настройка инфоблока для профиля выгрузки (доступна только в момент выгрузки)
     *
     * @return Market\Export\IblockLink\Model|null
     */
    protected function getIblockLink()
    {
        /** @var Market\Export\Promo\Model $promo */
        /** @var Market\Export\Setup\Model $setup */
        $promo = $this->getParent();
        $setup = $promo ? $promo->getParent() : null;
        $result = null;

        if ($setup)
        {
            $iblockId = $this->getIblockId();
            $iblockLinkCollection = $setup->getIblockLinkCollection();

            $result = $iblockLinkCollection->getByIblockId($iblockId);
        }

        return $result;
    }

    protected function getDiscountProductFilterList($context)
    {
        $result = [];

        if ($this->discount !== null)
        {
            $result = $this->discount->getProductFilterList($context);
        }

        return $result;
    }
}