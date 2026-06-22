<?php
namespace Yandex\Market\Trading\Entity\Sale;

use Yandex\Market;
use Yandex\Market\Trading\Business;
use Yandex\Market\Reference\Concerns;
use Bitrix\Main;

class Platform extends Market\Trading\Entity\Reference\Platform
{
	use Concerns\HasMessage;

	const PLATFORM_PREFIX = 'YM_';

	protected $systemPlatform;

	public function getId()
	{
		return $this->getSystemPlatform()->getId();
	}

	public function isInstalled()
	{
		return $this->getSystemPlatform()->isInstalled();
	}

	public function install(Business\Model $business)
	{
		$data = $this->getBusinessData($business);
		$systemPlatform = $this->getSystemPlatform();

		if ($systemPlatform->isInstalled())
		{
			$saveResult = $systemPlatform->updateExtended($data);

			Market\Result\Facade::handleException($saveResult);

			return $systemPlatform->getId();
		}

		$saveResult = $systemPlatform->installExtended($data);

		Market\Result\Facade::handleException($saveResult);

		return $saveResult->getId();
	}

	public function migrate($platformId, Business\Model $business)
	{
		return $this->getSystemPlatform()->migrate($platformId, $this->getBusinessData($business));
	}

	protected function getBusinessData(Business\Model $business)
	{
		return [
			'NAME' => self::getMessage(
				'NAME',
				[ '#BUSINESS#' => $business->getName() ],
				$business->getName()
			),
		];
	}

	public function uninstall()
	{
		$callResult = $this->getSystemPlatform()->uninstall();

		return $this->makeSystemCallResult('uninstall', $callResult);
	}

	public function isActive()
	{
		return $this->getSystemPlatform()->isActive();
	}

	public function activate()
	{
		$callResult = $this->getSystemPlatform()->setActive();

		return $this->makeSystemCallResult('activate', $callResult);
	}

	public function deactivate()
	{
		$callResult = $this->getSystemPlatform()->unsetActive();

		return $this->makeSystemCallResult('deactivate', $callResult);
	}

	public function getSalePlatform()
	{
		return $this->getSystemPlatform();
	}

	protected function getSystemPlatform()
	{
		if ($this->systemPlatform === null)
		{
			$this->systemPlatform = $this->loadSystemPlatform();
		}

		return $this->systemPlatform;
	}

	/**
	 * @return Internals\Platform
	 * @noinspection PhpReturnDocTypeMismatchInspection
	 * @noinspection PhpIncompatibleReturnTypeInspection
	 */
	protected function loadSystemPlatform()
	{
		if (!method_exists(Internals\Platform::class, 'getInstanceByCode'))
		{
			/** @noinspection PhpDeprecationInspection */
			return Internals\Platform::getInstance($this->getSystemPlatformCode());
		}

		return Internals\Platform::getInstanceByCode($this->getSystemPlatformCode());
	}

	protected function makeSystemCallResult($method, $callResult)
	{
		if ($callResult !== false) { return new Main\Result(); }

		$result = new Main\Result();
		$result->addError(new Main\Error(
			self::getMessage('METHOD_ERROR_' . mb_strtoupper($method))
		));

		return $result;
	}

	protected function getSystemPlatformCode()
	{
		$code = static::PLATFORM_PREFIX . $this->businessId;
		$lengthLimit = 20;

		if (mb_strlen($code) >= $lengthLimit)
		{
			return mb_substr($code, 0, $lengthLimit);
		}

		return $code;
	}
}