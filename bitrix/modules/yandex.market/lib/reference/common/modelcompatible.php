<?php
/** @noinspection PhpDeprecationInspection */
namespace Yandex\Market\Reference\Common;

use Yandex\Market\Reference\Assert;

trait ModelCompatible
{
	/** @deprecated */
	public function setCollection(Collection $collection)
	{
		$this->setParentCollection($collection);
	}

	/** @deprecated */
	protected function getChildCollection($fieldKey)
	{
		$reference = $this->getChildCollectionReference();

		Assert::notNull($reference[$fieldKey], sprintf('reference[%s]', $fieldKey));
		Assert::isSubclassOf($reference[$fieldKey], Collection::class);

		return $this->getCollection($fieldKey, $reference[$fieldKey]);
	}

	/** @deprecated */
	protected function getChildCollectionReference()
	{
		return [];
	}

	/** @deprecated */
	protected function getChildModel($fieldKey)
	{
		$reference = $this->getChildModelReference();

		Assert::notNull($reference[$fieldKey], sprintf('reference[%s]', $fieldKey));
		Assert::isSubclassOf($reference[$fieldKey], Collection::class);

		return $this->getModel($fieldKey, $reference[$fieldKey]);
	}

	/** @deprecated */
	protected function getChildModelReference()
	{
		return [];
	}
}