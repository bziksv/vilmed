<?php
namespace Yandex\Market\Api\Campaigns;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Trading\Service\Common\Options;

class Facade
{
	use Concerns\HasMessage;

	public static function businessId(Options $options, LoggerInterface $logger = null)
	{
		$campaign = static::campaign($options, $logger);

		return $campaign->getBusiness()->getId();
	}

	public static function campaign(Options $options, LoggerInterface $logger = null)
	{
		$campaign = static::campaigns($options, $logger)->getItemByCampaignId($options->getCampaignId());

		if ($campaign === null)
		{
			throw new Main\SystemException(self::getMessage('UNKNOWN_CAMPAIGN_FOR_TOKEN', [
				'#CAMPAIGN_ID#' => $options->getCampaignId(),
			]));
		}

		return $campaign;
	}

	public static function businessCampaigns(Options $options, LoggerInterface $logger = null)
	{
		$all = static::campaigns($options, $logger);
		$campaign = $all->getItemByCampaignId($options->getCampaignId());

		if ($campaign === null)
		{
			throw new Main\SystemException(self::getMessage('UNKNOWN_CAMPAIGN_FOR_TOKEN', [
				'#CAMPAIGN_ID#' => $options->getCampaignId(),
			]));
		}

		return $all->sameBusiness($campaign->getBusiness()->getId());
	}

	public static function campaigns($auth, LoggerInterface $logger = null)
	{
		$page = 1;
		$result = new Model\CampaignCollection();

		do
		{
			if ($page > 1000)
			{
				throw new Main\SystemException('infinite loop on fetch campaigns');
			}

			$request = new Request($auth, $logger);
			$request->setPage($page);

			$response = $request->execute();

			/** @var Model\Campaign $campaign */
			foreach ($response->getCampaigns() as $campaign)
			{
				$result->addItem($campaign);
				$campaign->setParentCollection($result);
			}

			++$page;
		}
		while ($response->getPager()->hasNext());

		return $result;
	}
}