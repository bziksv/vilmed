<?php

namespace Yandex\Market\Trading\Service\Reference;

use Bitrix\Main;
use Yandex\Market;
use Yandex\Market\Glossary;
use Yandex\Market\Logger\Trading\Logger;
use Yandex\Market\Trading\Setup\TradingContext;
use Yandex\Market\Trading\Setup\CampaignContext;

abstract class Provider
{
	protected $context;
	protected $router;
	protected $options;
	protected $installer;
	protected $info;
	protected $status;
	protected $printer;
	protected $modelFactory;
	protected $requestFactory;
	protected $dictionary;
	protected $feature;
	protected $container;

	public function wakeup(TradingContext $context, array $optionValues)
	{
		$this->context = $context;
		$this->getOptions()->setValues($optionValues);
	}

	public function getCode()
	{
		$serviceCode = $this->getServiceCode();
		$behaviorCode = $this->getBehaviorCode();

		if ($behaviorCode === Market\Trading\Service\Manager::BEHAVIOR_DEFAULT)
		{
			return $serviceCode;
		}

		return "{$serviceCode}:{$behaviorCode}";
	}

	public function getUniqueKey()
	{
		return $this->getCode();
	}

	abstract public function getServiceCode();

	public function getBehaviorCode()
	{
		return Market\Trading\Service\Manager::BEHAVIOR_DEFAULT;
	}

	/** @return TradingContext */
	public function getContext()
	{
		if ($this->context === null)
		{
			throw new Main\SystemException('use $trading->wakeupService() before get context');
		}

		return $this->context;
	}

	public function getRouter()
	{
		if ($this->router === null)
		{
			$this->router = $this->createRouter();
		}

		return $this->router;
	}

	/**
	 * @return Router
	 */
	abstract protected function createRouter();

	public function getInstaller()
	{
		if ($this->installer === null)
		{
			$this->installer = $this->createInstaller();
		}

		return $this->installer;
	}

	/**
	 * @return Installer
	 */
	abstract protected function createInstaller();

	public function getOptions()
	{
		if ($this->options === null)
		{
			$this->options = $this->createOptions();
		}

		return $this->options;
	}

	/**
	 * @return Options
	 */
	abstract protected function createOptions();

	public function getInfo()
	{
		if ($this->info === null)
		{
			$this->info = $this->createInfo();
		}

		return $this->info;
	}

	/**
	 * @return Info
	 */
	abstract protected function createInfo();

	public function getStatus()
	{
		if ($this->status === null)
		{
			$this->status = $this->createStatus();
		}

		return $this->status;
	}

	/**
	 * @return Status
	 */
	abstract protected function createStatus();

	public function getLogger()
	{
		return $this->createLogger();
	}

	/** @return Market\Psr\Log\LoggerInterface */
	protected function createLogger()
	{
		if ($this->context === null) { return new Logger(Glossary::SERVICE_TRADING); }

		$context = $this->getContext();

		$logger = new Logger(Glossary::SERVICE_TRADING, $this->context->getSetupId());
		$logger->setLevel($this->getOptions()->getLogLevel());
		$logger->setContext('BUSINESS_ID', (int)$this->getContext()->getBusiness()->getId());
		$logger->setContext(
			'CAMPAIGN_ID',
			$context instanceof CampaignContext ? $context->getCampaign()->getId() : 0
		);

		return $logger;
	}

	public function getPrinter()
	{
		if ($this->printer === null)
		{
			$this->printer = $this->createPrinter();
		}

		return $this->printer;
	}

	/**
	 * @return Printer
	 */
	protected function createPrinter()
	{
		throw new Market\Exceptions\NotImplementedMethod(static::class, 'createPrinter');
	}

	public function getRequestFactory()
	{
		if ($this->requestFactory === null)
		{
			$this->requestFactory = $this->createRequestFactory();
		}

		return $this->requestFactory;
	}

	/**
	 * @return RequestFactory
	 */
	protected function createRequestFactory()
	{
		return new RequestFactory($this);
	}

	public function getModelFactory()
	{
		if ($this->modelFactory === null)
		{
			$this->modelFactory = $this->createModelFactory();
		}

		return $this->modelFactory;
	}

	/**
	 * @return ModelFactory
	 */
	protected function createModelFactory()
	{
		return new ModelFactory($this);
	}

	public function getDictionary()
	{
		if ($this->dictionary === null)
		{
			$this->dictionary = $this->createDictionary();
		}

		return $this->dictionary;
	}

	/**
	 * @return Dictionary
	 */
	protected function createDictionary()
	{
		return new Dictionary($this);
	}

	public function getFeature()
	{
		if ($this->feature === null)
		{
			$this->feature = $this->createFeature();
		}

		return $this->feature;
	}

	protected function createFeature()
	{
		return new Feature($this);
	}

	public function getContainer()
	{
		if ($this->container === null)
		{
			$this->container = $this->createContainer();
		}

		return $this->container;
	}

	protected function createContainer()
	{
		return new Container($this);
	}
}