<?php
namespace Yandex\Market\Export\Xml\Tag\Concerns;

interface HasTagValueChecker
{
	public function checkTagValue($tagValue);
}