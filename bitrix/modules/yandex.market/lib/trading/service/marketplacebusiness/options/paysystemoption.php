<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness\Options;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Campaign\Placement;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Utils\UserField\DependField;

/** @property TradingService\MarketplaceBusiness\Provider $provider */
class PaySystemOption extends TradingService\Reference\Options\Fieldset
{
	use Concerns\HasMessage;

	public function isMatchPlacement($placement)
	{
		return $this->getValue('PLACEMENT') === $placement;
	}

	public function getFieldDescription()
	{
		return parent::getFieldDescription() + [
			'SETTINGS' => [
				'SUMMARY' => '#PLACEMENT#: &laquo;#ID#&raquo; (#TYPE#, #METHOD#)',
				'LAYOUT' => 'summary',
				'VALIGN_PUSH' => 'pill',
				'DEFAULT_VALUE' => $this->defaultValue(),
			],
		];
	}

	private function defaultValue()
	{
		$context = $this->provider->getContext();
		$enum = $context->getEnvironment()->getPaySystem()->getEnum($context->getSiteId());
		$first = reset($enum);

		if ($first === false) { return []; }

		return [
			[
				'PLACEMENT' => Placement::FBS,
				'ID' => $first['ID'],
				'TYPE' => TradingService\Marketplace\PaySystem::TYPE_POSTPAID,
				'CASHBOX_CHECK_FBS' => TradingService\Marketplace\PaySystem::CASHBOX_CHECK_DISABLED,
			],
			[
				'PLACEMENT' => Placement::FBS,
				'ID' => $first['ID'],
				'TYPE' => TradingService\Marketplace\PaySystem::TYPE_PREPAID,
				'CASHBOX_CHECK_FBS' => TradingService\Marketplace\PaySystem::CASHBOX_CHECK_DISABLED,
			],
		];
	}

	public function getFields()
	{
		$configurationChildren = $this->provider->getOptions()->getConfigurationChildren();

		$fields = $this->getCommonFields($configurationChildren);
		$childrenGroups = $this->getChildrenFieldGroups($configurationChildren);
		
		return $this->mergeChildrenGroups($fields, $childrenGroups);
	}

	protected function getCommonFields(array $configurationChildren)
	{
		$context = $this->provider->getContext();

		return [
			'PLACEMENT' => [
				'TYPE' => 'enumeration',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('PLACEMENT'),
				'VALUES' => array_map(
					static function($placement) { return [ 'ID' => $placement, 'VALUE' => $placement ]; },
					array_keys($configurationChildren)
				),
			],
			'ID' => [
				'TYPE' => 'enumeration',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('ID'),
				'VALUES' => $context->getEnvironment()->getPaySystem()->getEnum($context->getSiteId()),
				'SETTINGS' => [
					'ALLOW_UNKNOWN' => 'Y', // preserve deactivated services
				],
			],
		];
	}

	private function getChildrenFieldGroups(array $configurationChildren)
	{
		$groups = [];

		foreach ($configurationChildren as $placement => $campaignProvider)
		{
			if ($campaignProvider instanceof TradingService\MarketplaceDbs\Provider)
			{
				$groups[$placement] = $campaignProvider->getOptions()->getPaySystemOptions()->getFields();
			}
			else if ($campaignProvider instanceof TradingService\Marketplace\Provider)
			{
				$groups[$placement] = $this->getFbsFields($campaignProvider);
			}
		}
		
		return array_reverse($groups);
	}

	private function getFbsFields(TradingService\Marketplace\Provider $campaignProvider)
	{
		$paySystem = $campaignProvider->getPaySystem();
		$default = $paySystem::CASHBOX_CHECK_DISABLED;
		$values = $paySystem->getCashboxCheckEnum();

		uasort($values, static function($optionA, $optionB) use ($default) {
			$sortA = $optionA['ID'] === $default ? 0 : 1;
			$sortB = $optionB['ID'] === $default ? 0 : 1;

			if ($sortA === $sortB) { return 0; }

			return ($sortA < $sortB ? -1 : 1);
		});

		return [
			'TYPE' => [
				'TYPE' => 'enumeration',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('TYPE'),
				'VALUES' => $paySystem->getTypeEnum(),
			],
			'CASHBOX_CHECK_FBS' => [
				'TYPE' => 'enumeration',
				'NAME' => self::getMessage('CASHBOX_CHECK_FBS'),
				'HELP_MESSAGE' => self::getMessage('CASHBOX_CHECK_FBS_HELP'),
				'VALUES' => $values,
				'HIDDEN' => !Main\Loader::includeModule('sale') || !class_exists(Sale\Cashbox\Cashbox::class) ? 'Y' : 'N',
				'SETTINGS' => [
					'DEFAULT_VALUE' => $default,
					'ALLOW_NO_VALUE' => 'N',
				],
			],
		];
	}

	private function mergeChildrenGroups(array $fields, array $childrenGroups)
	{
		if (empty($childrenGroups)) { return $fields; }
		if (count($childrenGroups) === 1) { return array_merge($fields, reset($childrenGroups)); }

		$usage = $this->childrenFieldsUsage($childrenGroups);
		$groupsCount = count($childrenGroups);

		foreach ($childrenGroups as $groupFields)
		{
			foreach ($groupFields as $name => $field)
			{
				if (isset($fields[$name])) { continue; }

				$fieldUsage = $usage[$name];

				if (count($fieldUsage) < $groupsCount)
				{
					$placementDepend = [
						'PLACEMENT' => [
							'RULE' => DependField::RULE_ANY,
							'VALUE' => array_keys($fieldUsage),
						],
					];

					if (isset($field['DEPEND']))
					{
						$field['DEPEND'] = $placementDepend + [ $field['DEPEND'] ];
					}
					else
					{
						$field['DEPEND'] = $placementDepend;
					}
				}

				$fields[$name] = $field;
			}
		}

		return $fields;
	}

	private function childrenFieldsUsage(array $childrenGroups)
	{
		$usage = [];

		foreach ($childrenGroups as $placement => $fields)
		{
			foreach ($fields as $name => $field)
			{
				if (!isset($usage[$name])) { $usage[$name] = []; }

				$usage[$name][$placement] = true;
			}
		}

		return $usage;
	}
}
