<?php
namespace Yandex\Market\Type\Decorator;

use Yandex\Market\Error;
use Yandex\Market\Type;
use Yandex\Market\Reference\Concerns;

class PackRatio extends Type\AbstractType
{
    use Concerns\HasMessage;

    private $decorated;
    private $inverted;
    private $numberType;

    public function __construct(Type\Type $type, $inverted = false)
    {
        parent::__construct();

        $this->decorated = $type;
        $this->inverted = $inverted;
        $this->numberType = new Type\NumberType([
            'value_precision' => 4,
            'value_positive' => true,
        ]);
    }

    public function type()
    {
        return $this->decorated->type();
    }

    public function sanitize($value, array $context = [], array $settings = null)
    {
        $packSettings = $this->extendSettings($settings);

        if ($packSettings instanceof Error\Base)
        {
            $sanitized = $this->decorated->sanitize($value, $context, $settings);

            if ($sanitized === null || $sanitized instanceof Error\Base) { return $sanitized; }

            return $packSettings;
        }

        return $this->decorated->sanitize($value, $context, $packSettings);
    }

    private function extendSettings(array $settings = null)
    {
        if (!isset($settings['pack_ratio']) || !is_scalar($settings['pack_ratio']) || (string)$settings['pack_ratio'] === '')
        {
            return $settings;
        }

        $value = $this->numberType->sanitize($settings['pack_ratio']);

        if ($value instanceof Error\Base)
        {
            $message = $value->getMessage();
            $message = self::getMessage('ERROR', [ '#MESSAGE#' => $message ], $message);

            return new Error\XmlNode($message, $value->getCode());
        }

        $ratio = (float)$value;

        if ($this->inverted)
        {
            if ($ratio === 0.0) { return $settings; }

            $ratio = (1 / $ratio);
        }

        return $settings + [
            'value_ratio' => $ratio,
        ];
    }
}