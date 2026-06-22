<?php
namespace Yandex\Market\Catalog\Segment\Price;

use Yandex\Market\Type;
use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml\Tag;

class CampaignTermsFormat implements Param\Format
{
	public function getDocumentationUrl()
	{
		return null;
	}

	public function getTag()
	{
		return new Tag\Base([
			'name' => 'campaignVat',
			'children' => [
				new Tag\Vat([ 'value_format' => Type\VatType::FORMAT_NUMERIC ]),
			],
		]);
	}
}