<?php
namespace Yandex\Market\Error;

class SkipError extends Base
{
    public function __construct($message = 'skip', $code = 0, $customData = null)
    {
        parent::__construct($message, $code, $customData);
    }
}