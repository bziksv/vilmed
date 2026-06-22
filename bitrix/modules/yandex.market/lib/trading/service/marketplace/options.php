<?php

namespace Yandex\Market\Trading\Service\Marketplace;

use Yandex\Market;
use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

class Options extends TradingService\Common\Options
{
	use Market\Reference\Concerns\HasOnce;
	use Market\Reference\Concerns\HasMessage;

	const STOCKS_PLAIN = 'plain';
	const STOCKS_ONLY_AVAILABLE = 'onlyAvailable';
	/** @deprecated */
	const STOCKS_WITH_RESERVE = 'withReserve';

	const PRICES_MODE_CAMPAIGN = 'campaign';
	const PRICES_MODE_BUSINESS = 'business';

	/** @var Provider */
	protected $provider;

	public function __construct(Provider $provider)
	{
		parent::__construct($provider);
	}

	public function getPaySystemId($paymentType)
	{
		$paySystemTypeUpper = Market\Data\TextString::toUpper($paymentType);

		return (string)$this->getValue('PAY_SYSTEM_' . $paySystemTypeUpper);
	}

	public function getDeliveryId()
	{
		return (string)$this->getValue('DELIVERY_ID');
	}

	public function includeBasketSubsidy()
	{
		return (string)$this->getValue('BASKET_SUBSIDY_INCLUDE') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getSubsidyPaySystemId()
	{
		return (string)$this->getValue('SUBSIDY_PAY_SYSTEM_ID');
	}

	public function getCashboxCheck()
	{
		return $this->getValue('CASHBOX_CHECK', PaySystem::CASHBOX_CHECK_DISABLED);
	}

	public function getProductStores()
	{
		$catalogAdapter = $this->provider->getCatalogAdapter();

		if ($catalogAdapter->isStocksEnabled())
		{
			return $catalogAdapter->getProductStore();
		}

		$result = array_unique(array_merge(
			$this->getProductSelfStores(),
			$this->getStoreGroupCommand()->stores()
		));

		sort($result);

		return $result;
	}

	public function getProductSelfStores()
	{
		$catalogAdapter = $this->provider->getCatalogAdapter();

		if ($catalogAdapter->isStocksEnabled())
		{
			return $catalogAdapter->getProductStore();
		}

		return parent::getProductStores();
	}

	public function getPackRatioSources()
	{
		$catalogAdapter = $this->provider->getCatalogAdapter();

		if ($catalogAdapter->isStocksEnabled() || $catalogAdapter->isPriceEnabled())
		{
			return $catalogAdapter->getPackRatioSources();
		}

		return parent::getPackRatioSources();
	}

	public function getStoreGroupPrimarySetup()
	{
		return $this->getStoreGroupCommand()->primarySetup();
	}

	public function getStoreGroup()
	{
		return $this->getStoreGroupCommand()->linked();
	}

	public function getStoreGroupCommand()
	{
		return $this->provider->getContainer()->single(Command\GroupStores::class);
	}

	public function usePushStocks()
	{
		return (
			(string)$this->getValue('USE_PUSH_STOCKS') === Market\Reference\Storage\Table::BOOLEAN_Y
			&& !$this->provider->getCatalogAdapter()->isStocksEnabled()
		);
	}

	public function usePushPrices()
	{
		return (
			(string)$this->getValue('USE_PUSH_PRICES') === Market\Reference\Storage\Table::BOOLEAN_Y
			&& !$this->priceConfigMigrated()
		);
	}

	public function priceConfigMigrated()
	{
		return (
			$this->provider->getCatalogAdapter()->isPriceEnabled()
			&& $this->provider->getCatalogAdapter()->wasSubmitted()
		);
	}

	public function getPricesMode()
	{
		return $this->getValue('PRICES_MODE');
	}

	public function getPriceSource()
	{
		if ($this->priceConfigMigrated())
		{
			return null;
		}

		return parent::getPriceSource();
	}

	public function getPriceTypes()
	{
		if ($this->priceConfigMigrated())
		{
			return [];
		}

		return parent::getPriceTypes();
	}

	public function getProductFeeds()
	{
		$selfFeeds = $this->getSelfProductFeeds();

		if (empty($selfFeeds)) { return []; }

		$partials = [
			$selfFeeds,
		];

		foreach ($this->getStoreGroupCommand()->feeds() as $siblingFeeds)
		{
			if (empty($siblingFeeds)) { return []; }

			$partials[] = $siblingFeeds;
		}

        $ids = array_unique(array_merge(...$partials));

		Main\Type\Collection::normalizeArrayValuesByInt($ids);

		return $ids;
	}

	public function getSelfProductFeeds()
	{
		$ids = (array)$this->getValue('PRODUCT_FEED');

		Main\Type\Collection::normalizeArrayValuesByInt($ids);

		return $ids;
	}

	public function productUpdatedAt()
	{
		$dateFormatted = (string)$this->getValue('PRODUCT_UPDATED_AT');

		return (
			$dateFormatted !== ''
				? new Main\Type\DateTime($dateFormatted, \DateTime::ATOM)
				: null
		);
	}

	public function getStocksBehavior()
	{
		$catalogAdapter = $this->provider->getCatalogAdapter();

		if ($catalogAdapter->isStocksEnabled())
		{
			return $catalogAdapter->getStocksBehavior();
		}

		return $this->getValue('STOCKS_BEHAVIOR');
	}

	public function useOrderReserve()
	{
		return $this->getStocksBehavior() === static::STOCKS_ONLY_AVAILABLE;
	}

	public function isAllowModifyBasket()
	{
		return (string)$this->getValue('ORDER_ACCEPT_WITH_ERRORS') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getSelfTestOption()
	{
		return $this->getFieldset('SELF_TEST', Options\SelfTestOption::class);
	}

	public function getShipmentStatus($action)
	{
		$value = $this->getValue('STATUS_SHIPMENT_' . $action);

		if (is_array($value)) { return $value; }

		return (string)$value === '' ? [] : [ $value ];
	}

	public function useTrackReturn()
	{
		return (string)$this->getValue('USE_TRACK_RETURN') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getEnvironmentFieldActions(TradingEntity\Reference\Environment $environment)
	{
		return array_filter([
			$this->getEnvironmentCisActions($environment),
			$this->getEnvironmentItemsActions(),
			$this->getEnvironmentCashboxActions(),
		]);
	}

	protected function getEnvironmentCisActions(TradingEntity\Reference\Environment $environment)
	{
		return [
			'FIELD' => 'SHIPMENT.ITEM.STORE.MARKING_CODE',
			'PATH' => 'send/order/boxes',
			'PAYLOAD' => static function(array $action) use ($environment) {
				$itemsMap = [];
				$newIndex = 0;
				$result = [
					'items' => [],
					'allowRemove' => false,
				];

				foreach ($action['VALUE'] as $storeItem)
				{
					$markingCode = trim($storeItem['VALUE']);

					if ($markingCode === '') { continue; }

					$markingType = $environment->getProduct()->getMarkingGroupType($storeItem['MARKING_GROUP']);
					$itemKey = $storeItem['XML_ID'] . ':' . $storeItem['PRODUCT_ID'];

					if ($markingType === Market\Data\Trading\MarkingRegistry::UIN)
					{
						$identifier = Market\Data\Trading\Uin::formatMarkingCode($markingCode);
						$key = 'uin';
					}
					else
					{
						$identifier = Market\Data\Trading\Cis::formatMarkingCode($markingCode);
						$key = 'cis';
					}

					if (isset($itemsMap[$itemKey]))
					{
						$itemIndex = $itemsMap[$itemKey];
						$result['items'][$itemIndex]['instances'][] = [ $key => $identifier ];
					}
					else
					{
						$itemsMap[$itemKey] = $newIndex;
						$result['items'][$newIndex] = [
							'productId' => $storeItem['PRODUCT_ID'],
							'xmlId' => $storeItem['XML_ID'],
							'instances' => [
								[ $key => $identifier ],
							],
						];

						++$newIndex;
					}
				}

				return !empty($result['items']) ? $result : null;
			},
		];
	}

	protected function getEnvironmentItemsActions()
	{
		if (Market\Config::getOption('trading_silent_basket', 'N') === 'Y') { return null; }

		return [
			'FIELD' => 'BASKET.QUANTITY',
			'PATH' => 'send/order/boxes',
			'PAYLOAD' => static function(array $action) {
				$result = [
					'items' => [],
					'allowRemove' => true,
				];

				foreach ($action['VALUE'] as $basketItem)
				{
					$quantity = (float)$basketItem['VALUE'];

					if ($quantity <= 0) { continue; }

					$result['items'][] = [
						'productId' => $basketItem['PRODUCT_ID'],
						'xmlId' => $basketItem['XML_ID'],
						'count' => $quantity,
					];
				}

				return $result;
			},
		];
	}

	protected function getEnvironmentCashboxActions()
	{
		if ($this->getCashboxCheck() !== PaySystem::CASHBOX_CHECK_DISABLED) { return null; }

		return [
			'FIELD' => 'CASHBOX.CHECK',
			'PATH' => 'system/cashbox/reset',
			'PAYLOAD' => [],
			'DELAY' => false,
		];
	}

	protected function applyValues()
	{
		parent::applyValues();
		$this->applyYandexMode();
		$this->applyOrderCourierProperties();
		$this->applyElectronicAcceptanceCertificateProperties();
		$this->applyPaySystemId();
		$this->applyStocksBehavior();
		$this->applyPricesMode();
	}

	protected function applyYandexMode()
	{
		if ($this->getValue('YANDEX_MODE') !== null) { return; }

		$this->values['YANDEX_MODE'] = $this->getValue('CAMPAIGN_ID') ? static::YANDEX_MODE_PUSH : static::YANDEX_MODE_PULL;
	}

	protected function applyProductStoresReserve()
	{
		$stored = (array)$this->getValue('PRODUCT_STORE');
		$required = array_diff($stored, [
			TradingEntity\Common\Store::PRODUCT_FIELD_QUANTITY_RESERVED,
		]);

		if (count($stored) !== count($required))
		{
			$this->values['PRODUCT_STORE'] = array_values($required);
			$this->values['USE_ORDER_RESERVE'] = Market\Ui\UserField\BooleanType::VALUE_Y;
		}
		else if (!empty($stored) && !isset($this->values['USE_ORDER_RESERVE']))
		{
			$this->values['USE_ORDER_RESERVE'] = Market\Ui\UserField\BooleanType::VALUE_N;
		}
	}

	protected function applyOrderCourierProperties()
	{
		if (
			empty($this->values['PROPERTY_VEHICLE_NUMBER'])
			|| !empty($this->values['PROPERTY_COURIER_VEHICLE_NUMBER'])
		)
		{
			return;
		}

		$this->values['PROPERTY_COURIER_VEHICLE_NUMBER'] = $this->values['PROPERTY_VEHICLE_NUMBER'];
		unset($this->values['PROPERTY_VEHICLE_NUMBER']);
	}

	protected function applyElectronicAcceptanceCertificateProperties()
	{
		if (
			empty($this->values['PROPERTY_ELECTRONIC_ACCEPTANCE_CERTIFICATE'])
			|| !empty($this->values['PROPERTY_EAC_CODE'])
		)
		{
			return;
		}

		$this->values['PROPERTY_EAC_CODE'] = $this->values['PROPERTY_ELECTRONIC_ACCEPTANCE_CERTIFICATE'];
		unset($this->values['PROPERTY_ELECTRONIC_ACCEPTANCE_CERTIFICATE']);
	}

	protected function applyPaySystemId()
	{
		if (empty($this->values['PAY_SYSTEM_ID'])) { return; }

		foreach ($this->provider->getPaySystem()->getTypes() as $paymentType)
		{
			$optionName = 'PAY_SYSTEM_' . $paymentType;

			if (isset($this->values[$optionName])) { continue; }

			$this->values[$optionName] = $this->values['PAY_SYSTEM_ID'];
		}

		unset($this->values['PAY_SYSTEM_ID']);
	}

	protected function applyStocksBehavior()
	{
		if (isset($this->values['USE_ORDER_RESERVE']))
		{
			if (empty($this->values['STOCKS_BEHAVIOR']))
			{
				$useReserve = ((string)$this->values['USE_ORDER_RESERVE'] === Market\Reference\Storage\Table::BOOLEAN_Y);
				$this->values['STOCKS_BEHAVIOR'] = $useReserve ? static::STOCKS_ONLY_AVAILABLE : static::STOCKS_PLAIN;
			}

			unset($this->values['USE_ORDER_RESERVE']);
		}

		/** @noinspection PhpDeprecationInspection */
		if (!empty($this->values['STOCKS_BEHAVIOR']) && $this->values['STOCKS_BEHAVIOR'] === static::STOCKS_WITH_RESERVE)
		{
			$this->values['STOCKS_BEHAVIOR'] = static::STOCKS_ONLY_AVAILABLE;
		}
	}

	protected function applyPricesMode()
	{
		if (!empty($this->values['PRICES_MODE']) || !$this->usePushPrices()) { return; }

		$this->values['PRICES_MODE'] = static::PRICES_MODE_CAMPAIGN;
	}

	public function getTabs()
	{
		return [
			'COMMON' => [
				'name' => self::getMessage('TAB_COMMON'),
				'sort' => 1000,
			],
			'STORE' => [
				'name' => self::getMessage('TAB_STORE'),
				'sort' => 2000,
			],
			'ORDER' => [
				'name' => self::getMessage('TAB_ORDER'),
				'sort' => 3000,
			],
			'DELIVERY_AND_PAYMENT' => [
				'name' => self::getMessage('TAB_DELIVERY_AND_PAYMENT'),
				'sort' => 4000,
			],
			'STATUS' => [
				'name' => self::getMessage('TAB_STATUS'),
				'sort' => 5000,
				'data' => [
					'WARNING' => self::getMessage('TAB_STATUS_NOTE'),
				],
			],
		];
	}

	public function getFields()
	{
		$environment = $this->provider->getContext()->getEnvironment();
		$siteId = $this->provider->getContext()->getSiteId();

		return
			$this->getLogFields()
			+ $this->getIncomingRequestFields()
			+ $this->getOrderDeliveryFields($environment, $siteId)
			+ $this->getOrderPaySystemFields($environment, $siteId)
			+ $this->getOrderBasketSubsidyFields($environment, $siteId)
			+ $this->getOrderCashboxFields()
			+ $this->getOrderPersonFields($environment, $siteId)
			+ $this->getOrderPropertyBuyerFields($environment, $siteId)
			+ $this->getOrderPropertyUtilFields($environment, $siteId)
			+ $this->getOrderPropertyCourierFields($environment, $siteId)
			+ $this->getProductStoreFields($environment, $siteId)
			+ $this->getPushStocksFields()
			+ $this->getPushPricesFields()
			+ $this->getProductPriceFields($environment, $siteId)
			+ $this->getProductFeedFields()
			+ $this->getStatusInFields($environment, $siteId)
			+ $this->getStatusOutFields($environment, $siteId)
			+ $this->getStatusOutSyncFields()
			+ $this->getStatusShipmentFields($environment)
			+ $this->getOrderAcceptFields();
	}

	protected function getOrderPersonFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		return $this->applyFieldsOverrides(parent::getOrderPersonFields($environment, $siteId), [
			'TAB' => 'ORDER',
			'GROUP' => null,
			'SORT' => 3100,
		]);
	}

	protected function getOrderPaySystemFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$paySystem = $environment->getPaySystem();
		$paySystemEnum = $paySystem->getEnum($siteId);
		$firstPaySystem = reset($paySystemEnum);
		$servicePaySystem = $this->provider->getPaySystem();
		$result = [];
		$sort = 4015;

		foreach ($servicePaySystem->getTypes() as $paymentType)
		{
			$result['PAY_SYSTEM_' . $paymentType] = [
				'TYPE' => 'enumeration',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'MANDATORY' => $paySystem->isRequired() ? 'Y' : 'N',
				'NAME' => self::getMessage('PAY_SYSTEM', [
					'#TYPE#' => $servicePaySystem->getTypeTitle($paymentType, 'SHORT'),
				]),
				'GROUP' => self::getMessage('GROUP_ORDER'),
				'GROUP_DESCRIPTION' => self::getMessage('GROUP_ORDER_DESCRIPTION'),
				'VALUES' => $paySystemEnum,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $firstPaySystem !== false ? $firstPaySystem['ID'] : null,
					'STYLE' => 'max-width: 220px;',
				],
				'SORT' => ++$sort,
			];
		}

		return $result;
	}

	protected function getOrderDeliveryFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$delivery = $environment->getDelivery();
		$deliveryEnum = $delivery->getEnum($siteId);
		$defaultDelivery = null;
		$emptyDelivery = array_filter($deliveryEnum, static function($option) {
			return $option['TYPE'] === Market\Data\Trading\Delivery::EMPTY_DELIVERY;
		});

		if (empty($emptyDelivery))
		{
			$firstEmptyDelivery = reset($emptyDelivery);
			$defaultDelivery = $firstEmptyDelivery['ID'];
		}
		else if (!empty($deliveryEnum))
		{
			$firstDelivery = reset($deliveryEnum);
			$defaultDelivery = $firstDelivery['ID'];
		}

		return [
			'DELIVERY_ID' => [
				'TYPE' => 'enumeration',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'MANDATORY' => $delivery->isRequired() ? 'Y' : 'N',
				'NAME' => self::getMessage('DELIVERY_ID'),
				'GROUP' => self::getMessage('GROUP_ORDER'),
				'GROUP_DESCRIPTION' => self::getMessage('GROUP_ORDER_DESCRIPTION'),
				'VALUES' => $deliveryEnum,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $defaultDelivery,
					'STYLE' => 'max-width: 220px;',
				],
				'SORT' => 4010,
			],
		];
	}

	protected function getOrderBasketSubsidyFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$paySystem = $environment->getPaySystem();
		$paySystemEnum = $paySystem->getEnum($siteId);

		return [
			'BASKET_SUBSIDY_INCLUDE' => [
				'TYPE' => 'boolean',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('BASKET_SUBSIDY_INCLUDE'),
				'SORT' => 4150,
				'SETTINGS' => [
					'DEFAULT_VALUE' => Market\Ui\UserField\BooleanType::VALUE_Y,
				],
			],
			'SUBSIDY_PAY_SYSTEM_ID' => [
				'TYPE' => 'enumeration',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('SUBSIDY_PAY_SYSTEM_ID'),
				'HELP_MESSAGE' => self::getMessage('SUBSIDY_PAY_SYSTEM_ID_HELP'),
				'VALUES' => $paySystemEnum,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $paySystem->getInnerPaySystemId(),
					'CAPTION_NO_VALUE' => self::getMessage('SUBSIDY_PAY_SYSTEM_ID_NO_VALUE'),
					'STYLE' => 'max-width: 220px;',
				],
				'SORT' => 4151,
				'DEPEND' => [
					'BASKET_SUBSIDY_INCLUDE' => [
						'RULE' => 'ANY',
						'VALUE' => Market\Ui\UserField\BooleanType::VALUE_Y,
					],
				],
			],
		];
	}

	protected function getOrderCashboxFields()
	{
		$paySystem = $this->provider->getPaySystem();
		$default = $paySystem::CASHBOX_CHECK_DISABLED;
		$values = $paySystem->getCashboxCheckEnum();

		uasort($values, static function($optionA, $optionB) use ($default) {
			$sortA = $optionA['ID'] === $default ? 0 : 1;
			$sortB = $optionB['ID'] === $default ? 0 : 1;

			if ($sortA === $sortB) { return 0; }

			return ($sortA < $sortB ? -1 : 1);
		});

		return [
			'CASHBOX_CHECK' => [
				'TYPE' => 'enumeration',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('CASHBOX_CHECK'),
				'HELP_MESSAGE' => self::getMessage('CASHBOX_CHECK_HELP'),
				'VALUES' => $values,
				'HIDDEN' => !Main\Loader::includeModule('sale') || !class_exists(Sale\Cashbox\Cashbox::class) ? 'Y' : 'N',
				'SETTINGS' => [
					'DEFAULT_VALUE' => $default,
					'ALLOW_NO_VALUE' => 'N',
					'STYLE' => 'max-width: 220px;',
				],
				'SORT' => 4070,
			],
		];
	}

	protected function getOrderPropertyBuyerFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$buyerClass = $this->provider->getModelFactory()->getBuyerClassName();
		$fields = $buyerClass::getMeaningfulFields();
		$options = [];

		foreach ($fields as $fieldName)
		{
			$options[$fieldName] = [
				'TAB' => 'ORDER',
				'GROUP' => self::getMessage('GROUP_PROPERTY_BUYER'),
				'NAME' => $buyerClass::getMeaningfulFieldTitle($fieldName),
			];
		}

		return $this->createPropertyFields($environment, $siteId, $options, 3201);
	}

	protected function getOrderPropertyUtilFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = parent::getOrderPropertyUtilFields($environment, $siteId);

		return $this->applyFieldsOverrides($result, [
			'TAB' => 'ORDER',
			'GROUP' => self::getMessage('GROUP_ORDER_PROPERTY'),
			'SORT' => 3300,
		]);
	}

	protected function getOrderPropertyCourierFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$options = [];

		foreach (Model\Order\Delivery\Courier::getMeaningfulFields() as $field)
		{
			$options['COURIER_' . $field] = [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => Model\Order\Delivery\Courier::getMeaningfulFieldTitle($field),
				'GROUP' => self::getMessage('GROUP_COURIER_PROPERTY'),
			];
		}

		return $this->createPropertyFields($environment, $siteId, $options, 4900);
	}

	protected function getProductStoreFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$fields = parent::getProductStoreFields($environment, $siteId) + [
			'STOCKS_BEHAVIOR' =>  [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('STOCKS_BEHAVIOR'),
				'HELP_MESSAGE' => self::getMessage('STOCKS_BEHAVIOR_HELP'),
				'SORT' => 1105,
				'VALUES' => [
					[
						'ID' => static::STOCKS_ONLY_AVAILABLE,
						'VALUE' => self::getMessage('STOCKS_BEHAVIOR_ONLY_AVAILABLE'),
					],
					[
						'ID' => static::STOCKS_PLAIN,
						'VALUE' => self::getMessage('STOCKS_BEHAVIOR_PLAIN'),
					],
				],
				'SETTINGS' => [
					'DEFAULT_VALUE' => static::STOCKS_ONLY_AVAILABLE,
					'ALLOW_NO_VALUE' => 'N',
				],
			],
		];

		if ($this->provider->getCatalogAdapter()->isStocksEnabled())
		{
			$fields = $this->applyFieldsOverrides($fields, [
				'HIDDEN' => 'Y',
			]);
		}

		return $fields;
	}

	protected function getPushStocksFields()
	{
		return  [
			'USE_PUSH_STOCKS' => [
				'TYPE' => 'boolean',
				'TAB' => 'STORE',
				'GROUP' => self::getMessage('GROUP_PUSH_DATA'),
				'NAME' => self::getMessage('USE_PUSH_STOCKS'),
				'HELP_MESSAGE' => self::getMessage('USE_PUSH_STOCKS_HELP'),
				'HIDDEN' => $this->provider->getCatalogAdapter()->isStocksEnabled() ? 'Y' : 'N',
				'SORT' => 2200,
				'SETTINGS' => [
					'DEFAULT_VALUE' => Market\Ui\UserField\BooleanType::VALUE_Y,
				],
			],
		];
	}

	protected function getPushPricesFields()
	{
		$usedCatalogPrices = $this->priceConfigMigrated();

		return [
			'USE_PUSH_PRICES' => [
				'TYPE' => 'boolean',
				'TAB' => 'STORE',
				'GROUP' => self::getMessage('GROUP_PUSH_DATA'),
				'NAME' => self::getMessage('USE_PUSH_PRICES'),
				'HELP_MESSAGE' => self::getMessage('USE_PUSH_PRICES_HELP'),
				'SORT' => 2225,
				'HIDDEN' => $usedCatalogPrices ? 'Y' : 'N',
			],
			'PRICES_MODE' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('PRICES_MODE'),
				'HELP_MESSAGE' => self::getMessage('PRICES_MODE_HELP'),
				'SORT' => 2226,
				'HIDDEN' => $usedCatalogPrices ? 'Y' : 'N',
				'VALUES' => [
					[
						'ID' => static::PRICES_MODE_BUSINESS,
						'VALUE' => self::getMessage('PRICES_MODE_BUSINESS'),
					],
					[
						'ID' => static::PRICES_MODE_CAMPAIGN,
						'VALUE' => self::getMessage('PRICES_MODE_CAMPAIGN'),
					],
				],
				'SETTINGS' => [
					'ALLOW_NO_VALUE' => 'N',
					'DEFAULT_VALUE' => static::PRICES_MODE_BUSINESS,
				],
				'DEPEND' => [
					'USE_PUSH_PRICES' => [
						'RULE' => Market\Utils\UserField\DependField::RULE_EMPTY,
						'VALUE' => false,
					],
				],
			],
		];
	}

	protected function getProductFeedFields()
	{
		return [
			'PRODUCT_FEED' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('PRODUCT_FEED'),
				'HELP_MESSAGE' => self::getMessage('PRODUCT_FEED_HELP'),
				'MULTIPLE' => 'Y',
				'VALUES' => $this->getFeedEnum(),
				'SORT' => 2250,
				'HIDDEN' => $this->provider->getCatalogAdapter()->wasSubmitted() ? 'Y' : 'N',
				'SETTINGS' => [
					'STYLE' => 'max-width: 220px;',
					'VALIGN_PUSH' => true,
				],
			],
		];
	}

	protected function getFeedEnum()
	{
		$result = [];

		$query = Market\Export\Setup\Table::getList([
			'select' => [ 'ID', 'NAME', 'GROUP_NAME' => 'GROUP.NAME' ],
			'order' => [ 'GROUP.ID' => 'ASC', 'ID' => 'ASC' ],
		]);

		while ($row = $query->fetch())
		{
			$result[] = [
				'ID' => $row['ID'],
				'VALUE' => sprintf('[%s] %s', $row['ID'], $row['NAME']),
				'GROUP' => $row['GROUP_NAME'],
			];
		}

		return $result;
	}

	protected function getProductPriceFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = parent::getProductPriceFields($environment, $siteId);
		$overrides = [
			'SORT' => 2230,
		];

		if ($this->provider->getCatalogAdapter()->isPriceEnabled())
		{
			$overrides['HIDDEN'] = 'Y';
		}
		else if (!Market\Config::isExpertMode())
		{
			$overrides['DEPEND'] = [
				'USE_PUSH_PRICES' => [
					'RULE' => Market\Utils\UserField\DependField::RULE_EMPTY,
					'VALUE' => false,
				],
			];
		}

		return $this->applyFieldsOverrides($result, $overrides);
	}

	protected function getProductSelfTestFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = [];
		$defaults = [
			'TAB' => 'ORDER',
			'GROUP' => self::getMessage('SELF_TEST'),
			'SORT' => 3900,
		];

		foreach ($this->getSelfTestOption()->getFields() as $name => $field)
		{
			$key = sprintf('SELF_TEST[%s]', $name);

			$result[$key] = $field + $defaults;
		}

		return $result;
	}

	protected function getStatusInFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = parent::getStatusInFields($environment, $siteId);

		if (isset($result['STATUS_IN_RETURN']))
		{
			$result['USE_TRACK_RETURN'] = [
				'TYPE' => 'boolean',
				'TAB' => 'STATUS',
				'NAME' => self::getMessage('USE_TRACK_RETURN'),
				'HELP_MESSAGE' => self::getMessage('USE_TRACK_RETURN_HELP'),
				'SORT' => $result['STATUS_IN_RETURN']['SORT'] + 1,
				'DEPEND' => [
					'STATUS_IN_RETURN' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
			];
		}

		if (isset($result['STATUS_IN_PROCESSING_SHIPPED']))
		{
			$result['STATUS_IN_PROCESSING_SHIPPED']['DEPRECATED'] = 'Y';
		}

		return $result;
	}

	protected function getStatusShipmentFields(TradingEntity\Reference\Environment $environment)
	{
		$environmentStatus = $environment->getStatus();
		$variants = $environmentStatus->getVariants();
		$enum = $environmentStatus->getEnum($variants);
		$meaningfulMap = $environmentStatus->getMeaningfulMap();

		return [
			'STATUS_SHIPMENT_CONFIRM' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STATUS',
				'GROUP' => self::getMessage('GROUP_STATUS_SHIPMENT'),
				'NAME' => self::getMessage('STATUS_SHIPMENT_CONFIRM'),
				'VALUES' => $enum,
				'MULTIPLE' => 'Y',
				'SETTINGS' => [
					'DEFAULT_VALUE' =>
							isset($meaningfulMap[Market\Data\Trading\MeaningfulStatus::DEDUCTED])
								? (array)$meaningfulMap[Market\Data\Trading\MeaningfulStatus::DEDUCTED]
								: [],
					'STYLE' => 'max-width: 300px;',
					'VALIGN_PUSH' => true,
				],
				'SORT' => 3000,
			],
		];
	}

	public function getOrderAcceptFields()
	{
		return [
			'ORDER_ACCEPT_WITH_ERRORS' => [
				'TAB' => 'ORDER',
				'TYPE' => 'boolean',
				'NAME' => self::getMessage('ORDER_ACCEPT_WITH_ERRORS'),
				'HELP_MESSAGE' => self::getMessage('ORDER_ACCEPT_WITH_ERRORS_HELP'),
				'SORT' => 3990,
				'DEPEND' => [
					'YANDEX_MODE' => [
						'RULE' => Market\Utils\UserField\DependField::RULE_EXCLUDE,
						'VALUE' => [ self::YANDEX_MODE_PULL ],
					],
				],
			],
		];
	}

	protected function knownFieldsets()
	{
		return [
			$this->getSelfTestOption(),
		];
	}
}