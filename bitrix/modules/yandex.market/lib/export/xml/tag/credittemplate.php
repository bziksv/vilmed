<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Type;
use Yandex\Market\Export\Xml;

class CreditTemplate extends Base
{
	public function getDefaultParameters()
	{
		return [
			'name' => 'credit-template',
			'value_type' => Type\Manager::TYPE_NUMBER
		];
	}

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
        if ($parent instanceof Xml\Data\XmlElement)
        {
            $result = $parent->addChild($this->name);
            $result->addAttribute('id', $value);
        }
        else
        {
            $result = $parent->addChild($this->name, $value);
        }

		return $result;
	}
}