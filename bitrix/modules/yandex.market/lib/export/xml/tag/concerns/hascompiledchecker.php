<?php
namespace Yandex\Market\Export\Xml\Tag\Concerns;

use Yandex\Market\Export\Xml\Data\ExportElement;

interface HasCompiledChecker
{
    public function checkCompiled(ExportElement $node, ExportElement $parent);
}