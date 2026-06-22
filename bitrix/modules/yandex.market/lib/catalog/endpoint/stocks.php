<?php
namespace Yandex\Market\Catalog\Endpoint;

use Bitrix\Main;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Config;
use Yandex\Market\Data;
use Yandex\Market\Catalog;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\Psr\Log\LoggerInterface;
use Yandex\Market\Trading\Service\Marketplace\Api as MarketApi;
use Yandex\Market\Trading\State\PushAgent;

class Stocks implements Driver, DriverWithCampaignGroup, DriverWithExpiryDate
{
	use Concerns\HasMessage;

	/** @var int */
	private $campaignId;
	private $campaignGroup;

	public function __construct($campaignId, array $campaignGroup = [])
	{
		$this->campaignId = $campaignId;
		$this->campaignGroup = $campaignGroup;
	}

	public function type()
	{
		return Catalog\Glossary::ENDPOINT_STOCKS;
	}

	public function campaignId()
	{
		return $this->campaignId;
	}

	public function campaignGroup()
	{
		return $this->campaignGroup;
	}

	public function audit()
	{
		return Audit::CATALOG_STOCKS;
	}

	public function expiryDate()
	{
		$expire = new Data\Type\CanonicalDateTime();
		$expire->add(sprintf(
			'-P%sD',
			max(1, (int)Config::getOption(PushAgent::optionName('expire_days'), 1))
		));

		return $expire;
	}

	public function priority($placementStatus, array $prepared, array $submitted = null)
	{
        if (!PriorityDictionary::wasPublished($placementStatus))
        {
            return PriorityDictionary::STOCKS_NEW;
        }

        if (PriorityDictionary::willShip($prepared, $submitted) && PriorityDictionary::willDiscount($prepared, $submitted))
        {
            return PriorityDictionary::STOCKS_PUBLISHED + PriorityDictionary::MODIFIER;
        }

        if (PriorityDictionary::willOutOfStock($prepared, $submitted))
        {
            return PriorityDictionary::STOCKS_PUBLISHED - PriorityDictionary::MODIFIER;
        }

        return PriorityDictionary::STOCKS_PUBLISHED;
	}

	public function limit()
	{
		return 2000;
	}

	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
	{
		$request = new MarketApi\SendStocks\Request($this->campaignId, $auth, $logger);
		$request->setSkus($this->compileSkus($payloadBag));
		$request->execute();

		$this->logSubmitted($payloadBag, $logger);

		return array_fill_keys(array_keys($payloadBag), new Result\Base());
	}

	private function compileSkus(array $bag)
	{
		$result = [];
		$updatedAt = Data\Date::convertForService(new Main\Type\DateTime());

		foreach ($bag as $sku => $data)
		{
			$result[] = [
				'sku' => (string)$sku,
				'items' => [
					[
						'count' => (int)$data['count'],
						'updatedAt' => $updatedAt,
					],
				],
			];
		}

		return $result;
	}

	private function logSubmitted(array $payloadBag, LoggerInterface $logger)
	{
		$now = new Main\Type\DateTime();
		$nowContext = [
			'now' => $now->toString(),
			'timezone' => $now->format('P'),
		];

		foreach ($payloadBag as $sku => $payload)
		{
			$context = [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			];

			if (!empty($payload['context']) && is_array($payload['context']))
			{
				$context += $payload['context'];
				$context += $nowContext;
			}

			$logger->info(self::getMessage('SUBMITTED', [
				'#COUNT#' => (int)$payload['count'],
			]), $context);
		}
	}
}