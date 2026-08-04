<?
namespace Arturgolubev\Chatgpt\Suppliers;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools;

class DeepSeek {
    static function getServerName(){
		$sName = UTools::getSetting('deepseek_custom_base');
		return ($sName) ? $sName : 'https://api.deepseek.com';
	}
    
    static function getApiKey(){
        return trim(UTools::getSetting('deepseek_api_key'));
    }

    static function getCallData($message, $options){
		$data = [];

		$role = (isset($options['role']) && $options['role']) ? $options['role'] : UTools::getSetting('alg_role');
		
		$messages = [
			["role" => $role, "content" => $message]
		];
		
		$data = [
			"messages" => $messages,
			"model" => UTools::getSetting('deepseek_model'),
			"temperature" => floatval(UTools::getSetting('alg_temperature')),
		];
		
		return $data;
	}

    static function callTextApi($message, $options){
        $result = [];

        $apiKey = self::getApiKey();

        if(!$apiKey){
            $error = 1;
            $result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_NO_API_KEY_ERROR');
        }

        if(!$error){
            if(\CArturgolubevChatgpt::DEBUG){
                $result = self::getDebug();
            }else{
                $data = self::getCallData($message, $options);

                Tools::writeModuleDebug(false, 'deepseek chat get', $data);

                $url = self::getServerName()."/chat/completions";

                foreach(GetModuleEvents(\CArturgolubevChatgpt::MODULE_ID, "modifyFinalDataBeforeSendRequest", true) as $arEvent)
                    ExecuteModuleEventEx($arEvent, ['deepseek', &$apiKey, &$url, &$data, $options]);

                $headers = [
                    "Accept: application/json" ,
                    "Content-Type: application/json" ,
                    "Authorization: Bearer " . $apiKey
                ];

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Tools::getTimeout());
                curl_setopt($curl, CURLOPT_TIMEOUT, Tools::getTimeout());
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, Json::Encode($data));

                $baseResult = curl_exec($curl);

                $result["error"] = curl_error($curl);
                $result["error_no"] = curl_errno($curl);
                $result["header"] = curl_getinfo($curl);

                curl_close($curl);

                if($baseResult){
                    if(UTools::isHtmlPage($baseResult)){
                        $result['result']['error']['message'] = $baseResult;
                    }else{
                        $result["result"] = Json::Decode($baseResult);
                    }
                }else{
                    $result['result']['error']['message'] = '['.$result['header']['http_code'].'] '.(($result["error"]) ? $result["error"] : 'Empty answer.');
                }

                Tools::writeModuleDebug(false, 'deepseek chat result', $result);
            }
        }

        $result = Tools::prepareResult($options, $result);

        return $result;
    }

    static function getDebug(){
        return [
            'result' => [
                'usage' => [
                    'total_tokens' => 940,
                ],
                'choices' => [
                    0 => [
                        'message' => [
                            'content' => 'Test debug content (deepseek)'
                        ]
                    ]
                ]
            ]
        ];
    }
}