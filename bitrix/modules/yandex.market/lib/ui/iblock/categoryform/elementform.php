<?php
namespace Yandex\Market\Ui\Iblock\CategoryForm;

interface ElementForm extends Form
{
	/** @return int|null */
	public function elementId();
}