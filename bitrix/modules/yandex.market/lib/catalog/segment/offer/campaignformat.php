<?php
namespace Yandex\Market\Catalog\Segment\Offer;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;

class CampaignFormat implements Param\Format
{
    public function getDocumentationUrl()
	{
		return null;
	}

	public function getTag()
	{
		return new Xml\Tag\Base([
			'name' => 'offerCampaign',
			'children' => [
                new Xml\Tag\MinQuantity([ 'value_skip' => 1 ]),
                new Xml\Tag\StepQuantity([ 'value_skip' => 1 ]),
			],
		]);
	}
}