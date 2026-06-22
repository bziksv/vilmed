<?php

namespace Yandex\Market\Component\Base;

use Yandex\Market\Components\AdminFormEdit;
use Yandex\Market\Components\AdminGridList;
use Bitrix\Main;

abstract class AbstractProvider
{
	/** @var AdminFormEdit|AdminGridList */
	protected $component;

	public function __construct(\CBitrixComponent $component, array $componentParameters = [])
	{
		$this->component = $component;
	}

	public function prepareComponentParams(array $componentParameters)
	{
		return $componentParameters;
	}

	public function getComponentResult($key)
	{
		return isset($this->component->arResult[$key])
			? $this->component->arResult[$key]
			: null;
	}

	public function setComponentResult($key, $value)
	{
		$this->component->arResult[$key] = $value;
	}

	/** @deprecated */
	public function getRequiredParams()
	{
		return [];
	}

	/** @deprecated */
	public function getRequiredModules()
	{
		return [];
	}

	public function getComponentParam($key)
	{
		return isset($this->component->arParams[$key])
			? $this->component->arParams[$key]
			: null;
	}

	public function setComponentParam($key, $value)
	{
		$this->component->arParams[$key] = $value;
	}

	public function getComponentLang($key, $replaces = null)
	{
		return $this->component->getLang($key, $replaces);
	}

	/** @return array|null */
	public function processAjaxAction($action, $data)
	{
		throw new Main\NotImplementedException('ACTION_NOT_FOUND');
	}

	public function processPostAction($action, $data)
	{
		throw new Main\NotImplementedException('ACTION_NOT_FOUND');
	}
}