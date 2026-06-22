<?php
namespace Yandex\Market\Utils;

use Bitrix\Main;
use Yandex\Market\Data\TextString;

class MessageRegistry
{
	private static $moduleInstance;

	private $classFinder;
	private $prefix;
	private $prefixes = [];
	private $included = [];

	/** @return MessageRegistry */
	public static function getModuleInstance()
	{
		if (static::$moduleInstance === null)
		{
			static::$moduleInstance = new static(ClassFinder::forModule());
		}

		return static::$moduleInstance;
	}

	public function __construct(ClassFinder $classFinder, $prefix = '')
	{
		$this->classFinder = $classFinder;
		$this->prefix = $prefix;
	}

	public function load($className)
	{
		if (isset($this->included[$className])) { return; }

		$path = $this->classFinder->getPath($className);

		Main\Localization\Loc::loadMessages($path);
		$this->included[$className] = true;
	}

	public function getPrefix($className)
	{
		if (!isset($this->prefixes[$className]))
		{
			$this->prefixes[$className] = $this->makePrefixes($className);
		}

		return $this->prefixes[$className][0];
	}

	public function getCompatiblePrefix($className)
	{
		if (!isset($this->prefixes[$className]))
		{
			$this->prefixes[$className] = $this->makePrefixes($className);
		}

		return $this->prefixes[$className][1];
	}
	
	private function makePrefixes($className)
	{
		$relativeName = $this->classFinder->getRelativeName($className);

		return [
			$this->prefix . Name::screamingSnakeCase($relativeName),
			$this->prefix . mb_strtoupper(str_replace('\\', '_', $relativeName)),
		];
	}
}