<?
namespace Arturgolubev\Chatgpt\Tasks;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Hl,
    \Arturgolubev\Chatgpt\Tasks;

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/admin/automatic_tasks.php");
    
class Element {
    static function getTaskWorkElements($task_id, $limit = 1){
		$result = Tasks\Logic::uniGetlist('elements', [
			'order' => ["ID" => "ASC"],
			"filter" => ['=UF_TASK' => $task_id, '=UF_STATUS' => ''],
			"limit" => $limit,
		]);

		return $result;
	}
    static function getTaskElementByID($task_element_id){
		$result = Tasks\Logic::uniGetlist('elements', [
			"filter" => ['ID' => $task_element_id]
		], 1);
		
		return $result;
	}
    static function getAddElementsToTask($postFields){
		$result = [
			'add' => 0,
			'skip' => 0,
		];
		
		if($postFields['task_id'] == 'new'){
			$data = Tasks\Task::createTask([
				'iblock_id' => $postFields['params']['ibid'],
				'name' => 'new',
				'entity_type' => ($postFields['params']['entity'] == 'elements' ? 'E' : 'S')
			]);

			if($data['error_message']){
				$result['error_message'] = $data['error_message'];
			}else{
				$task_id = $result['new_task_id'] = $data['id'];
			}
		}else{
			$task_id = intval($postFields['task_id']);
		}

		if(!$result['error_message']){
			$result['task_id'] = $task_id;

			$elementClass = Tasks\Logic::getTaskElementClass();
			if($elementClass){
				$arSkip = [];

				$elements = Tasks\Logic::uniGetlist('elements', [
					"select" => ["UF_ELEMENT"],
					"filter" => ['=UF_TASK' => $task_id, '=UF_ELEMENT' => $postFields['elements']]
				]);

				foreach($elements as $item){
					$arSkip[] = $item['UF_ELEMENT'];
				}

				foreach($postFields['elements'] as $elementId){
					if(in_array($elementId, $arSkip)){
						$result['skip']++;
						continue;
					}

					$data = [
						"UF_TASK" => $task_id,
						"UF_ELEMENT" => $elementId,
					];

					$addResult = $elementClass::add($data);
					if(!$addResult->isSuccess()){
						$result['error_message'] = implode('; ', $addResult->getErrorMessages());
						break;
					}
					
					$result['add']++;
				}
			}
		}

		return $result;
	}
    
	static function checkElementInTask($taskID, $elementID){
		$elements = Tasks\Logic::uniGetlist('elements', [
			"select" => ["UF_ELEMENT"],
			"filter" => ['=UF_TASK' => $taskID, '=UF_ELEMENT' => $elementID]
		]);
		
		$result = (is_array($elements) && count($elements));

		return $result;
	}

	static function addElementToTask($taskID, $elementID){
		$result = [];

		$elementClass = Tasks\Logic::getTaskElementClass();
		if($elementClass){
			$data = [
				"UF_TASK" => $taskID,
				"UF_ELEMENT" => $elementID,
			];

			$addResult = $elementClass::add($data);
			if(!$addResult->isSuccess()){
				$result['error_message'] = implode('; ', $addResult->getErrorMessages());
			}else{
				$result['success'] = 1;
			}
		}

		return $result;
	}

	static function update($id, $data){
		$elementClass = Tasks\Logic::getTaskElementClass();
		
		if($data['UF_PARAMS']){
			$data['UF_PARAMS'] = Json::encode($data['UF_PARAMS']);
		}
		
		$r = $elementClass::update($id, $data);

        return $r->isSuccess();
	}

    static function clean($id){
		return self::update($id, [
            'UF_STATUS' => '',
            'UF_GENERATION_DATE' => '',
            'UF_GENERATION_RESULT' => '',
            'UF_VALUE_BACKUP' => '',
            'UF_PARAMS' => '',
        ]);
	}

    static function returnBackup($element_id){
		$result = [
			'success' => 0,
			'error_message' => '',
		];

		$elementData = self::getTaskElementByID($element_id);
		if($elementData){
			$taskData = Tasks\Task::getTaskByID($elementData['UF_TASK']);
			if(is_array($taskData)){
				$saveParams = [
					'genresult' => $elementData['UF_VALUE_BACKUP'],
					'savefield' => $taskData['UF_PARAMS']['save_field'],
					'ID' => $elementData['UF_ELEMENT'],
					'IBLOCK_ID' => $taskData['UF_IBLOCK'],
				];

				if($saveParams['savefield'] && $saveParams['ID'] && $saveParams['IBLOCK_ID']){
					if($taskData['UF_ETYPE'] == 'E'){
						$saveResult = \CArturgolubevChatgpt::saveToElement($saveParams);
					}else{
						$saveResult = \CArturgolubevChatgpt::saveToSection($saveParams);
					}

					if($saveResult['error_message']){
						$result['error_message'] = $saveResult['error_message'];
					}else{
						$result['success'] = 1;
						self::clean($element_id);
					}
				}else{
					$result['error_message'] = 'Not enough data for return';
				}
			}else{
				$result['error_message'] = 'Task Not Found';
			}
		}else{
			$result['error_message'] = 'Element Not Found';
		}

		return $result;
	}

	static function deleteTaskElement($element_id){
		// $elementClass = Tasks\Logic::getTaskElementClass();
		// $elementClass::delete($element_id);
		self::deleteTaskElements([$element_id]);
	}

	static function deleteTaskElements($elementIds){
		$elementClass = Tasks\Logic::getTaskElementClass();
		if(is_array($elementIds) && !empty($elementIds)){
			$elements = Tasks\Logic::uniGetlist('elements', [
				'select' => ["ID"],
				'order' => ["ID" => "ASC"],
				"filter" => ['=ID' => $elementIds]
			]);

			foreach($elements as $element){
				$elementClass::delete($element['ID']);
			}
		}
	}
}