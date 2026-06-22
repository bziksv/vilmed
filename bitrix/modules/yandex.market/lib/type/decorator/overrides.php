<?php
namespace Yandex\Market\Type\Decorator;

use Yandex\Market\Error;
use Yandex\Market\Type;

class Overrides extends Type\AbstractType
    implements Type\Concerns\HasRecommendation
{
    private $decorated;
    private $overrides;

    public function __construct(Type\Type $type, array $overrides = [])
    {
        parent::__construct();

        $this->decorated = $type;
        $this->overrides = $overrides;
    }

    public function type()
    {
        return $this->decorated->type();
    }

    public function recommendation(array $context = [])
    {
        if (!($this->decorated instanceof Type\Concerns\HasRecommendation)) { return []; }

        return $this->decorated->recommendation($context);
    }

    public function sanitize($value, array $context = [], array $settings = null)
    {
        if (in_array($value, $this->overrides, true))
        {
            return $value;
        }

        $sanitized = $this->decorated->sanitize($value, $context, $settings);

        if ($sanitized === null || $sanitized instanceof Error\Base) { return $sanitized; }

		if (is_scalar($sanitized))
		{
			$sanitizedString = (string)$sanitized;

	        if (isset($this->overrides[$sanitizedString]) || array_key_exists($sanitizedString, $this->overrides))
	        {
	            return $this->overrides[$sanitizedString];
	        }
        }

        return $sanitized;
    }

    public function setOverrides(array $overrides)
    {
        $this->overrides = $overrides;

        return $this;
    }
}