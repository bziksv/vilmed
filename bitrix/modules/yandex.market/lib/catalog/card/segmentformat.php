<?php
namespace Yandex\Market\Catalog\Card;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns\HasMessage;

class SegmentFormat implements Param\Format
{
	use HasMessage;

    public function getDocumentationUrl()
    {
        return 'https://yandex.ru/dev/market/partner-api/doc/ru/reference/content/updateOfferContent';
    }

    public function getTag()
    {
		self::includeSelfMessages();

        return new Xml\Tag\Base([
            'name' => 'card',
            'children' => [
                new Tag\ParameterValues([ 'preselect' => true, 'multiple' => true ]),
                new Tag\Param([
                    'multiple' => true,
                    'preselect' => true,
                    'attributes' => [
                        new Tag\NameAttribute([ 'required' => true, 'preselect' => true, 'lang_key' => 'PARAM_NAME' ]),
                        new Xml\Attribute\Base([ 'name' => 'unit', 'lang_key' => 'PARAM_UNIT' ]),
                    ],
                ]),
            ],
        ]);
    }
}