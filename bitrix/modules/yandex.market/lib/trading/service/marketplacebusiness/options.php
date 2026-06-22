<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Campaign\Placement;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Ui;
use Yandex\Market\Utils\UserField\DependField;

/** @property Provider $provider */
class Options extends TradingService\Reference\Options
{
	use Concerns\HasOnce;
	use Concerns\HasMessage;

	public function __construct(Provider $provider)
	{
		parent::__construct($provider);
	}

	public function mergeBusinessValues(array $childrenValues)
	{
		$knownPlacements = $this->getKnownPlacements();
		$exportValues = [];

		foreach ($knownPlacements as $placement)
		{
			if (!isset($childrenValues[$placement])) { continue; }

			$behaviorValues = $childrenValues[$placement];
			$exportValues += array_diff_key($behaviorValues, [
				'CAMPAIGN_ID' => true,
				'YANDEX_MODE' => true,
				'DELIVERY_OPTIONS' => true,
				'SHIPMENT_SCHEDULE' => true,
				'PAY_SYSTEM_OPTIONS' => true,
				'USE_PUSH_STOCKS' => true,
				'USE_PUSH_PRICES' => true,
			]);
			$exportValues = $this->appendStatusValuesSuffix($placement, $exportValues, $behaviorValues);

			if ($placement === Placement::FBS)
			{
				$exportValues = $this->makeFbsPaySystemOptions($exportValues, $behaviorValues);
				$exportValues = $this->makeFbsDeliveryOptions($exportValues, $behaviorValues);
			}
			else if ($placement === Placement::DBS)
			{
				$exportValues = $this->makeDbsPaySystemOptions($exportValues, $behaviorValues);
				$exportValues = $this->makeDbsDeliveryOptions($exportValues, $behaviorValues);
			}
		}

		return $exportValues;
	}

	private function appendStatusValuesSuffix($placement, array $exportValues, array $behaviorValues)
	{
		$statusFields = [
			'SYNC_STATUS_OUT' => true,
			'PROPERTY_CANCELLATION_ACCEPT' => true,
			'USE_TRACK_RETURN' => true,
		];

		foreach ($behaviorValues as $name => $value)
		{
			if (isset($statusFields[$name]) || mb_strpos($name, 'STATUS_') === 0)
			{
				$exportValues["{$name}_{$placement}"] = $value;
				$statusFields[$name] = true;
			}
		}

		return array_diff_key($exportValues, $statusFields);
	}

	private function makeFbsPaySystemOptions(array $exportValues, array $behaviorValues)
	{
		if (!isset($exportValues['PAY_SYSTEM_OPTIONS']))
		{
			$exportValues['PAY_SYSTEM_OPTIONS'] = [];
		}

		if (!empty($behaviorValues['PAY_SYSTEM_POSTPAID']))
		{
			$exportValues['PAY_SYSTEM_OPTIONS'][] = [
				'PLACEMENT' => Placement::FBS,
				'ID' => $behaviorValues['PAY_SYSTEM_POSTPAID'],
				'TYPE' => TradingService\Marketplace\PaySystem::TYPE_POSTPAID,
				'CASHBOX_CHECK_FBS' => isset($behaviorValues['CASHBOX_CHECK'])
					? $behaviorValues['CASHBOX_CHECK']
					: TradingService\Marketplace\PaySystem::CASHBOX_CHECK_DISABLED,
			];
		}

		if (!empty($behaviorValues['PAY_SYSTEM_PREPAID']))
		{
			$exportValues['PAY_SYSTEM_OPTIONS'][] = [
				'PLACEMENT' => Placement::FBS,
				'ID' => $behaviorValues['PAY_SYSTEM_PREPAID'],
				'TYPE' => TradingService\Marketplace\PaySystem::TYPE_PREPAID,
				'CASHBOX_CHECK_FBS' => isset($behaviorValues['CASHBOX_CHECK'])
					? $behaviorValues['CASHBOX_CHECK']
					: TradingService\Marketplace\PaySystem::CASHBOX_CHECK_DISABLED,
			];
		}

		return array_diff_key($exportValues, [
			'PAY_SYSTEM_POSTPAID' => true,
			'PAY_SYSTEM_PREPAID' => true,
			'CASHBOX_CHECK' => true,
		]);
	}

	private function makeFbsDeliveryOptions(array $exportValues, array $behaviorValues)
	{
		if (!empty($behaviorValues['DELIVERY_ID']))
		{
			if (!isset($exportValues['DELIVERY_OPTIONS']))
			{
				$exportValues['DELIVERY_OPTIONS'] = [];
			}

			$exportValues['DELIVERY_OPTIONS'][] = [
				'PLACEMENT' => Placement::FBS,
				'ID' => $behaviorValues['DELIVERY_ID'],
			];
		}

		return array_diff_key($exportValues, [
			'DELIVERY_ID' => true,
		]);
	}

	private function makeDbsPaySystemOptions(array $exportValues, array $behaviorValues)
	{
		if (empty($behaviorValues['PAY_SYSTEM_OPTIONS'])) { return $exportValues; }

		if (!isset($exportValues['PAY_SYSTEM_OPTIONS']))
		{
			$exportValues['PAY_SYSTEM_OPTIONS'] = [];
		}

		foreach ($behaviorValues['PAY_SYSTEM_OPTIONS'] as $option)
		{
			$exportValues['PAY_SYSTEM_OPTIONS'][] = $option + [ 'PLACEMENT' => Placement::DBS ];
		}

		return $exportValues;
	}

	private function makeDbsDeliveryOptions(array $exportValues, array $behaviorValues)
	{
		if (empty($behaviorValues['DELIVERY_OPTIONS'])) { return $exportValues; }

		if (!isset($exportValues['DELIVERY_OPTIONS']))
		{
			$exportValues['DELIVERY_OPTIONS'] = [];
		}

		foreach ($behaviorValues['DELIVERY_OPTIONS'] as $option)
		{
			$exportValues['DELIVERY_OPTIONS'][] = $option + [ 'PLACEMENT' => Placement::DBS ];
		}

		return $exportValues;
	}

	protected function modifyPlacementValues($placement, array $values)
	{
		$values['YANDEX_MODE'] = TradingService\Marketplace\Options::YANDEX_MODE_PULL;
		$values = $this->cutPlacementValuesSuffix($placement, $values);

		if ($placement === Placement::FBS)
		{
			$values = $this->modifyFbsPaySystemValues($values);
			$values = $this->modifyFbsDeliveryValues($values);
		}

		return $values;
	}

	protected function cutPlacementValuesSuffix($placement, array $values)
	{
		$knownPlacements = array_flip($this->getKnownPlacements());

		foreach ($values as $name => $value)
		{
			if (!preg_match('/^(.+)_([^_]+)$/', $name, $matches)) { continue; }
			if (!isset($knownPlacements[$matches[2]])) { continue; }

			if ($matches[2] === $placement)
			{
				$values[$matches[1]] = $value;
			}

			unset($values[$name]);
		}

		return $values;
	}

	protected function modifyFbsPaySystemValues(array $values)
	{
		$values['CASHBOX_CHECK'] = TradingService\Marketplace\PaySystem::CASHBOX_CHECK_DISABLED;

		foreach ($values['PAY_SYSTEM_OPTIONS'] as $paySystemValues)
		{
			if ($paySystemValues['TYPE'] === TradingService\Marketplace\PaySystem::TYPE_POSTPAID)
			{
				$values['PAY_SYSTEM_POSTPAID'] = $paySystemValues['ID'];
			}
			else if ($paySystemValues['TYPE'] === TradingService\Marketplace\PaySystem::TYPE_PREPAID)
			{
				$values['PAY_SYSTEM_PREPAID'] = $paySystemValues['ID'];
			}

			$values['CASHBOX_CHECK'] = $paySystemValues['CASHBOX_CHECK_FBS'];
		}

		unset($values['PAY_SYSTEM_OPTIONS']);

		return $values;
	}

	protected function modifyFbsDeliveryValues(array $values)
	{
		if (empty($values['DELIVERY_OPTIONS'])) { return $values; }

		$deliveryOption = reset($values['DELIVERY_OPTIONS']);
		$values['DELIVERY_ID'] = $deliveryOption['ID'];

		unset($values['DELIVERY_OPTIONS']);

		return $values;
	}

	public function getDeliveryOptions()
	{
		return $this->getFieldsetCollection('DELIVERY_OPTIONS', Options\DeliveryOptions::class);
	}

	public function getPaySystemOptions()
	{
		return $this->getFieldsetCollection('PAY_SYSTEM_OPTIONS', Options\PaySystemOptions::class);
	}

	/** @return array<string, TradingService\Common\Provider> */
	public function getConfigurationChildren()
	{
		return $this->once('getConfigurationChildren', function() {
			$placements = array_fill_keys($this->getKnownPlacements(), null);

			/** @var Campaign\Model $campaign */
			foreach ($this->provider->getContext()->getBusiness()->getCampaignCollection() as $campaign)
			{
				$placement = $campaign->getPlacement();

				if (isset($placements[$placement])) { continue; }
				if (!array_key_exists($placement, $placements)) { continue; }

				$placements[$placement] = $this->provider->getCampaignFactory()->getProvider($campaign);
			}

			return array_filter($placements);
		});
	}

	/* sync with \Yandex\Market\Trading\Business\TradingRepository::getBusinessPlacements() */
	public function getKnownPlacements()
	{
		return [
			Placement::FBS,
			Placement::DBS,
		];
	}

	public function getTabs()
	{
		$result = [];

		foreach ($this->getConfigurationChildren() as $placement => $campaignProvider)
		{
			$tabs = $campaignProvider->getOptions()->getTabs();
			$tabs = $this->modifyStatusTab($placement, $tabs);

			$result += $tabs;
		}

		return $result;
	}

	protected function modifyStatusTab($placement, array $tabs)
	{
		if (!isset($tabs['STATUS'])) { return $tabs; }

		$tabs["STATUS_{$placement}"] = [
			'name' => "{$tabs['STATUS']['name']} ({$placement})",
		] + $tabs['STATUS'];

		unset($tabs['STATUS']);

		return $tabs;
	}

	public function getFields()
	{
		$groups = [
			'BUSINESS' => $this->getSelfGroup(),
		];
		$groups += $this->getChildrenGroups();

		return $this->mergeChildrenFields($groups);
	}

	protected function getSelfGroup()
	{
		return
			$this->getDeliveryFields()
			+ $this->getPaySystemFields();
	}

	protected function getDeliveryFields()
	{
		return [
			'DELIVERY_OPTIONS' => $this->getDeliveryOptions()->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('DELIVERY_GROUP'),
				'NAME' => self::getMessage('DELIVERY_OPTIONS'),
				'SORT' => 4010,
			],
		];
	}

	protected function getPaySystemFields()
	{
		return [
			'PAY_SYSTEM_OPTIONS' => $this->getPaySystemOptions()->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'TAB' => 'DELIVERY_AND_PAYMENT',
				'GROUP' => self::getMessage('PAYMENT_GROUP'),
				'NAME' => self::getMessage('PAY_SYSTEM_OPTIONS'),
				'SORT' => 4110,
			],
		];
	}

	protected function getChildrenGroups()
	{
		$groups = [];
		$defaultPersonType = $this->getPersonTypeDefaultValue();

		foreach ($this->getConfigurationChildren() as $placement => $campaignProvider)
		{
			$fields = $campaignProvider->getOptions()->getFields();
			$fields = $this->unsetCampaignFields($fields);
			$fields = $this->unsetStoreFields($fields);
			$fields = $this->unsetPushFields($fields);
			$fields = $this->injectDefaultPersonType($fields, $defaultPersonType);
			$fields = $this->modifyStatusFields($placement, $fields);

            $groups[$placement] = $fields;
		}

		return $groups;
	}

	protected function unsetCampaignFields(array $fields)
	{
		return array_diff_key($fields, [
			'CAMPAIGN_ID' => true,
			'DELIVERY_OPTIONS' => true,
			'DELIVERY_ID' => true,
			'CASHBOX_CHECK' => true,
			'PAY_SYSTEM_OPTIONS' => true,
			'PAY_SYSTEM_PREPAID' => true,
			'PAY_SYSTEM_POSTPAID' => true,
		]);
	}

	protected function unsetStoreFields(array $fields)
	{
		foreach ($fields as $name => $field)
		{
			if (!isset($field['TAB']) || $field['TAB'] !== 'STORE') { continue; }

			unset($fields[$name]);
		}

		return $fields;
	}

	protected function unsetPushFields(array $fields, $prefix = '')
	{
		$pushValues = [
			$prefix . 'YANDEX_MODE' => [ TradingService\Marketplace\Options::YANDEX_MODE_PUSH, TradingService\Marketplace\Options::YANDEX_MODE_PULL ],
			$prefix . 'USE_PUSH_STOCKS' => [ Ui\UserField\BooleanType::VALUE_Y, Ui\UserField\BooleanType::VALUE_N ],
			$prefix . 'USE_PUSH_PRICES' => [ Ui\UserField\BooleanType::VALUE_Y, Ui\UserField\BooleanType::VALUE_N ],
			$prefix . 'YANDEX_INCOMING_URL' => [],
		];
		$disabled = [];

		foreach ($fields as $key => &$field)
		{
			if (isset($field['FIELDS']))
			{
				$field['FIELDS'] = $this->unsetPushFields($field['FIELDS'], '@');
			}

			if (isset($pushValues[$key]))
			{
				unset($fields[$key]);
				continue;
			}

			if (empty($field['DEPEND'])) { continue; }

			$hasOtherDepend = false;

			foreach ($field['DEPEND'] as $dependKey => $dependRule)
			{
				if ($dependKey === 'LOGIC' || $dependKey === $key || isset($disabled[$dependKey])) { continue; }

				if (!isset($pushValues[$dependKey]))
				{
					$hasOtherDepend = true;
					break;
				}

				if ($dependRule['RULE'] === DependField::RULE_ANY)
				{
					$searchValue = $pushValues[$dependKey][0];
				}
				else if ($dependRule['RULE'] === DependField::RULE_EXCLUDE)
				{
					$searchValue = $pushValues[$dependKey][1];
				}
				else if ($dependRule['RULE'] === DependField::RULE_EMPTY)
				{
					$searchValue = false;
				}
				else
				{
					$hasOtherDepend = true;
					break;
				}

				$match = (
					$dependRule['VALUE'] === $searchValue
					|| (
						is_array($dependRule['VALUE']) && count($dependRule['VALUE']) === 1
						&& in_array($searchValue, $dependRule['VALUE'], true)
					)
				);

				if (!$match)
				{
					$hasOtherDepend = true;
					break;
				}
			}

			if (!$hasOtherDepend)
			{
				$disabled[$key] = true;
				unset($fields[$key]);
			}
		}
		unset($field);

		return $fields;
	}

	protected function getPersonTypeDefaultValue()
	{
		$context = $this->provider->getContext();

		return $context->getEnvironment()->getPersonType()->getLegalId($context->getSiteId());
	}

	protected function injectDefaultPersonType(array $fields, $personTypeId)
	{
		foreach ($fields as &$field)
		{
			if ($field['TYPE'] !== 'orderProperty' && $field['TYPE'] !== 'buyerProfile') { continue; }

			$field['SETTINGS']['PERSON_TYPE_DEFAULT'] = $personTypeId;
		}
		unset($field);

		return $fields;
	}

	protected function modifyStatusFields($placement, array $fields)
	{
		$result = $fields;
		$changed = [];

		foreach ($fields as $code => $field)
		{
			if (!isset($field['TAB']) || $field['TAB'] !== 'STATUS') { continue; }

			$field['TAB'] = "STATUS_{$placement}";

			$changed[$code] = "{$code}_{$placement}";
			$result["{$code}_{$placement}"] = $field;
			unset($result[$code]);
		}

		return $this->applyDependRename($result, $changed);
	}

	protected function applyDependRename(array $fields, array $renamed)
	{
		foreach ($fields as &$field)
		{
			if (empty($field['DEPEND'])) { continue; }

			foreach ($renamed as $from => $to)
			{
				if (isset($field['DEPEND'][$from]))
				{
					$field['DEPEND'][$to] = $field['DEPEND'][$from];
					unset($field['DEPEND'][$from]);
				}
			}
		}
		unset($field);

		return $fields;
	}

    protected function mergeChildrenFields(array $groups)
    {
		$childrenCount = count(array_diff(array_keys($groups), [ 'BUSINESS' ]));
		$groupsUsage = $this->childrenGroupsUsage($groups);
		$keysUsage = $this->childrenFieldsUsage($groups);
		$result = [];

        foreach ($groups as $placement => $fields)
        {
			foreach ($fields as $code => $field)
			{
				if (isset($result[$code])) { continue; }

				if ($placement === 'BUSINESS' || $childrenCount <= 1 || mb_strpos((string)$field['TAB'], 'STATUS_') === 0)
				{
					// nothing
				}
				else if (isset($field['GROUP']) && count($groupsUsage[$field['GROUP']]) === 1)
				{
					if (mb_strpos($field['GROUP'], ' (') === false)
					{
						$field['GROUP'] .= " ({$placement})";
					}
				}
				else if (count($keysUsage[$code]) === 1)
				{
					$field['NAME'] .= " ({$placement})";
				}

				$field['PLACEMENT'] = $keysUsage[$code];
				$result[$code] = $field;
			}
        }

		return $result;
    }

	protected function childrenFieldsUsage(array $groups)
	{
		$result = [];

		foreach ($groups as $behavior => $fields)
		{
			foreach ($fields as $code => $field)
			{
				if (isset($result[$code][$behavior])) { continue; }

				if (isset($result[$code]))
				{
					$result[$code][$behavior] = true;
					continue;
				}

				$result[$code] = [ $behavior => true ];
			}
		}

		return $result;
	}

	protected function childrenGroupsUsage(array $groups)
	{
		$result = [];

		foreach ($groups as $behavior => $fields)
		{
			foreach ($fields as $field)
			{
				if (!isset($field['GROUP'])) { continue; }

				$fieldGroup = $field['GROUP'];

				if (isset($result[$fieldGroup][$behavior])) { continue; }

				if (isset($result[$fieldGroup]))
				{
					$result[$fieldGroup][$behavior] = true;
					continue;
				}

				$result[$fieldGroup] = [ $behavior => true ];
			}
		}

		return $result;
	}

	protected function knownFieldsetCollections()
	{
		return [
			$this->getDeliveryOptions(),
			$this->getPaySystemOptions(),
		];
	}
}