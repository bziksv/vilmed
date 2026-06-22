<?php
namespace Yandex\Market\Component\Base;

use Yandex\Market\Components;
use Bitrix\Main;

/** @property Components\AdminFormEdit $component */
abstract class EditForm extends AbstractProvider
{
	/** @return array */
	public function modifyRequest(array $request, array $fields)
	{
		return $request;
	}

	/** @return array */
	abstract public function getFields(array $select = [], array $item = null);

	/** @return array */
	abstract public function load($primary, array $select = [], $isCopy = false);

	public function initial(array $select = [])
	{
		return [];
	}

	/** @return array */
	public function extend(array $data, array $fields)
	{
		return $data;
	}

	/**@return Main\Entity\Result */
	abstract public function validate(array $data, array $fields);

	/** @return Main\Entity\AddResult */
	abstract public function add(array $data);

	/** @return Main\Entity\UpdateResult */
	abstract public function update($primary, array $data);
}