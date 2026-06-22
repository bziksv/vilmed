<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Xml\Routine\Recommendation;
use Yandex\Market\Reference\Concerns;

class VendorCode extends Base
{
	use Concerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'name' => 'vendorCode',
		];
	}

	public function getSourceRecommendation(array $context = [])
	{
		return Recommendation\Property::filter([
			'LOGIC' => 'OR',
			[ '%CODE' => [ 'CML2_ARTICLE', 'ARTICLE', 'VENDOR_CODE' ] ],
			[ '%NAME' => explode(',', self::getMessage('FILTER_TITLE')) ],
		], $context);
	}
}