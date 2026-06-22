<?php
namespace Yandex\Market\Api\Campaigns;

use Yandex\Market\Api;
use Yandex\Market\Api\Reference\Transport\Cache;

/** @method Response execute() */
class Request extends Api\Reference\RequestTokenized
{
	protected $page = 1;
	protected $pageSize = 50;

	public function getHost()
	{
		return 'api.partner.market.yandex.ru';
	}

	public function getPath()
	{
		return '/campaigns.json';
	}

	public function getQuery()
	{
		return [
			'page' => $this->page,
			'pageSize' => $this->pageSize,
		];
	}

	public function setPage($page)
	{
		$this->page = (int)$page;
	}

	public function setPageSize($pageSize)
	{
		$this->pageSize = (int)$pageSize;
	}

	public function buildResponse($data)
	{
		return new Response($data);
	}
}