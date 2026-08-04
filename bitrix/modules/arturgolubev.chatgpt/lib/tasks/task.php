<?
namespace Arturgolubev\Chatgpt\Tasks;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Hl,
	\Arturgolubev\Chatgpt\Tasks;

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/admin/automatic_tasks.php");

class Task {
    static function createTask($postFields){
		$result = [];
		
		$tasks_class = Tasks\Logic::getTaskClass();
		if($tasks_class){
			$dopData = [
				'provider' => htmlspecialchars($postFields['provider']),
				'save_field' => htmlspecialchars($postFields['save_field']),
				'save_only_empty' => htmlspecialchars($postFields['save_only_empty']),
				'files' => htmlspecialchars($postFields['files']),
			];
			$data = [
				"UF_NAME" => htmlspecialchars($postFields['name']),
				"UF_IBLOCK" => intval($postFields['iblock_id']),
				"UF_ETYPE" => htmlspecialchars($postFields['entity_type']),
				"UF_STATUS" => 'new',
				"UF_PROMPT" => htmlspecialchars($postFields['prompt']),
				"UF_PARAMS" => Json::encode($dopData),
			];

			$addResult = $tasks_class::add($data);
			if(!$addResult->isSuccess()){
				$result['error_message'] = implode('; ', $addResult->getErrorMessages());
			}else{
				$result['id'] = $addResult->getId();
			}
		}
		
		return $result;
	}
	
    static function updateTask($postFields){
		$result = [];

		$tasks_class = Tasks\Logic::getTaskClass();
		if($tasks_class){
			$dopData = [
				'provider' => htmlspecialchars($postFields['provider']),
				'save_field' => htmlspecialchars($postFields['save_field']),
				'save_only_empty' => htmlspecialchars($postFields['save_only_empty']),
				'files' => htmlspecialchars($postFields['files']),
			];

			$data = [
				"UF_NAME" => htmlspecialchars($postFields['name']),
				"UF_PROMPT" => htmlspecialchars($postFields['prompt']),
				"UF_PARAMS" => Json::encode($dopData),
			];

			$addResult = $tasks_class::update($postFields['id'], $data);
			if(!$addResult->isSuccess()){
				$result['error_message'] = implode('; ', $addResult->getErrorMessages());
			}
		}

		return $result;
	}

    static function finishTask($task_id, $data){
		$tasks_class = Tasks\Logic::getTaskClass();
		if($tasks_class){
			Tasks\Logic::deleteTaskAgent($task_id);

			if($data['UF_PARAMS']){
				$data['UF_PARAMS'] = Json::encode($data['UF_PARAMS']);
			}
			
			$tasks_class::update($task_id, $data);
		}
	}

	static function deleteTask($task_id){
		$result = [];

		$taskClass = Tasks\Logic::getTaskClass();
		$elementClass = Tasks\Logic::getTaskElementClass();

		if($task_id && $taskClass && $elementClass){
			Tasks\Logic::deleteTaskAgent($task_id);

			$rsData = $elementClass::getList([
				"select" => ["ID", '*'],
				"order" => ["ID" => "DESC"],
				"filter" => ['=UF_TASK' => $task_id]
			]);
			while($arData = $rsData->Fetch()){
				$elementClass::delete($arData['ID']);
			}

			$r = $taskClass::delete($task_id);
		}

		return $result;
	}

	static function copyTask($task_id){
		$result = [];

		$result['old_task'] = self::getTaskByID($task_id);

		$result['create_task_result'] = self::createTask([
			'provider' => $result['old_task']['UF_PARAMS']['provider'],
			'save_field' => $result['old_task']['UF_PARAMS']['save_field'],
			'name' => $result['old_task']['UF_NAME'].' copy',
			'iblock_id' => $result['old_task']['UF_IBLOCK'],
			'entity_type' => $result['old_task']['UF_ETYPE'],
			'prompt' => $result['old_task']['UF_PROMPT'],
		]);

		if($result['create_task_result']['error_message']){
			$result['error_message'] = $result['create_task_result']['error_message'];
		}else{
			$taskElements = Tasks\Logic::uniGetlist('elements', [
				'select' => ['ID', 'UF_ELEMENT'],
				"filter" => ['=UF_TASK' => $task_id]
			]);

			if(count($taskElements)){
				foreach($taskElements as $tElement){
					Tasks\Element::addElementToTask($result['create_task_result']['id'], $tElement['UF_ELEMENT']);
				}
			}
		}

		return $result;
	}

	static function startTask($task_id, $options = []){
		$result = [];

		if($task_id){
			$taskElements = Tasks\Logic::uniGetlist('elements', [
				// 'select' => ['ID', 'UF_STATUS'],
				"filter" => ['=UF_TASK' => $task_id]
			]);

			if(!count($taskElements)){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_TASKS_EDIT_START_TASK_ERROR_NO_ELEMENTS');
			}else{
				foreach($taskElements as $tElement){
					if($options['restart'] && $options['restart'] == 'all'){
						if($tElement['UF_STATUS']){
							Tasks\Element::update($tElement['ID'], [
								'UF_STATUS' => '',
								'UF_GENERATION_DATE' => '',
								'UF_GENERATION_RESULT' => '',
								'UF_VALUE_BACKUP' => '',
								'UF_PARAMS' => '',
							]);
						}
					}else{
						if($tElement['UF_STATUS'] == 'error'){
							Tasks\Element::update($tElement['ID'], [
								'UF_STATUS' => '',
								'UF_GENERATION_DATE' => '',
								'UF_PARAMS' => '',
							]);
						}
					}
				}

				Tasks\Logic::addTaskAgent($task_id);

				$taskClass = Tasks\Logic::getTaskClass();
				$taskClass::update($task_id, [
					"UF_STATUS" => 'work',
				]);
			}
		}

		return $result;
	}

	static function clearStartTask($task_id){
		Tasks\Logic::addTaskAgent($task_id);

		$taskClass = Tasks\Logic::getTaskClass();
		$taskClass::update($task_id, [
			"UF_STATUS" => 'work',
		]);
	}
	
	static function stopTask($task_id){
		$result = [];

		if($task_id){
			Tasks\Logic::deleteTaskAgent($task_id);

			$taskClass = Tasks\Logic::getTaskClass();
			$taskClass::update($task_id, [
				"UF_STATUS" => 'stop',
			]);
		}

		return $result;
	}

    static function getTaskByID($id){
		$result = Tasks\Logic::uniGetlist('tasks', [
			"filter" => ['ID' => $id]
		], 1);

		return $result;
	}
    static function getTasksAddList($postFields){
		$result = Tasks\Logic::uniGetlist('tasks', [
			"select" => ["ID", "UF_NAME"],
			"filter" => [
				'=UF_IBLOCK' => intval($postFields['ibid']),
				'=UF_ETYPE' => ($postFields['entity'] == 'elements' ? 'E' : 'S'),
			]
		]);

		return $result;
	}
}