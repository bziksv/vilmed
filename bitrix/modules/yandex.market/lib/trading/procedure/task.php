<?php

namespace Yandex\Market\Trading\Procedure;

use Bitrix\Main;

class Task
{
	/** @var string */
	protected $entityType;
	/** @var string */
	protected $entityId;

	public function __construct($entityType, $entityId)
	{
		$this->entityType = $entityType;
		$this->entityId = $entityId;
	}

	public function clear($campaignId, $path)
	{
		QueueTable::deleteBatch([
			'filter' => [
				'=CAMPAIGN_ID' => $campaignId,
				'=PATH' => $path,
				'=ENTITY_TYPE' => $this->entityType,
				'=ENTITY_ID' => $this->entityId,
			],
		]);
	}

	public function schedule($campaignId, $path, $data, $interval = 600)
	{
		QueueTable::add([
			'CAMPAIGN_ID' => $campaignId,
			'PATH' => $path,
			'DATA' => $data,
			'INTERVAL' => $interval,
			'ENTITY_TYPE' => $this->entityType,
			'ENTITY_ID' => $this->entityId,
			'EXEC_DATE' => new Main\Type\DateTime(),
			'EXEC_COUNT' => 0,
		]);

		$this->registerRepeatAgent();
	}

	protected function registerRepeatAgent()
	{
		Agent::register([
			'method' => 'repeat',
			'next_exec' => new Main\Type\DateTime(),
		]);
	}
}