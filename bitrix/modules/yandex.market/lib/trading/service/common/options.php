<?php
namespace Yandex\Market\Trading\Service\Common;

use Yandex\Market;
use Bitrix\Main;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Trading\Settings;

abstract class Options extends TradingService\Reference\Options
{
	use Market\Reference\Concerns\HasMessage;

	const YANDEX_MODE_PUSH = 'push';
	const YANDEX_MODE_PULL = 'pull';

	const EXTERNAL_ID_FIELD_ACCOUNT_NUMBER = 'f:ACCOUNT_NUMBER';

	/** @var Provider */
	protected $provider;

	public function __construct(Provider $provider)
	{
		parent::__construct($provider);
	}

	public function getCampaignId()
	{
		$campaignId = $this->provider->getContext()->getCampaign()->getId();

		if ($campaignId <= 0)
		{
			throw new Settings\Options\RequiredValueException('CAMPAIGN_ID');
		}

		return $campaignId;
	}

	public function getYandexMode()
	{
		return $this->getValue('YANDEX_MODE');
	}

	public function getYandexTokens()
	{
		$tokens = (array)$this->requireValue('YANDEX_TOKEN');

		foreach ($tokens as $tokenKey => &$token)
		{
			$token = (string)$token;

			if ($token === '')
			{
				unset($tokens[$tokenKey]);
			}
		}

		return $tokens;
	}

	public function isAllowModifyPrice()
	{
		return true;
	}

	public function isAllowModifyBasket()
	{
		return false;
	}

	public function getPersonType()
	{
		return $this->requireValue('PERSON_TYPE');
	}

	public function getProfileId()
	{
		return (string)$this->getValue('PROFILE_ID');
	}

	public function getProperty($fieldName)
	{
		$value = $this->getValue('PROPERTY_' . $fieldName);

		if (mb_strpos((string)$value, 'f:') === 0) { return null; }

		return $value;
	}

	public function useAccountNumberTemplate()
	{
		return $this->getValue('PROPERTY_EXTERNAL_ID') === static::EXTERNAL_ID_FIELD_ACCOUNT_NUMBER;
	}

	public function getAccountNumberTemplate()
	{
		$value = trim($this->getValue('ACCOUNT_NUMBER_TEMPLATE'));

		if ($value === '')
		{
			$value = $this->getAccountNumberDefault();
		}
		else if (mb_strpos($value, '{id}') === false)
		{
			$value .= '{id}';
		}

		return $value;
	}

	protected function getAccountNumberDefault()
	{
		$serviceCode = $this->provider->getServiceCode();

		if ($serviceCode === TradingService\Manager::SERVICE_MARKETPLACE)
		{
			return 'Y-{id}';
		}

		return sprintf('%s_{id}', $serviceCode);
	}

	public function getProductStores()
	{
		return (array)$this->getValue('PRODUCT_STORE');
	}

	public function isProductStoresTrace()
	{
		return (string)$this->getValue('PRODUCT_STORE_TRACE') === Market\Reference\Storage\Table::BOOLEAN_Y;
	}

	public function getPackRatioSources()
	{
		$value = $this->getValue('PRODUCT_RATIO_SOURCE');

		if (!is_array($value)) { return []; }

		$result = [];

		foreach ($value as $one)
		{
			list($source, $field) = explode(':', (string)$one);

			if ($source === '' || $field === '') { continue; }

			$result[] = [
				$source,
				$field,
			];
		}

		return $result;
	}

	public function getPriceSource()
	{
		return $this->getValue('PRODUCT_PRICE_SOURCE');
	}

	public function getPriceTypes()
	{
		return (array)$this->getValue('PRODUCT_PRICE_TYPE');
	}

	public function usePriceDiscount()
	{
		return ((string)$this->getValue('PRODUCT_PRICE_DISCOUNT') === Market\Reference\Storage\Table::BOOLEAN_Y);
	}

	public function getStatusIn($externalStatus)
	{
		$optionKey = 'STATUS_IN_' . $externalStatus;
		$value = $this->getValue($optionKey);

		if (is_array($value)) { return $value; }

		return (string)$value === '' ? [] : [ $value ];
	}

	public function getStatusOut($bitrixStatus)
	{
		$result = null;

		foreach ($this->provider->getStatus()->getOutgoingVariants() as $status)
		{
			if (in_array($bitrixStatus, $this->getStatusOutRaw($status), true))
			{
				$result = $status;
				break;
			}
		}

		return $result;
	}

	public function getStatusOutRaw($externalStatus)
	{
		$value = $this->getValue('STATUS_OUT_' . mb_strtoupper($externalStatus));

		if (is_array($value)) { return $value; }

		return (string)$value === '' ? [] : [ $value ];
	}

	public function useSyncStatusOut()
	{
		return ((string)$this->getValue('SYNC_STATUS_OUT') === Market\Reference\Storage\Table::BOOLEAN_Y);
	}

	protected function applyValues()
	{
		$this->applyProductStoresReserve();
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
		}
	}

	protected function getIncomingRequestFields()
	{
		$tradingContext = $this->provider->getContext();
		$urlId = (string)$tradingContext->getUrlId();

		if ($urlId === '')
		{
			return [
				'YANDEX_MODE' => [
					'TYPE' => 'enumeration',
					'GROUP' => self::getMessage('INCOMING_REQUEST_GROUP'),
					'NAME' => self::getMessage('YANDEX_MODE'),
					'HIDDEN' => 'Y',
					'MANDATORY' => 'Y',
					'DESCRIPTION' => self::getMessage('YANDEX_MODE_DESCRIPTION'),
					'VALUES' => [
						[
							'ID' => static::YANDEX_MODE_PULL,
							'VALUE' => self::getMessage('YANDEX_MODE_PULL'),
						],
					],
					'SORT' => 1050,
					'SETTINGS' => [
						'DISPLAY' => 'CHECKBOX',
						'DEFAULT_VALUE' => static::YANDEX_MODE_PULL,
					],
				],
			];
		}

		$siteId = $tradingContext->getSiteId();
		$incomingPath = $tradingContext->getEnvironment()->getRoute()->getPublicPath($this->provider->getCode(), $urlId);
		$incomingVariables = array_filter([
			'protocol' => 'https',
			'host' => Market\Data\SiteDomain::getHost($siteId),
		]);

		return [
			'YANDEX_MODE' => [
				'TYPE' => 'enumeration',
				'GROUP' => self::getMessage('INCOMING_REQUEST_GROUP'),
				'NAME' => self::getMessage('YANDEX_MODE'),
				'HIDDEN' => Market\Config::getOption('trading_yandex_mode') === 'Y' || $this->getYandexMode() !== static::YANDEX_MODE_PULL ? 'N' : 'Y',
				'MANDATORY' => 'Y',
				'DESCRIPTION' => self::getMessage('YANDEX_MODE_DESCRIPTION'),
				'VALUES' => [
					[
						'ID' => static::YANDEX_MODE_PULL,
						'VALUE' => self::getMessage('YANDEX_MODE_PULL'),
					],
					[
						'ID' => static::YANDEX_MODE_PUSH,
						'VALUE' => self::getMessage('YANDEX_MODE_PUSH'),
					],
				],
				'SORT' => 1050,
				'SETTINGS' => [
					'DISPLAY' => 'CHECKBOX',
					'DEFAULT_VALUE' => static::YANDEX_MODE_PULL,
				],
			],
			'YANDEX_TOKEN' => [
				'TYPE' => 'string',
				'MANDATORY' => 'Y',
				'MULTIPLE' => 'Y',
				'NAME' => self::getMessage('YANDEX_TOKEN'),
				'DESCRIPTION' => self::getMessage('YANDEX_TOKEN_DESCRIPTION'),
				'SORT' => 1100,
				'SETTINGS' => [
					'VALIGN_PUSH' => true,
				],
				'DEPEND' => [
					'LOGIC' => 'OR',
					'YANDEX_TOKEN' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
					'YANDEX_MODE' => [
						'RULE' => 'ANY',
						'VALUE' => static::YANDEX_MODE_PUSH,
					],
				],
			],
			'YANDEX_INCOMING_URL' => [
				'TYPE' => 'incomingUrl',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('YANDEX_INCOMING_URL'),
				'DESCRIPTION' => self::getMessage('YANDEX_INCOMING_URL_DESCRIPTION'),
				'VALUE' => Market\Utils\Url::absolutizePath($incomingPath, $incomingVariables),
				'SETTINGS' => [
					'READONLY' => true,
					'COPY_BUTTON' => true,
					'SITE_ID' => $siteId,
				],
				'SORT' => 1110,
				'DEPEND' => [
					'LOGIC' => 'OR',
					'YANDEX_TOKEN' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
					'YANDEX_MODE' => [
						'RULE' => 'ANY',
						'VALUE' => static::YANDEX_MODE_PUSH,
					],
				],
			],
		];
	}

	protected function getOrderPersonFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$personType = $environment->getPersonType();
		$personTypeDefault = $this->getPersonTypeDefaultValue($personType, $siteId);
		$personTypeEnum = $personType->getEnum($siteId);
		$hasDefaultPersonType = ($personTypeDefault !== null);
		$user = $environment->getUserRegistry()->getAnonymousUser($this->provider->getServiceCode(), $siteId);

		if (!$hasDefaultPersonType && !empty($personTypeEnum))
		{
			$personTypeFirstOption = reset($personTypeEnum);
			$personTypeDefault = $personTypeFirstOption['ID'];
		}

		return [
			'PERSON_TYPE' => [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('PERSON_TYPE'),
				'GROUP' => self::getMessage('GROUP_PROPERTY'),
				'MANDATORY' => 'Y',
				'VALUES' => $personTypeEnum,
				'HIDDEN' => $hasDefaultPersonType && !Market\Config::isExpertMode() ? 'Y' : 'N',
				'SETTINGS' => [
					'DEFAULT_VALUE' => $personTypeDefault,
					'STYLE' => 'max-width: 220px;',
				],
				'SORT' => 3500,
			],
			'PROFILE_ID' => [
				'TYPE' => 'buyerProfile',
				'NAME' => self::getMessage('PROFILE_ID'),
				'DESCRIPTION' => self::getMessage('PROFILE_ID_HELP'),
				'GROUP' => self::getMessage('GROUP_PROPERTY'),
				'SETTINGS' => [
					'STYLE' => 'max-width: 220px;',
					'PERSON_TYPE_FIELD' => 'PERSON_TYPE',
					'PERSON_TYPE_DEFAULT' => $personTypeDefault,
					'USER_ID' => $user->getId(),
					'SERVICE' => $this->provider->getCode(),
				],
				'SORT' => 3510,
			],
		];
	}

	protected function getPersonTypeDefaultValue(TradingEntity\Reference\PersonType $personType, $siteId)
	{
		return $personType->getLegalId($siteId);
	}

	protected function getOrderPropertyUtilFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$orderClassName = $this->provider->getModelFactory()->getOrderClassName();
		$fields = $orderClassName::getMeaningfulFields();
		$options = [];
		$additional = [];
		$sort = 3700;

		foreach ($fields as $field)
		{
			$options[$field] = [
				'NAME' => $orderClassName::getMeaningfulFieldTitle($field),
				'HELP_MESSAGE' => $orderClassName::getMeaningfulFieldHelp($field),
				'GROUP' => self::getMessage('GROUP_PROPERTY'),
				'SORT' => $sort++,
			];

			if ($field === 'EXTERNAL_ID')
			{
				$options[$field] += [
					'VALUES' => [
						[
							'ID' => static::EXTERNAL_ID_FIELD_ACCOUNT_NUMBER,
							'VALUE' => self::getMessage('EXTERNAL_ID_FIELD_ACCOUNT_NUMBER'),
							'GROUP' => self::getMessage('EXTERNAL_ID_FIELDS'),
						],
					],
					'SETTINGS' => [
						'DEFAULT_GROUP' => self::getMessage('EXTERNAL_ID_PROPERTIES'),
					],
				];

				$additional['ACCOUNT_NUMBER_TEMPLATE'] = [
					'TYPE' => 'string',
					'NAME' => self::getMessage('ACCOUNT_NUMBER_TEMPLATE'),
					'HELP_MESSAGE' => self::getMessage('ACCOUNT_NUMBER_TEMPLATE_HELP'),
					'SORT' => $sort++,
					'SETTINGS' => [
						'DEFAULT_VALUE' => $this->getAccountNumberDefault(),
						'PLACEHOLDER' => $this->getAccountNumberTemplate(),
					],
					'DEPEND' => [
						'PROPERTY_' . $field => [
							'RULE' => Market\Utils\UserField\DependField::RULE_ANY,
							'VALUE' => static::EXTERNAL_ID_FIELD_ACCOUNT_NUMBER,
						],
 					],
				];
			}
		}

		$result = $this->createPropertyFields($environment, $siteId, $options, $sort) + $additional;

		uasort($result, static function($a, $b) {
			if ($a['SORT'] === $b['SORT']) { return 0; }

			return ($a['SORT'] < $b['SORT'] ? -1 : 1);
		});

		return $result;
	}

	protected function createPropertyFields(TradingEntity\Reference\Environment $environment, $siteId, array $fields, $sort)
	{
		$personType = $environment->getPersonType();
		$personTypeDefault = $this->getPersonTypeDefaultValue($personType, $siteId);
		$result = [];

		foreach ($fields as $fieldName => $field)
		{
			$defaultSettings = [
				'TYPE' => $fieldName,
				'PERSON_TYPE_FIELD' => 'PERSON_TYPE',
				'PERSON_TYPE_DEFAULT' => $personTypeDefault,
				'STYLE' => 'max-width: 220px;',
			];
			$defaultFields = [
				'TYPE' => 'orderProperty',
				'SORT' => $sort,
			];
			$propertyField = $field + $defaultFields;
			$propertyField['SETTINGS'] = isset($field['SETTINGS']) ? $field['SETTINGS'] + $defaultSettings : $defaultSettings;

			$result['PROPERTY_' . $fieldName] = $propertyField;

			++$sort;
		}

		return $result;
	}

	protected function getProductStoreFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$store = $environment->getStore();

		return [
			'PRODUCT_STORE' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'MULTIPLE' => 'Y',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('PRODUCT_STORE'),
				'INTRO' => self::getMessage('PRODUCT_STORE_DESCRIPTION'),
				'VALUES' => $store->getEnum($siteId),
				'SETTINGS' => [
					'DISPLAY' => 'CHECKBOX',
					'DEFAULT_VALUE' => $store->getDefaults(),
				],
				'SORT' => 1100,
			],
			'PRODUCT_STORE_TRACE' => [
				'TYPE' => 'boolean',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('PRODUCT_STORE_TRACE'),
				'SORT' => 1110,
			],
			'PRODUCT_RATIO_SOURCE' => [
				'TYPE' => 'exportParam',
				'TAB' => 'STORE',
				'MULTIPLE' => 'Y',
				'NAME' => self::getMessage('PRODUCT_RATIO_SOURCE'),
				'HELP_MESSAGE' => self::getMessage('PRODUCT_RATIO_SOURCE_HELP'),
				'SORT' => 1120,
				'SETTINGS' => [
					'VALIGN_PUSH' => true,
				],
			],
		];
	}

	protected function getProductPriceFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$price = $environment->getPrice();
		$userGroup = $environment->getUserGroupRegistry()->getGroup($this->provider->getServiceCode(), $siteId);
		$userGroupIds = (array)$userGroup->getId();

		return [
			'PRODUCT_PRICE_SOURCE' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('PRODUCT_PRICE_SOURCE'),
				'VALUES' => $price->getSourceEnum(),
				'SETTINGS' => [
					'CAPTION_NO_VALUE' => self::getMessage('PRODUCT_PRICE_SOURCE_NO_VALUE'),
				],
				'SORT' => 2000,
			],
			'PRODUCT_PRICE_TYPE' => [
				'TYPE' => 'enumeration',
				'TAB' => 'STORE',
				'MULTIPLE' => 'Y',
				'NAME' => self::getMessage('PRODUCT_PRICE_TYPE'),
				'MANDATORY' => 'Y',
				'VALUES' => $price->getTypeEnum(),
				'SETTINGS' => [
					'DISPLAY' => 'CHECKBOX',
					'DEFAULT_VALUE' => $price->getTypeDefaults($userGroupIds),
				],
				'DEPEND' => [
					'PRODUCT_PRICE_SOURCE' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
				'SORT' => 2010,
			],
			'PRODUCT_PRICE_DISCOUNT' => [
				'TYPE' => 'boolean',
				'TAB' => 'STORE',
				'NAME' => self::getMessage('PRODUCT_PRICE_DISCOUNT'),
				'SETTINGS' => [
					'DEFAULT_VALUE' => Market\Reference\Storage\Table::BOOLEAN_Y,
				],
				'DEPEND' => [
					'PRODUCT_PRICE_SOURCE' => [
						'RULE' => 'EMPTY',
						'VALUE' => false,
					],
				],
				'SORT' => 2020,
			],
		];
	}

	protected function getStatusInFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$serviceStatus = $this->provider->getStatus();
		$environmentStatus = $environment->getStatus();
		$environmentVariants = $environmentStatus->getVariants();
		$environmentEnum = $environmentStatus->getEnum($environmentVariants);
		$incomingVariants = $serviceStatus->getIncomingVariants();
		$statusDefaults = $this->makeStatusDefaults($environmentStatus->getMeaningfulMap(), $serviceStatus->getIncomingMeaningfulMap());
		$sort = 1000;
		$result = [];

		foreach ($incomingVariants as $statusVariant)
		{
			$defaultValue = isset($statusDefaults[$statusVariant]) ? (array)$statusDefaults[$statusVariant] : [];

			$result['STATUS_IN_' . $statusVariant] = [
				'TYPE' => 'enumeration',
				'TAB' => 'STATUS',
				'GROUP' => self::getMessage('GROUP_STATUS_IN'),
				'NAME' => $serviceStatus->getTitle($statusVariant),
				'HELP_MESSAGE' => $serviceStatus->getHelp($statusVariant),
				'MULTIPLE' => 'Y',
				'VALUES' => $environmentEnum,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $defaultValue,
					'STYLE' => 'max-width: 300px;',
					'ALLOW_NO_VALUE' => 'Y',
					'VALIGN_PUSH' => true,
				],
				'SORT' => $sort,
			];

			++$sort;
		}

		return $result;
	}

	protected function getStatusOutFields(TradingEntity\Reference\Environment $environment, $siteId)
	{
		$environmentStatus = $environment->getStatus();
		$environmentStatusVariants = $environmentStatus->getVariants();
		$environmentStatusEnum = $environmentStatus->getEnum($environmentStatusVariants);
		$serviceStatus = $this->provider->getStatus();
		$serviceOutgoingVariants = $serviceStatus->getOutgoingVariants();
		$statusDefaults = $this->makeStatusDefaults($environmentStatus->getMeaningfulMap(), $serviceStatus->getOutgoingMeaningfulMap());
		$sort = 2000;
		$result = [];

		foreach ($serviceOutgoingVariants as $serviceOutgoingVariant)
		{
			$defaultValue = isset($statusDefaults[$serviceOutgoingVariant]) ? (array)$statusDefaults[$serviceOutgoingVariant] : [];

			$result['STATUS_OUT_' . $serviceOutgoingVariant] = [
				'TYPE' => 'enumeration',
				'TAB' => 'STATUS',
				'GROUP' => self::getMessage('GROUP_STATUS_OUT'),
				'NAME' => $serviceStatus->getTitle($serviceOutgoingVariant) . ' (' . $serviceOutgoingVariant . ')',
				'MULTIPLE' => 'Y',
				'VALUES' => $environmentStatusEnum,
				'SETTINGS' => [
					'DEFAULT_VALUE' => $defaultValue,
					'STYLE' => 'max-width: 300px;',
					'ALLOW_NO_VALUE' => 'Y',
					'VALIGN_PUSH' => true,
				],
				'SORT' => $sort,
			];

			++$sort;
		}

		return $result;
	}

	protected function makeStatusDefaults($environmentMeaningfulMap, $serviceMeaningfulMap)
	{
		$result = [];

		foreach ($environmentMeaningfulMap as $meaningfulStatus => $environmentVariant)
		{
			if (isset($serviceMeaningfulMap[$meaningfulStatus]))
			{
				$serviceVariant = $serviceMeaningfulMap[$meaningfulStatus];

				$result[$serviceVariant] = $environmentVariant;
			}
		}

		return $result;
	}

	protected function getStatusOutSyncFields()
	{
		return [
			'SYNC_STATUS_OUT' => [
				'TYPE' => 'boolean',
				'TAB' => 'STATUS',
				'NAME' => self::getMessage('SYNC_STATUS_OUT'),
				'HELP_MESSAGE' => self::getMessage('SYNC_STATUS_OUT_HELP', [
					'#GROUP#' => rtrim(self::getMessage('GROUP_STATUS_OUT'), ': '),
				]),
				'SORT' => 2102,
			],
		];
	}

	protected function applyFieldsOverrides(array $fields, array $overrides = null)
	{
		if ($overrides !== null)
		{
			foreach ($fields as &$field)
			{
				$fieldOverrides = $overrides;

				if (isset($overrides['DEPEND'], $field['DEPEND']))
				{
					$fieldOverrides['DEPEND'] = $overrides['DEPEND'] + $field['DEPEND'];
				}

				$field = $fieldOverrides + $field;

				if (isset($overrides['SORT']))
				{
					++$overrides['SORT'];
				}
			}
			unset($field);
		}

		return $fields;
	}
}