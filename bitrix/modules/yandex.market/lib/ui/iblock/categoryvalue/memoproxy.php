<?php
namespace Yandex\Market\Ui\Iblock\CategoryValue;

use Yandex\Market\Reference\Concerns;

class MemoProxy implements CategoryValue
{
	use Concerns\HasOnce;

	private $decorated;

	public function __construct(CategoryValue $decorated)
	{
		$this->decorated = $decorated;
	}

	public function decorated()
	{
		return $this->decorated;
	}

	public function value()
	{
		return $this->once('value', null, function() {
			return $this->decorated->value();
		});
	}

	public function save(array $value = null)
	{
		$this->decorated->save($value);
	}

	public function parent()
	{
		return $this->once('parent', null, function() {
			$parent = $this->decorated->parent();

			if ($parent === null) { return null; }

			return MemoPool::get($parent);
		});
	}
}