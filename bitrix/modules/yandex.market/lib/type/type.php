<?php
namespace Yandex\Market\Type;

use Yandex\Market\Error;

interface Type
{
    /** @return string */
    public function type();

    /** @return Error\XmlNode|mixed|null */
    public function sanitize($value, array $context = [], array $settings = null);

    /** @return Type */
    public function configure(array $parameters);

    public function setFormatters(array $formatters);

    public function addFormatter(Formatter\Formatter $formatter);

    public function setOverrides(array $overrides);
}