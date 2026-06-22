<?php
namespace Yandex\Market\Api\Reports\Info;

use Yandex\Market\Api\Glossary;
use Yandex\Market\Api\Reference\RequestTokenized;
use Yandex\Market\Reference\Assert;

/** @method Response execute() */
class Request extends RequestTokenized
{
	protected $reportId;

	public function getHost()
	{
		return Glossary::MARKET_API_HOST;
	}

	public function getPath()
	{
		return "/reports/info/{$this->getReportId()}";
	}

	public function getReportId()
	{
		Assert::notNull($this->reportId, 'reportId');

		return $this->reportId;
	}

	public function setReportId($reportId)
	{
		$this->reportId = (string)$reportId;
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}