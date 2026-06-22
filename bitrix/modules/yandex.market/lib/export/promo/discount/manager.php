<?php

namespace Yandex\Market\Export\Promo\Discount;

use Yandex\Market;
use Bitrix\Main;

Main\Localization\Loc::loadMessages(__FILE__);

class Manager
{
    /** @var string[] */
    protected static $providerList;

    /** @return string[] */
    public static function getTypeList()
    {
        return array_merge(
            static::getInternalTypeList(),
            static::getProviderTypeList()
        );
    }

    public static function isInternalType($type)
    {
        return in_array($type, static::getInternalTypeList(), true);
    }

    /** @return string[] */
    public static function getInternalTypeList()
    {
        return [
            Market\Export\Promo\Table::PROMO_TYPE_PROMO_CODE,
            Market\Export\Promo\Table::PROMO_TYPE_FLASH_DISCOUNT,
			Market\Export\Promo\Table::PROMO_TYPE_GIFT_WITH_PURCHASE,
            Market\Export\Promo\Table::PROMO_TYPE_GIFT_N_PLUS_M,
            Market\Export\Promo\Table::PROMO_TYPE_BONUS_CARD,
        ];
    }

    /** @return string */
    public static function getInternalTypeTitle($type)
    {
    	$typeUpper = Market\Data\TextString::toUpper($type);
        $typeKey = str_replace(['-', ' '], '_', $typeUpper);

        return Market\Config::getLang('EXPORT_PROMO_PROVIDER_INTERNAL_TYPE_' . $typeKey);
    }

    /** @return string[] */
    public static function getProviderTypeList()
    {
        return array_keys(static::getProviderList());
    }

    /**
     * Список провайдеров
     *
     * @return array $type => $className
     *
     * @throws Main\ArgumentOutOfRangeException
     */
    public static function getProviderList()
    {
        if (static::$providerList === null)
        {
            static::$providerList = array_merge(
                static::loadModuleProviderList(),
                static::loadUserProviderList()
            );
        }

        return static::$providerList;
    }

    /**
     * Название для типа провайдера (не внутреннего)
     *
     * @param $type
     * @param $className
     *
     * @return string
     *
     * @throws Main\SystemException
     */
    public static function getProviderTypeTitle($type, $className = null)
    {
        if ($className === null)
        {
            $className = static::getProviderTypeClassName($type);
        }

        return $className::getTitle();
    }

    /**
     * Объект для типа провайдера
     *
     * @param $type
     * @param $externalId
     *
     * @return AbstractProvider
     *
     * @throws Main\SystemException
     */
    public static function getProviderInstance($type, $externalId)
    {
        $className = static::getProviderTypeClassName($type);

        return new $className($externalId);
    }

    /**
     * Название класса для типа провайдера
     *
     * @param $type
     *
     * @return AbstractProvider
     *
     * @throws Main\SystemException
     */
    public static function getProviderTypeClassName($type)
    {
        $list = static::getProviderList();

        if (isset($list[$type]))
        {
            $result = $list[$type];
        }
        else
        {
            throw new Main\SystemException(
                Market\Config::getLang('EXPORT_PROMO_PROVIDER_CLASS_NAME_NOT_FOUND', [
                    '#TYPE#' => $type
                ])
            );
        }

        return $result;
    }

    /**
     * Варианты значений для поля типа enum
     *
     * @return array
     */
    public static function getTypeEnum()
    {
        $result = [];
        $externalGroup = Market\Config::getLang('EXPORT_PROMO_PROVIDER_EXTERNAL_GROUP');
        $internalGroup = Market\Config::getLang('EXPORT_PROMO_PROVIDER_INTERNAL_GROUP');

	    foreach (static::getProviderList() as $type => $className)
	    {
		    $result[] = [
			    'ID' => $type,
			    'VALUE' => static::getProviderTypeTitle($type, $className),
				'GROUP' => $externalGroup
		    ];
	    }

        foreach (static::getInternalTypeList() as $internalType)
        {
            $result[] = [
                'ID' => $internalType,
                'VALUE' => static::getInternalTypeTitle($internalType),
				'GROUP' => $internalGroup
            ];
        }

        return $result;
    }

    /**
     * Загружаем провайдеры, встроенные в модуль
     *
     * @return array
     */
    protected static function loadModuleProviderList()
    {
	    /** @var class-string<AbstractProvider>[] $typeList */
        $result = [];
        $typeList = [
            'sale_discount' => SaleProvider::class,
            'catalog_discount' => CatalogProvider::class,
	        'catalog_price' => PriceProvider::class,
        ];

        foreach ($typeList as $type => $className)
        {
            if ($className::isEnvironmentSupport())
            {
                $result[$type] = $className;
            }
        }

        return $result;
    }

    /**
     * Загружаем провайдеры, определенные пользователем
     *
     * @return array
     *
     * @throws Main\ArgumentOutOfRangeException
     */
    protected static function loadUserProviderList()
    {
        $result = [];

        $event = new Main\Event(Market\Config::getModuleName(), 'onExportPromoProviderBuildList');
        $event->send();

        foreach ($event->getResults() as $eventResult)
        {
            $eventData = $eventResult->getParameters();

            if (isset($eventData['TYPE']))
            {
                if (
                    !isset($eventData['CLASS_NAME']) // is required
                    || !is_subclass_of($eventData['CLASS_NAME'], AbstractProvider::class) // must be child of reference
                )
                {
                    throw new Main\ArgumentOutOfRangeException('invalid provider class');
                }

                $result[$eventData['TYPE']] = $eventData['CLASS_NAME'];
            }
        }

        return $result;
    }
}