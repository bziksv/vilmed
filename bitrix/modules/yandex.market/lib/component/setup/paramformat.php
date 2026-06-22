<?php
namespace Yandex\Market\Component\Setup;

use Yandex\Market\Export\Param;
use Yandex\Market\Export\Xml;

class ParamFormat implements Param\Format
{
	private $format;

	public function __construct($service, $format)
	{
		$this->format = Xml\Format\Manager::getEntity($service, $format);
	}

	public function getDocumentationUrl()
	{
		return $this->format->getDocumentationLink();
	}

	public function getTag()
	{
		return $this->format->getOffer();
	}
}