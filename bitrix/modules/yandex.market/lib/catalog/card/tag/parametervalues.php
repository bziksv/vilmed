<?php
namespace Yandex\Market\Catalog\Card\Tag;

use Yandex\Market\Export\Entity;
use Yandex\Market\Export\Entity\Market\Property\Source;
use Yandex\Market\Export\Xml\Tag;
use Yandex\Market\Type;

class ParameterValues extends Tag\Base
{
    public function getDefaultParameters()
    {
        return [
            'name' => 'parameterValues',
            'value_type' => Type\Manager::TYPE_CARD_PARAMETERS,
        ];
    }

    public function tune(array $context)
    {
        $this->isMultiple = false;
    }

    public function getSourceRecommendation(array $context = [])
    {
        return [
            [
                'TYPE' => Entity\Manager::TYPE_MARKET_PROPERTY,
                'FIELD' => Source::FIELD_PARAMETERS,
            ],
        ];
    }
}