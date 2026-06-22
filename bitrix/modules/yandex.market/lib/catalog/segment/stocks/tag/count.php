<?php
namespace Yandex\Market\Catalog\Segment\Stocks\Tag;

use Yandex\Market\Error;
use Yandex\Market\Export\Entity;
use Yandex\Market\Export\Xml;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\UserField;

class Count extends Xml\Tag\Count
{
	use Concerns\HasMessage;

	public function getSettingsDescription(array $context = [])
	{
		return [
			'PACK_RATIO' => [
				'TYPE' => 'param',
				'TITLE' => self::getMessage('SETTINGS_PACK_RATIO'),
				'DESCRIPTION' => self::getMessage('SETTINGS_PACK_RATIO_HELP'),
				'GROUP' => 'PACK_RATIO',
			],
			'USE_RESERVE' => [
				'TYPE' => 'boolean',
				'TITLE' => self::getMessage('SETTINGS_USE_RESERVE'),
				'DESCRIPTION' => self::getMessage('SETTINGS_USE_RESERVE_HELP'),
				'DEFAULT_VALUE' => UserField\BooleanType::VALUE_Y,
				'GROUP' => 'USE_RESERVE',
			],
		];
	}

	public function extendTagDescription($tagDescription, array $context)
	{
		if (!isset($tagDescription['VALUE']['TYPE'], $tagDescription['VALUE']['FIELD'], $context['CAMPAIGN_ID']))
		{
			return $tagDescription;
		}

		$useReserve = (isset($tagDescription['SETTINGS']['USE_RESERVE']) && (string)$tagDescription['SETTINGS']['USE_RESERVE'] === UserField\BooleanType::VALUE_Y);
		$countField = $useReserve ? 'AVAILABLE' : 'COUNT';

		$tagDescription['SETTINGS']['RESERVE_CONTEXT'] = [
			'TYPE' => Entity\Manager::TYPE_TRADING_RESERVE,
			'FIELD' => "{$context['CAMPAIGN_ID']}.{$tagDescription['VALUE']['TYPE']}.{$tagDescription['VALUE']['FIELD']}.CONTEXT",
		];

		$tagDescription['VALUE'] = [
			'TYPE' => Entity\Manager::TYPE_TRADING_RESERVE,
			'FIELD' => "{$context['CAMPAIGN_ID']}.{$tagDescription['VALUE']['TYPE']}.{$tagDescription['VALUE']['FIELD']}.{$countField}",
		];

		return $tagDescription;
	}

	public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
	{
		$sanitized = parent::sanitize($value, $context, $tagValue, $siblingsValues);

		if ($sanitized === null || $sanitized instanceof Error\Base) { return $sanitized; }

		$export = [
			'value' => $sanitized,
		];

		if (!empty($tagValue['SETTINGS']['RESERVE_CONTEXT']))
		{
			$export['context'] = $tagValue['SETTINGS']['RESERVE_CONTEXT'];
		}

		if (!empty($tagValue['SETTINGS']['PACK_RATIO']) && is_scalar($tagValue['SETTINGS']['PACK_RATIO']))
		{
			if (!isset($export['context']['item'])) { $export['context']['item'] = []; }

			$export['context']['item']['RATIO'] = $tagValue['SETTINGS']['PACK_RATIO'];
		}

		return $export;
	}

	public function insertNode($value, Xml\Data\ExportElement $parent)
	{
		$node = $parent->addChild($this->name, $value['value']);

		if (!empty($value['context']))
		{
			$parent->addChild('context', $value['context']);
		}

		return $node;
	}
}