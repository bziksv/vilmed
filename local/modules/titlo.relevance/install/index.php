<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class titlo_relevance extends CModule
{
	public $MODULE_ID = 'titlo.relevance';
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
		$this->MODULE_NAME = Loc::getMessage('TITLO_RELEVANCE_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('TITLO_RELEVANCE_MODULE_DESC');
		$this->PARTNER_NAME = Loc::getMessage('TITLO_RELEVANCE_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('TITLO_RELEVANCE_PARTNER_URI');
	}

	public function DoInstall()
	{
		$this->InstallDB();
		$this->InstallFiles();
		$this->InstallEvents();
		return true;
	}

	public function DoUninstall()
	{
		$this->UnInstallEvents();
		$this->UnInstallFiles();
		$this->UnInstallDB();
		return true;
	}

	public function InstallDB()
	{
		global $DB;

		if (!ModuleManager::isModuleInstalled($this->MODULE_ID)) {
			ModuleManager::registerModule($this->MODULE_ID);
		}

		if (class_exists('\Bitrix\Main\ModuleTable')) {
			\Bitrix\Main\ModuleTable::getEntity()->cleanCache();
		}

		$DB->Query("
			CREATE TABLE IF NOT EXISTS titlo_relevance_queue (
				ID int(11) NOT NULL AUTO_INCREMENT,
				ENTITY_TYPE char(1) NOT NULL DEFAULT 'E',
				ENTITY_ID int(11) NOT NULL DEFAULT 0,
				URL varchar(2048) NOT NULL DEFAULT '',
				PHRASE varchar(255) NOT NULL DEFAULT '',
				STATUS varchar(32) NOT NULL DEFAULT 'queued',
				HISTORY_ID int(11) DEFAULT NULL,
				ANALYSIS_ID varchar(64) DEFAULT NULL,
				ERROR text,
				CREATED_AT datetime DEFAULT NULL,
				UPDATED_AT datetime DEFAULT NULL,
				PRIMARY KEY (ID),
				KEY ix_status (STATUS),
				KEY ix_entity (ENTITY_TYPE, ENTITY_ID)
			)
		");

		if (\Bitrix\Main\Loader::includeModule('iblock')) {
			require_once __DIR__ . '/../lib/Config.php';
			require_once __DIR__ . '/../lib/UserFields.php';
			\Titlo\Relevance\UserFields::ensurePhraseField((int) Option::get($this->MODULE_ID, 'iblock_id', 0));
			Option::set($this->MODULE_ID, 'uf_version', '1.1.1');
		}

		$agentName = '\\Titlo\\Relevance\\Agent::run();';
		$rs = CAgent::GetList([], ['NAME' => $agentName, 'MODULE_ID' => $this->MODULE_ID]);
		if (!$rs->Fetch()) {
			CAgent::AddAgent(
				$agentName,
				$this->MODULE_ID,
				'N',
				60,
				'',
				'Y',
				'',
				100
			);
		}

		return true;
	}

	public function UnInstallDB()
	{
		global $DB;

		$res = CAgent::GetList(['ID' => 'DESC'], ['MODULE_ID' => $this->MODULE_ID]);
		while ($arRes = $res->Fetch()) {
			CAgent::Delete($arRes['ID']);
		}

		$DB->Query('DROP TABLE IF EXISTS titlo_relevance_queue');
		$DB->Query('DROP TABLE IF EXISTS titlo_phrase_jobs');
		$DB->Query('DROP TABLE IF EXISTS titlo_phrase_batches');
		$DB->Query('DROP TABLE IF EXISTS titlo_prompts');
		$DB->Query('DROP TABLE IF EXISTS titlo_work_history');

		Option::delete($this->MODULE_ID);
		ModuleManager::unRegisterModule($this->MODULE_ID);

		if (class_exists('\Bitrix\Main\ModuleTable')) {
			\Bitrix\Main\ModuleTable::getEntity()->cleanCache();
		}

		return true;
	}

	public function InstallFiles()
	{
		CopyDirFiles(
			__DIR__ . '/admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
			true,
			true
		);
		return true;
	}

	public function UnInstallFiles()
	{
		DeleteDirFiles(
			__DIR__ . '/admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
		);
		return true;
	}

	public function InstallEvents()
	{
		return true;
	}

	public function UnInstallEvents()
	{
		return true;
	}
}
