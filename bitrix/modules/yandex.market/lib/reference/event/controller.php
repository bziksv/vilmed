<?php

namespace Yandex\Market\Reference\Event;

use Bitrix\Main;
use Yandex\Market\Config;
use Yandex\Market\Data\TextString;
use Yandex\Market\Utils\FileIterator;

class Controller
{
	protected static $isCompatabilityVersion;

	/**
	 * Добавляем обработчик
	 *
	 * @param $сlassName string
	 * @param $handlerParams array
	 *
	 * @throws Main\NotImplementedException
	 * @throws Main\SystemException
	 * */
	public static function register($className, $handlerParams)
	{
		$handlerDescription = static::getHandlerDescription($className, $handlerParams);

		static::saveHandler($handlerDescription);
	}

	/**
	 * Удаляем обработчик
	 *
	 * @param $className
	 * @param $handlerParams
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 * @throws \Bitrix\Main\NotImplementedException
	 */
	public static function unregister($className, $handlerParams)
	{
		$handlerDescription = static::getHandlerDescription($className, $handlerParams);
		$registeredList = static::getRegisteredHandlers($className);
		$handlerKey = static::getHandlerKey($handlerDescription, true);

		if (isset($registeredList[$handlerKey]))
		{
			static::deleteHandler($registeredList[$handlerKey]);
		}
	}

	/**
	 * Обновляет привязки регулярных обработчиков событий
	 *
	 * @throws Main\NotImplementedException
	 * @throws Main\SystemException
	 * */
	public static function updateRegular()
	{
		$baseClassName = Regular::class;

		$classList = (new FileIterator())->findClasses($baseClassName);
		$handlerList = static::getClassHandlers($classList);
		$registeredList = static::getRegisteredHandlers($baseClassName);

		static::saveHandlers($handlerList);
		static::deleteHandlers($handlerList, $registeredList);
	}

	/**
	 * Удаляем все события
	 *
	 * @throws \Bitrix\Main\Db\SqlQueryException
	 */
	public static function deleteAll()
	{
		$namespace = Config::getNamespace();
		$registeredList = static::getRegisteredHandlers($namespace, true);

		static::deleteHandlers([], $registeredList);
	}

	/**
	 * Обходит список классов и готовит массив для записи
	 *
	 * @param $classList array список классов
	 *
	 * @return array список обработчиков для регистрации
	 * @throws Main\NotImplementedException
	 * @throws Main\ArgumentException
	 */
	protected static function getClassHandlers($classList)
	{
		$result = array();

		/** @var Regular $className */
		foreach ($classList as $className)
		{
			$normalizedClassName = $className::getClassName();
			$handlers = $className::getHandlers();

			foreach ($handlers as $handler)
			{
				$handlerDescription = static::getHandlerDescription(
					$normalizedClassName,
					$handler
				);
				$handlerKey = static::getHandlerKey($handlerDescription, true);

				$result[$handlerKey] = $handlerDescription;
			}
		}

		return $result;
	}

	/**
	 * Возвращает описание обработчика для регистрации, проверяет существование метода
	 *
	 * @param $className class-string<Base>
	 * @param $handlerParams array|null параметры обработчика
	 *
	 * @return array
	 * @throws Main\NotImplementedException
	 * @throws Main\ArgumentException
	 */
	protected static function getHandlerDescription($className, $handlerParams)
	{
		if (empty($handlerParams['module']) || empty($handlerParams['event']))
		{
			throw new Main\ArgumentException(
				'Require module and event param in ' . $className
			);
		}

		$method = isset($handlerParams['method']) ? $handlerParams['method'] : $handlerParams['event'];

		if (!method_exists($className, $method))
		{
			throw new Main\NotImplementedException(
				'Method ' . $method
				. ' not defined in ' . $className
				. ' and cannot be registered as event handler'
			);
		}

		if (static::isCompatibilityMode($className)) // is compatibility mode
		{
			$newClassName = static::getCompatibilityClassName();
			$newMethod = static::getCompatibilityMethod();
			$newArguments = (
				isset($handlerParams['arguments']) && is_array($handlerParams['arguments'])
					? $handlerParams['arguments']
					: []
			);

			array_unshift($newArguments, $method);
			array_unshift($newArguments, $className);

			$className = $newClassName;
			$method = $newMethod;
			$handlerParams['arguments'] = $newArguments;
		}

		return array(
			'module'    => $handlerParams['module'],
			'event'     => $handlerParams['event'],
			'class'     => $className::getClassName(),
			'method'    => $method,
			'sort'      => isset($handlerParams['sort']) ? (int)$handlerParams['sort'] : 100,
			'arguments' => isset($handlerParams['arguments']) ? $handlerParams['arguments'] : ''
		);
	}

	/**
	 * Получаем список ранее зарегистрированных обработчиков
	 *
	 * @param $baseClassName string название класса, наследников которого необходимо получить
	 * @param $isBaseNamespace bool первый аргумент не является классом
	 *
	 * @return array список зарегистрированных обработчиков
	 * @throws Main\Db\SqlQueryException
	 * */
	protected static function getRegisteredHandlers($baseClassName, $isBaseNamespace = false)
	{
		$registeredList = array();
		$compatabilityClassName = static::getCompatibilityClassName();
		$compatabilityMethod = static::getCompatibilityMethod();
		$namespaceLower = str_replace('\\', '\\\\', TextString::toLower(Config::getNamespace()));
		$connection = Main\Application::getConnection();
		$sqlHelper = $connection->getSqlHelper();
		$query = $connection->query(
			'SELECT * FROM b_module_to_module WHERE TO_CLASS like "' . $sqlHelper->forSql($namespaceLower) . '%"'
		);

		while ($handlerRow = $query->fetch())
		{
			$handlerClassName = $handlerRow['TO_CLASS'];

			if (
				$handlerClassName === $compatabilityClassName
				&& $handlerRow['TO_METHOD'] === $compatabilityMethod
				&& $handlerRow['TO_METHOD_ARG'] !== ''
			)
			{
				$handlerArguments = unserialize($handlerRow['TO_METHOD_ARG']);

				if ($handlerArguments !== false && isset($handlerArguments[0]))
				{
					$handlerClassName = $handlerArguments[0];
				}
			}

			if (
				$isBaseNamespace
				|| !class_exists($handlerClassName)
				|| is_subclass_of($handlerClassName, $baseClassName)
				|| ltrim($handlerClassName, '\\') === ltrim($baseClassName, '\\')
			)
			{
				$handlerKey = static::getHandlerKey($handlerRow);
				$registeredList[$handlerKey] = $handlerRow;
			}
		}

		return $registeredList;
	}

	/**
	 * Ключ массива для обработчика события
	 *
	 * @param $handlerData array
	 * @param $byDescription boolean генерировать ключ по описанию или зарегистрированнуму обработчику
	 *
	 * @return string
	 */
	protected static function getHandlerKey($handlerData, $byDescription = false)
	{
		$signKeys = array(
			'module'    => 'FROM_MODULE_ID',
			'event'     => 'MESSAGE_ID',
			'class'     => 'TO_CLASS',
			'method'    => 'TO_METHOD',
			'arguments' => 'TO_METHOD_ARG'
		);
		$values = array();

		foreach ($signKeys as $descriptionKey => $rowKey)
		{
			$key = $byDescription ? $descriptionKey : $rowKey;
			$values[] = is_array($handlerData[$key]) && !empty($handlerData[$key]) ? serialize($handlerData[$key]) : $handlerData[$key];
		}

		return TextString::toLower(implode('|', $values));
	}

	/**
	 * Регистрирует все обработчики в базе данных
	 *
	 * @param $handlerList array список обработчиков для регистрации
	 *
	 * @throws Main\SystemException
	 * */
	protected static function saveHandlers($handlerList)
	{
		foreach ($handlerList as $handlerDescription)
		{
			static::saveHandler($handlerDescription);
		}
	}

	/**
	 * Регистрируем обработчик в базе данных
	 *
	 * @param $handlerDescription array обработчик
	 * */
	protected static function saveHandler($handlerDescription)
	{
		$eventManager = Main\EventManager::getInstance();

		$eventManager->registerEventHandler(
			$handlerDescription['module'],
			$handlerDescription['event'],
			Config::getModuleName(),
			$handlerDescription['class'],
			$handlerDescription['method'],
			$handlerDescription['sort'],
			'',
			$handlerDescription['arguments']
		);
	}

	/**
	 * Удаляет неиспользуемые обработчикы из базы данных
	 *
	 * @param $handlerList array список обработчиков для регистрации
	 * @param $registeredList array список ранее зарегистрированных обработчиков
	 * */
	protected static function deleteHandlers($handlerList, $registeredList)
	{
		foreach ($registeredList as $handlerKey => $handlerRow)
		{
			if (!isset($handlerList[$handlerKey]))
			{
				static::deleteHandler($handlerRow);
			}
		}
	}

	/**
	 * Удаляет обработчик из базы данных
	 *
	 * @param $handlerRow array ранее зарегистрированный обработчик
	 * */
	protected static function deleteHandler($handlerRow)
	{
		$eventManager = Main\EventManager::getInstance();

		$handlerArgs = $handlerRow['TO_METHOD_ARG'];

		if (is_string($handlerArgs))
		{
			$handlerArgsUnserialize = unserialize($handlerArgs);

			if (is_array($handlerArgsUnserialize) && !empty($handlerArgsUnserialize))
			{
				$handlerArgs = $handlerArgsUnserialize;
			}
		}

		$eventManager->unregisterEventHandler(
			$handlerRow['FROM_MODULE_ID'],
			$handlerRow['MESSAGE_ID'],
			Config::getModuleName(),
			$handlerRow['TO_CLASS'],
			$handlerRow['TO_METHOD'],
			'',
			$handlerArgs
		);
	}

	protected static function isCompatibilityMode($className)
	{
		$charLimit = 50;

		return (static::isCompatibilityVersion() && TextString::getLength($className) > $charLimit);
	}

	protected static function isCompatibilityVersion()
	{
		if (static::$isCompatabilityVersion === null)
		{
			static::$isCompatabilityVersion = ((float)Main\ModuleManager::getVersion('main') < 16);
		}

		return static::$isCompatabilityVersion;
	}

	protected static function getCompatibilityClassName()
	{
		return Config::getNamespace() . '\\EventManager';
	}

	protected static function getCompatibilityMethod()
	{
		return 'callMethod';
	}
}
