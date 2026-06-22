<?php
namespace Yandex\Market\Migration\V301;

use Yandex\Market\Trading;
use Yandex\Market\Ui\Trading\MenuCompiler;

class DropEmptyBusiness
{
	public function apply()
	{
		$menuCompiler = new MenuCompiler();

		foreach (Trading\Business\Model::loadList() as $business)
		{
			if (
				$business->getCatalog() === null
				&& $business->getTradingCollection()->count() === 0
				&& $business->getSalesBoostCollection()->count() === 0
			)
			{
				$menuCompiler->uninstallBusiness($business->getId());
				$business->delete();
			}
		}

		$menuCompiler->save();
	}
}