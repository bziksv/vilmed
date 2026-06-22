<?php
namespace Yandex\Market\Export\Xml\Attribute;

use Yandex\Market\Export\Entity;
use Yandex\Market\Reference\Concerns;

class VolumeName extends Base
{
    use Concerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'id' => 'volume_name',
			'name' => 'name',
		];
	}

	public function isDefined()
	{
		return true;
	}

	public function getDefinedSource(array $context = [])
	{
		return [
			'TYPE' => Entity\Manager::TYPE_TEXT,
			'VALUE' => self::getMessage('DEFINED_VALUE'),
		];
	}
}