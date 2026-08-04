<?
namespace Arturgolubev\Chatgpt;

use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Encoding,
	\Arturgolubev\Chatgpt\Tools,
	\Arturgolubev\Chatgpt\Unitools as UTools;

class Ehandlers {
	static function addActionMenu($list){		
		if(!Loader::IncludeModule(\CArturgolubevChatgpt::MODULE_ID) || !defined("ADMIN_SECTION")) return $list;
			
		foreach(['tbl_iblock_element', 'tbl_iblock_list', 'tbl_iblock_section', 'tbl_product_list', 'tbl_product_admin'] as $tName){
			if(Encoding::exStripos($list->table_id, $tName) !== false){
				$isElementsPage = 1;
			}
		}
		
		if($isElementsPage){
			if($_GET["IBLOCK_ID"]){
				$question = Tools::checkRights('question');
				$tasks = Tools::checkRights('tasks');

				if($question || $tasks){
					$tmp = $list->arActions;
					
					$list->arActions = [];

					if($question){
						$list->arActions["agcg_generate"] = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MASS_GENERATE_BUTTON");
					}

					if(intval(UTools::getSetting('hl_id_tasks')) && $tasks){
						$list->arActions["agcg_queue"] = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MASS_QUEUE_BUTTON");
					}
					
					foreach($tmp as $k=>$v){
						$list->arActions[$k] = $v;
					}
				}
			}
		}
		
		return $list;
	}
}