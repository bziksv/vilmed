<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Export\Xml;

class Root extends Base
{
    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        return true;
    }

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
		return $parent->addChild($this->name, new Xml\Data\RootValue());
	}
}