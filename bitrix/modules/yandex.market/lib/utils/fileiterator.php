<?php
namespace Yandex\Market\Utils;

use Yandex\Market\Config;

class FileIterator
{
	private $basePath;
	private $baseNamespace;
	/** @noinspection SpellCheckingInspection */
	private $deprecated = [
		'/ui/trading/concerns/hashandlemigration.php',
		'/trading/service/beru',
		'/trading/service/turbo',
		'/component/tradingimport',
		'/service/data',
		'/ui/userfield/autocomplete',
		'/ui/userfield/servicecategory',
		'/ui/service',
		'/api/oauth2/accesstoken',
		'/api/oauth2/verificationcode',
		'/ui/userfield/tokentype.php',
	];

	public function __construct($basePath = null, $baseNamespace = null)
	{
		$this->basePath = $basePath === null ? Config::getModulePath() : $basePath;
		$this->baseNamespace = $baseNamespace === null ? Config::getNamespace() : $baseNamespace;
	}

	public function findClasses($baseClassName, $suffix = null)
	{
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->basePath));
		$found = [];

		/** @var \DirectoryIterator $entry */
		foreach ($iterator as $entry)
		{
			if (!$entry->isFile() || $entry->getExtension() !== 'php') { continue; }

			$relativePath = str_replace($this->basePath, '', $entry->getPath());
			$fileName = $entry->getBasename('.php');

			if ($this->isDeprecated($relativePath, $fileName)) { continue; }

			$className = $this->className($relativePath, $fileName, $suffix);

			if ($className === null) { continue; }

			if (is_subclass_of($className, $baseClassName))
			{
				$found[] = $className;
			}
		}

		return $found;
	}

	private function isDeprecated($relativePath, $fileName)
	{
		$fullPath = $relativePath . '/' . $fileName . '.php';

		foreach ($this->deprecated as $deprecatedPath)
		{
			if (mb_strpos($fullPath, $deprecatedPath) === 0)
			{
				return true;
			}
		}

		return false;
	}

	private function className($relativePath, $fileName, $suffix = null)
	{
		$relativePath = preg_replace('/\.php$/', '', $relativePath);
		$className = $this->baseNamespace . str_replace('/', '\\', $relativePath) . '\\' . $fileName;

		if (class_exists($className))
		{
			return $className;
		}

		if ($this->canAppendSuffix($fileName, $suffix))
		{
			$classNameWithSuffix = $className . $suffix;

			if (class_exists($classNameWithSuffix))
			{
				return $classNameWithSuffix;
			}
		}

		return null;
	}

	protected function canAppendSuffix($fileName, $suffix = null)
	{
		if ($suffix === null) { return false; }

		$suffixLower = mb_strtolower($suffix);

		if ($fileName === $suffixLower) { return true; }

		$suffixPosition = mb_stripos($fileName, $suffixLower);

		return (
			$suffixPosition === false
			|| $suffixPosition + mb_strlen($suffixLower) !== mb_strlen($fileName)
		);
	}
}