<?php
namespace Yandex\Market\Api\Reports\Info;

use Yandex\Market\Api\Reference as ApiReference;
use Yandex\Market\Reference\Concerns;

class Response extends ApiReference\Response
{
	use Concerns\HasMessage;

	const STATUS_PENDING = 'PENDING';
	const STATUS_PROCESSING = 'PROCESSING';
	const STATUS_FAILED = 'FAILED';
	const STATUS_DONE = 'DONE';

	public function getStatus()
	{
		return (string)$this->requireField('result.status');
	}

	public function getSubStatus()
	{
		return (string)$this->getField('result.subStatus');
	}

	public function textSubStatus()
	{
		$subStatus = $this->getSubStatus();

		if ($subStatus === '') { return self::getMessage('SUB_STATUS_UNKNOWN'); }

		return self::getMessage('SUB_STATUS_' . $subStatus, null, $subStatus);
	}

	public function getEstimatedGenerationTime()
	{
		return (int)$this->getField('result.estimatedGenerationTime');
	}

	public function getFile()
	{
		return (string)$this->requireField('result.file');
	}
}