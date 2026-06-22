<?php
namespace Yandex\Market\Api\Business\OfferMappings\Update;

use Yandex\Market\Api\Reference\Model;

class OfferError extends Model
{
    public function getType()
    {
        return (string)$this->requireField('type');
    }

    public function getMessage()
    {
        return (string)$this->requireField('message');
    }

    /** @return int|null */
    public function getParameterId()
    {
        return $this->getField('parameterId');
    }

	public function errorMessage()
	{
		$message = "[{$this->getType()}] {$this->getMessage()}";
		$parameterId = $this->getParameterId();

		if ($parameterId !== null)
		{
			$message .= "({$parameterId})";
		}

		return $message;
	}
}