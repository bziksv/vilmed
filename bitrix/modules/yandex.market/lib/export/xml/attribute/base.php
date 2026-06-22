<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Export\Xml;
use Bitrix\Main;

Main\Localization\Loc::loadMessages(__FILE__);

class Base extends Xml\Reference\Node
{
	/** @var bool */
	protected $isPrimary;

	protected function refreshParameters()
	{
		parent::refreshParameters();

		$this->isPrimary = !empty($this->parameters['primary']);
	}

	public function isPrimary()
	{
		return $this->isPrimary;
	}

	public function getLangKey()
	{
		$nameLang = $this->getParameter('lang_key');

		if ($nameLang === null)
		{
			$nameLang = str_replace(['.', ' ', '-'], '_', $this->id);
			$nameLang = mb_strtoupper($nameLang);
		}

		return 'EXPORT_ATTRIBUTE_' . $nameLang;
	}

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
        if ($parent instanceof Xml\Data\XmlElement)
        {
            $parent->addAttribute($this->name, $value);
        }
        else
        {
            $parent->addChild($this->name, $value);
        }

		return $parent;
	}
}
