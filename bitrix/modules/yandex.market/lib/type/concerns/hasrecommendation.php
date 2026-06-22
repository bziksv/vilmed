<?php
namespace Yandex\Market\Type\Concerns;

interface HasRecommendation
{
    /** @return array{TYPE: string|null, VALUE: string, DISPLAY: string|null}[] */
    public function recommendation(array $context = []);
}