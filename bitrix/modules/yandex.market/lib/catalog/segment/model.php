<?php
namespace Yandex\Market\Catalog\Segment;

use Yandex\Market\Export;
use Yandex\Market\Reference;

class Model extends Reference\Storage\Model
{
	public static function getDataClass()
	{
		return Table::class;
	}

    public function isBusiness()
    {
        return ($this->getCampaignId() === 0);
    }

    public function isCampaign()
    {
        return ($this->getCampaignId() > 0);
    }

	public function getCampaignId()
	{
		return (int)$this->getField('CAMPAIGN_ID');
	}

    public function getParamCollection()
    {
        return $this->getCollection('PARAM', Export\Param\Collection::class);
    }

    protected function queryChildCollection($collectionClassName, $fieldKey)
    {
        if ($fieldKey === 'PARAM')
        {
	        $queryParams = $this->getChildCollectionQueryParameters($fieldKey);

			if ($queryParams === null) { return null; }

            return Export\Param\Repository::loadCollection($this, $queryParams);
        }

        return parent::queryChildCollection($collectionClassName, $fieldKey);
    }
}