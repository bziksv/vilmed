<?php
namespace Yandex\Market\Type;

use Yandex\Market\Reference\Concerns;

class StringType extends AbstractType
{
    use Concerns\HasMessage;

    protected $settings = [
        'max_length' => null,
    ];

    public function type()
    {
        return Manager::TYPE_STRING;
    }

	public function sanitize($value, array $context = [], array $settings = null)
	{
        if (!is_scalar($value)) { return null; }

        $value = trim(strip_tags((string)$value));

		if ($value === '') { return null; }

        $maxLength = $this->setting('max_length', $settings);

		if ($maxLength !== null)
		{
            $value = $this->truncateText($value, $maxLength);
		}

		return $value;
	}

	protected function truncateText($text, $maxLength)
	{
		$result = $text;

		if (mb_strlen($result) > $maxLength)
		{
			$suffix = self::getMessage('TRUNCATE', null, '...');
			$suffixLength = mb_strlen($suffix);

			$result = mb_substr($result, 0, $maxLength - $suffixLength);
			$result = rtrim($result, '.') . $suffix;
		}

		return $result;
	}
}