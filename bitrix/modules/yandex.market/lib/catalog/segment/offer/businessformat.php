<?php
namespace Yandex\Market\Catalog\Segment\Offer;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Type;

class BusinessFormat implements Param\Format
{
	use Concerns\HasMessage;

	public function getDocumentationUrl()
	{
		return 'https://yandex.ru/dev/market/partner-api/doc/ru/reference/business-assortment/updateOfferMappings';
	}

	public function getTag()
	{
		self::includeSelfMessages();

		return new Xml\Tag\Base([
			'name' => 'offer',
			'children' => [
                new Xml\Tag\Name([ 'name' => 'name', 'preselect' => true ]),
                new Xml\Tag\MarketCategoryId([ 'name' => 'marketCategoryId', 'preselect' => true ]),
                new Xml\Tag\Category(),
                new Xml\Tag\Picture([ 'name' => 'pictures', 'multiple' => true, 'value_type' => new Type\FileType(), 'preselect' => true ]),
                new Xml\Tag\Base([ 'name' => 'videos', 'multiple' => true, 'max_count' => 6, 'value_type' => new Type\FileType() ]),
                new Xml\Tag\Base([ 'name' => 'firstVideoAsCover', 'value_type' => new Type\BooleanType() ]),
                new Xml\Tag\Base([
                    'name' => 'manuals',
                    'multiple' => true,
                    'max_count' => 6,
                    'children' => [
                        new Xml\Tag\Base([ 'name' => 'url', 'value_type' => new Type\FileType(), 'required' => true, 'lang_key' => 'MANUALS_URL' ]),
                        new Xml\Tag\Base([ 'name' => 'title', 'visible' => true, 'lang_key' => 'MANUALS_TITLE' ]),
                    ],
                ]),
                new Xml\Tag\Vendor([ 'name' => 'vendor', 'preselect' => true, 'visible' => true ]),
                new Xml\Tag\Barcode([ 'name' => 'barcodes', 'multiple' => true, 'preselect' => true, 'visible' => true ]),
                new Xml\Tag\Description([ 'name' => 'description', 'preselect' => true ]),
                new Xml\Tag\Base([ 'name' => 'manufacturerCountries', 'multiple' => true ]),
                new Xml\Tag\Base([
                    'name' => 'weightDimensions',
                    'children' => [
                        new Xml\Tag\Width([ 'preselect' => true, 'required' => true ]),
                        new Xml\Tag\Height([ 'preselect' => true, 'required' => true ]),
                        new Xml\Tag\Length([ 'preselect' => true, 'required' => true ]),
                        new Xml\Tag\Weight([ 'preselect' => true, 'required' => true ]),
                    ],
                ]),
                new Xml\Tag\VendorCode([ 'name' => 'vendorCode', 'preselect' => true ]),
                new Xml\Tag\Base([ 'name' => 'tags', 'multiple' => true ]),
                new Tag\TimePeriod([ 'name' => 'shelfLife' ]),
                new Tag\TimePeriod([ 'name' => 'lifeTime' ]),
                new Tag\TimePeriod([ 'name' => 'guaranteePeriod' ]),
                new Xml\Tag\Base([ 'name' => 'customsCommodityCode', 'value_type' => new Type\TnVedCodeType() ]),
                new Xml\Tag\Base([ 'name' => 'certificates', 'multiple' => true ]),
                new Xml\Tag\Base([ 'name' => 'boxCount', 'value_type' => new Type\NumberType() ]),
                new Xml\Tag\Condition([
                    'critical' => true,
                    'attributes' => [
                        new Xml\Attribute\ConditionType([
                            'required' => true,
                            'formatter' => new Type\Formatter\UpperCase(),
                            'preselect' => true,
	                        'lang_key' => 'CONDITION_TYPE',
                        ]),
                    ],
                    'children' => [
                        new Xml\Tag\ConditionReason([
							'name' => 'reason',
	                        'required' => true,
	                        'preselect' => true,
	                        'lang_key' => 'CONDITION_REASON',
                        ]),
                        new Xml\Tag\ConditionQuality([
                            'name' => 'quality',
                            'required' => true,
                            'formatter' => new Type\Formatter\UpperCase(),
                            'preselect' => true,
	                        'lang_key' => 'CONDITION_QUALITY',
                        ]),
                    ],
                ]),
                new Xml\Tag\Base([
                    'name' => 'type',
                    'value_type' => new Type\EnumType(new Listing\Type()),
                ]),
                new Xml\Tag\Base([ 'name' => 'downloadable', 'value_type' => new Type\BooleanType() ]),
                new Xml\Tag\Base([ 'name' => 'adult', 'value_type' => new Type\BooleanType() ]),
                new Xml\Tag\Age([
                    'name' => 'age',
                    'unit_attribute' => 'ageUnit',
                    'attributes' => [
                        new Xml\Attribute\Base([
                            'name' => 'ageUnit',
                            'required' => true,
                            'value_type' => (new Type\EnumType(new Listing\AgeUnit()))
                                ->addFormatter(new Type\Formatter\UpperCase()),
                        ]),
                    ],
                ]),
                new Xml\Tag\Base([
                    'name' => 'marketSku',
                    'value_type' => new Type\NumberType([
                        Type\NumberType::SETTING_PRECISION => 0,
                        Type\NumberType::SETTING_POSITIVE => true,
                    ]),
                ]),
                new Xml\Tag\Base([ 'name' => 'onlyPartnerMediaContent', 'value_type' => new Type\BooleanType() ]),

                new Xml\Tag\MinQuantity([ 'preselect' => true, 'value_skip' => 1 ]),
                new Xml\Tag\StepQuantity([ 'preselect' => true, 'value_skip' => 1 ]),
			],
		]);
	}
}