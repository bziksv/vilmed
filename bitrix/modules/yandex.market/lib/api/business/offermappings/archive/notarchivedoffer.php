<?php
namespace Yandex\Market\Api\Business\OfferMappings\Archive;

use Yandex\Market\Api\Reference;
use Yandex\Market\Reference\Concerns;

class NotArchivedOffer extends Reference\Model
{
    use Concerns\HasMessage;

    const ERROR_OFFER_HAS_STOCKS = 'OFFER_HAS_STOCKS';
    const ERROR_UNKNOWN = 'UNKNOWN';

    public function getOfferId()
    {
        return (string)$this->requireField('offerId');
    }

    public function getError()
    {
        return (string)$this->getField('error');
    }

    public function getErrorMessage()
    {
        $error = $this->getError();

        if ($error === self::ERROR_OFFER_HAS_STOCKS || $error === self::ERROR_UNKNOWN)
        {
            return self::getMessage('ERROR_' . $error);
        }

        return self::getMessage('ERROR_UNDEFINED', [ '#ERROR#' => $error ]);
    }
}