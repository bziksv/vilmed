<?
namespace Arturgolubev\Chatgpt\Controller;

use \Bitrix\Main\Error;

use \Arturgolubev\Chatgpt\Tools;

class Simple extends \Bitrix\Main\Engine\Controller
{
    public function textRequestAction($data){
        $result = [];
        if($this->checkOperationRights()){
            $params = [
                'provider' => htmlspecialcharsbx($data['provider']),
                'question' => htmlspecialcharsbx($data['question']),
                'keynum' => 0,
            ];
            
            $data = \CArturgolubevChatgpt::askQuestion($params);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }else{
                $result['text'] = $data['created_text'];
            }

            $result['full_result'] = $data['full_result'];

            $this->prepareResult($result);
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    public function imageRequestAction($data){
        $result = [];
        if($this->checkOperationRights()){
            $params = [
                'content_type' => 'image',
                'provider' => htmlspecialcharsbx($data['provider']),
                'question' => htmlspecialcharsbx($data['question']),
                'size' => htmlspecialcharsbx($data['size']),
                'quality' => htmlspecialcharsbx($data['quality']),
                'output_format' => htmlspecialcharsbx($data['output_format']),
                'keynum' => 0,
            ];

            $data = \CArturgolubevChatgpt::createImage($params);
            if($data['error_message']){
                $result['error_message'] = $data['error_message'];
            }else{
                $result['image'] = $data['created_image'];
            }

            $this->prepareResult($result);
        }else{
            $this->addError(new Error('Access Denied', 'CHECK_USER'));
        }
        
        return $result;
    }

    private function prepareResult(&$result){
        if($result['error_message']){
            $result['error'] = 1;
        }
    }

    private function checkOperationRights(){
        global $USER;
        if(!is_object($USER)){
            $USER = new \CUser();
        }

        return ($USER->IsAdmin() || Tools::checkRights('question'));
    }
}