<?php
namespace Yandex\Market\Catalog\Segment\Offer\Tag;

use Yandex\Market\Export\Xml;
use Yandex\Market\Type;

class TimePeriod extends Xml\Tag\Base
    implements Xml\Tag\Concerns\HasTagValueModifier
{
    private $timePeriodType;

    public function __construct(array $parameters = [])
    {
        parent::__construct($parameters);

        $this->timePeriodType = new Type\TimePeriodType();
		$this->hasEmptyValue = false;
    }

    public function getDefaultParameters()
    {
        return [
            'name' => 'timePeriod',
	        'value_type' => Type\Manager::TYPE_NUMBER,
	        'value_positive' => true,
            'children' => [
                new Xml\Tag\Base([
                    'name' => 'timeUnit',
                    'required' => true,
                    'value_type' => Type\Manager::TYPE_ENUM,
                    'value_listing' => new Xml\Listing\PeriodUnit(),
                ]),
                new Xml\Tag\Base([
                    'name' => 'comment',
                    'visible' => true,
                ]),
            ],
        ];
    }

    public function tune(array $context)
    {
        $unit = $this->getChild('timeUnit');

        if ($unit !== null)
        {
            $unit->extendParameters([
                'required' => false,
            ]);
        }
    }

    public function modifyTagValues(array $tagValues, array $context)
    {
        foreach ($tagValues as &$tagValue)
        {
            if (!isset($tagValue['VALUE'])) { continue; }

            $timePeriod = $tagValue['VALUE'];

            if (!is_string($timePeriod) || is_numeric($timePeriod)) { continue; }

            $parts = $this->timePeriodType->sanitize($timePeriod);

            if (empty($parts)) { continue; }

            list($period, $unit) = reset($parts);

            $tagValue['VALUE'] = $period;

            if (!isset($tagValue['CHILDREN']['timeUnit'])) { $tagValue['CHILDREN']['timeUnit'] = []; }

            $tagValue['CHILDREN']['timeUnit']['VALUE'] = $unit;
        }
        unset($tagValue);

        return $tagValues;
    }

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
		$wrapper = $parent->addChild($this->name, null, $this->isMultiple);
		$wrapper->addChild('timePeriod', $value);

		return $wrapper;
	}
}