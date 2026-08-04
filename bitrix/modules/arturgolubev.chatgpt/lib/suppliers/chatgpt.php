<?
namespace Arturgolubev\Chatgpt\Suppliers;

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Web\Json;

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools;

class ChatGpt {
    static function getServerName(){
		$sName = UTools::getSetting('chatgpt_custom_base');
		return ($sName) ? $sName : 'https://api.openai.com/v1';
	}

    static function getApiKey(){
		$result = [
			'error' => '',
		];
		
		$result['keys'] = UTools::explodeByEOL(UTools::getSetting('api_key'));
        $result['key'] = $result['keys'][0];

        if(!$result['key']){
            $result['error'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_NO_API_KEY_ERROR');
        }
		
		return $result;
	}

    static function getImageCallData($message, $options){
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
    }

    static function callTextApi($message, $options){
        $result = [];

        $api_keys = self::getApiKey();

        if($api_keys['error']){
            $error = 1;
            $result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.$api_keys['error'];
        }

        if(!$error){
            if(\CArturgolubevChatgpt::DEBUG){
                $result = self::getDebug();
            }else{
                $proxy = self::getProxy();
                $data = self::getCallData($message, $options);

                Tools::writeModuleDebug(false, 'gpt chat get', $data);

                $apiKey = $api_keys['key'];
                $url = self::getServerName()."/chat/completions";

                foreach(GetModuleEvents(\CArturgolubevChatgpt::MODULE_ID, "modifyFinalDataBeforeSendRequest", true) as $arEvent)
                    ExecuteModuleEventEx($arEvent, ['chatgpt', &$apiKey, &$url, &$data, $options]);

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

                if(is_array($proxy)){
                    curl_setopt($curl, CURLOPT_PROXY, $proxy['ip']);
                    if($proxy['login']){
                        curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy['login']);
                    }
                }

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

                $result = self::prepareResult($result);

                Tools::writeModuleDebug(false, 'gpt chat result', $result);
            }
        }

        $result = Tools::prepareResult($options, $result);

        return $result;
    }

    static function callImageApi($message, $options){
        $result = [];

        $api_keys = self::getApiKey();

        if($api_keys['error']){
            $error = 1;
            $result['result']['error']['message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ERROR').' '.$api_keys['error'];
        }

        if(!$error){
            $data = self::getImageCallData($message, $options);

            Tools::writeModuleDebug(false, 'gpt image get', $data);

            $proxy = self::getProxy();
            $apiKey = $api_keys['key'];
            $url = (isset($data['image'])) ? self::getServerName()."/images/edits" : self::getServerName()."/images/generations";

            foreach(GetModuleEvents(\CArturgolubevChatgpt::MODULE_ID, "modifyFinalDataBeforeSendRequest", true) as $arEvent)
                ExecuteModuleEventEx($arEvent, ['chatgpt', &$apiKey, &$url, &$data, $options]);

            $curl = curl_init();

            if(isset($data['image'])){
                $headers = [
                    "Authorization: Bearer " . $apiKey
                ];

                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }else{
                $headers = [
                    "Accept: application/json",
                    "Content-Type: application/json",
                    "Authorization: Bearer ".$apiKey
                ];

                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, Json::Encode($data));
            }

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_URL, $url);

            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, Tools::getTimeout());
            curl_setopt($curl, CURLOPT_TIMEOUT, Tools::getTimeout());

            if(is_array($proxy)){
                curl_setopt($curl, CURLOPT_PROXY, $proxy['ip']);
                if($proxy['login']){
                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy['login']);
                }
            }

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

            Tools::writeModuleDebug(false, 'gpt image result', $result);
        }

        $result = Tools::prepareResult($options, $result);

        if(!$result['prepared']['error']){
            $output_format = ($options['output_format'] ? $options['output_format'] : $options['input']['output_format']);

            $image_url = self::prepareImageOutput($result['result']['data'][0], $output_format);

            if($image_url){
                $result['prepared']['image'] = $image_url;
			}else{
				$result['result']['error']['message'] = 'Image not found in response';
				$result = Tools::prepareResult($options, $result);
			}
        }

        return $result;
    }

    static function getCallData($message, $options){
		$data = [];

        $model = UTools::getSetting('alg_model');
        if($model == 'other'){
            $model = UTools::getSetting('alg_model_other');
        }

		$role = (isset($options['role']) && $options['role']) ? $options['role'] : UTools::getSetting('alg_role');
		
        if(is_array($options['files']) && count($options['files'])){
            $content = [
                ['type' => 'text', 'text' => $message],
            ];

            foreach($options['files'] as $file_link){
                // $content[] = ['type' => 'image_url', 'image_url' => ['url' => $file_link]];

                $parseUrl = parse_url($file_link);
                $filePath = $_SERVER['DOCUMENT_ROOT'].$parseUrl['path'];

                if(file_exists($filePath)){
                    $imageData = file_get_contents($filePath);
                }

                if(!$imageData){
                    if(preg_match('#^https?://#i', $file_link)){
                        $imageData = file_get_contents($file_link);
                    }
                }

                if($imageData){
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($imageData);
                    $base64 = base64_encode($imageData);

                    $base64Image = 'data:' . $mime . ';base64,' . $base64;
                    $content[] = ['type' => 'image_url', 'image_url' => ['url' => $base64Image]];
                }
            }

            // echo '<pre>'; print_r($content); echo '</pre>';
            // die();
        }else{
            $content = $message;
        }

		$messages = [
			["role" => $role, "content" => $content]
		];
		
		$max_tokens = intval(UTools::getSetting('alg_max_tokens'));
		if($max_tokens <= 0){
			$max_tokens = 4096;
		}

        if(strpos($model, 'gpt-5') !== false || strpos($model, 'o4-') !== false){
            $data = [
                "messages" => $messages,
                "model" => $model,
                'max_completion_tokens' => $max_tokens,
            ];
        }else{
            if($max_tokens > 4096){
                $max_tokens = 4096;
            }

            $data = [
                "messages" => $messages,
                "model" => $model,
                'max_tokens' => $max_tokens,
            ];
        }

        // echo '<pre>'; print_r($data); echo '</pre>';
        // die();
		
		return $data;
	}

    static function getProxy(){
        $result = false;

        Tools::remakeProxy();
        
        $data = [
            'ip' => UTools::getSetting('proxy_ip'),
            'port' => UTools::getSetting('proxy_port'),
            'login' => UTools::getSetting('proxy_login'),
            'pass' => UTools::getSetting('proxy_password'),
        ];

        if($data['ip']){
            $result = [];

            $result['ip'] = $data['ip'];
            if($data['port']){
                $result['ip'] .= ':'.$data['port'];
            }

            if($data['login']){
                $result['login'] = $data['login'];
                if($data['pass']){
                    $result['login'] .= ':'.$data['pass'];
                }
            }

            $result['data'] = $data;
        }
        
        return $result;
    }

	static function prepareImageOutput($element, $format){
		$image_url = $element['url'];

		if(!$image_url && $element['b64_json']){
			$decodedImage = base64_decode($element['b64_json']);
			$image_name = '/upload/tmp/arturgolubev.chatgpt/generated_images/image_'.time().'.'.$format;

			$file = new \Bitrix\Main\IO\File($_SERVER["DOCUMENT_ROOT"].$image_name);
			$file->putContents($decodedImage);

			$image_url = $image_name;
		}

		return $image_url;
	}

    static function prepareResult($result){
        if(is_array($result['result']) && is_array($result['result']['error'])){
            if($result['result']['error']['message']){
                $result['result']['error']['message'] = str_replace([
                    'territory not supported',
                    'check your plan and billing details',
                ], [
                    'territory not supported '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_ERROR_COUNTRY_NOT_SUPPORTED'),
                    'check your plan and billing details '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_ERROR_CHECK_BILLING'),
                ], $result['result']['error']['message']);
            }
        }

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
                            'content' => 'Test debug content (chatgpt)'
                        ]
                    ]
                ]
            ]
        ];
    }
}