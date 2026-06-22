<?php
namespace Yandex\Market\Trading\Service\Reference;

use Yandex\Market\Api;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Logger\Level;
use Yandex\Market\Trading\Entity as TradingEntity;

abstract class Options extends Options\Skeleton
	implements Api\Reference\HasAuth
{
	use Concerns\HasMessage;
	use Concerns\HasOnce;

	abstract public function getTabs();

	public function getBusinessId()
	{
		return $this->provider->getContext()->getBusiness()->getId();
	}

	public function getBusinessOverlay()
	{
		return $this->provider->getContext()->getBusiness()->getOverlay();
	}

	public function getApiAuth()
	{
		return $this->provider->getContext()->getBusiness()->getOptions()->getApiAuth();
	}

	public function getLogLevel()
	{
		return $this->getValue('LOG_LEVEL', Level::INFO);
	}

	public function getProductSkuMap()
	{
		return $this->provider->getContext()->getBusiness()->getOptions()->getSkuMap()->getFieldMap();
	}

	public function getProductSkuPrefix()
	{
		return $this->provider->getContext()->getBusiness()->getOptions()->getSkuMap()->getPrefix();
	}

	public function getSetupId()
	{
		return $this->provider->getContext()->getSetupId();
	}

	public function getSiteId()
	{
		return $this->provider->getContext()->getSiteId();
	}

	public function getPlatformId()
	{
		return $this->provider->getContext()->getBusiness()->getPlatformId();
	}

	public function getEnvironmentFieldActions(TradingEntity\Reference\Environment $environment)
	{
		return [];
	}
	
	protected function getLogFields()
	{
		return [
			'LOG_LEVEL' => [
				'TYPE' => 'enumeration',
				'TAB' => 'ORDER',
				'NAME' => self::getMessage('LOG_LEVEL'),
				'DESCRIPTION' => self::getMessage('LOG_LEVEL_DESCRIPTION'),
				'VALUES' => array_map(static function($level) {
					return [
						'ID' => $level,
						'VALUE' => Level::getTitle($level),
					];
				}, [
					Level::EMERGENCY,
					Level::ERROR,
					Level::WARNING,
					Level::INFO,
					Level::DEBUG,
				]),
				'SETTINGS' => [
					'DEFAULT_VALUE' => Level::INFO,
					'ALLOW_NO_VALUE' => 'N',
				],
				'SORT' => 1120,
			],
		];
	}
}