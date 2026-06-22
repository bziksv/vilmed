<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;
use Bitrix\Main;

/** @noinspection PhpUnused */
class DateType extends AbstractType
{
    use Concerns\HasMessage;

    protected $settings = [
        'date_format' => 'Y-m-d H:i:s',
    ];

    public function type()
    {
        return Manager::TYPE_DATE;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
        $dateTime = $this->cast($value);

		if ($dateTime === null)
		{
            return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
		}

        return $dateTime->format($this->setting('date_format', $settings));
	}

	protected function cast($value)
	{
		if ($value instanceof Main\Type\Date)
		{
			return $value;
		}

        if ($value instanceof \DateTime)
		{
			return Main\Type\DateTime::createFromPhp($value);
		}

        if (is_numeric($value)) // is timestamped
        {
			return $value > 1000000 ? Main\Type\DateTime::createFromTimestamp($value) : null;
        }

		if (is_string($value))
		{
			try
			{
				return new Main\Type\DateTime($value);
			}
			catch (Main\ObjectException $exception)
			{
				trigger_error($exception->getMessage(), E_USER_WARNING);
			}
		}

		return null;
	}
}