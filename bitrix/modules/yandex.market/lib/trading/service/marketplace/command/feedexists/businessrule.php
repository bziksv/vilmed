<?php
namespace Yandex\Market\Trading\Service\Marketplace\Command\FeedExists;

use Bitrix\Main;
use Yandex\Market\Trading;

class BusinessRule implements Rule
{
	protected $business;

	public function __construct(Trading\Business\Model $business)
	{
		$this->business = $business;
	}

	public function getFeeds()
	{
		$used = [];

		/** @var Trading\Setup\Model $trading */
		foreach ($this->business->getTradingCollection() as $trading)
		{
			if (!$trading->isActive()) { continue; }

			$options = $trading->wakeupService()->getOptions();

			if (!($options instanceof Trading\Service\Marketplace\Options)) { continue; }

			$feeds = $options->getSelfProductFeeds();

			if (empty($feeds)) { return []; }

			$used += array_flip($feeds);
		}

        $result = array_keys($used);
        Main\Type\Collection::normalizeArrayValuesByInt($result);

		return $result;
	}
}