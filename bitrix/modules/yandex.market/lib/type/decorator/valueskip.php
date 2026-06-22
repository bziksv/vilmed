<?php
namespace Yandex\Market\Type\Decorator;

use Yandex\Market\Error;
use Yandex\Market\Type;

class ValueSkip extends Type\AbstractType
	implements Type\Concerns\HasRecommendation
{
	private $decorated;
	private $values;

	public function __construct(Type\Type $type, array $values)
	{
		parent::__construct();

		$this->decorated = $type;
		$this->values = $values;
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
		$sanitized = $this->decorated->sanitize($value, $context, $settings);

		if ($sanitized === null || $sanitized instanceof Error\Base) { return $sanitized; }
		if (in_array($sanitized, $this->values, true)) { return new Error\SkipError(); }

		return $sanitized;
	}

	public function setValueSkip(array $values)
	{
		$this->values = $values;
	}
}