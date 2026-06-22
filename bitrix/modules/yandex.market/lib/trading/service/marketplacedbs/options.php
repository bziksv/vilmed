<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Entity as TradingEntity;

class Options extends TradingService\Marketplace\Options
	implements
		TradingService\Common\Concerns\Options\UserRegistrationInterface
{
	use TradingService\Common\Concerns\Options\HasUserRegistration;
	use Market\Reference\Concerns\HasMessage;

	/** @var Provider */
	protected $provider;

	public function __construct(Provider $provider)
	{
		parent::__construct($provider);
	}

	/**
	 * @deprecated
	 * @throws Main\NotSupportedException
	 */
	public function getPaySystemId($paymentType)
	{
		throw new Main\NotSupportedException();
	}

	/**
	 * @deprecated
	 * @throws Main\NotSupportedException
	 */
	public function getDeliveryId()
	{
		throw new Main\NotSupportedException();
	}

	public function isDeliveryStrict()
	{
		return (string)$this->getValue('DELIVERY_STRICT') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getDeliveryOptions()
	{
		return $this->getFieldsetCollection('DELIVERY_OPTIONS', Options\DeliveryOptions::class);
	}

	public function getShipmentSchedule()
	{
		return $this->getFieldset('SHIPMENT_SCHEDULE', Options\ShipmentSchedule::class);
	}

	/** @return string|null */
	public function getOutletStoreField()
	{
		return $this->getValue('OUTLET_STORE_FIELD');
	}

	public function isPaySystemStrict()
	{
		return (string)$this->getValue('PAY_SYSTEM_STRICT') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getPaySystemOptions()
	{
		return $this->getFieldsetCollection('PAY_SYSTEM_OPTIONS', Options\PaySystemOptions::class);
	}

	public function useAddressDetails()
	{
		return (string)$this->getValue('USE_ADDRESS_DETAILS') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function includeLiftPrice()
	{
		return (string)$this->getValue('INCLUDE_LIFT_PRICE') !== Market\Reference\Storage\Table::BOOLEAN_N;
	}

	public function getStatusOut($bitrixStatus)
	{
		$result = parent::getStatusOut($bitrixStatus);

		if ($result === null && $this->getCancelStatusOptions()->hasStatus($bitrixStatus))
		{
			$result = Status::STATUS_CANCELLED;
		}

		return $result;
	}

	public function getCancelStatusOptions()
	{
		return $this->getFieldsetCollection('STATUS_OUT_CANCELLED_OPTION', Options\CancelStatusOptions::class);
	}

	public function getEnvironmentFieldActions(TradingEntity\Reference\Environment $environment)
	{
		return array_filter([
			$this->getEnvironmentCancellationAcceptActions(),
			$this->getEnvironmentDeliveryDateActions(),
			$this->getEnvironmentOutletStorageLimitActions(),
			$this->getEnvironmentItemsActions(),
			$this->getEnvironmentCisActions($environment),
			$this->getEnvironmentCashboxActions(),
		]);
	}

	protected function getEnvironmentCancellationAcceptActions()
	{
		$propertyId = (string)$this->getProperty('CANCELLATION_ACCEPT');

		if ($propertyId === '') { return null; }

		$cancellationAccept = $this->provider->getCancellationAccept();
		$map = [
			Market\Data\Trading\CancellationAccept::CONFIRM => [ 'accepted' => true ],
		];

		foreach ($cancellationAccept->getReasonVariants() as $variant)
		{
			$map[Market\Data\Trading\CancellationAccept::REJECT . ':' . $variant] = [
				'accepted' => false,
				'reason' => $variant,
			];
		}

		return [
			'FIELD' => sprintf('PROPERTY_%s.VALUE', $propertyId),
			'PATH' => 'send/cancellation/accept',
			'PAYLOAD_MAP' => $map,
		];
	}

	protected function getEnvironmentDeliveryDateActions()
	{
		$propertyId = (string)$this->getProperty('DELIVERY_DATE_FROM');

		if ($propertyId === '') { return null; }

		return [
			'FIELD' => sprintf('PROPERTY_%s.VALUE', $propertyId),
			'PATH' => 'send/delivery/date',
			'PAYLOAD' => static function(array $action) {
				$value = is_array($action['VALUE']) ? reset($action['VALUE']) : $action['VALUE'];

				if (Market\Utils\Value::isEmpty($value)) { return null; }

				return [
					'date' => $value,
					'reason' => Action\SendDeliveryDate\Activity::REASON_USER_MOVED_DELIVERY_DATES,
				];
			},
		];
	}

	protected function getEnvironmentOutletStorageLimitActions()
	{
		$propertyId = (string)$this->getProperty('OUTLET_STORAGE_LIMIT_DATE');

		if ($propertyId === '') { return null; }

		return [
			'FIELD' => sprintf('PROPERTY_%s.VALUE', $propertyId),
			'PATH' => 'send/delivery/storageLimit',
			'PAYLOAD' => static function(array $action) {
				$value = is_array($action['VALUE']) ? reset($action['VALUE']) : $action['VALUE'];

				if (Market\Utils\Value::isEmpty($value)) { return null; }

				return [
					'newDate' => $value,
				];
			},
		];
	}

	protected function getEnvironmentCashboxActions()
	{
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
		$this->applyOrderUserRuleValues();
		$this->applyCancelOptionValues();
	}

	protected function applyOrderUserRuleValues()
	{
		$rule = $this->getUserRule();
		$disabled = $this->getUserRuleDisabled();

		if (in_array($rule, $disabled, true))
		{
			$this->values['ORDER_USER_RULE'] = $this->getUserRuleDefault();
		}
	}

	protected function applyCancelOptionValues()
	{
		$cancelOptions = $this->getCancelStatusOptions();
		$oldCancelStatusOutKey = 'STATUS_OUT_' . Status::STATUS_CANCELLED;
		$oldCancelStatusOut = (string)$this->getValue($oldCancelStatusOutKey);

		if ($oldCancelStatusOut !== '' && count($cancelOptions) === 0)
		{
			$cancelOptions->setValues([
				[ 'STATUS' => $oldCancelStatusOut ],
			]);
		}

		unset($this->values[$oldCancelStatusOutKey]);
	}

	public function getFields()
	{
		$environment = $this->provider->getContext()->getEnvironment();
		$siteId = $this->provider->getContext()->getSiteId();

		return
			$this->getLogFields()
			+ $this->getIncomingRequestFields()
			+ $this->getProductStoreFields($environment, $siteId)
			+ $this->getPushStocksFields()
			+ $this->getPushPricesFields()
			+ $this->getProductPriceFields($environment, $siteId)
			+ $this->getProductFeedFields()
			+ $this->getProductSelfTestFields($environment, $siteId)
			+ $this->getOrderUserRuleFields($environment, $siteId)
			+ $this->getOrderPersonFields($environment, $siteId)
			+ $this->getOrderPropertyFields($environment, $siteId)
			+ $this->getOrderAcceptFields()
			+ $this->getDeliveryFields($environment, $siteId)
			+ $this->getOutletFields($environment, $siteId)
			+ $this->getPaySystemFields($environment, $siteId)
			+ $this->getOrderBasketSubsidyFields($environment, $siteId)
			+ $this->getAddressCommonFields($environment, $siteId)
			+ $this->getAddressDetailsFields($environment, $siteId)
			+ $this->getDeliveryLiftFields($environment, $siteId)
			+ $this->getAddressCoordinatesFields($environment, $siteId)
			+ $this->getDeliveryDispatchTypeFields($environment, $siteId)
			+ $this->getDeliveryDatesFields($environment, $siteId)
			+ $this->getStatusInFields($environment, $siteId)
			+ $this->getCancellationAcceptFields($environment, $siteId)
			+ $this->getStatusOutFields($environment, $siteId)
			+ $this->getCancelledStatusOutFields($environment, $siteId)
			+ $this->getCancelReasonFields($environment, $siteId)
			+ $this->getStatusOutSyncFields();
	}

	protected function getPersonTypeDefaultValue(TradingEntity\Reference\PersonType $personType, $siteId)
	{
		return $personType->getIndividualId($siteId);
	}

	protected function getUserRuleDefault()
	{
		return TradingService\Common\Concerns\Options\UserRegistrationInterface::USER_RULE_ANONYMOUS;
	}

	protected function getUserRuleDisabled()
	{
		return [
			TradingService\Common\Concerns\Options\UserRegistrationInterface::USER_RULE_MATCH_EMAIL,
			TradingService\Common\Concerns\Options\UserRegistrationInterface::USER_RULE_MATCH_PHONE,
		];
	}

	protected function getOrderUserRuleFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$fields = TradingService\Common\Concerns\Options\UserRegistration::getFields($environment, $siteId);
		$fields = $this->extendOrderUserRuleFields($fields);

		return $this->applyFieldsOverrides($fields, [
			'TAB' => 'ORDER',
			'GROUP' => null,
			'SORT' => 3000,
		]);
	}

	protected function getOrderPropertyFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		return
			$this->getOrderPropertyBuyerFields($environment, $siteId)
			+ $this->getOrderPropertyUtilFields($environment, $siteId);
	}

	protected function getDeliveryFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$deliveryOptions = $this->getDeliveryOptions();
		$shipmentSchedule = $this->getShipmentSchedule();

		return [
			'DELIVERY_STRICT' => [
				'TYPE' => 'boolean',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('DELIVERY_GROUP'),
				'NAME' => self::getMessage('DELIVERY_STRICT'),
				'SORT' => 4000,
			],
			'DELIVERY_OPTIONS' => $deliveryOptions->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('DELIVERY_GROUP'),
				'NAME' => self::getMessage('DELIVERY_OPTIONS'),
				'SORT' => 4010,
			],
			'SHIPMENT_SCHEDULE' => $shipmentSchedule->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('DELIVERY_GROUP'),
				'NAME' => self::getMessage('SHIPMENT_SCHEDULE'),
				'HELP_MESSAGE' => self::getMessage('SHIPMENT_SCHEDULE_HELP'),
				'SORT' => 4020,
				'DEPEND' => [
					'YANDEX_MODE' => [
						'RULE' => Market\Utils\UserField\DependField::RULE_EXCLUDE,
						'VALUE' => [ static::YANDEX_MODE_PULL ],
					],
				],
			],
		];
	}

	protected function getOutletFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$store = $environment->getStore();

		return [
			'OUTLET_STORE_FIELD' => [
				'TYPE' => 'enumeration',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('OUTLET_FIELD'),
				'SORT' => 4050,
				'VALUES' => $store->getFieldEnum($siteId),
				'SETTINGS' => [
					'DEFAULT_VALUE' => $store->getOutletDefaultField(),
					'STYLE' => 'max-width: 220px;',
				],
			],
		];
	}

	protected function getPaySystemFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$paySystemOptions = $this->getPaySystemOptions();

		return [
			'PAY_SYSTEM_STRICT' => [
				'TYPE' => 'boolean',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('PAYMENT_GROUP'),
				'NAME' => self::getMessage('PAY_SYSTEM_STRICT'),
				'SORT' => 4100,
			],
			'PAY_SYSTEM_OPTIONS' => $paySystemOptions->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('PAYMENT_GROUP'),
				'NAME' => self::getMessage('PAY_SYSTEM_OPTIONS'),
				'SORT' => 4110,
			],
		];
	}

	protected function getOrderBasketSubsidyFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = parent::getOrderBasketSubsidyFields($environment, $siteId);

		return $this->applyFieldsOverrides($result, [
			'TAB' => 'DELIVERY_AND_PAYMENT',
			'SORT' => 4120,
		]);
	}

	protected function getAddressCommonFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$propertyFields = [];
		$keys = [
			'ZIP',
			'CITY',
			'ADDRESS',
		];

		foreach ($keys as $key)
		{
			$propertyFields[$key] = [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('ADDRESS_' . $key, null, $key),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
			];
		}

		return $this->createPropertyFields($environment, $siteId, $propertyFields, 4200);
	}

	protected function getAddressDetailsFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		// common

		$result = [
			'USE_ADDRESS_DETAILS' => [
				'TYPE' => 'boolean',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('USE_ADDRESS_DETAILS'),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
				'SORT' => 4250,
			],
		];

		// property map

		$propertyFields = [];

		foreach (Model\Order\Delivery\Address::getAddressFields() as $key)
		{
			$propertyFields[$key] = [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => Model\Order\Delivery\Address::getFieldTitle($key),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
				'DEPEND' => [
					'USE_ADDRESS_DETAILS' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
			];
		}

		$propertyFields['LIFT_TYPE'] = [
			'TAB' => 'DELIVERY_AND_PAYMENT',
			'NAME' => self::getMessage('LIFT_TYPE'),
			'GROUP' => self::getMessage('GROUP_ADDRESS'),
			'DEPEND' => [
				'USE_ADDRESS_DETAILS' => [
					'RULE' => 'EMPTY',
					'VALUE' => false,
				],
			],
			'SETTINGS' => [
				'SERVICE_CODE' => $this->provider->getCode(),
				'ADD_URL' => Market\Ui\Admin\Path::getToolsUrl('OrderProperty/LiftTypeCreate'),
			],
		];

		$result += $this->createPropertyFields($environment, $siteId, $propertyFields, 4251);

		return $result;
	}

	protected function getDeliveryLiftFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$propertyFields = [
			'LIFT_TYPE' => [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('LIFT_TYPE'),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
				'DEPEND' => [
					'USE_ADDRESS_DETAILS' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
				'SETTINGS' => [
					'SERVICE_CODE' => $this->provider->getCode(),
					'ADD_URL' => Market\Ui\Admin\Path::getToolsUrl('OrderProperty/LiftTypeCreate'),
				],
			],
			'LIFT_PRICE' => [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('LIFT_PRICE'),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
				'DEPEND' => [
					'USE_ADDRESS_DETAILS' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
			],
		];

		$result = $this->createPropertyFields($environment, $siteId, $propertyFields, 4265);
		$result += [
			'INCLUDE_LIFT_PRICE' => [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'TYPE' => 'boolean',
				'NAME' => self::getMessage('INCLUDE_LIFT_PRICE'),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
				'SORT' => max(...array_column($result, 'SORT')) + 1,
				'DEPEND' => [
					'USE_ADDRESS_DETAILS' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
				'SETTINGS' => [
					'DEFAULT_VALUE' => Market\Ui\UserField\BooleanType::VALUE_Y,
				],
			],
		];

		return $result;
	}

	protected function getAddressCoordinatesFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$propertyFields = [];

		foreach (Model\Order\Delivery\Address::getCoordinatesFields() as $key)
		{
			$propertyFields[$key] = [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => Model\Order\Delivery\Address::getFieldTitle($key),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
			];
		}

		return $this->createPropertyFields($environment, $siteId, $propertyFields, 4271);
	}

	protected function getDeliveryDispatchTypeFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$propertyFields = [
			'DISPATCH_TYPE' => [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('DISPATCH_TYPE'),
				'GROUP' => self::getMessage('GROUP_ADDRESS'),
			],
		];

		return $this->createPropertyFields($environment, $siteId, $propertyFields, 4281);
	}

	protected function getDeliveryDatesFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$keys = [
			'DELIVERY_DATE_FROM',
			'DELIVERY_DATE_TO',
			'DELIVERY_INTERVAL_FROM',
			'DELIVERY_INTERVAL_TO',
			'DELIVERY_REAL_DATE',
			'OUTLET_STORAGE_LIMIT_DATE',
		];

		foreach ($keys as $key)
		{
			$propertyFields[$key] = [
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'NAME' => self::getMessage('GROUP_DELIVERY_DATES_' . $key, null, $key),
				'GROUP' => self::getMessage('GROUP_DELIVERY_DATES'),
			];
		}

		return $this->createPropertyFields($environment, $siteId, $propertyFields, 4300);
	}

	protected function getCancellationAcceptFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$fields = [
			'CANCELLATION_ACCEPT' => [
				'TAB' => 'STATUS',
				'NAME' => self::getMessage('CANCELLATION_ACCEPT'),
				'DESCRIPTION' => self::getMessage('CANCELLATION_ACCEPT_DESCRIPTION', [
					'#NOTIFICATION_TEMPLATE#' => $this->compileNotificationMakeDescription('order/cancellation/notify', $siteId),
				]),
				'SETTINGS' => [
					'SERVICE_CODE' => $this->provider->getCode(),
					'ADD_URL' => Market\Ui\Admin\Path::getToolsUrl('OrderProperty/CancellationAcceptCreate'),
				],
			],
		];

		return $this->createPropertyFields($environment, $siteId, $fields, 1100);
	}

	protected function compileNotificationMakeDescription($path, $siteId)
	{
		$behaviors = array_filter([
			'EMAIL' => true,
			'SMS' => (new Market\Ui\Trading\Notification\SmsRepository())->isSupported(),
		]);
		$parts = [];
		$queryData = [
			'lang' => LANGUAGE_ID,
			'service' => $this->provider->getCode(),
			'path' => $path,
			'site' => $siteId,
			'sessid' => bitrix_sessid(),
		];

		foreach ($behaviors as $behavior => $supported)
		{
			$replaces = [
				'#URL#' => Market\Ui\Admin\Path::getModuleUrl(
					'trading_notification_template',
					$queryData + [ 'type' => $behavior ]
				),
			];

			/** @noinspection HtmlUnknownTarget */
			$parts[] = self::getMessage(
				'NOTIFICATION_BEHAVIOR_' . $behavior,
				$replaces,
				sprintf('<a href="%s" target="_blank">%s</a>', $replaces['#URL#'], $behavior)
			);
		}

		return implode(
			self::getMessage('NOTIFICATION_BEHAVIOR_GLUE'),
			$parts
		);
	}

	protected function getStatusOutFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$result = parent::getStatusOutFields($environment, $siteId);
		$canceledKey = 'STATUS_OUT_' . Status::STATUS_CANCELLED;

		return array_diff_key($result, [ $canceledKey => true ]);
	}

	protected function getCancelledStatusOutFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$cancelStatusOptions = $this->getCancelStatusOptions();
		$serviceStatus = $this->provider->getStatus();
		$statusDefaults = $this->makeStatusDefaults($environment->getStatus()->getMeaningfulMap(), $serviceStatus->getOutgoingMeaningfulMap());
		$cancelReasonDefaults = $environment->getStatus()->getCancelReasonMeaningfulMap();
		$cancelReasonDefaultsMap = $this->makeCancelReasonDefaultsMap($cancelReasonDefaults);
		$defaultValues = isset($statusDefaults[Status::STATUS_CANCELLED])
			? array_map(static function($status) use ($cancelReasonDefaultsMap) {
				return [
					'STATUS' => $status,
					'CANCEL_REASON' => isset($cancelReasonDefaultsMap[$status]) ? $cancelReasonDefaultsMap[$status] : null,
				];
			}, (array)$statusDefaults[Status::STATUS_CANCELLED])
			: [];

		return [
			'STATUS_OUT_CANCELLED_OPTION' => $cancelStatusOptions->getFieldDescription() + [
				'TAB' => 'STATUS',
				'NAME' => sprintf('%s (%s)', $serviceStatus->getTitle(Status::STATUS_CANCELLED), Status::STATUS_CANCELLED),
				'TYPE' => 'fieldset',
				'SETTINGS' => [
					'LAYOUT' => 'table',
					'DEFAULT_VALUE' => $defaultValues,
					'VALIGN_PUSH' => true,
				],
				'SORT' => 2100,
			],
		];
	}

	protected function makeCancelReasonDefaultsMap($cancelReasonDefaults)
	{
		$result = [];

		foreach ($cancelReasonDefaults as $cancelReason => $statuses)
		{
			if (is_array($statuses))
			{
				$result += array_fill_keys($statuses, $cancelReason);
			}
			else
			{
				$result[$statuses] = $cancelReason;
 			}
		}

		return $result;
	}

	protected function getCancelReasonFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$fields = [
			'REASON_CANCELED' => [
				'TAB' => 'STATUS',
				'NAME' => self::getMessage('REASON_CANCELED'),
				'SETTINGS' => [
					'CAPTION_NO_VALUE' => self::getMessage('REASON_CANCELED_CAPTION_NO_VALUE'),
					'DEFAULT_GROUP' => self::getMessage('REASON_CANCELED_DEFAULT_GROUP'),
					'SERVICE_CODE' => $this->provider->getCode(),
					'ADD_URL' => Market\Ui\Admin\Path::getToolsUrl('OrderProperty/CancelReasonCreate'),
				],
			],
		];

		return $this->createPropertyFields($environment, $siteId, $fields, 2100);
	}

	protected function knownFieldsetCollections()
	{
		return array_merge(parent::knownFieldsetCollections(), [
			$this->getDeliveryOptions(),
			$this->getPaySystemOptions(),
			$this->getCancelStatusOptions(),
		]);
	}

	protected function knownFieldsets()
	{
		return array_merge(parent::knownFieldsets(), [
			$this->getShipmentSchedule(),
		]);
	}
}