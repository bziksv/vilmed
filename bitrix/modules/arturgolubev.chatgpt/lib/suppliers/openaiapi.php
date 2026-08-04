<?
namespace Arturgolubev\Chatgpt\Suppliers;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools;

class OpenaiApi {
    static function callOpenaiApi($message, $options){
		$result = [];

		$apiKey = trim(UTools::getSetting('openai_compatible_api_key'));

		if(!$apiKey){
			$error = 1;
			$result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_NO_API_KEY_ERROR');
		}

		if(!$error){
			if(\CArturgolubevChatgpt::DEBUG){
				$result = self::getDebug();
			}else{
				$url = UTools::getSetting('openai_compatible_base_link')."/chat/completions";
				$data = self::getCallData($message, $options);
				
				Tools::writeModuleDebug(false, 'gpt chat get', $data);
				
				foreach(GetModuleEvents(\CArturgolubevChatgpt::MODULE_ID, "modifyFinalDataBeforeSendRequest", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, ['openaiapi', &$apiKey, &$url, &$data, $options]);

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
				// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

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

				Tools::writeModuleDebug(false, 'gpt chat result', $result);
			}
		}
		
		$result = Tools::prepareResult($options, $result);

		return $result;
	}

    /* static function getImageCallData($message, $options){
        $model = UTools::getSetting('alg_image_model');
        if($model == 'other'){
            $model = UTools::getSetting('alg_image_other');
        }

        $data = [
            'model' => $model,
            'prompt' => $message,
            'n' => 1,
        ];

        $paramsList = ['size', 'output_format', 'quality'];
        foreach($paramsList as $param_name){
            if($options['input'][$param_name]){
                $data[$param_name] = $options['input'][$param_name];
            }
        }

        if(is_array($options['files']) && count($options['files'])){
            $domain = (\CMain::IsHTTPS() ? "https://" : "http://") . $_SERVER["HTTP_HOST"];

            // $data['image'] = [];
            foreach($options['files'] as $fileLink){
                $fileLinkBase = $_SERVER['DOCUMENT_ROOT'].str_replace($domain, '', $fileLink);
                $info = pathinfo($fileLinkBase);

                $data['image'] = new \CURLFile($fileLinkBase, "image/".($info['extension'] == 'jpg' ? 'jpeg' : $info['extension']), $info['filename'].'.'.$info['extension']);
                // $data['image'][] = new \CURLFile($fileLinkBase, "image/".($info['extension'] == 'jpg' ? 'jpeg' : $info['extension']), $info['filename'].'.'.$info['extension']);
                // $data['image[]'] = new \CURLFile($fileLinkBase, "image/".$info['extension'], $info['filename'].$info['extension']);
            }
        }

        return $data;
    } */

    static function getCallData($message, $options){
		$data = [];

        /* if(is_array($options['files']) && count($options['files'])){ // no used
            $content = [
                ['type' => 'text', 'text' => $message],
            ];

            foreach($options['files'] as $file_link){
                $parseUrl = parse_url($file_link);
                $filePath = $_SERVER['DOCUMENT_ROOT'].$parseUrl['path'];

                if(file_exists($filePath)){
                    $imageData = file_get_contents($filePath);
                }

                if(!$imageData){
                    $imageData = file_get_contents($file_link);
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($imageData);
                $base64 = base64_encode($imageData);

                $base64Image = 'data:' . $mime . ';base64,' . $base64;

                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $base64Image]];
            }
        }else{
            $content = $message;
        } */

        $content = $message;

		$messages = [
			["role" => 'user', "content" => $content]
		];

        $data = [
            "messages" => $messages,
            "model" => UTools::getSetting('openai_compatible_model'),
            'temperature' => floatval(UTools::getSetting('openai_compatible_temperature')),
        ];
		
		return $data;
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
                            'content' => 'Test debug content (OpenAI compatible)'
                        ]
                    ]
                ]
            ]
        ];
    }
}