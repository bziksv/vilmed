<?php
namespace Yandex\Market\Trading\Service\MarketplaceDbs;

use Yandex\Market\State;
use Yandex\Market\Trading\Service\Marketplace;

class Installer extends Marketplace\Installer
{
	public function install()
	{
		parent::install();
		$this->tweakSelfTestOutOfStock();
	}

	protected function tweakSelfTestOutOfStock()
	{
		$name = 'self_test_out_of_stock_' . $this->provider->getContext()->getSetupId();
		$options = $this->provider->getOptions()->getSelfTestOption();

		if ($options->isOutOfStock())
		{
			State::set($name, 0);
		}
		else
		{
			State::remove($name);
		}
	}
}