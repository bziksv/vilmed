<?php
namespace Yandex\Market\Component\Model;

use Yandex\Market;

class GridList extends Market\Component\Data\GridList
{
	/** @return class-string<Market\Reference\Storage\Model> */
	protected function getModelClass()
	{
		$className = $this->getComponentParam('MODEL_CLASS_NAME');

		Market\Reference\Assert::nonEmptyString($className, 'arParams[MODEL_CLASS_NAME]');
		Market\Reference\Assert::isSubclassOf($className, Market\Reference\Storage\Model::class);

		return $className;
	}

	/** @return class-string<Market\Reference\Storage\Table> */
	protected function getDataClass()
	{
        $modelClass = $this->getModelClass();

        return $modelClass::getDataClass();
	}
}