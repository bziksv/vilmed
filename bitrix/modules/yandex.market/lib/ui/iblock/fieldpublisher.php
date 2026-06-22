<?php
namespace Yandex\Market\Ui\Iblock;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Event;

/** @noinspection PhpUnused */
class FieldPublisher extends Event\Regular
{
	const FIELD = 0;
	const PROPERTY = 1;

    public static function getHandlers()
    {
        $handlers = [];

        foreach (self::getClassMap() as $type => list($userFieldClass, $propertyClass))
        {
			if ($userFieldClass !== null)
			{
	            $handlers[] = [
	                'module' => 'main',
	                'event' => 'OnUserTypeBuildList',
	                'method' => 'getFieldDescription',
	                'arguments' => [$type],
	            ];
	        }

			if ($propertyClass !== null)
			{
				$handlers[] = [
					'module' => 'iblock',
					'event' => 'OnIBlockPropertyBuildList',
					'method' => 'getPropertyDescription',
					'arguments' => [$type],
				];
			}
        }

        return $handlers;
    }

    public static function getFieldDescription($type)
    {
		return self::buildDescription($type, self::FIELD);
    }

    public static function getPropertyDescription($type)
    {
		return self::buildDescription($type, self::PROPERTY);
    }

	/** @noinspection PhpUndefinedMethodInspection */
	private static function buildDescription($type, $entity)
	{
		try
		{
			$classMap = self::getClassMap();

			Assert::notNull($classMap[$type][$entity], sprintf('classMap[%s][%s]', $type, $entity));

			$class = $classMap[$type][$entity];

			Assert::methodExists($class, 'getUserTypeDescription');

			return $class::getUserTypeDescription();
		}
		catch (Main\SystemException $e)
		{
			trigger_error($e->getMessage(), E_USER_WARNING);

			return null;
		}
	}

    private static function getClassMap()
    {
        return [
            'Category' => [ CategoryField::class, CategoryProperty::class ],
        ];
    }
}