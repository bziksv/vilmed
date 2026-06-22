<?php
namespace Yandex\Market\Api\Categories\Tree\Model;

class TreeReducer
{
    protected $callback;
    protected $includeRoot;

    public function __construct($callback, $includeRoot = false)
    {
        $this->callback = $callback;
        $this->includeRoot = $includeRoot;
    }

    public function reduce(Category $root, $initial = null)
    {
        if ($this->includeRoot)
        {
            $carry = $this->walkCategory($root, [], $initial);
        }
        else
        {
            $carry = $this->walkChildren($root->getChildren(), [], $initial);
        }

        return $carry;
    }

    protected function walkCategory(Category $category, array $chain, $carry)
    {
        $carry = $this->apply($category, $chain, $carry);

        return $this->walkChildren($category->getChildren(), array_merge($chain, [$category]), $carry);
    }

    protected function walkChildren(CategoryCollection $children, array $chain, $carry)
    {
        foreach ($children as $child)
        {
            $carry = $this->walkCategory($child, $chain, $carry);
        }

        return $carry;
    }

    protected function apply($category, $chain, $carry)
    {
        return call_user_func($this->callback, $carry, $category, $chain);
    }
}