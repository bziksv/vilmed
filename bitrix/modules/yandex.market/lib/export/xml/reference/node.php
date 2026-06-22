<?php
namespace Yandex\Market\Export\Xml\Reference;

use Yandex\Market;
use Yandex\Market\Export\Xml;

abstract class Node
{
	/** @var array */
	protected $parameters;
	/** @var bool */
	protected $isVisible;
	/** @var bool */
	protected $isRequired;
	/** @var string */
	protected $name;
	/** @var string */
	protected $id;
	/** @var Market\Type\Type */
	protected $type;
	/** @var string[]|null */
	protected $overrides;

	public function __construct(array $parameters = [])
	{
		$this->parameters = $parameters + $this->getDefaultParameters();

		$this->refreshParameters();
	}

	public function getDefaultParameters()
	{
		return [];
	}

	public function extendParameters($parameters)
	{
		$this->parameters = array_merge($this->parameters, $parameters);

		$this->refreshParameters();
	}

	protected function refreshParameters()
	{
		$parameters = $this->parameters;

		$this->name = isset($parameters['name']) ? $parameters['name'] : null; // maybe set in child
		$this->id = isset($parameters['id']) ? $parameters['id'] : $this->name;
		$this->isRequired = !empty($parameters['required']);
		$this->isVisible = !empty($parameters['visible']);
        $this->type = $this->compileType($parameters);
	}

    protected function compileType(array $parameters)
    {
        if (empty($parameters['value_type']))
        {
            $type = new Market\Type\StringType();
        }
        else if ($parameters['value_type'] instanceof Market\Type\Type)
        {
            $type = $parameters['value_type'];
        }
        else
        {
            $type = Market\Type\Manager::getType($parameters['value_type']);
        }

        return $type->configure($parameters);
    }

	abstract public function getLangKey();

	public function tune(array $context)
	{
		// nothing by default
	}

	public function preselect(array $context)
	{
		$recommendation = $this->getSourceRecommendation($context);

		if (empty($recommendation)) { return null; }

		return reset($recommendation);
	}

	/** @return string */
	public function getTitle()
	{
		return Market\Config::getLang($this->getLangKey() . '_TITLE', null, $this->getName());
	}

	/** @return string */
	public function getDescription()
	{
		return Market\Config::getLang($this->getLangKey() . '_DESCRIPTION', null, '');
	}

	/** @return mixed|null */
	public function getParameter($key, $default = null)
	{
		return (isset($this->parameters[$key]) ? $this->parameters[$key] : $default);
	}

	/** @return bool */
	public function isVisible()
	{
		return $this->isVisible;
	}

	/** @return bool */
	public function isRequired()
	{
		return $this->isRequired;
	}

	/** @return bool */
	public function isDefined()
	{
		return ($this->getParameter('defined_value') !== null);
	}

	/** @return array|null */
	public function getDefinedSource(array $context = [])
	{
		$result = null;
		$definedValue = $this->getParameter('defined_value');

		if ($definedValue !== null)
		{
			$result = [
				'TYPE' => Market\Export\Entity\Manager::TYPE_TEXT,
				'VALUE' => $definedValue
			];
		}

		return $result;
	}

	/**
     * @return string
     * @noinspection PhpUnusedParameterInspection
     */
	public function getDefaultSource(array $context = [])
	{
		return Market\Export\Entity\Manager::TYPE_TEXT;
	}

	/** @return array */
	public function getSourceRecommendation(array $context = [])
	{
        if ($this->type instanceof Market\Type\Concerns\HasRecommendation)
        {
            return array_map(static function(array $option) {
                return $option + [ 'TYPE' => Market\Export\Entity\Manager::TYPE_TEXT ];
            }, $this->type->recommendation($context));
        }

		return [];
	}

	/** @return string */
	public function getId()
	{
		return $this->id;
	}

	/** @return string */
	public function getName()
	{
		return $this->name;
	}

	/** @return string */
	public function getValueType()
	{
		return $this->type->type();
	}

    /** @noinspection PhpUnusedParameterInspection */
    public function getDefaultValue(array $context = [], $siblingsValues = null)
	{
		return $this->getParameter('default_value');
	}

    public function sanitize($value, array $context = [], array $tagValue = null, array $siblingsValues = null)
    {
        if ($value === null || $value === '') { return null; }

        return $this->type->sanitize($value, $context, $this->typeSettings($tagValue, $siblingsValues));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function typeSettings(array $tagValue = null, array $siblingsValues = null)
    {
        return null;
    }

	/** @return Xml\Data\ExportElement */
	abstract public function insertNode($value, Xml\Data\ExportElement $parent);

	public function compareValue($value, array $context = [], Market\Result\XmlValue $nodeValue = null)
	{
		$sanitized = $this->sanitize($value, $context);

        if ($sanitized instanceof Market\Error\Base) { return null; }

        return $sanitized;
    }

    /**
     * @deprecated
     * @noinspection PhpUnusedParameterInspection
     */
	protected function formatValue($value, array $context = [], Market\Result\XmlNode $nodeResult = null, $settings = null)
	{
        return $value;
	}

    /**
     * @deprecated
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate($value, array $context, $siblingsValues = null, Market\Result\XmlNode $nodeResult = null, $settings = null)
    {
        $sanitized = $this->sanitize($value, $context);

        return (
            $sanitized !== null
            && !($sanitized instanceof Market\Error\Base)
        );
    }
}