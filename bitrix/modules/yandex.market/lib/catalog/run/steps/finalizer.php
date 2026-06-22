<?php
namespace Yandex\Market\Catalog\Run\Steps;

use Bitrix\Main\Application;
use Yandex\Market\Data;
use Yandex\Market\Result;
use Yandex\Market\Catalog;
use Yandex\Market\State;
use Yandex\Market\Trading;
use Yandex\Market\Utils;

class Finalizer extends Data\Run\StepSkeleton
{
	protected $processor;

	public function __construct(Catalog\Run\Processor $processor)
	{
		$this->processor = $processor;
	}

	public function getName()
	{
		return 'finalizer';
	}

	public function run($action, $offset = null)
	{
		if (
			$action === Catalog\Run\Processor::ACTION_CHANGE
			|| Utils::isCli()
			|| !Application::getInstance()->getContext()->getRequest()->isAdminSection()
		)
		{
			return new Result\Step();
		}

		$result = new Result\Step();
		$offsetObject = new Data\Run\Offset($offset);

		(new Data\Run\Waterfall())
			->add(new Transport\HttpCatcher($this->getName(), $this->processor->makeLogger()))
			->add([$this, 'markSubmitted'])
			->add([$this, 'tweakTrading'])
			->run($offsetObject);

		if ($offsetObject->interrupted())
		{
			$result->setOffset((string)$offsetObject);
			$result->setTotal(1);
		}

		return $result;
	}

	public function markSubmitted(Data\Run\Waterfall $waterfall)
	{
		State::set("catalog_submitted_{$this->processor->getSetup()->getId()}", 'Y');

		$waterfall->next();
	}

	public function tweakTrading(Data\Run\Waterfall $waterfall)
	{
		/** @var Trading\Setup\Model $trading */
		foreach ($this->processor->getSetup()->getBusiness()->getTradingCollection() as $trading)
		{
			if (!$trading->isActive()) { continue; }

			$trading->wakeupService()->getInstaller()->onCatalogSubmitted();
		}

		$waterfall->next();
	}
}

