<?php
namespace Yandex\Market\Type\Decorator;

use Yandex\Market\Error;
use Yandex\Market\Type;

class Formatter extends Type\AbstractType
    implements Type\Concerns\HasRecommendation
{
    private $decorated;
    private $formatters;

    /**
     * @param Type\AbstractType $type
     * @param Type\Formatter\Formatter[] $formatters
     */
    public function __construct(Type\Type $type, array $formatters = [])
    {
        parent::__construct();

        $this->decorated = $type;
        $this->formatters = $formatters;
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

        foreach ($this->formatters as $formatter)
        {
            $sanitized = $formatter->format($sanitized);
        }

        return $sanitized;
    }

    public function setFormatters(array $formatters)
    {
        $this->formatters = $formatters;

        return $this;
    }

    public function addFormatter(Type\Formatter\Formatter $formatter)
    {
        $this->formatters[] = $formatter;

        return $this;
    }
}