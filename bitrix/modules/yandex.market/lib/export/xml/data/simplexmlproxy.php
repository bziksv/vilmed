<?php
namespace Yandex\Market\Export\Xml\Data;

use Bitrix\Main;
use Yandex\Market\Reference\Assert;

class SimpleXmlProxy implements ExportElement
{
	/** @var \SimpleXMLElement */
	private $node;
	/** @var SimpleXmlCompiler */
	private $compiler;
	/** @var static[] */
	private $children;

	public static function fromCompiler(SimpleXmlCompiler $compiler)
	{
		return new static($compiler->asElement(), $compiler);
	}

	public function __construct(\SimpleXMLElement $node, SimpleXmlCompiler $compiler = null)
	{
		$this->node = $node;
		$this->compiler = $compiler;
	}

    public function compiler()
    {
        if ($this->compiler === null)
        {
            $this->compiler = new SimpleXmlCompiler();
        }

        return $this->compiler;
    }

	public function inject(\SimpleXMLElement $node)
	{
		$this->node = $node;
	}

	public function extract()
	{
		return $this->node;
	}

	public function getName()
	{
		return $this->node->getName();
	}

	public function getValue()
	{
		return (string)$this->node[0];
	}

    public function setValue($value)
    {
        $this->node[0] = $value;
    }

    public function appendValue($value, $glue)
	{
		$filled = $this->getValue();

		if ($filled === '')
		{
			$this->node[0] = $value;
			return;
		}

		$this->node[0] = $filled . $glue . $value;
	}

	public function hasChildren()
	{
        if ($this->children === null)
        {
            return count($this->node->children()) > 0;
        }

		return !empty($this->children);
	}

	public function getChildren()
	{
        $this->bootChildren();

		return $this->children;
	}

    private function bootChildren()
    {
        if ($this->children !== null) { return; }

        $children = [];

        foreach ($this->node->children() as $child)
        {
            $children[] = new static($child);
        }

        $this->children = $children;
    }

	public function getChild($name)
	{
        $result = [];

		foreach ($this->getChildren() as $child)
		{
			if ($child->getName() === $name)
			{
                $result[] = $child;
			}
		}

		return $result;
	}

	public function addChild($name, $value = null, $multiple = false)
	{
        $this->bootChildren();

		$node = $value !== null
            ? $this->node->addChild($name, $value)
            : $this->node->addChild($name);
        $child = new static($node);

        $this->children[] = $child;

		return $child;
	}

	public function removeChild(ExportElement $child)
	{
		$key = array_search($child, $this->children, true);

		if ($key === false) { return; }

		/** @var SimpleXmlProxy $child */
		Assert::isInstanceOf($child, static::class);

		array_splice($this->children, $key, 1);
		$child->destroy();
	}

	protected function destroy()
	{
		unset($this->node[0]);
	}

	public function addAttribute($name, $value)
	{
        /** @noinspection PhpDeprecationInspection */
        if (!Main\Application::isUtfMode())
		{
			@$this->node->addAttribute($name, $value); // sanitize encoding warning (no convert, performance issue)
			return;
		}

		$this->node->addAttribute($name, $value);
	}

    public function getAttributes()
    {
        $result = [];

        foreach ($this->node->attributes() as $name => $value)
        {
            $result[$name] = (string)$value;
        }

        return $result;
    }

    public function getAttribute($name)
    {
        $attributes = $this->node->attributes();

        if ($attributes === null || !isset($attributes[$name])) { return null; }

        return (string)$attributes[$name];
    }

    public function removeAttribute($name)
    {
        $attributes = $this->node->attributes();

		if ($attributes === null) { return; }

        if (isset($attributes[$name]))
        {
            unset($attributes[$name]);
        }
    }

	public function build()
	{
		$xml = $this->node->asXML();

        if ($this->compiler === null) { return $xml; }

		return $this->compiler->modify($xml);
	}
}