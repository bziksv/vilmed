<?php
namespace Yandex\Market\Export\Param;

use Yandex\Market\Export\Xml;

interface Format
{
	/** @return string|null */
	public function getDocumentationUrl();

	/** @return Xml\Tag\Base */
	public function getTag();
}