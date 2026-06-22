<?php
namespace Yandex\Market\Ui;

use Bitrix\Main;

class Extension
{
	const GLOBAL_PREFIX = 'yandex_market';

    /** @noinspection PhpUnused */
    public static function loadLocal($extension)
	{
		self::load('@' . $extension);
	}

	private static function bootLocal($local, $global)
	{
		if (static::hasRegisteredExtension($global)) { return true; }

		$relativePath = Assets::getPluginDirectory($local);
		$configPath = $relativePath . '/config.php';
		$configPath = Main\IO\Path::convertRelativeToAbsolute($configPath);
		$configFile = new Main\IO\File($configPath);

		if ($configFile->isExists())
		{
			$config = include $configFile->getPath();

			if (!is_array($config)) { return false; }

			$config = static::makeConfigPath($config, $relativePath);
			$config = static::makeConfigLang($config, $relativePath);
			$config = static::makeConfigRel($config);

			\CJSCore::RegisterExt($global, $config);

			return true;
		}

		$scriptRelative = Assets::getPluginPath($local);
		$scriptPath = Main\IO\Path::convertRelativeToAbsolute($scriptRelative);
		$scriptFile = new Main\IO\File($scriptPath);

		if ($scriptFile->isExists())
		{
			\CJSCore::RegisterExt($global, [
				'js' => [ $scriptRelative ],
			]);

			return true;
		}

		return false;
	}

	private static function hasRegisteredExtension($extension)
	{
		return (
			\CJSCore::IsExtRegistered($extension)
			|| (
				class_exists(Main\UI\Extension::class)
				&& Main\UI\Extension::register($extension)
			)
		);
	}

	private static function makeConfigPath(array $config, $relativePath)
	{
		$chains = [ 'js', 'css' ];

		foreach ($chains as $chain)
		{
			if (!isset($config[$chain])) { continue; }

			$newChain = [];
			$isChanged = false;

			foreach ((array)$config[$chain] as $path)
			{
				if (mb_strpos($path, '/') !== 0)
				{
					$isChanged = true;
					$path = $relativePath . '/' . $path;
				}

				$newChain[] = $path;
			}

			if ($isChanged)
			{
				$config[$chain] = $newChain;
			}
		}

		return $config;
	}

	private static function makeConfigLang(array $config, $relativePath)
	{
		if (!isset($config['lang'])) { return $config; }

		if (!is_array($config['lang'])) { $config['lang'] = [ $config['lang'] ]; }

		foreach ($config['lang'] as &$path)
		{
			if ($path === true)
			{
				$path = $relativePath . '/config.php';
			}
		}
		unset($path);

		return $config;
	}

	private static function makeConfigRel(array $config)
	{
		if (!isset($config['rel'])) { return $config; }

		$newRel = [];

		foreach ((array)$config['rel'] as $package)
		{
			$newRel[] = self::globalExtension($package);
		}

		$config['rel'] = $newRel;

		return $config;
	}

	public static function registerCompatible($extension)
	{
		if (static::hasRegisteredExtension($extension)) { return $extension; }

		$extension = 'compatible.' . $extension;
		$name = str_replace('.', '_', $extension);

		return static::bootLocal($extension, $name) ? $name : null;
	}

	public static function assets($extension)
	{
		$info = self::info($extension);

		if (!is_array($info)) { return []; }

		$result = array_intersect_key($info, [
			'css' => true,
			'js' => true,
			'variable' => true,
		]);

		if (isset($info['rel']))
		{
			$result['rel'] = [];

			foreach ((array)$info['rel'] as $rel)
			{
				$result['rel'][] = self::assets($rel);
			}
		}

		return $result;
	}

	public static function injectFileUrl(array $info)
	{
		foreach (['css', 'js'] as $group)
		{
			if (empty($info[$group])) { continue; }

			$info[$group] = self::makeFileUrl($info[$group]);
		}

		if (isset($info['rel']))
		{
			foreach ($info['rel'] as &$rel)
			{
				$rel = self::injectFileUrl($rel);
			}
			unset($rel);
		}

		return $info;
	}

	private static function makeFileUrl($files)
	{
		if (!is_array($files)) { $files = [ $files ]; }

		$urls = [];

		foreach ($files as $file)
		{
			if (!is_string($file) || \CMain::IsExternalLink($file))
			{
				$urls[] = $file;
				continue;
			}

			$maxMtime = null;
			$targetPath = null;
			$paths = [ $file ];

			if (Main\Page\Asset::canUseMinifiedAssets() && preg_match("/(.+)\\.(js|css)$/i", $file, $matches))
			{
				array_unshift($paths, "{$matches[1]}.min.{$matches[2]}");
			}

			foreach ($paths as $path)
			{
				$filePath = Main\Loader::getDocumentRoot() . $path;

				if (!file_exists($filePath)) { continue; }

				$mtime = filemtime($filePath);

				if ($mtime > $maxMtime && filesize($filePath) > 0)
				{
					$maxMtime = $mtime;
					$targetPath = $path;
				}
			}

			if ($targetPath === null)
			{
				$urls[] = $file;
				continue;
			}

			$urls[] = \CUtil::GetAdditionalFileURL($targetPath, true);
		}

		return $urls;
	}

	public static function info($extension)
	{
		$global = self::globalExtension($extension);

		return \CJSCore::getExtInfo($global);
	}

	public static function load($extension)
	{
        $globals = [];

        foreach ((array)$extension as $name)
        {
            $globals[] = self::globalExtension($name);
        }

        \CJSCore::Init($globals);
	}

	private static function globalExtension($extension)
	{
		if (mb_strpos($extension, '@') === 0)
		{
			$local = mb_substr($extension, 1);
			$global = self::GLOBAL_PREFIX . '.' . $local;
            $global = str_replace('.', '_', $global);

			self::bootLocal($local, $global);

			return $global;
		}

		return $extension;
	}

	public static function loadConditional($extension, $varName, $location = Main\Page\AssetLocation::AFTER_CSS)
	{
		$assets = \CJSCore::getExtInfo($extension);
		$loadJs = static::getConditionLoad($assets);
		$script = sprintf(
			'<script data-bxrunfirst>if (!window.%s && (!top.%s || (window.frameElement && /side-panel-iframe/.test(window.frameElement.className)))) { %s }</script>',
			$varName,
			$varName,
			$loadJs
		);

		$assets = Main\Page\Asset::getInstance();
		$assets->addString($script, true, $location);
	}

	private static function getConditionLoad($assets)
	{
		$loadJs = '';

		if (isset($assets['css']))
		{
			$loadJs .= static::getConditionLoadCss((array)$assets['css']);
		}

		if (isset($assets['js']))
		{
			$loadJs .= static::getConditionLoadJs((array)$assets['js']);
		}

		return $loadJs;
	}

	private static function getConditionLoadCss($pathList)
	{
		return static::getConditionLoadByBitrix($pathList, 'loadCSS');
	}

	private static function getConditionLoadJs($pathList)
	{
		$beforeLoad = static::getConditionLoadJsSync($pathList);
		$afterLoad = static::getConditionLoadByBitrix($pathList, 'loadScript');

		return sprintf('
			if (document.readyState === "loading") { 
				%s 
			} else { 
				%s 
			}
			',
			$beforeLoad,
			$afterLoad
		);
	}

	private static function getConditionLoadByBitrix($pathList, $method)
	{
		$pathString = implode('", "', $pathList);

		return sprintf('(window.BX||top.BX).%s(["%s"]);', $method, $pathString);
	}

	/** @noinspection HtmlUnknownTarget */
	private static function getConditionLoadJsSync($pathList)
	{
		$tags = array_map(static function($path) { return sprintf('<script src="%s"></script>', $path); }, $pathList);
		$tagsString = implode(PHP_EOL, $tags);
		$tagsString = str_replace(['"', '</'], ['\\"', '<\\/'], $tagsString);

		return sprintf('document.write("%s");', $tagsString);
	}

	/** @noinspection PhpUnused */
	public static function loadOne(array $variants, $fallbackFirst = false)
	{
		$name = static::getOne($variants, $fallbackFirst);

		static::load($name);
	}

	public static function getOne(array $variants, $fallbackFirst = false)
	{
		$canLoadVariants = array_filter($variants, [__CLASS__, 'canLoad']);

		if (!empty($canLoadVariants))
		{
			$loadedVariants = array_filter($canLoadVariants, [__CLASS__, 'isLoaded']);

			if (!empty($loadedVariants))
			{
				$result = reset($loadedVariants);
			}
			else
			{
				$result = reset($canLoadVariants);
			}
		}
		else if ($fallbackFirst)
		{
			$result = reset($variants);
		}
		else
		{
			throw new Main\SystemException(sprintf(
				'cant find valid extension from %s',
				implode(', ', $variants)
			));
		}

		return $result;
	}

	public static function canLoad($name)
	{
		$result = true;

		if (!\CJSCore::IsExtRegistered($name))
		{
			$result = false;
		}
		else
		{
			$info = \CJSCore::getExtInfo($name);
			$types = [ 'css', 'js' ];
			$docRoot = Main\Loader::getDocumentRoot();

			foreach ($types as $type)
			{
				if (!isset($info[$type])) { continue; }

				$pathList = (array)$info[$type];

				foreach ($pathList as $path)
				{
					$absolutePath = $docRoot . $path;

					if (!file_exists($absolutePath))
					{
						$result = false;
						break;
					}
				}
			}
		}

		return $result;
	}

	public static function isLoaded($name)
	{
		return method_exists('CJSCore', 'isExtensionLoaded') && \CJSCore::isExtensionLoaded($name);
	}
}