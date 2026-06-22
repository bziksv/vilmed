<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;

/** @noinspection PhpUnused */
class DatePeriodType extends AbstractType
{
	protected $periodType;
	protected $dateType;

	public function __construct(array $parameters = null)
	{
        parent::__construct($parameters);

		$this->periodType = new PeriodType();
        $this->dateType = new DateType();
	}

    public function type()
    {
        return Manager::TYPE_DATEPERIOD;
    }

    public function sanitize($value, array $context = [], array $settings = null)
	{
		$periodSanitized = $this->periodType->sanitize($value, $context, $settings);

		if (!($periodSanitized instanceof Error\Base))
        {
            return $periodSanitized;
        }

		$dateSanitized = $this->dateType->sanitize($value, $context, $settings);

        if (!($dateSanitized instanceof Error\Base))
        {
            return $dateSanitized;
        }

        if ($periodSanitized->getCode() === 'NUMBER_NOT_FOUND')
        {
            return $dateSanitized;
        }

        return $periodSanitized;
    }
}