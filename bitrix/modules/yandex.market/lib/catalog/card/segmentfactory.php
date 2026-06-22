<?php
namespace Yandex\Market\Catalog\Card;

use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Segment\Collection;
use Yandex\Market\Export\Param\TagBundle;
use Yandex\Market\Trading\Business;

class SegmentFactory implements Catalog\Segment\Factory
{
    public function businessConfig(Business\Model $business)
    {
        return new Catalog\Segment\BusinessConfig(new SegmentFormat());
    }

    public function campaignConfigs(Business\Model $business)
    {
        return [];
    }

    public function endpoints(Business\Model $business, Collection $segmentCollection)
    {
        $businessSegment = $segmentCollection->getBusinessItem();

        if ($businessSegment === null) { return null; }

        return [
            new Catalog\Endpoint\Endpoint(
                new Catalog\Endpoint\Card($business->getId()),
                new TagBundle(
                    (new SegmentFormat())->getTag(),
                    $businessSegment->getParamCollection()->getTagMap()
                )
            ),
        ];
    }
}