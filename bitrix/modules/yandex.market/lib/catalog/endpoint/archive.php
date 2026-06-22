<?php
namespace Yandex\Market\Catalog\Endpoint;

use Yandex\Market\Api\Business\OfferMappings;
use Yandex\Market\Api\Reference\Auth;
use Yandex\Market\Catalog;
use Yandex\Market\Error;
use Yandex\Market\Logger\Trading\Audit;
use Yandex\Market\Result;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Psr\Log\LoggerInterface;

class Archive implements Driver
{
    use Concerns\HasMessage;

    private $businessId;

    public function __construct($businessId)
    {
        $this->businessId = $businessId;
    }

    public function type()
	{
		return Catalog\Glossary::ENDPOINT_ARCHIVE;
	}

	public function campaignId()
	{
		return 0;
	}

	public function audit()
	{
		return Audit::CATALOG_ARCHIVE;
	}

	public function priority($placementStatus, array $prepared, array $submitted = null)
	{
        $archive = !empty($prepared[Catalog\Glossary::ENDPOINT_ARCHIVE]['value']);

		return $archive ? PriorityDictionary::ARCHIVE : PriorityDictionary::UNARCHIVE;
	}

	public function limit()
	{
		return 200;
	}

	public function submit(array $payloadBag, Auth $auth, LoggerInterface $logger)
	{
        list($archive, $unArchive, $invalid) = $this->splitBag($payloadBag);

		return (
            $this->archive($archive, $auth, $logger)
            + $this->unArchive($unArchive, $auth, $logger)
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

    private function archive(array $skus, Auth $auth, LoggerInterface $logger)
    {
        if (empty($skus)) { return []; }

        $request = new OfferMappings\Archive\Request($this->businessId, $auth, $logger);
        $request->setOfferIds($skus);

        $response = $request->execute();

        $result = array_combine(
            $skus,
            array_map(static function() { return new Result\Base(); }, $skus)
        );

        $this->fillNotArchived($result, $response->getNotArchivedOffers());
        $this->fillSubmitStatus($result, true, $logger);

        return $result;
    }

    private function fillNotArchived(array $submitResults, OfferMappings\Archive\NotArchivedOfferCollection $notArchivedOffers)
    {
        /** @var OfferMappings\Archive\NotArchivedOffer $notArchivedOffer */
        foreach ($notArchivedOffers as $notArchivedOffer)
        {
            $sku = $notArchivedOffer->getOfferId();
            $message = $notArchivedOffer->getErrorMessage();

			if (!isset($submitResults[$sku])) { continue; }

            $submitResult = $submitResults[$sku];
            $submitResult->addError(new Error\Base(self::getMessage(
                'NOT_ARCHIVED',
                [ '#MESSSAGE#' => $message ],
                $message
            )));
        }
    }

    private function unArchive(array $skus, Auth $auth, LoggerInterface $logger)
    {
        if (empty($skus)) { return []; }

        $request = new OfferMappings\UnArchive\Request($this->businessId, $auth, $logger);
        $request->setOfferIds($skus);

        $response = $request->execute();

        $result = array_combine(
            $skus,
            array_map(static function() { return new Result\Base(); }, $skus)
        );

        $this->fillNotUnarchived($result, $response->getNotUnarchivedOfferIds());
        $this->fillSubmitStatus($result, false, $logger);

        return $result;
    }

    private function fillNotUnarchived(array $submitResults, array $skus)
    {
        foreach ($skus as $sku)
        {
			if (!isset($submitResults[$sku])) { continue; }

            /** @var Result\Base $submitResult */
            $submitResult = $submitResults[$sku];
            $submitResult->addError(new Error\Base(self::getMessage('NOT_UNARCHIVED')));
        }
    }

    private function fillSubmitStatus(array $submitResults, $archive, LoggerInterface $logger)
    {
        /** @var Result\Base $submitResult */
        foreach ($submitResults as $sku => $submitResult)
        {
            if (!$submitResult->isSuccess()) { continue; }

            $submitResult->setData([
                'PLACEMENT' => [
                    'STATUS' => $archive
                        ? Catalog\Run\Storage\PlacementTable::STATUS_ARCHIVED
                        : Catalog\Run\Storage\PlacementTable::STATUS_PUBLISHED,
                ],
            ]);

			$logger->info(self::getMessage($archive ? 'ARCHIVED' : 'UNARCHIVED'), [
				'ENTITY_TYPE' => Catalog\Glossary::ENTITY_SKU,
				'ENTITY_ID' => $sku,
			]);
        }
    }
}