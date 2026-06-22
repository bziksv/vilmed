<?php
namespace Yandex\Market\Trading\Service\Marketplace\Model;

use Yandex\Market;

/**
 * @property Returns[] $collection
 */
class ReturnCollection extends Market\Api\Reference\Collection
{
	protected $paging;

	public static function getItemReference()
	{
		return Returns::class;
	}

	/** @return Market\Api\Model\Paging|null */
	public function getPaging()
	{
		return $this->paging;
	}

	public function setPaging(Market\Api\Model\Paging $paging)
	{
		$this->paging = $paging;
	}
}