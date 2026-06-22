<?php
/** @noinspection PhpDeprecationInspection */
namespace Yandex\Market\Api\Reference;

use Yandex\Market\Reference\Assert;

trait ModelCompatible
{
	/** @deprecated */
	public function getRequiredField($name)
	{
		return $this->requireField($name);
	}

	/** @deprecated */
	protected function getRequiredCollection($fieldKey)
	{
		$reference = $this->getChildCollectionReference();

		Assert::notNull($reference[$fieldKey], sprintf('reference[%s]', $fieldKey));
		Assert::isSubclassOf($reference[$fieldKey], Collection::class);

		return $this->requireCollection($fieldKey, $reference[$fieldKey]);
	}

	/** @deprecated */
	protected function getRequiredModel($fieldKey)
	{
		$reference = $this->getChildCollectionReference();

		Assert::notNull($reference[$fieldKey], sprintf('reference[%s]', $fieldKey));
		Assert::isSubclassOf($reference[$fieldKey], Collection::class);

		return $this->requireModel($fieldKey, $reference[$fieldKey]);
	}
}