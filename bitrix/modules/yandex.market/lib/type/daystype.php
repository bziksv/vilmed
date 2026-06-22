<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;

/** @noinspection PhpUnused */
class DaysType extends AbstractType
{
    use Concerns\HasMessage;

	protected $numberType;

    public function __construct(array $parameters = null)
    {
        parent::__construct($parameters);

        $this->numberType = new NumberType();
    }

    public function type()
    {
        return Manager::TYPE_DAYS;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$partials = $this->cast($value);

		if ($partials === null)
		{
            return new Error\XmlNode('ERROR_NOT_PARSED', 'NOT_PARSED');
		}

        $previousValue = null;

        foreach ($partials as $part)
        {
            $partSanitized = $this->numberType->sanitize($part, $context, $settings);

            if ($partSanitized === null)
            {
                return new Error\XmlNode('ERROR_NOT_PARSED', 'NOT_PARSED');
            }

            if ($partSanitized instanceof Error\Base)
            {
                return $partSanitized;
            }

            if ($previousValue !== null && $previousValue >= $partSanitized)
            {
                return new Error\XmlNode(self::getMessage('ERROR_NOT_ASC_ORDER'), 'NOT_ASC_ORDER');
            }

            $previousValue = $partSanitized;
        }

        return implode('-', $partials);
	}

	protected function cast($value)
	{
        $value = trim($value);

        if (is_numeric($value))
        {
            return [ $value ];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)[^0-9.]{1,3}(\d+(?:\.\d+)?)$/', $value, $matches))
        {
            $result = [];

            for ($i = 1; $i <= 2; $i++)
            {
                $result[] = (int)$matches[$i];
            }

            return $result;
        }

        return null;
	}
}