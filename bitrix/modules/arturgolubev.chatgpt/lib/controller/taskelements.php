<?
namespace Arturgolubev\Chatgpt\Controller;

use \Bitrix\Main\Error,
    Bitrix\Main\Engine\ActionFilter;

use \Arturgolubev\Chatgpt\Tools,
    \Arturgolubev\Chatgpt\Tasks;

class TaskElements extends \Bitrix\Main\Engine\Controller
{
    public function deleteFromTaskAction($element_id){
        $result = [];

        if($this->checkOperationRights()){
            Tasks\Element::deleteTaskElement($element_id);
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function getInfoAction($element_id){
        $result = [];

        if($this->checkOperationRights()){
            $data = Tasks\Element::getTaskElementByID($element_id);
            if(is_array($data)){
                $result['info'] = $data;
            }else{
                $result['error_message'] = 'Not Found';
            }
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }

        return $result;
    }

    public function readdElementAction($element_id){
        $result = [];

        if($this->checkOperationRights()){
            $result['clean'] = Tasks\Element::clean($element_id);
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function returnDataTaskElementAction($element_id){
        $result = [];

        if($this->checkOperationRights()){
            $result['backup_returned'] = Tasks\Element::returnBackup($element_id);
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