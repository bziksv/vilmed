<?php
namespace Yandex\Market\Export\Xml\Data;

class ArrayElement implements ExportElement
{
	/** @var string */
	private $name;
	/** @var mixed|null */
    private $value;
	/** @var static[] */
    private $children = [];
    private $multipleChildren = [];

	public function __construct($name, $value = null)
	{
		$this->name = (string)$name;
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
        return $this->value = $value;
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
        if ($multiple) { $this->multipleChildren[$name] = true; }

        $child = new static($name, $value);
		$this->children[] = $child;

		return $child;
	}

	public function removeChild(ExportElement $child)
	{
        $index = array_search($child, $this->children, true);

        if ($index === false) { return; }

        array_splice($this->children, $index, 1);
	}

	public function build()
	{
		if (!empty($this->children))
		{
			$result = [];

			if ($this->value !== null && !($this->value instanceof RootValue))
			{
				$result['value'] = $this->castValue();
			}

			foreach ($this->children as $child)
			{
                $name = $child->getName();

                if (isset($this->multipleChildren[$name]))
                {
                    if (!isset($result[$name])) { $result[$name] = []; }

                    $result[$name][] = $child->build();
                    continue;
                }

				$result[$name] = $child->build();
			}

			return $result;
		}

        return $this->castValue();
	}

    private function castValue()
    {
        if (!is_scalar($this->value) && !is_array($this->value))
        {
            return (string)$this->value;
        }

        return $this->value;
    }
}