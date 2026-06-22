<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;

/** @noinspection PhpUnused */
class CountType extends NumberType
{
    protected $settings = [
        'value_negative' => true,
        'value_positive' => false,
        'value_precision' => 0,
        'value_round' => NumberType::ROUND_FLOOR,
        'value_ratio' => 1,
    ];

    public function type()
    {
        return Manager::TYPE_COUNT;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
        $sanitized = parent::sanitize($value, $context, $settings);

        if ($sanitized === null || $sanitized instanceof Error\XmlNode)
        {
            return $sanitized;
        }

		return max(0, (int)$sanitized);
	}
}