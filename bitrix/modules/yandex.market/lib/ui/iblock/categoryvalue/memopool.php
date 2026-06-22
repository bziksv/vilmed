<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Utils\Caller;

class MemoPool
{
	use Concerns\HasOnce;

	private static $instances = [];

	public static function get(CategoryValue $decorated)
	{
		$hash = self::hash($decorated);

		if (!isset(self::$instances[$hash]))
		{
			self::$instances[$hash] = new MemoProxy($decorated);
		}

		return self::$instances[$hash];
	}

	private static function hash(CategoryValue $decorated)
	{
		$reflection = new \ReflectionClass($decorated);
		$constructor = $reflection->getConstructor();

		$hash = $reflection->getName();

		if ($constructor !== null)
		{
			$arguments = [];

			foreach ($constructor->getParameters() as $parameter)
			{
				$property = new \ReflectionProperty($decorated, $parameter->getName());
				$property->setAccessible(true);

				$arguments[] = $property->getValue($decorated);
			}

			$hash .= '::' . Caller::getArgumentsHash($arguments);
		}

		return $hash;
	}
}