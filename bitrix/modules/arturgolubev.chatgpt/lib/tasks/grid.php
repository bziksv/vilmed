<?
namespace Arturgolubev\Chatgpt\Tasks;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Hl,
    \Arturgolubev\Chatgpt\Tasks;

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/admin/automatic_tasks.php");

class Grid {
	static function listTaskElements($task_id, $sort, $nav, $filterData){
        $result = $elements = [];

		$ibElements = $ibElementsData = [];

		$taskData = Tasks\Task::getTaskByID($task_id);
		
		$data_class = Tasks\Logic::getTaskElementClass();
		if($data_class){
			$filter = ['=UF_TASK' => $task_id];

			$rsData = $data_class::getList([
				"select" => ['*'],
				"order" => ['ID' => 'ASC'],
				"filter" => $filter,
				'limit' => $nav->getLimit(),
				'offset' => $nav->getLimit()*($nav->getCurrentPage()-1),
			]);
			while($arData = $rsData->Fetch()){
				if($arData["UF_PARAMS"]){
					$arData["UF_PARAMS"] = Json::decode($arData["UF_PARAMS"]);
				}

				if($arData['UF_ELEMENT']){
					$ibElements[] = $arData['UF_ELEMENT'];
				}

				$elements[] = $arData;
			}

			$count = $data_class::getCount($filter);
			$nav->setRecordCount($count);
		}

		if($taskData['UF_ETYPE'] == 'S'){
			if(count($ibElements)){
				$rdbSection = \CIBlockSection::GetList(
					['SORT' => 'ASC'],
					['ID' => $ibElements],
					false,
					$lstSelect = ['ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_TYPE_ID'],
					false
				);
				while($dctSection = $rdbSection->fetch()) {
					$ibElementsData[$dctSection['ID']] = $dctSection;
				}
			}
		}else{
			if(count($ibElements)){
				$lstSelect = [
					'IBLOCK_ID',
					'IBLOCK_TYPE_ID',
					'ID',
					'NAME',
				];
				$rdb = \CIBlockElement::GetList([], ['ID' => $ibElements], false, false, $lstSelect);
				while($dctElement = $rdb->fetch()) {
					$ibElementsData[$dctElement['ID']] = $dctElement;
				}
			}
		}

		foreach($elements as $element_item){
			$error = '';

			if(is_array($element_item['UF_PARAMS']) && $element_item['UF_PARAMS']['error_type']){
				$error = $element_item['UF_PARAMS']['error_message'].' ['.$element_item['UF_PARAMS']['error_type'].']';
			}

			$curIbElement = $ibElementsData[$element_item['UF_ELEMENT']];

			$elementData = $curIbElement['NAME'];
			$elementData .= ' [<a href="/bitrix/admin/'.($taskData['UF_ETYPE'] == 'S' ? 'iblock_section_edit': 'iblock_element_edit').'.php?IBLOCK_ID='.$curIbElement['IBLOCK_ID'].'&type='.$curIbElement['IBLOCK_TYPE_ID'].'&lang=ru&ID='.$curIbElement['ID'].'&WF=Y" target="_blank">'.$element_item['UF_ELEMENT'].'</a>]';

			$actions = [
				[
					'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_MOREINFO"),
					'onclick' => 'agcg.showTaskElementInfoWindow('.$element_item['ID'].');'
				],
				[
					'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_DELETE_ELEMENT"),
					'onclick' => 'agcg.deleteTaskElementConfirm('.$element_item['ID'].');'
				],
			];

			if($element_item['UF_GENERATION_RESULT']){
				$actions[] = [
					'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_READD_ELEMENT"),
					'onclick' => 'agcg.readdTaskElement('.$element_item['ID'].');'
				];
				$actions[] = [
					'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_ELEMENTS_LIST_TABLE_ACTION_RETURN_DATA_ELEMENT"),
					'onclick' => 'agcg.returnDataTaskElement('.$element_item['ID'].');'
				];
			}

			$result[] = [
				'data' => [
					'ID' => $element_item['ID'],
					'ELEMENT' => $elementData,
					'STATUS' => $element_item['UF_STATUS'],
					'STATUS_FORMAT' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_TASKS_EDIT_ELEMENTS_FIELD_STATUS_'.$element_item['UF_STATUS']),
					'ERROR_TEXT' => $error,
					'GENERATION_DATE' => $element_item['UF_GENERATION_DATE'],
					'GENERATION_RESULT' => $element_item['UF_GENERATION_RESULT'],
				],
				'actions' => $actions
			];
		}

        return $result;
    }

    static function listTasks($sort, $nav, $filterData){
        $result = $tasks =[];
		
		global $APPLICATION;

		$data_class = Tasks\Logic::getTaskClass();
		if($data_class){
			$rsData = $data_class::getList([
				'select' => ["*"],
				'order' => ["ID" => "DESC"],
				"filter" => [],
				'limit' => $nav->getLimit(),
				'offset' => $nav->getLimit()*($nav->getCurrentPage()-1),
			]);
			while($arData = $rsData->Fetch()){
				if($arData["UF_PARAMS"]){
					$arData["UF_PARAMS"] = Json::decode($arData["UF_PARAMS"]);
				}

				$tasks[] = $arData;
			}

			$count = $data_class::getCount([]);
			$nav->setRecordCount($count);
		}
		
		foreach($tasks as $tid => $task){
			$elementsFull = Tasks\Logic::uniGetlist('elements', [
				'select' => ['ID'],
				"filter" => ['=UF_TASK' => $task['ID']]
			]);

			if(count($elementsFull)){
				$elementsEmpty = Tasks\Logic::uniGetlist('elements', [
					'select' => ['ID'],
					"filter" => ['=UF_TASK' => $task['ID'], '!UF_STATUS' => '']
				]);
			}

			$tasks[$tid]['ELEMENTS'] = (count($elementsFull) ? count($elementsEmpty).' / '.count($elementsFull) : 0);
		}
		
		foreach($tasks as $task_item){
			$page = $APPLICATION->GetCurPageParam("action=tasks_edit&id=".$task_item['ID'], ['id', 'bxajaxid', 'grid_action', 'grid_id', 'action']);

			$result[] = [
				'data' => [
					'ID' => $task_item['ID'],
					'NAME' => $task_item['UF_NAME'],
					'IBLOCK' => $task_item['UF_IBLOCK'],
					'STATUS' => $task_item['UF_STATUS'],
					'STATUS_FORMAT' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_STATUS_'.$task_item['UF_STATUS']),
					'PROMPT' => $task_item['UF_PROMPT'],
					'ETYPE' => $task_item['UF_ETYPE'],
					'ETYPE_FORMAT' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_'.$task_item['UF_ETYPE']),
					'ELEMENTS' => $task_item['ELEMENTS'],
				],
				'actions' => [
					[
						'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_EDIT"),
						'onclick' => 'document.location.href="'.$page.'"'
					],
					[
						'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_COPY"),
						'onclick' => 'agcg.taskListCopy('.$task_item['ID'].');'
					],
					[
						'text' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ACTION_DELETE"),
						'onclick' => 'agcg.taskListDelete('.$task_item['ID'].');'
					],
				]
			];
		}

        return $result;
    }
}