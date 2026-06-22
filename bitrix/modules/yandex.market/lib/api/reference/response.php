<?php
namespace Yandex\Market\Api\Reference;

use Bitrix\Main;

class Response extends Model
{
	protected $raw;

	public static function initialize($fields, $relativePath = '')
	{
		if (is_array($fields))
		{
			return parent::initialize($fields, $relativePath);
		}

		/** @var static $result */
		$result = parent::initialize([], $relativePath);
		$result->setRaw($fields);

		return $result;
	}

	/** @deprecated */
	public function validate()
	{
		return new Main\Result();
	}

	protected function setRaw($contents)
	{
		$this->raw = $contents;
	}

	public function getRaw()
	{
		return $this->raw !== null ? $this->raw : $this->getFields();
	}
}