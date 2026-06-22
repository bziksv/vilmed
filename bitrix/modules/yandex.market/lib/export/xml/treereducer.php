<?php
namespace Yandex\Market\Export\Xml;

class TreeReducer
{
	private $callback;
	private $includeAttributes;

	public function __construct(callable $callback, $includeAttributes = false)
	{
		$this->callback = $callback;
		$this->includeAttributes = $includeAttributes;
	}

	public function reduceList(array $nodes, $initial = null)
	{
		return $this->walkChildren($nodes, [], $initial);
	}

	public function reduce(Reference\Node $node, $initial = null)
	{
		return $this->walkNode($node, [], $initial);
	}

	protected function walkNode(Reference\Node $node, array $parents, $carry)
	{
		$carry = $this->apply($node, $parents, $carry);

		if (!($node instanceof Tag\Base)) { return $carry; }

		$parents[] = $node;
		$carry = $this->walkChildren($node->getChildren(), $parents, $carry);

		if ($this->includeAttributes)
		{
			$carry = $this->walkChildren($node->getAttributes(), $parents, $carry);
		}

		return $carry;
	}

	protected function walkChildren(array $nodes, array $parents, $carry)
	{
		foreach ($nodes as $node)
		{
			$carry = $this->walkNode($node, $parents, $carry);
		}

		return $carry;
	}

	protected function apply(Reference\Node $node, array $parents, $carry)
	{
		return call_user_func($this->callback, $node, $parents, $carry);
	}
}