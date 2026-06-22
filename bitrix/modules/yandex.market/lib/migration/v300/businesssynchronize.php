<?php
namespace Yandex\Market\Migration\V300;

use Bitrix\Main;
use Yandex\Market\Reference\Agent;
use Yandex\Market\Trading;

class BusinessSynchronize extends Agent\Base
{
	public static function run()
	{
		foreach (Trading\Business\Model::loadList() as $business)
		{
			try
			{
				$business->getCampaignRepository()->synchronize(true);
			}
			catch (Main\SystemException $exception)
			{
				$business->createLogger()->error($exception);
			}
		}

		return false;
	}
}