<?php
namespace Yandex\Market\Export\CollectionProduct;

use Yandex\Market\Reference;
use Yandex\Market\Export;

class Model extends Reference\Storage\Model
{
	use Reference\Concerns\HasOnce;

	/** @var Export\Filter\Collection|null */
	protected $filterCollection;

	public static function getDataClass()
	{
		return Table::class;
	}

	public function getIblockId()
	{
		return (int)$this->getField('IBLOCK_ID');
	}

	public function getContext()
	{
		return Export\Entity\Iblock\Provider::getContext($this->getIblockId());
	}

	public function getFilterCollection()
	{
		if ($this->filterCollection !== null)
		{
			return $this->filterCollection;
		}

		return $this->getCollection('FILTER', Export\Filter\Collection::class);
	}

	public function setFilterCollection(Export\Filter\Collection $filterCollection)
	{
		$this->filterCollection = $filterCollection;
	}
}