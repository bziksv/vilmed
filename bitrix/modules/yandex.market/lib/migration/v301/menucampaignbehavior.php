<?php
namespace Yandex\Market\Migration\V301;

use Yandex\Market\Trading;
use Yandex\Market\Ui\Trading\MenuCompiler;
use Yandex\Market\Ui\Trading\Menu;

class MenuCampaignBehavior
{
	public function apply()
	{
		$readyBusinesses = array_column(Menu::stored(), 'ID', 'ID');
		$menuCompiler = new MenuCompiler();

		foreach (Trading\Business\Model::loadList() as $business)
		{
			$businessId = $business->getId();

			if (!isset($readyBusinesses[$businessId])) { continue; }

			if ($business->getTradingCollection()->getCampaignItems()->filterActive()->count() > 0)
			{
				$menuCompiler->injectCampaignBehavior($business->getId());
			}
		}

		$menuCompiler->save();
	}
}