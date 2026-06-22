<?php
namespace Yandex\Market\Api\Reports;

use Yandex\Market\Api\Reference\Response;

class GenerateResponse extends Response
{
	public function getReportId()
	{
		return (string)$this->requireField('result.reportId');
	}

	public function getEstimatedGenerationTime()
	{
		return (int)$this->getField('result.estimatedGenerationTime');
	}
}