<?php
namespace Yandex\Market\Catalog\Segment;

use Yandex\Market\Reference\Storage;

/** @property Model[] $collection */
class Collection extends Storage\Collection
{
    public static function getItemReference()
    {
        return Model::class;
    }

	/** @return Model|null */
	public function getBusinessItem()
	{
		foreach ($this->collection as $segment)
		{
			if ($segment->isBusiness())
			{
				return $segment;
			}
		}

		return null;
	}

    public function getCampaignItem($campaignId)
    {
        foreach ($this->collection as $segment)
        {
            if ($segment->getCampaignId() === (int)$campaignId)
            {
                return $segment;
            }
        }

        return null;
    }

	public function getCampaignItems()
	{
		$result = new static();

		foreach ($this->collection as $segment)
		{
			if ($segment->getCampaignId() > 0)
			{
				$result->addItem($segment);
			}
		}

		return $result;
	}
}