<?php
namespace Yandex\Market\Export\Xml\Tag;

use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns as GlobalConcerns;
use Yandex\Market\Export\Xml;

class Restrictions extends Base
    implements Concerns\HasCompiledChecker, Concerns\HasTagValueChecker
{
	use GlobalConcerns\HasMessage;

	public function getDefaultParameters()
	{
		return [
			'name' => 'restrictions',
		];
	}

	public function checkTagValue($tagValue)
	{
		return $tagValue === null && !$this->isRequired ? new Error\SkipError() : null;
	}

	public function checkCompiled(Xml\Data\ExportElement $node, Xml\Data\ExportElement $parent)
    {
		return $this->checkTagChildren($node) ?: $this->checkTagWholesale($node, $parent);
	}

	private function checkTagChildren(Xml\Data\ExportElement $node)
	{
		foreach ($node->getChildren() as $first)
		{
			$hasTrue = false;

			foreach ($first->getChildren() as $second)
			{
				if ($second->getValue() === true)
				{
					$hasTrue = true;
					break;
				}
			}

			if (!$hasTrue)
			{
				$error = new Error\XmlNode(self::getMessage('ONE_CHILD_MUST_BE_POSITIVE', [
					'#TAG#' => $first->getName(),
				]));
				$error->markCritical();

				return $error;
			}
		}

		return null;
	}

    private function checkTagWholesale(Xml\Data\ExportElement $node, Xml\Data\ExportElement $parent)
	{
		$tradingNode = $node->getChild('trading')[0];
		$wholesaleNode = $tradingNode !== null ? $tradingNode->getChild('wholesale')[0] : null;

		if ($wholesaleNode === null || $wholesaleNode->getValue() !== true) { return true; }

		$priceTagName = $this->getParameter('wholesalePrice');
		$priceTags = $parent->getChild($priceTagName);

		if (empty($priceTags))
		{
			$error = new Error\XmlNode(self::getMessage('WHOLESALE_PRICES_REQUIRED', [
				'#PRICE_TAG#' => $priceTagName,
				'#WHOLESAGE_TAG#' => $this->id . '.trading.wholesale',
			]));
			$error->markCritical();

			return $error;
		}

		return null;
	}
}