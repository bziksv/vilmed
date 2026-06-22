<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api\Campaigns\HiddenOffers;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Catalog;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Result;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Psr\Log\LoggerInterface;

class Hide implements Driver
{
    use Concerns\HasMessage;

    /** @var int */
    private $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function type()
    {
        return Catalog\Glossary::ENDPOINT_ARCHIVE;
    }

    public function campaignId()
    {
        return $this->campaignId;
    }

	public function audit()
	{
		return Audit::CATALOG_ARCHIVE;
	}

    public function priority($placementStatus, array $prepared, array $submitted = null)
    {
        $archive = !empty($prepared[Catalog\Glossary::ENDPOINT_ARCHIVE]['value']);

        return $archive ? PriorityDictionary::HIDE : PriorityDictionary::UNHIDE;
    }

    public function limit()
    {
        return 500;
    }

    public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
    {
        list($archive, $unArchive, $invalid) = $this->splitBag($payloadBag);

        return (
            $this->hide($archive, $auth, $logger)
            + $this->hideDelete($unArchive, $auth, $logger)
            + $invalid
        );
    }

    private function splitBag(array $bag)
    {
        $invalid = [];
        $archive = [];
        $unArchive = [];

        foreach ($bag as $sku => $payload)
        {
            if (!isset($payload['value']))
            {
                $invalid[$sku] = new Result\Base();
                $invalid[$sku]->addError(self::getMessage('INVALID_PAYLOAD'));

                continue;
            }

            if (!empty($payload['value']))
            {
                $archive[] = $sku;
            }
            else
            {
                $unArchive[] = $sku;
            }
        }

        return [ $archive, $unArchive, $invalid ];
    }

    private function hide(array $skus, Auth $auth, LoggerInterface $logger)
    {
        if (empty($skus)) { return []; }

        $request = new HiddenOffers\Post\Request($this->campaignId, $auth, $logger);
        $request->setHiddenOffers($skus);

        $request->execute();

	    $this->logSubmitted($skus, true, $logger);

        return $this->createSubmitResults($skus, true);
    }

    private function hideDelete(array $skus, Auth $auth, LoggerInterface $logger)
    {
        if (empty($skus)) { return []; }

        $request = new HiddenOffers\Delete\Request($this->campaignId, $auth, $logger);
        $request->setHiddenOffers($skus);

        $request->execute();

		$this->logSubmitted($skus, false, $logger);

        return $this->createSubmitResults($skus, false);
    }

	private function logSubmitted(array $skus, $hide, LoggerInterface $logger)
	{
		foreach ($skus as $sku)
		{
			$logger->info(self::getMessage($hide ? 'HIDDEN' : 'SHOWN'), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);
		}
	}

    private function createSubmitResults(array $skus, $hide)
    {
        $skuResult = new Result\Base();
        $skuResult->setData([
            'PLACEMENT' => [
                'STATUS' => $hide
                    ? Catalog\Run\Storage\PlacementTable::STATUS_ARCHIVED
                    : Catalog\Run\Storage\PlacementTable::STATUS_PUBLISHED,
            ],
        ]);

        return array_fill_keys($skus, $skuResult);
    }
}