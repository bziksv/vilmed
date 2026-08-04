<?
namespace Arturgolubev\Chatgpt\Admin;

use \Bitrix\Main\Config\Option,
	\Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Tools;

class SettingsHelper {
	const MODULE_ID = 'arturgolubev.chatgpt';
	
	static function baseWorkers(){
		Tools::remakeProxy();
		Tools::checkHlTemplate();

		self::tests();
	}

	static function tests(){
		// $r = \CArturgolubevChatgpt::checkSberToken();
		// echo '<pre>'; print_r($r); echo '</pre>';

		// $r = \CArturgolubevChatgpt::getSberToken();
		// echo '<pre>'; print_r($r); echo '</pre>';


		// $r = \CArturgolubevChatgpt::getCallParams('MY QUESTION');
		// echo '<pre>'; print_r($r); echo '</pre>';
		
		// $a = UTools::getCurPage();
		// echo '<pre>'; print_r($a); echo '</pre>';
	}

	static function checkModuleRules(){
		$arSearchNoteSettings = array();

		if (!function_exists('curl_init')){
			$arSearchNoteSettings[] = Loc::getMessage("ARTURGOLUBEV_CHATGPT_CURL_NOT_FOUND");
		}
		
		if(count($arSearchNoteSettings)>0){
			\CAdminMessage::ShowMessage(array("DETAILS"=>implode('<br>', $arSearchNoteSettings), "MESSAGE" => Loc::getMessage("ARTURGOLUBEV_CHATGPT_ERROS_SETTING_TITLE"), "HTML"=>true));
		}
	}
	
	static function getUserGroups(){
		$items = ['' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_SELECTBOX_NO_SELECT')];
		
		$arSkip = array(1, 2, 3, 4, 5);
		
		$rsGroups = \CGroup::GetList($by = "c_sort", $order = "asc", array());
		while($arGroups = $rsGroups->Fetch()){
			if(in_array($arGroups["ID"], $arSkip)) continue;
			$items[$arGroups["ID"]] = $arGroups["NAME"].' ['.$arGroups["ID"].']';
		}
		
		return $items;
	}
}