<?php
namespace Yandex\Market\Catalog\Card;

use Yandex\Market\Catalog;

/** @property Model[] $collection */
class Collection extends Catalog\Segment\Collection
{
    public static function getItemReference()
    {
        return Model::class;
    }
}