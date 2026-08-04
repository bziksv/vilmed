<?
IncludeModuleLangFile(__FILE__);
include_once $_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/arturgolubev.chatgpt/lib/installation.php';

Class arturgolubev_chatgpt extends CModule
{
	const MODULE_ID = 'arturgolubev.chatgpt';
	var $MODULE_ID = 'arturgolubev.chatgpt'; 
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;
	var $strError = '';

	function __construct()
	{
		$arModuleVersion = array();
		include(dirname(__FILE__)."/version.php");
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = GetMessage("arturgolubev.chatgpt_MODULE_NAME");
		$this->MODULE_DESCRIPTION = GetMessage("arturgolubev.chatgpt_MODULE_DESC");

		$this->PARTNER_NAME = GetMessage("arturgolubev.chatgpt_PARTNER_NAME");
		$this->PARTNER_URI = GetMessage("arturgolubev.chatgpt_PARTNER_URI");
	}

	function InstallDB($arParams = array())
	{
		$agentName = '\Arturgolubev\Chatgpt\Settings::checkModuleUpdates("'.self::MODULE_ID.'");';

		$rsAgent = \CAgent::GetList([], ['NAME' => $agentName]);
		if(!$rsAgent->Fetch()){
			\CAgent::AddAgent(
				$agentName,
				self::MODULE_ID,
				'N',
				86400*30,
				date('d.m.Y H:i:s', strtotime('+1 minutes')), "Y", date('d.m.Y H:i:s', strtotime('+1 minutes')), 100
			);
		}

		RegisterModuleDependences('main', 'OnEpilog', self::MODULE_ID, 'CArturgolubevChatgpt', 'onEpilog');
		RegisterModuleDependences('main', 'OnAdminListDisplay', self::MODULE_ID, 'CArturgolubevChatgpt', 'addActionMenu');
		return true;
	}

	function UnInstallDB($arParams = array())
	{
		
		$res = CAgent::GetList(Array("ID" => "DESC"), array("MODULE_ID" => self::MODULE_ID));
		while($arRes = $res->GetNext()){
			CAgent::Delete($arRes['ID']);
		}

		UnRegisterModuleDependences('main', 'OnEpilog', self::MODULE_ID, 'CArturgolubevChatgpt','onEpilog');
		UnRegisterModuleDependences('main', 'OnAdminListDisplay', self::MODULE_ID, 'CArturgolubevChatgpt', 'addActionMenu');
		
		return true;
	}

	function InstallEvents()
	{
		return true;
	}

	function UnInstallEvents()
	{
		return true;
	}

	function InstallFiles($arParams = array())
	{
		$mPath = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID;
		
		CopyDirFiles($mPath."/install/js", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js",true,true);
		CopyDirFiles($mPath."/install/tools", $_SERVER["DOCUMENT_ROOT"]."/bitrix/tools",true,true);
		
		CopyDirFiles($mPath."/install/admin", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin",true,true);
		
		CopyDirFiles($mPath."/install/themes", $_SERVER["DOCUMENT_ROOT"]."/bitrix/themes", true, true);
		CopyDirFiles($mPath."/install/gadgets", $_SERVER["DOCUMENT_ROOT"]."/bitrix/gadgets",true,true);
		
		return true;
	}

	function UnInstallFiles()
	{
		DeleteDirFilesEx("/bitrix/js/".self::MODULE_ID);
		DeleteDirFilesEx("/bitrix/tools/".self::MODULE_ID);
		
		DeleteDirFilesEx("/bitrix/themes/.default/icons/".self::MODULE_ID."/");
		DeleteDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".self::MODULE_ID."/install/themes/.default/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/themes/.default");
		
		DeleteDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".self::MODULE_ID."/install/admin/", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin");

		return true;
	}

	function DoInstall()
	{
		global $APPLICATION;
		$this->InstallFiles();
		$this->InstallDB();
		RegisterModule(self::MODULE_ID);
		
		
		if (class_exists('agInstaHelperChatgpt')){
			agInstaHelperChatgpt::IncludeAdminFile(GetMessage("MOD_INST_OK"), "/bitrix/modules/".self::MODULE_ID."/install/success_install.php");
		}
	}

	function DoUninstall()
	{
		global $APPLICATION;
		UnRegisterModule(self::MODULE_ID);
		$this->UnInstallDB();
		$this->UnInstallFiles();
	}
}
?>
