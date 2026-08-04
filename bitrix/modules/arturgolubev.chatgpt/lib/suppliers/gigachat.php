<?
namespace Arturgolubev\Chatgpt\Suppliers;

use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools;

class GigaChat {
	static function callImageApi($message, $options){
		$result = self::callTextApi($message, $options);

		if(!$result['prepared']['error']){
			$content = $result['result']['choices'][0]['message']['content'];
			if(preg_match('/<img\s+src="([^"]+)"/', $content, $matches)){
				$src = $matches[1];
			}

			if($src){
				$image = self::callGetImage($src, $options);
				$result['prepared']['image'] = $image['result']['prepared']['image'];
			}else{
				$result['result']['error']['message'] = 'Image not found in response ('.$result['result']['choices'][0]['message']['content'].')';
				$result = Tools::prepareResult($options, $result);
			}
		}

		return $result;
	}

	static function callTextApi($message, $options){
		$result = [];

		$checkResult = self::checkSberToken();

		if(is_array($checkResult) && $checkResult['error_message']){
			$error = 1;
			$result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.$checkResult['error_message'];
		}

		$access_token = UTools::getSetting('sber_access_token');
		
		if(!$error && !$access_token){
			$error = 1;
			$result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_SBER_NO_ACCESS_TOKEN_ERROR');
		}
		
		if(!$error){
			if(\CArturgolubevChatgpt::DEBUG){
				$result = GigaChat::getDebug();
				// $result['result']['error']['message'] = 'LIMIT';
			}else{
				$data = GigaChat::getCallData($message, $options);
				
				Tools::writeModuleDebug(false, 'gigachat chat get', $data);
				
				$url = "https://gigachat.devices.sberbank.ru/api/v1/chat/completions";

				foreach(GetModuleEvents(\CArturgolubevChatgpt::MODULE_ID, "modifyFinalDataBeforeSendRequest", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, ['gigachat', &$access_token, &$url, &$data, $options]);

				$headers = [
					"Content-Type: application/json",
					"Authorization: Bearer " . $access_token
				];

				// echo '<pre>'; print_r($data); echo '</pre>';

				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Tools::getTimeout()); 
				curl_setopt($curl, CURLOPT_TIMEOUT, Tools::getTimeout());
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);		
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, Json::Encode($data));
				curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/serts/russian_trusted_root_ca_pem.crt');
				
				$baseResult = curl_exec($curl);
				
				$result["error"] = curl_error($curl);
				$result["error_no"] = curl_errno($curl);
				$result["header"] = curl_getinfo($curl);
				
				curl_close($curl);

				if($baseResult){
					if(UTools::isJsonPage($baseResult)){
						$result["result"] = Json::Decode($baseResult);

						if($result["result"]['status']){
							$result['result']['error']['message'] = $result['result']['message'];
						}
					}else{
						$result['result']['error']['message'] = $baseResult;
					}
				}else{
					$result['result']['error']['message'] = 'Empty answer';
				}

				Tools::writeModuleDebug(false, 'gigachat chat result', $result);
			}
		}
		
		$result = Tools::prepareResult($options, $result);
		
		return $result;
	}

	static function callGetImage($imageID, $options){
		$result = [];

		$checkResult = self::checkSberToken();

		if(is_array($checkResult) && $checkResult['error_message']){
			$error = 1;
			$result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.$checkResult['error_message'];
		}

		$access_token = UTools::getSetting('sber_access_token');
		
		if(!$error && !$access_token){
			$error = 1;
			$result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_SBER_NO_ACCESS_TOKEN_ERROR');
		}
		
		if(!$error){
			$url = "https://gigachat.devices.sberbank.ru/api/v1/files/{$imageID}/content";

			$headers = [
				"Authorization: Bearer " . $access_token
			];

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Tools::getTimeout()); 
			curl_setopt($curl, CURLOPT_TIMEOUT, Tools::getTimeout());
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);		
			curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/serts/russian_trusted_root_ca_pem.crt');	

			$baseResult = curl_exec($curl);
			
			$result["error"] = curl_error($curl);
			$result["error_no"] = curl_errno($curl);
			$result["header"] = curl_getinfo($curl);
			
			curl_close($curl);

			if($baseResult){
				$image_name = '/upload/tmp/arturgolubev.chatgpt/generated_images/image_'.time().'.'.'jpg';
				$file = new \Bitrix\Main\IO\File($_SERVER["DOCUMENT_ROOT"].$image_name);
				$file->putContents($baseResult);

				$result["result"]['prepared']['image'] = $image_name;
			}else{
				$result['result']['error']['message'] = 'Empty answer';
			}

			Tools::writeModuleDebug(false, 'gigachat chat result', $result);
		}
		
		return $result;
	}

	static function checkSberToken(){
		$exp = intval(intval(UTools::getSetting('sber_access_expires'))/1000);
		$now = intval(microtime(true));

		$real = $exp-$now;

		if($real <= 60){
			return self::getSberToken();
		}

		return [];
	}

	static function getSberToken(){
		$result = [];

		// UTools::setSetting('sber_access_token', '');
		// UTools::setSetting('sber_access_expires', '');

		$headers = [
			"Authorization: Bearer ".UTools::getSetting('sber_authorization'),
			"Content-Type: application/x-www-form-urlencoded",
			"RqUID: ".Tools::getGuid4(),
		];
		
		$data = [
			'scope' => UTools::getSetting('sber_scope')
		];

		// echo '<pre>'; print_r($headers); echo '</pre>';
		// echo '<pre>'; print_r($data); echo '</pre>';
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://ngw.devices.sberbank.ru:9443/api/v2/oauth");
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Tools::getTimeout()); 
		curl_setopt($curl, CURLOPT_TIMEOUT, Tools::getTimeout());
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);		
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($curl, CURLOPT_CAINFO, __DIR__ . '/serts/russian_trusted_root_ca_pem.crt');
		
		$baseResult = curl_exec($curl);
		
		$result["error"] = curl_error($curl);
		$result["error_no"] = curl_errno($curl);
		$result["header"] = curl_getinfo($curl);
		
		curl_close($curl);

		if($baseResult){
			$result["result"] = Json::Decode($baseResult);

			if($result["result"]['access_token']){
				UTools::setSetting('sber_access_token', $result["result"]['access_token']);
				UTools::setSetting('sber_access_expires', $result["result"]['expires_at']);
			}elseif($result["result"]['message']){
				$result["error_message"] = $result['result']['message'];
				if($result["result"]['code']){
					$result["error_message"] .= ' [error code = '.$result["result"]['code'].']';
				}
			}
		}elseif($result["error"]){
			$result["error_message"] = $result["error"].' [error_no: '.$result['error_no'].']';
		}

		return $result;
	}

    static function getCallData($message, $options){
        $data = [];

        $role = (isset($options['role']) && $options['role']) ? $options['role'] : UTools::getSetting('sber_role');
        
        $max_tokens = intval(UTools::getSetting('sber_max_tokens'));
        if(!$max_tokens) $max_tokens = 2048;
        
        $data = [
            "messages" => [
                ["role" => $role, "content" => $message]
            ],
            "model" => UTools::getSetting('sber_model', 'GigaChat:latest'),
            "temperature" => floatval(UTools::getSetting('sber_temperature')),
            "max_tokens" => $max_tokens,
			"function_call" => "auto"
        ];

		if($options['content_type'] == 'image'){
			unset($data['max_tokens']);
		}
        
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
                            'content' => 'Test debug content (gigachat)'
                        ]
                    ]
                ]
            ]
        ];
    }
}