<?php
namespace Yandex\Market\Catalog\Card\Tag;

use Yandex\Market\Export\Xml\Tag;
use Yandex\Market\Export\Entity;

class Param extends Tag\Param
{
	public function preselect(array $context)
    {
        $result = [];

        if (!empty($context['OFFER_PROPERTY_ID']))
        {
            $result[] = [
               'TYPE' => Entity\Manager::TYPE_IBLOCK_OFFER_PROPERTY,
               'FIELD' => $context['OFFER_PROPERTY_ID'],
            ];
        }

        return $result;
    }
}