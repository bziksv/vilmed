<?php

namespace Yandex\Market\Component\Concerns;

use Yandex\Market;
use Bitrix\Main;

trait HasUiService
{
	protected $uiService;

	private function uiServiceExtendComponentParameters($params)
	{
		global $APPLICATION;

		$params['SERVICE'] = trim($params['SERVICE']);
		$params['SERVICE_BEHAVIOR'] = trim($params['SERVICE_BEHAVIOR']);
		$params['USE_SERVICE'] = isset($params['USE_SERVICE']) && $params['USE_SERVICE'] === 'Y';

		if ($params['SERVICE'] !== '')
		{
			$params['BASE_URL'] = $APPLICATION->GetCurPageParam(
				http_build_query([ 'service' => $params['SERVICE'] ]),
				[ 'service' ]
			);

			$params['GRID_ID'] .= '_' . Market\Data\TextString::toUpper($params['SERVICE']);
		}

		return $params;
	}

	public function getUiService()
	{
		if ($this->uiService === null)
		{
			$this->uiService = $this->loadUiService();
		}

		return $this->uiService;
	}

	protected function loadUiService()
	{
		$serviceName = (string)$this->getUiServiceParameterValue();

		if ($serviceName === '')
		{
			$result = Market\Ui\Service\Manager::getCommonInstance();
		}
		else
		{
			$result = Market\Ui\Service\Manager::getInstance($serviceName);
		}

		return $result;
	}

	protected function getUiServiceParameterValue()
	{
		if (!($this instanceof Market\Component\Base\AbstractProvider))
		{
			throw new Main\NotImplementedException('not implemented HasUiService::getUiServiceParameter');
		}

		return $this->getComponentParam('SERVICE');
	}

	protected function getUiServiceFilter($fieldName = 'EXPORT_SERVICE', $behavior = 'EXPORT')
	{
		$uiService = $this->getUiService();
		$exportServices = $behavior === 'TRADING'
			? $uiService->getTradingServices()
			: $uiService->getExportServices();

		if (!$uiService->isInverted())
		{
			$result = [
				'=' . $fieldName => $exportServices,
			];
		}
		else if (!empty($exportServices))
		{
			$result = [
				'!=' . $fieldName => $exportServices,
			];
		}
		else
		{
			$result = null;
		}

		return $result;
	}
}