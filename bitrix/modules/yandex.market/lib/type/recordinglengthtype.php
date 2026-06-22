<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class RecordingLengthType extends AbstractType
{
    use Concerns\HasMessage;

    public function type()
    {
        return Manager::TYPE_RECORDING_LENGTH;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if (!preg_match('/(\d+)[.:](\d{2})/', $value, $matches))
        {
            return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
        }

        $seconds = (int)$matches[2];

        if ($seconds < 0 || $seconds >= 60)
        {
            return new Error\XmlNode(self::getMessage('ERROR_SECONDS_OUT_OF_BOUND'), 'SECONDS_OUT_OF_BOUND');
        }

		return $this->time($matches[1]) . '.' . $this->time($matches[2]);
	}

    protected function time($value)
    {
        $length = mb_strlen($value);

        if ($length >= 2) { return $value; }

        return str_repeat('0', 2 - $length) . $value;
    }
}