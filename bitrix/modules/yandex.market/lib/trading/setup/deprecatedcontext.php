<?php
namespace Yandex\Market\Trading\Setup;

use Bitrix\Main;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Entity\Reference\Environment;

class DeprecatedContext extends CampaignContext
{
	public function __construct(Environment $environment, $siteId, $setupId)
	{
		parent::__construct(new Business\Model(), new Campaign\Model(), $environment, $siteId, $setupId);
	}

	public function getBusiness()
	{
		throw new Main\NotSupportedException('business not supported in deprecated context');
	}
}