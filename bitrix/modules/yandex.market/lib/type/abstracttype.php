<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Export\Xml;
use Yandex\Market\Result;

abstract class AbstractType implements Type
{
    protected $settings = [];

    public function __construct(array $parameters = null)
    {
        if ($parameters !== null)
        {
            $this->passSettings($parameters);
        }
    }

    protected function passSettings(array $parameters)
    {
        foreach ($this->settings as $name => $value)
        {
            if (isset($parameters[$name]))
            {
                $this->settings[$name] = $parameters[$name];
            }
        }
    }

    protected function setting($name, array $settings = null, $default = null)
    {
        if (isset($settings[$name]))
        {
            return $settings[$name];
        }

        if (isset($this->settings[$name]))
        {
            return $this->settings[$name];
        }

        return $default;
    }

    public function configure(array $parameters)
    {
        $type = $this;

	    $type->passSettings($parameters);

        if (isset($parameters['overrides']))
        {
            $type = $type->setOverrides($parameters['overrides']);
        }

		if (isset($parameters['value_skip']))
		{
			if (!is_array($parameters['value_skip'])) { $parameters['value_skip'] = [ $parameters['value_skip'] ]; }

			$type = $type->setValueSkip($parameters['value_skip']);
		}

	    if (isset($parameters['formatter']))
	    {
		    $type = is_array($parameters['formatter'])
			    ? $type->setFormatters($parameters['formatter'])
			    : $type->addFormatter($parameters['formatter']);
	    }

        return $type;
    }

    public function setFormatters(array $formatters)
    {
        return new Decorator\Formatter($this, $formatters);
    }

    public function addFormatter(Formatter\Formatter $formatter)
    {
        return new Decorator\Formatter($this, [ $formatter ]);
    }

    public function setOverrides(array $overrides)
    {
        return new Decorator\Overrides($this, $overrides);
    }

	public function setValueSkip(array $values)
	{
		return new Decorator\ValueSkip($this, $values);
	}

    /**
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function validate($value, array $context = [], Xml\Reference\Node $node = null, Result\XmlNode $nodeResult = null)
    {
        $sanitized = $this->sanitize($value);

        if ($sanitized === null || $sanitized === '')
        {
            if ($nodeResult) { $nodeResult->invalidate(); }

            return false;
        }

        if ($sanitized instanceof Error\Base)
        {
            if ($nodeResult) { $nodeResult->addError($sanitized); }

            return false;
        }

        return true;
    }

    /**
     * @return mixed
     * @noinspection PhpUnusedParameterInspection
     */
    public function format($value, array $context = [], Xml\Reference\Node $node = null, Result\XmlNode $nodeResult = null)
    {
        $sanitized = $this->sanitize($value, $context);

        if ($sanitized instanceof Error\Base) { return null; }

        return $sanitized;
    }
}