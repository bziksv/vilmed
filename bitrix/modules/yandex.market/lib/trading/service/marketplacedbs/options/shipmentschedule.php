<?php

namespace Yandex\Market\Trading\Service\MarketplaceDbs\Options;

use Yandex\Market;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Trading\Service as TradingService;

class ShipmentSchedule extends TradingService\Reference\Options\Fieldset
{
	use Market\Reference\Concerns\HasMessage;

	public function makeCommonDeliveryOption()
	{
		$option = new DeliveryOption($this->provider);
		$option->setValues([
			'SHIPMENT_DATE_BEHAVIOR' => $this->getSchedule()->hasValid()
				? DeliveryOption::SHIPMENT_DATE_BEHAVIOR_ORDER_DAY
				: DeliveryOption::SHIPMENT_DATE_BEHAVIOR_DELIVERY_DAY,
		]);

		return $option;
	}

	public function getSchedule()
	{
		return $this->getFieldsetCollection('SCHEDULE', ScheduleOptions::class);
	}

	public function getAssemblyDelay()
	{
		/** @var AssemblyDelayOption $assemblyDelay */
		$assemblyDelay = $this->getFieldset('ASSEMBLY_DELAY', AssemblyDelayOption::class);
		$assemblyDelay->disableUseDefaults();

		return $assemblyDelay;
	}

	public function getHoliday()
	{
		return $this->getFieldset('HOLIDAY', HolidayOption::class);
	}

	protected function applyValues()
	{
		$this->applyShipmentDelayToAssembly();
		$this->getAssemblyDelay()->applyTimeValue($this->getSchedule());
	}

	protected function applyShipmentDelayToAssembly()
	{
		$shipmentDelay = (string)$this->getValue('SHIPMENT_DELAY');
		$assemblyDelay = $this->getAssemblyDelay();

		if ($shipmentDelay === '' || !$assemblyDelay->isEmpty()) { return; }

		$assemblyDelay->setValues([
			'TIME' => $shipmentDelay,
		]);
		unset($this->values['SHIPMENT_DELAY']);
	}

	public function getFieldDescription()
	{
		return parent::getFieldDescription() + [
			'SETTINGS' => [
				'SUMMARY' => '#SCHEDULE# (#HOLIDAY.CALENDAR#)',
				'PLACEHOLDER' => self::getMessage('PLACEHOLDER'),
				'LAYOUT' => 'summary',
				'MODAL_WIDTH' => 600,
				'MODAL_HEIGHT' => 450,
				'VALIGN_PUSH' => 'pill',
			],
		];
	}

	public function getFields()
	{
		return
			$this->getSelfFields()
			+ $this->getHolidayFields();
	}

	protected function getSelfFields()
	{
		return [
			'SCHEDULE' => $this->getSchedule()->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'NAME' => self::getMessage('SCHEDULE'),
				'GROUP' => self::getMessage('SCHEDULE_GROUP'),
				'HELP_MESSAGE' => self::getMessage('SCHEDULE_HELP'),
			],
			'ASSEMBLY_DELAY' => $this->getAssemblyDelay()->getFieldDescription() + [
				'TYPE' => 'fieldset',
				'NAME' => self::getMessage('ASSEMBLY_DELAY'),
				'HELP_MESSAGE' => self::getMessage('ASSEMBLY_DELAY_HELP'),
			],
		];
	}

	protected function getHolidayFields()
	{
		$result = [];
		$defaults = [
			'GROUP' => self::getMessage('HOLIDAY_GROUP'),
		];

		foreach ($this->getHoliday()->getFields() as $name => $field)
		{
			$key = sprintf('HOLIDAY[%s]', $name);
			$overrides = $this->getHolidayFieldOverrides($name);

			if (isset($field['DEPEND']))
			{
				$newDepend = [];

				foreach ($field['DEPEND'] as $dependName => $rule)
				{
					$newName = sprintf('[HOLIDAY][%s]', $dependName);
					$newDepend[$newName] = $rule;
				}

				$field['DEPEND'] = $newDepend;
			}

			$result[$key] = $overrides + $field + $defaults;
		}

		return $result;
	}

	protected function getHolidayFieldOverrides($name)
	{
		$langKeys = [
			'NAME' => '',
			'HELP_MESSAGE' => 'HELP',
		];
		$result = [];

		foreach ($langKeys as $resultKey => $type)
		{
			$suffix = ($type !== '' ? '_' . $type : '');
			$message = (string)static::getMessage('HOLIDAY_' . $name . $suffix, null, '');

			if ($message === '') { continue; }

			$result[$resultKey] = $message;
		}

		return $result;
	}

	protected function knownFieldsetCollections()
	{
		return [
			$this->getSchedule(),
		];
	}

	protected function knownFieldsets()
	{
		return [
			$this->getHoliday(),
			$this->getAssemblyDelay(),
		];
	}
}