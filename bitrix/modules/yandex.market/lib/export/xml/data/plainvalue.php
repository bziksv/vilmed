<?php
namespace Yandex\Market\Export\Xml\Data;

class PlainValue
{
    private $content;

    /** @param string $content */
    public function __construct($content)
    {
        $this->content = $content;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function __toString()
    {
        return $this->content;
    }
}