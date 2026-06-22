<?php
namespace Yandex\Market\Trading\Service\MarketplaceBusiness\Options;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Campaign\Placement;
use Yandex\Market\Trading\Service as TradingService;
use Yandex\Market\Utils\UserField\DependField;

/** @property TradingService\MarketplaceBusiness\Provider $provider */
class DeliveryOption extends TradingService\Reference\Options\Fieldset
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
				'SUMMARY' => self::getMessage('SUMMARY', null, '#PLACEMENT#: #TYPE# &laquo;#ID#&raquo;, #DAYS# (#HOLIDAY.CALENDAR#)'),
				'LAYOUT' => 'summary',
				'MODAL_WIDTH' => 600,
				'MODAL_HEIGHT' => 450,
				'VALIGN_PUSH' => 'pill',
				'DEFAULT_VALUE' => $this->defaultValue(),
			],
		];
	}

	private function defaultValue()
	{
		$context = $this->provider->getContext();
		$delivery = $context->getEnvironment()->getDelivery();
		$deliveryId = (int)$delivery->getEmptyDeliveryId();

		if ($deliveryId === 0)
		{
			$deliveryEnum = $delivery->getEnum($context->getSiteId());

			if (empty($deliveryEnum)) { return []; }

			$firstDelivery = reset($deliveryEnum);
			$deliveryId = $firstDelivery['ID'];
		}

		return [
			[
				'PLACEMENT' => Placement::FBS,
				'ID' => $deliveryId,
			],
		];
	}

	public function getFields()
	{
		$fields = $this->getCommonFields();
		$fields = $this->injectDbsFields($fields);

		return $fields;
	}

	private function getCommonFields()
	{
		$context = $this->provider->getContext();

		return [
			'PLACEMENT' => [
				'TYPE' => 'enumeration',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('PLACEMENT'),
				'VALUES' => array_map(
					static function($placement) { return [ 'ID' => $placement, 'VALUE' => $placement ]; },
					array_keys($this->provider->getOptions()->getConfigurationChildren())
				),
			],
			'ID' => [
				'TYPE' => 'enumeration',
				'MANDATORY' => 'Y',
				'NAME' => self::getMessage('ID'),
				'VALUES' => $context->getEnvironment()->getDelivery()->getEnum($context->getSiteId()),
				'SETTINGS' => [
					'ALLOW_UNKNOWN' => 'Y', // preserve deactivated services
				],
			],
		];
	}

	private function injectDbsFields(array $fields)
	{
		$childrenOptions = $this->provider->getOptions()->getConfigurationChildren();

		if (!isset($childrenOptions[Placement::DBS])) { return $fields; }

		$dbsProvider = $childrenOptions[Placement::DBS];

		if (!($dbsProvider instanceof TradingService\MarketplaceDbs\Provider)) { return $fields; }

		$defaultDepend = [
			'PLACEMENT' => [
				'RULE' => DependField::RULE_ANY,
				'VALUE' => Placement::DBS,
			],
		];

		foreach ($dbsProvider->getOptions()->getDeliveryOptions()->getFields() as $key => $field)
		{
			if (isset($fields[$key]) || isset($field['DEPEND']['@YANDEX_MODE']))
			{
				continue;
			}

			$field['DEPEND'] = isset($field['DEPEND'])
				? $field['DEPEND'] + $defaultDepend
				: $defaultDepend;

			$fields[$key] = $field;
		}

		return $fields;
	}
}
