<?php
namespace Yandex\Market\Catalog\Run;

use Bitrix\Main;
use Yandex\Market\Catalog;
use Yandex\Market\Data;
use Yandex\Market\Glossary;
use Yandex\Market\Logger;

class Processor implements Data\Run\Processor
{
    protected $setup;
    protected $parameters;
    /** @var Data\Run\Step[] */
    protected $steps;
    protected $limitResource;

    public function __construct(Catalog\Setup\Model $setup, array $parameters = [])
    {
        $this->setup = $setup;
        $this->parameters = $parameters;
        $this->steps = [
            new Steps\Collector($this),
            new Steps\SkuFiller($this),
            new Steps\Downloader($this),
            new Steps\Submitter($this),
            new Steps\Finalizer($this),
        ];
        $this->limitResource = new Data\Run\ResourceLimit([
            'startTime' => $this->getParameter('startTime'),
            'timeLimit' => $this->getParameter('timeLimit'),
        ]);
    }

    public function steps()
    {
        return $this->steps;
    }

    public function clear()
    {
        foreach ($this->steps as $step)
        {
            if (!($step instanceof Data\Run\StepClearable)) { continue; }

            $step->clear();
        }
    }

    public function run($action = self::ACTION_FULL)
    {
        $this->loadModules();

        $interruptStep = $this->getParameter('step');
        $interruptOffset = $this->getParameter('stepOffset');

        if ($interruptStep === null && $action === static::ACTION_FULL) // is start full export
        {
            $this->clear();
        }

        return (new Data\Run\Stepper($this->steps))
            ->process($action, $interruptStep, $interruptOffset);
    }

    protected function loadModules()
    {
        if (!Main\Loader::includeModule('iblock'))
        {
            throw new Main\SystemException('cant load iblock module');
        }
    }

    public function getParameter($name, $default = null)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : $default;
    }

    public function getSetup()
    {
        return $this->setup;
    }

    public function isExpired()
    {
        $this->limitResource->tick();

        return $this->limitResource->isExpired();
    }

	public function makeLogger()
	{
		$logger = new Logger\Trading\Logger(Glossary::SERVICE_CATALOG, $this->setup->getId());
		$logger->setLevel($this->setup->getLogLevel());
		$logger->setContext('BUSINESS_ID', $this->setup->getBusinessId());

		return $logger;
	}
}