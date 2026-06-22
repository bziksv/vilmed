<?php
namespace Yandex\Market\Export\Xml\Tag\Concerns;

interface HasTagValueModifier
{
    public function modifyTagValues(array $tagValues, array $context);
}