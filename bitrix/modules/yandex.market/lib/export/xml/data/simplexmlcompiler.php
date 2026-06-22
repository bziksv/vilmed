<?php
namespace Yandex\Market\Export\Xml\Data;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Utils;

class SimpleXmlCompiler
{
    /** @noinspection SpellCheckingInspection */
	const REPLACE_MARKER = 'YANDEX_MARKET_XMLNODE_REPLACE_';
    const PLAIN_TAG_NAME = 'ym_plain';

	private $root;
	private $replaceIndex = 0;
	private $replaces = [];
    private $hasPlain = false;

	public function __construct(XmlElement $root = null)
	{
		$this->root = $root;
	}

	public function __toString()
	{
		return $this->asXml();
	}

	public function asXml()
	{
		$xml = $this->compile()->asXML();
		$xml = $this->applyReplaces($xml);
        $xml = $this->applyPlain($xml);

		return $xml;
	}

	public function modify($xml)
	{
		$xml = $this->applyReplaces($xml);
        $xml = $this->applyPlain($xml);

		return $xml;
	}

	public function asElement()
	{
		return $this->compile();
	}

	private function compile()
	{
        Assert::notNull($this->root, 'root');

		return $this->compileNode($this->root, $this->makeDocument());
	}

	private function compileNode(XmlElement $element, \SimpleXMLElement $parent)
	{
		$node = $this->addNode($element, $parent);

		$this->fillAttributes($element, $node);
		$this->fillChildren($element, $node);

		return $node;
	}

	private function makeDocument()
	{
		$encoding = Utils\Encoding::getCharset();

		return new \SimpleXMLElement('<?xml version="1.0" encoding="' . $encoding . '"?><root />', LIBXML_COMPACT);
	}

	private function addNode(XmlElement $element, \SimpleXMLElement $parent)
	{
        $name = $element->getName();
		$value = $element->getValue();

		if ($value instanceof PlainValue)
        {
            $name = self::PLAIN_TAG_NAME;
            $value = $this->pushReplace($value->getContent());

            $this->hasPlain = true;
        }
        else if ($value instanceof RootValue)
		{
			$value = empty($this->children) ? $this->pushReplace('') : null;
		}
		else if ($value instanceof CDataValue)
		{
			$value = $this->pushReplace($value->toXml());
		}
        else if ($value !== null)
        {
            $value = $this->castValue($value);
        }

		$node = $value !== null
			? $parent->addChild($name, $value)
			: $parent->addChild($name);

		if ($node === null)
		{
			throw new Main\SystemException(sprintf('cant add SimpleXMLElement child %s %s', $element->getName(), $element->getValue()));
		}

		return $node;
	}

	private function fillAttributes(XmlElement $element, \SimpleXMLElement $node)
	{
		foreach ($element->getAttributes() as $name => $value)
		{
            $value = $this->castValue($value);

            /** @noinspection PhpDeprecationInspection */
            if (!Main\Application::isUtfMode())
			{
				@$node->addAttribute($name, $value);
				continue;
			}

			$node->addAttribute($name, $value);
		}
	}

	private function fillChildren(XmlElement $element, \SimpleXMLElement $node)
	{
		foreach ($element->getChildren() as $child)
		{
			$this->compileNode($child, $node);
		}
	}

    private function castValue($value)
    {
        if (is_bool($value))
        {
            return $value ? 'true' : 'false';
        }

        return Utils\XmlValue::escape($value);
    }

    public function getReplaces()
    {
        return $this->replaces;
    }

    public function pushReplace($value, $index = null)
	{
        if ($index === null) { $index = $this->replaceIndex++; }

		$this->replaces[$index] = $value;

		return self::REPLACE_MARKER . $index;
	}

	/** @return string */
	private function applyReplaces($xml)
	{
		foreach ($this->replaces as $index => $replace)
		{
			$xml = str_replace(self::REPLACE_MARKER . $index, $replace, $xml);
		}

		return $xml;
	}

    public function hasPlain()
    {
        return $this->hasPlain;
    }

    public function registerPlain()
    {
        $this->hasPlain = true;
    }

	/** @return string */
	private function applyPlain($xml)
	{
        if (!$this->hasPlain) { return $xml; }

		return str_replace(
            [ '<' . static::PLAIN_TAG_NAME . '>', '</' . static::PLAIN_TAG_NAME . '>' ],
            '',
            $xml
        );
	}
}