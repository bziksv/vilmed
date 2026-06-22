<?php
namespace Yandex\Market\Component\Molecules;

use Bitrix\Main;
use Yandex\Market\Export\Entity;
use Yandex\Market\Export\ParamValue;
use Yandex\Market\Utils;

class ProductParam
{
	private $paramFields;

	public function __construct(array $paramFields)
	{
		$this->paramFields = $paramFields;
	}

	public function parse(array $data)
	{
		foreach ($this->fieldValues($data) as $name => $value)
		{
			if (!is_array($value)) { continue; }

			foreach ($value as &$param)
			{
				if (!empty($param['SETTINGS']))
				{
					$param['SETTINGS'] = $this->parseSettings($param['SETTINGS']);
				}

				if (isset($param['PARAM_VALUE']))
				{
					foreach ($param['PARAM_VALUE'] as &$paramValue)
					{
						if (!isset($paramValue['SOURCE_TYPE'], $paramValue['SOURCE_FIELD'])) { continue; }

						$paramValue['SOURCE_FIELD'] = $this->parseField($paramValue['SOURCE_TYPE'], $paramValue['SOURCE_FIELD']);
					}
					unset($paramValue);
				}
			}
			unset($param);

			Utils\Field::setChainValue($data, $name, $value);
		}

		return $data;
	}

	public function compile(array $data)
	{
		foreach ($this->fieldValues($data) as $name => $value)
		{
			if (!is_array($value)) { continue; }

			foreach ($value as &$param)
			{
				if (!empty($param['SETTINGS']))
				{
					$param['SETTINGS'] = $this->compileSettings($param['SETTINGS']);
				}

				if (isset($param['PARAM_VALUE']))
				{
					foreach ($param['PARAM_VALUE'] as &$paramValue)
					{
						if (!isset($paramValue['SOURCE_TYPE'], $paramValue['SOURCE_FIELD'])) { continue; }

						$paramValue['SOURCE_FIELD'] = $this->compileField($paramValue['SOURCE_TYPE'], $paramValue['SOURCE_FIELD']);
					}
					unset($paramValue);
				}
			}
			unset($param);

			Utils\Field::setChainValue($data, $name, $value);
		}

		return $data;
	}

	private function fieldValues(array $data)
	{
		$result = [];

		foreach ($this->paramFields as $name)
		{
			$parentNames = explode('.', $name);
			$rootName = array_shift($parentNames);
			$childName = array_pop($parentNames);
			$level = isset($data[$rootName]) && is_array($data[$rootName]) ? $data[$rootName] : [];

			while ($parentName = array_shift($parentNames))
			{
				$newLevel = [];

				foreach ($level as $key => $values)
				{
					$levelChildren = [];

					if (!empty($values[$parentName]) && is_array($values[$parentName]))
					{
						foreach ($values[$parentName] as $index => $value)
						{
							$levelChildren["{$key}.{$parentName}.{$index}"] = $value;
						}
					}

					$newLevel += !empty($levelChildren) ? $levelChildren : [ "{$key}.0.{$parentName}" => [] ];
				}

				$level = $newLevel;
			}

			foreach ($level as $prefix => $values)
			{
				$result["{$rootName}.{$prefix}.{$childName}"] = $values[$childName];
			}
		}

		return $result;
	}

	private function compileSettings($settings)
	{
		foreach ($settings as &$setting)
		{
			if (!isset($setting['TYPE'], $setting['FIELD'])) { continue; }

			$setting['FIELD'] = $this->compileField($setting['TYPE'], $setting['FIELD']);
		}
		unset($setting);

		return $settings;
	}

	private function parseSettings($settings)
	{
		foreach ($settings as &$setting)
		{
			if (!isset($setting['TYPE'], $setting['FIELD'])) { continue; }

			$setting['FIELD'] = $this->parseField($setting['TYPE'], $setting['FIELD']);
		}
		unset($setting);

		return $settings;
	}

	private function compileField($type, $field)
	{
		try
		{
			if (!is_array($field)) { return $field; }

			$source = Entity\Manager::getSource($type);

			if (!($source instanceof Entity\Reference\HasFieldCompilation))
			{
				return $field;
			}

			return $source->compileField($field);
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);

			return $field;
		}
	}

	private function parseField($type, $field)
	{
		try
		{
			if (!is_string($field) || $type === ParamValue\Table::SOURCE_TYPE_RECOMMENDATION)
			{
				return $field;
			}

			$source = Entity\Manager::getSource($type);

			if (!($source instanceof Entity\Reference\HasFieldCompilation))
			{
				return $field;
			}

			return $source->parseField($field);
		}
		catch (Main\ObjectNotFoundException $exception)
		{
			trigger_error($exception->getMessage(), E_USER_WARNING);

			return $field;
		}
	}
}