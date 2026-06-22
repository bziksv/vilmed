<?php
namespace Yandex\Market\Result;

use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Assert;

trait XmlNodeCompatible
{
    /** @deprecated */
    public function setErrorTagName($name) {}

    /** @deprecated */
    public function setErrorAttributeName($name) {}

    /** @deprecated */
    public function hasPlain()
    {
        $proxy = $this->simpleXmlProxy();

        if ($proxy === null) { return false; }

        return $proxy->compiler()->hasPlain();
    }

    /** @deprecated */
    public function registerPlain()
    {
        $proxy = $this->simpleXmlProxy();

        if ($proxy === null) { return; }

        $proxy->compiler()->registerPlain();
    }

    /** @deprecated */
    public function addReplace($text, $index = null)
    {
        $proxy = $this->simpleXmlProxy();

        if ($proxy === null)
        {
            return 'UNKNOWN';
        }

        return $proxy->compiler()->pushReplace($text, $index);
    }

    /** @deprecated */
    public function getReplaces()
    {
        $proxy = $this->simpleXmlProxy();

        if ($proxy === null) { return []; }

        return $proxy->compiler()->getReplaces();
    }

    /**
     * @deprecated
     * @use XmlNode::getExportElement()->getAttribute()
     */
    public function getTagAttribute($tagName, $attributeName)
    {
        if ($this->exportElement === null) { return null; }

        if ($this->exportElement->getName() === $tagName)
        {
            $targetTag = $this->exportElement;
        }
        else
        {
            $targetTag = $this->exportElement->getChild($tagName)[0];
        }

        if ($targetTag === null) { return null; }

        /** @var Xml\Data\XmlElement|Xml\Data\SimpleXmlProxy $targetTag */
        return $targetTag->getAttribute($attributeName);
    }

    /** @deprecated */
    public function setXmlElement(\SimpleXMLElement $xmlElement)
    {
        if ($this->exportElement instanceof Xml\Data\SimpleXmlProxy)
        {
            $this->exportElement->inject($xmlElement);
            return;
        }

        $this->exportElement = new Xml\Data\SimpleXmlProxy($xmlElement);
    }

    /**
     * @deprecated
     * @use XmlNode::getExportElement()
     */
    public function getXmlElement()
    {
        $proxy = $this->simpleXmlProxy();

        if ($proxy === null) { return null; }

        return $proxy->extract();
    }

    /**
     * @deprecated
     * @use XmlNode::getExportElement()->build()
     */
    public function getXmlContents()
    {
        if ($this->exportElement === null) { return null; }

        return (string)$this->exportElement->build();
    }

    /** @deprecated */
    public function invalidateXmlContents() {}

    /** @return Xml\Data\SimpleXmlProxy|null */
    private function simpleXmlProxy()
    {
        if ($this->exportElement === null) { return null; }

        if ($this->exportElement instanceof Xml\Data\XmlElement)
        {
            $this->exportElement = Xml\Data\SimpleXmlProxy::fromCompiler($this->exportElement->build());
        }

        Assert::isInstanceOf($this->exportElement, Xml\Data\SimpleXmlProxy::class);

        return $this->exportElement;
    }
}