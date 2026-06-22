<?php
namespace Yandex\Market\Export\Xml\Data;

use Yandex\Market\Reference\Assert;

class XmlElement implements ExportElement
{
	/** @var string */
	private $name;
    /** @var mixed|null */
	private $value;
    /** @var array<string, string> */
	private $attributes = [];
	/** @var static[] */
	private $children = [];

	public function __construct($name, $value = null)
	{
		$this->name = $name;
		$this->value = $value;
	}

    public function getName()
	{
		return $this->name;
	}

	public function getValue()
	{
		return $this->value;
	}

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function appendValue($value, $glue)
	{
		if ($this->value === null || $this->value === '')
		{
			$this->value = $value;
			return;
		}

		$this->value .= $glue . $value;
	}

	public function hasChildren()
	{
		return !empty($this->children);
	}

	public function getChildren()
	{
		return $this->children;
	}

	public function getChild($name)
	{
        $result = [];

		foreach ($this->children as $child)
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
		$child = new static($name, $value);
		$this->children[] = $child;

		return $child;
	}

	public function removeChild(ExportElement $child)
	{
		$key = array_search($child, $this->children, true);

		if ($key === false) { return; }

		/** @var XmlElement $child */
		Assert::isInstanceOf($child, static::class);

		array_splice($this->children, $key, 1);
	}

	public function getAttributes()
	{
		return $this->attributes;
	}

    public function getAttribute($name)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : null;
    }

    public function removeAttribute($name)
    {
        if (!isset($this->attributes[$name]) && !array_key_exists($name, $this->attributes)) { return; }

        unset($this->attributes[$name]);
    }

	public function addAttribute($name, $value)
	{
		$this->attributes[$name] = $value;
	}

	public function build()
	{
		return new SimpleXmlCompiler($this);
	}
}