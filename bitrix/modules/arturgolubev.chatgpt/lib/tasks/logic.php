<?
namespace Arturgolubev\Chatgpt\Tasks;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Hl,
    \Arturgolubev\Chatgpt\Tasks;

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/admin/automatic_tasks.php");
    
class Logic {
	static function getTaskClass(){
		$hlID = UTools::getSetting('hl_id_tasks');
		if($hlID)
			$data_class = Hl::getDataClassByID($hlID);
		
		return $data_class;
	}
	
	static function getTaskElementClass(){
		$hlID = UTools::getSetting('hl_id_task_elements');
		if($hlID)
			$data_class = Hl::getDataClassByID($hlID);
		
		return $data_class;
	}
    
	static function uniGetlist($type, $data, $oneLine = 0){
		$items = [];

		if(!isset($data['select']))
			$data["select"] = ["*"];

		if(!isset($data['order']))
			$data["order"] = ["ID" => "DESC"];

		if($type == 'elements'){
			$data_class = Tasks\Logic::getTaskElementClass();
		}elseif($type == 'tasks'){
			$data_class = Tasks\Logic::getTaskClass();
		}

		if($data_class){
			$rsData = $data_class::getList($data);
			while($arData = $rsData->Fetch()){
				if($arData["UF_PARAMS"]){
					$arData["UF_PARAMS"] = Json::decode($arData["UF_PARAMS"]);
				}

				$items[] = $arData;
			}
		}

		if($oneLine){
			$items = (count($items)) ? $items[0] : false;
		}
		
		return $items;
	}

    // agents
	static function getTaskAgent($task_id){
		$agent_func = 'CArturgolubevChatgpt::taskWorker('.$task_id.');';
		$res = \CAgent::GetList(Array("ID" => "DESC"), array("NAME" => $agent_func));
		if($arRes = $res->GetNext()) {
			return $arRes;
		}
	}
	static function addTaskAgent($task_id){
		$agent_func = 'CArturgolubevChatgpt::taskWorker('.intval($task_id).');';
		\CAgent::AddAgent($agent_func, 'arturgolubev.chatgpt', "N", 60, "", "Y", "", 100);
	}

	static function deleteTaskAgent($task_id){
		$agent = self::getTaskAgent($task_id);
		if(is_array($agent)){
			\CAgent::Delete($agent['ID']);
		}
	}

    // simple
	static function getIblockList(){
		$iblocks = [];
		$res = \CIBlock::GetList(["SORT" => "ASC"], [], false);
		while ($iblock = $res->Fetch()){
			$iblocks[] = [
				"ID" => $iblock["ID"],
				"NAME" => $iblock["NAME"],
				"TYPE_ID" => $iblock["IBLOCK_TYPE_ID"],
			];
		}
		
		return $iblocks;
	}
}