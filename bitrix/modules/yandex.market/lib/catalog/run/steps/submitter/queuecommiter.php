<?php
namespace Yandex\Market\Catalog\Run\Steps\Submitter;

use Yandex\Market\Data;
use Yandex\Market\Catalog;
use Yandex\Market\Catalog\Run\Storage;

class QueueCommiter
{
    private $catalogId;

    public function __construct($catalogId)
    {
        $this->catalogId = $catalogId;
    }

    public function success(Catalog\Endpoint\Driver $driver, array $skus)
    {
	    $this->status($driver, $skus, Storage\QueueTable::STATUS_SUCCESS);
    }

    public function error(Catalog\Endpoint\Driver $driver, array $skus)
    {
	    $this->status($driver, $skus, Storage\QueueTable::STATUS_ERROR);
    }

    public function missing(Catalog\Endpoint\Driver $driver, array $skus)
    {
		$this->status($driver, $skus, Storage\QueueTable::STATUS_MISSING);
    }

	private function status(Catalog\Endpoint\Driver $driver, array $skus, $status)
	{
		if (empty($skus)) { return; }

		Storage\QueueTable::updateBatch([
			'filter' => [
				'=CATALOG_ID' => $this->catalogId,
				'=ENDPOINT' => $driver->type(),
				'=CAMPAIGN_ID' => $driver->campaignId(),
				'=SKU' => $skus,
			],
		], [
			'STATUS' => $status,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}

	public function repeat(Catalog\Endpoint\Driver $driver, array $skus, $priority)
	{
		if (empty($skus)) { return; }

		Storage\QueueTable::updateBatch([
			'filter' => [
				'=CATALOG_ID' => $this->catalogId,
				'=ENDPOINT' => $driver->type(),
				'=CAMPAIGN_ID' => $driver->campaignId(),
				'=SKU' => $skus,
			],
		], [
			'PRIORITY' => $priority + 100,
			'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
		]);
	}

    public function errorByMethod(Catalog\Endpoint\Driver $driver)
    {
        Storage\QueueTable::updateBatch([
            'filter' => [
                '=CATALOG_ID' => $this->catalogId,
                '=ENDPOINT' => $driver->type(),
                '=CAMPAIGN_ID' => $driver->campaignId(),
                '=STATUS' => Storage\QueueTable::STATUS_WAIT,
            ],
        ], [
            'STATUS' => Storage\QueueTable::STATUS_ERROR,
            'TIMESTAMP_X' => new Data\Type\CanonicalDateTime(),
        ]);
    }
}