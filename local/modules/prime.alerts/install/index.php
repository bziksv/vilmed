<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class prime_alerts extends CModule
{
	public $MODULE_ID = 'prime.alerts';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_GROUP_RIGHTS = 'N';
	public $PARTNER_NAME;
	public $PARTNER_URI;

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';

		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
		$this->MODULE_NAME = Loc::getMessage('PRIME_ALERTS_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('PRIME_ALERTS_MODULE_DESC');
		$this->PARTNER_NAME = Loc::getMessage('PRIME_ALERTS_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('PRIME_ALERTS_PARTNER_URI');
	}

	public function DoInstall()
	{
		$this->InstallDB();
		$this->InstallEvents();
		return true;
	}

	public function DoUninstall()
	{
		$this->UnInstallEvents();
		$this->UnInstallDB();
		return true;
	}

	public function InstallDB()
	{
		if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) {
			ModuleManager::registerModule($this->MODULE_ID);
		}

		// ModuleManager::getInstalledModules() кеширует ORM на 86400с —
		// без сброса админка может показывать «Не установлен» после install.
		if (class_exists('\Bitrix\Main\ModuleTable')) {
			\Bitrix\Main\ModuleTable::getEntity()->cleanCache();
		}

		return true;
	}

	public function UnInstallDB()
	{
		Option::delete($this->MODULE_ID);
		ModuleManager::unRegisterModule($this->MODULE_ID);

		if (class_exists('\Bitrix\Main\ModuleTable')) {
			\Bitrix\Main\ModuleTable::getEntity()->cleanCache();
		}

		return true;
	}

	public function InstallEvents()
	{
		$this->UnInstallEvents();

		$em = EventManager::getInstance();
		$em->registerEventHandler('main', 'OnBeforeUserRegister', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onBeforeUserRegister');
		$em->registerEventHandler('main', 'OnBeforeUserAdd', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onBeforeUserAdd');
		$em->registerEventHandler('main', 'OnEndBufferContent', $this->MODULE_ID, '\\Prime\\Alerts\\Frontend', 'onEndBufferContent');
		$em->registerEventHandler('sale', 'OnSaleOrderBeforeSaved', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onSaleOrderBeforeSaved');
		return true;
	}

	public function UnInstallEvents()
	{
		$em = EventManager::getInstance();
		$em->unRegisterEventHandler('main', 'OnBeforeUserRegister', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onBeforeUserRegister');
		$em->unRegisterEventHandler('main', 'OnBeforeUserAdd', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onBeforeUserAdd');
		$em->unRegisterEventHandler('main', 'OnAfterUserRegister', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onAfterUserRegister');
		$em->unRegisterEventHandler('main', 'OnEpilog', $this->MODULE_ID, '\\Prime\\Alerts\\Frontend', 'onEpilog');
		$em->unRegisterEventHandler('main', 'OnProlog', $this->MODULE_ID, '\\Prime\\Alerts\\Frontend', 'onProlog');
		$em->unRegisterEventHandler('main', 'OnEndBufferContent', $this->MODULE_ID, '\\Prime\\Alerts\\Frontend', 'onEndBufferContent');
		$em->unRegisterEventHandler('sale', 'OnSaleOrderBeforeSaved', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onSaleOrderBeforeSaved');
		$em->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\\Prime\\Alerts\\Handlers', 'onSaleOrderSaved');
		return true;
	}
}
