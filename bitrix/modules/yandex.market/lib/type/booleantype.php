<?php
namespace Yandex\Market\Type;

use Yandex\Market\Reference\Concerns as GlobalConcerns;

class BooleanType extends AbstractType
    implements Concerns\HasRecommendation
{
    use GlobalConcerns\HasMessage;
    use GlobalConcerns\HasOnceStatic;

    public function type()
    {
        return Manager::TYPE_BOOLEAN;
    }

    public function recommendation(array $context = [])
    {
        return [
            [
                'VALUE' => 'Y',
                'DISPLAY' => self::getMessage('Y'),
            ],
            [
                'VALUE' => 'N',
                'DISPLAY' => self::getMessage('N'),
            ],
        ];
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		if (is_array($value)) { $value = reset($value); }

		if (empty($value) || $value === 'N')
		{
            return false;
        }

        if ($value === 'Y' || $value === true || !is_scalar($value))
        {
            return true;
        }

        if (is_numeric($value))
        {
            return $value > 0;
        }

        $negativeValues = static::negativeValues();
        $value = mb_strtoupper(trim($value));

		return !isset($negativeValues[$value]);
	}

	protected static function negativeValues()
	{
        return self::onceStatic('negativeValues', static function() {
            return array_flip(explode(',', self::getMessage('NEGATIVE'))) + [
                'N' => true,
                'FALSE' => true,
                '0' => true,
            ];
        });
	}
}