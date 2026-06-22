<?php
namespace Yandex\Market\Component\Setup;

use Yandex\Market\Export\Xml\Format as XmlFormat;

class Repository
{
	protected $modelClass;

	public function __construct($modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function makeServiceDependFields($fields)
	{
		$services = isset($fields['EXPORT_SERVICE']['VALUES']) ? array_column($fields['EXPORT_SERVICE']['VALUES'], 'ID') : [];
		$supportedMap = $this->getServiceSupportedFieldsMap($services);

		foreach ($supportedMap as $fieldName => $supportedServices)
		{
			if (!isset($fields[$fieldName])) { continue; }

			$excludeServices = array_diff($services, $supportedServices);

			if (empty($supportedServices))
			{
				unset($fields[$fieldName]);
			}
			else if (!empty($excludeServices))
			{
				if (!isset($fields[$fieldName]['DEPEND']))
				{
					$fields[$fieldName]['DEPEND'] = [];
				}

				$fields[$fieldName]['DEPEND']['EXPORT_SERVICE'] = [
					'RULE' => 'EXCLUDE',
					'VALUE' => array_values($excludeServices),
				];
			}
		}

		return $fields;
	}

	protected function getServiceSupportedFieldsMap($services)
	{
		$configurableKeys = [
			'SHOP_DATA',
			'ENABLE_CPA',
			'ENABLE_AUTO_DISCOUNTS',
		];
		$configurableFields = array_fill_keys($configurableKeys, []);

		foreach ($services as $serviceName)
		{
			$types = XmlFormat\Manager::getTypeList($serviceName);
			$typeName = reset($types);
			$format = XmlFormat\Manager::getEntity($serviceName, $typeName);

			$formatFields = $format->getSupportedFields();
			$formatFieldsMap = array_flip($formatFields);

			foreach ($configurableFields as $fieldName => &$supported)
			{
				if (!isset($formatFieldsMap[$fieldName])) { continue; }

				$supported[] = $serviceName;
			}
			unset($supported);
		}

		return $configurableFields;
	}
}