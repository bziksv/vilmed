<?php
namespace Yandex\Market\Export\Xml\Data;

class CDataValue
{
	private $body;

	public function __construct($body)
	{
		$this->body = $body;
	}

	public function __toString()
	{
		return $this->body;
	}

	public function toXml()
	{
		$content = str_replace(
			['<![CDATA[', ']]>'],
			['&lt;![CDATA[', ']]&gt;'],
			$this->body
		);
		$content = preg_replace("/[\x1-\x8\xB-\xD\xE-\x1F]/", '', $content); // remove special chars

		return '<![CDATA[' . PHP_EOL .  $content . PHP_EOL . ']]>';
	}
}