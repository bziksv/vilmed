<?
namespace Arturgolubev\Chatgpt;

use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc;

// use \Arturgolubev\Chatgpt\Unitools as UTools;

class Api {
	public static function simpleRequest($question, $options){
        $result = [];

        $params = [
            'question' => $question,
            'provider' => ($options['provider'] ? $options['provider'] : 'chatgpt'),
        ];

        $data = \CArturgolubevChatgpt::askQuestion($params);

        if($data['error_message']){
            $result['error_message'] = $data['error_message'];
        }

        if(!$result['error_message'] && $data['created_text']){
            $result['answer'] = $data['created_text'];
            $result['success'] = 1;
        }

        $result['debug']['send_params'] = $params;
        $result['debug']['raw_data'] = $data;

        return $result;
    }


    public static function addElementToTask($taskID, $elementID, $type, $autostart){
        $result = [
            'input' => [
                'taskID' => $taskID,
                'elementID' => $elementID,
                'type' => $type,
                'autostart' => $autostart,
            ]
        ];

        $taskID = intval($taskID);
        $elementID = intval($elementID);
        if(!$taskID || !$elementID){
            $result['error_message'] = 'No task or element ID';
        }

        if(!$result['error_message']){ // check task
            $taskData = \Arturgolubev\Chatgpt\Tasks\Task::getTaskByID($taskID);
            if(!is_array($taskData)){
                $result['error_message'] = 'Task Not Found';
            }elseif($taskData['UF_ETYPE'] != $type){
                $result['error_message'] = 'Task incorrect type';
            }
        }
        
        if(!$result['error_message']){ // add logic
            $inTask = \Arturgolubev\Chatgpt\Tasks\Element::checkElementInTask($taskID, $elementID);
            if($inTask){
                $result['error_message'] = 'Element already added';
            }else{
                $addTask = \Arturgolubev\Chatgpt\Tasks\Element::addElementToTask($taskID, $elementID);
                if($addTask['error_message']){
                    $result['error_message'] = $addTask['error_message'];
                }else{
                    $result['success'] = 1;
                }
            }
        }

        if(!$result['error_message'] && $autostart){ // start task
            if($taskData['UF_STATUS'] != 'work'){
                \Arturgolubev\Chatgpt\Tasks\Task::clearStartTask($taskID);
            }
        }

        return $result;
    }
}