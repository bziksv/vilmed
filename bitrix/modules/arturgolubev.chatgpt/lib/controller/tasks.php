<?
namespace Arturgolubev\Chatgpt\Controller;

use \Bitrix\Main\Error,
    Bitrix\Main\Engine\ActionFilter;

use \Arturgolubev\Chatgpt\Tools,
    \Arturgolubev\Chatgpt\Tasks as TasksLogic;

class Tasks extends \Bitrix\Main\Engine\Controller
{
    public function deleteAction($task_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = TasksLogic\Task::deleteTask($task_id);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function copyAction($task_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = TasksLogic\Task::copyTask($task_id);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }

            $result['data'] = $data;
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function stopAction($task_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = TasksLogic\Task::stopTask($task_id);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function startAction($task_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = TasksLogic\Task::startTask($task_id);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }


    public function restartAction($task_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = TasksLogic\Task::startTask($task_id, ['restart' => 'all']);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    private function checkOperationRights(){
        global $USER;
        if(!is_object($USER)){
            $USER = new \CUser();
        }

        return ($USER->IsAdmin() || Tools::checkRights('tasks'));
    }
}