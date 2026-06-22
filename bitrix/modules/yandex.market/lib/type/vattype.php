<?php
namespace Yandex\Market\Type;

use Yandex\Market\Config;
use Yandex\Market\Data;
use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns as GlobalConcerns;

class VatType extends AbstractType
	implements Concerns\HasRecommendation
{
    use GlobalConcerns\HasMessage;

    const FORMAT_NUMERIC = 'numeric';
    const FORMAT_TEXT = 'text';

	const CAST_PERCENT = 'percent';
	const CAST_ID = 'id';

    protected $settings = [
        'value_format' => self::FORMAT_TEXT,
    ];
    protected $idMap;
    protected $valuesMap = [
        'VAT_20' => 7,
        'VAT_10' => 2,
	    'VAT_07' => 11,
	    'VAT_05' => 10,
        'VAT_0' => 5,
        'NO_VAT' => 6,
    ];
	protected $castFormat;

	public function __construct(array $parameters = null)
	{
		parent::__construct($parameters);

		$this->idMap = array_flip($this->valuesMap);
		$this->castFormat = Config::getOption('type_vat_cast', self::CAST_PERCENT);
	}

	public function type()
    {
        return Manager::TYPE_VAT;
    }

	public function recommendation(array $context = [])
	{
		$result = [];

		foreach ($this->valuesMap as $vatCode => $vatId)
		{
			$result[] = [
				'VALUE' => $vatCode,
				'DISPLAY' => Data\Vat::getTitle($vatCode),
			];
		}

		return $result;
	}

	public function sanitize($value, array $context = [], array $settings = null)
	{
		$value = $this->cast($value);

		if ($value === null || !isset($this->valuesMap[$value]))
		{
            return new Error\XmlNode(self::getMessage('ERROR_INVALID'), 'INVALID');
		}

		return $this->output($value, $settings);
	}

	protected function cast($value)
	{
		$value = mb_strtoupper(trim($value));

		if (isset($this->valuesMap[$value]))
		{
			return $value;
		}

        if (!is_numeric($value))
		{
			if (preg_match('/^(\d+)%$/', $value, $matches))
			{
				return $this->castPercent($matches[1]);
			}

			return $value;
		}

		if ($this->castFormat === self::CAST_ID)
		{
			return isset($this->idMap[$value]) ? $this->idMap[$value] : null;
		}

		return $this->castPercent($value);
	}

	private function castPercent($value)
	{
		$value = (int)$value;

		if ($value >= 10)
		{
			return 'VAT_' . $value;
		}

		if ($value > 0)
		{
			return 'VAT_0' . $value;
		}

		return 'NO_VAT';
	}

    protected function output($value, array $settings = null)
    {
        $format = $this->setting('value_format', $settings);

        if ($format === self::FORMAT_TEXT)
        {
            return $value;
        }

        if ($format === self::FORMAT_NUMERIC)
        {
            return $this->valuesMap[$value];
        }

        return $value;
    }
}