<?
use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Iblock\InheritedProperty;

use \Arturgolubev\Chatgpt\Encoding,
	\Arturgolubev\Chatgpt\Tools,
	\Arturgolubev\Chatgpt\Hl,
	\Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tasks;

use Arturgolubev\Chatgpt\Suppliers\DeepSeek, 
	Arturgolubev\Chatgpt\Suppliers\ChatGpt,
	Arturgolubev\Chatgpt\Suppliers\GigaChat,
	Arturgolubev\Chatgpt\Suppliers\OpenaiApi;

include 'autoload.php';
include 'jscore.php';

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/include.php");

Class CArturgolubevChatgpt 
{
	const MODULE_ID = 'arturgolubev.chatgpt';
	
	const TOKEN = 300;

	const DEBUG = 0;
	const LOG_DEBUG = 0;
	
	// demo
	static function isDemo(){
		return (CModule::IncludeModuleEx(self::MODULE_ID) == 2);
	}

	static function checkDemoAccess(){
		if(self::isDemo()){
			$actualToken = intval(UTools::getSetting('alg_max_token'));
			if($actualToken >= self::TOKEN){
				return 1;
			}
		}

		return 0;
	}
	static function checkDemoCounts(){
		if(self::isDemo()){
			$actualToken = intval(UTools::getSetting('alg_max_token'));
			UTools::setSetting('alg_max_token', $actualToken + 1);
		}

		return 0;
	}

	/* all system */
	static function callChatProvider($question, $options){
		if($options['provider'] == 'sber'){
			if($options['content_type'] == 'image'){
				return GigaChat::callImageApi($question, $options);
			}else{
				return GigaChat::callTextApi($question, $options);
			}
		}elseif($options['provider'] == 'deepseek'){
			return DeepSeek::callTextApi($question, $options);
		}elseif($options['provider'] == 'openai_compatible'){
			return OpenaiApi::callOpenaiApi($question, $options);
		}else{
			if($options['content_type'] == 'image'){
				return ChatGpt::callImageApi($question, $options);
			}else{
				return ChatGpt::callTextApi($question, $options);
			}
		}
	}

	static function applyDefaultVals($postFields, $get = 1){
		$arCheckFields = ['provider', 'operation', 'type', 'for', 'from', 'length', 'html', 'lang', 'template_element', 'template_section', 'template_image', 'mass_save_field', 'save_only_empty', 'savefield', 'size', 'output_format', 'quality', 'files'];
		
		if(!isset($_SESSION['AGCG_DEFAULT']) || !is_array($_SESSION['AGCG_DEFAULT'])){
			$_SESSION['AGCG_DEFAULT'] = [];
		}
		
		foreach($arCheckFields as $field){
			if($postFields[$field]){
				if($field == 'template_element' || $field == 'template_section'){
					$postFields[$field] = Encoding::convertFromUtf($postFields[$field]);
				}
				
				$_SESSION['AGCG_DEFAULT'][$field] = $postFields[$field];
			}
		}
		
		if($get){
			foreach($arCheckFields as $field){
				if(!$postFields[$field]){
					if(isset($_SESSION['AGCG_DEFAULT'][$field])){
						$postFields[$field] = $_SESSION['AGCG_DEFAULT'][$field];
					}
				}
			}
		}
		
		// echo '<pre>'; print_r($_SESSION['AGCG_DEFAULT']); echo '</pre>';
		
		return $postFields;
	}
	
	static function createImage($input){
		if($input['provider'] == 'sber'){
			$data = GigaChat::callImageApi($input['question'], $input);
		}else{
			$data = ChatGpt::callImageApi($input['question'], $input);
		}

		if($data['prepared']['error']){
			$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CHATGPT_ERROR').' '.$data['prepared']['error_message'];
		}else{
			$result['created_image'] = $data['prepared']['image'];
		}

		return $result;
	}

	static function askQuestion($input){
		$result = [];

		$options = [
			'provider' => $input['provider'],
			'request_type' => 'simple',
			'object_type' => 'simple',
		];

		$data = self::callChatProvider($input['question'], $options);

		$result['full_result'] = $data['result'];
		
		if($data['prepared']['error']){
			$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CHATGPT_ERROR').' '.$data['prepared']['error_message'];
		}else{
			if($data['prepared']['answer']){
				$result['created_text'] = $data['prepared']['answer'];
			}else{
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
			}
		}
		
		return $result;
	}
	
	static function makeQuestionByTemplate($template, $main_info){
		$question = $template;
		
		preg_match_all('/#([a-zA-Z_0-9]+)#/is', $template, $match);
		
		if(is_array($match[1]) && count($match[1])){
			$arFind = [];
			$arReplace = [];
			foreach($match[1] as $macros){
				$arFind[] = '#'.$macros.'#';
				$arReplace[] = $main_info[$macros];
			}
			
			$question = str_replace($arFind, $arReplace, $question);
		}
		
		
		return $question;
	}
	
	static function makeQuestion($input, $main_info){
		// echo '<pre>'; print_r($input); echo '</pre>';
		
		$main_info = strip_tags(htmlspecialchars_decode($main_info));
		
		if($input['operation'] == 'REWRITE'){
			$question = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE");
		}elseif($input['operation'] == 'TRANSLATE'){
			$question = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TRANSLATE");
		}elseif($input['operation'] == 'CREATE' && $input['type'] == 'REVIEW'){
			$question = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_REVIEW");
		}else{
			$question = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE");
		}
		
		if($input['operation'] == 'REWRITE'){
			if($input['for'] != 'ARTICLE'){
				$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_TYPE_DESCRIPTION");
			}else{
				$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_TYPE_DESCRIPTION_ARTICLE");
			}
			
			switch($input['for']){
				case 'PRODUCT': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_FOR_PRODUCT");
				break;
				case 'ARTICLE': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_FOR_ARTICLE");
				break;
				case 'SERVICE': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_FOR_SERVICE");
				break;
			}
			
			// $question .= '. ';
			
			if($input['html']){
				$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_HTML");
			}

			$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_REWRITE_OBJECT"). $main_info;
		}else{
			switch($input['type']){
				case 'H1': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_H1");
				break;
				case 'TITLE': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_TITLE");
				break;
				case 'DESCRIPTION': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_DESCRIPTION");
				break;
				case 'KEYWORDS': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_KEYWORDS");
				break;
				case 'TEXT': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_TEXT");
				break;
				case 'REVIEW': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_TYPE_REVIEW");
				break;
			}
			
			switch($input['for']){
				case 'PRODUCT': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_FOR_PRODUCT");
				break;
				case 'ARTICLE': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_FOR_ARTICLE");
				break;
				case 'SERVICE': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_FOR_SERVICE");
				break;
				case 'PRODUCT_SECTION': 
					$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_FOR_PRODUCT_SECTION");
				break;
			}
			
			if($input['lang']){
				$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_TRANSLATE_LANG", ['#lang#' => ToLower(Encoding::convertFromUtf($input['lang']))]);
			}
			
			$question .= '"' . $main_info . '" ';
		
			
			if($input['length']){
				$question .= Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_LENGTH", ['#length#' => $input['length']]);
			}

			$arDopInstruction = [];
			
			if($input['html']){
				$arDopInstruction[] = Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_HTML");
			}

			if(count($arDopInstruction)){
				$question = trim($question) . Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_FOR_RESULT") . implode(', ', $arDopInstruction);
			}

			if($input['operation'] == 'CREATE'){
				if(in_array($input['type'], ['H1', 'TITLE', 'DESCRIPTION', 'KEYWORDS'])){
					$question .= '. '.Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_ELEMENT_WRITE_ONE_WARIANT");
				}
			}
		}

		if($input['additionals']){
			$question = trim($question).'. '.$input['additionals'];
		}
		
		return $question;
	}
	
	// element
	static function getElementBaseData($elementId){
		$result = [];
		
		if(Loader::IncludeModule("iblock")){
			$domain = (CMain::IsHTTPS() ? "https://" : "http://") . $_SERVER["HTTP_HOST"];

			$res = CIBlockElement::GetList([], ['ID' => $elementId], false, ["nPageSize"=>1], ['NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT', '*']);
			while($ob = $res->GetNextElement(true, false)){
				$result = $ob->GetFields();
				$props = $ob->GetProperties();
				
				$result['DETAIL_PAGE_URL'] = $domain.$result['DETAIL_PAGE_URL'];

				$result['DETAIL_TEXT_HTML'] = $result['DETAIL_TEXT'];
				$result['PREVIEW_TEXT_HTML'] = $result['PREVIEW_TEXT'];
				
				if($result['PREVIEW_PICTURE']){
					$result['PREVIEW_PICTURE'] = $domain.CFile::GetPath($result['PREVIEW_PICTURE']);
				}

				if($result['DETAIL_PICTURE']){
					$result['DETAIL_PICTURE'] = $domain.CFile::GetPath($result['DETAIL_PICTURE']);
				}

				$result['ALL_PROPS'] = '';

				foreach($props as $property){
					if($property['USER_TYPE'] == 'HTML' && is_array($property['VALUE'])){
						$property['VALUE'] = $property['VALUE']['TEXT'];
					}
					
					if($property['USER_TYPE'] == 'directory'){
						if(is_array($property['VALUE'])){
							foreach($property['VALUE'] as $k=>$v){
								$property['VALUE'][$k] = Hl::getPropValueField($property, $v);
							}
						}else{
							$property['VALUE'] = Hl::getPropValueField($property, $property['VALUE']);
						}
					}
					
					if($property['PROPERTY_TYPE'] == 'E'){
						if(!is_array($property['VALUE'])){
							$property['VALUE'] = [$property['VALUE']];
						}

						foreach($property['VALUE'] as $k=>$v){
							if($v){
								$resOne = CIBlockElement::GetList([], ['ID' => $v], false, ["nPageSize"=>1], ['ID', 'NAME']);
								while($arOneFields = $resOne->Fetch()){
									$property['VALUE'][$k] = $arOneFields['NAME'];
								}
							}
						}
					}

					if($property['PROPERTY_TYPE'] == 'F'){
						if(!is_array($property['VALUE'])){
							if($property['VALUE']){
								$property['VALUE'] = [$property['VALUE']];
							}else{
								$property['VALUE'] = [];
							}
						}

						foreach($property['VALUE'] as $k=>$v){
							if($v){
								$property['VALUE'][$k] = $domain . CFile::GetPath($v);
							}
						}
					}

					$valueString = (is_array($property['VALUE']) ? implode(', ', $property['VALUE']) : $property['VALUE']);

					if($valueString && !in_array($property['PROPERTY_TYPE'], ['F'])){
						$result['ALL_PROPS'] .= $property['NAME'].': '.$valueString.'; ';
					}

					$result['PROPERTY_'.$property['CODE']] = $valueString;
				}
				
				$ipropElementValues = new InheritedProperty\ElementValues($result['IBLOCK_ID'], $result['ID']);
				$values = $ipropElementValues->getValues();
				foreach($values as $key=>$val){
					$result['SEO_'.$key] = $val;
				}

				if($result['IBLOCK_SECTION_ID']){
					$rdbSections = \Bitrix\Iblock\SectionTable::getList(array(
						'select' => array('NAME'),
						'filter' => array('ID' => $result['IBLOCK_SECTION_ID'])
					));
					while ($dctSection = $rdbSections->fetch()) {
						$result['PARENT_SECTION_NAME'] = $dctSection['NAME'];
					}
				}
				
			}
		}
		
		return $result;
	}

	static function checkElementEmptySaveFiled($ibid, $eid, $field){
		$elementInfo = self::getElementBaseData($eid);

		return ['result' => ($elementInfo[$field] == ''), 'element_name' => $elementInfo['NAME']];
	}
	
	static function createElementText($input){
		$result = [];
		$options = [
			'content_type' => 'text',
			'provider' => $input['provider'],
			'request_type' => 'generation',
			'object_type' => 'element',
		];

		if($input['type'] == 'KEYWORDS'){
			$options['role'] = 'system';
		}
		
		$elementInfo = self::getElementBaseData($input['ID']);
		$result['element_name'] = $elementInfo['NAME'];
		
		if(!$elementInfo[$input['from']] && !in_array($input['operation'], ['TEMPLATE', 'IMAGE'])){
			$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_EMPTY_FIELD_FROM_ERROR');
		}

		if($input['mass_generation'] && self::isDemo()){
			$actualToken = intval(UTools::getSetting('alg_max_token'));
			if($actualToken >= self::TOKEN){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_DEMO_MASS_GENERATE_LIMIT');
			}
		}
		
		if(!$result['error_message']){
			$result['question_show'] = (UTools::getSetting('show_query') == 'Y');
			$result['show_tokens'] = (UTools::getSetting('show_tokens') == 'Y');
			
			if($input['operation'] == 'TEMPLATE'){
				$input['template_element'] = Encoding::convertFromUtf($input['template_element']);
				$result['question'] = self::makeQuestionByTemplate($input['template_element'], $elementInfo);
			}elseif($input['operation'] == 'IMAGE'){
				$options['content_type'] = 'image';
				$options['input'] = $input;

				$input['template_image'] = Encoding::convertFromUtf($input['template_image']);
				$result['question'] = self::makeQuestionByTemplate($input['template_image'], $elementInfo);
			}else{
				$result['question'] = self::makeQuestion($input, $elementInfo[$input['from']]);
			}

			if($input['files'] && $elementInfo[$input['files']]){
				$result['files_vals'] = $elementInfo[$input['files']];
				$options['files'] = explode(', ', $elementInfo[$input['files']]);
			}

			$result['content_type'] = $options['content_type'];

			foreach(GetModuleEvents(self::MODULE_ID, "modifyElementQuestionBeforeSend", true) as $arEvent)
				ExecuteModuleEventEx($arEvent, [&$result['question'], $input, $elementInfo]);
			
			if($input['preview']){
				return $result;
			}

			Tools::writeModuleDebug(false, 'createElementText', $input);

			$data = self::callChatProvider($result['question'], $options);

			$input['question'] = $result['question'];

			foreach(GetModuleEvents(self::MODULE_ID, "modifyElementAnswer", true) as $arEvent)
				ExecuteModuleEventEx($arEvent, [&$data, $input, $elementInfo]);

			// echo '<pre>'; print_r($data['prepared']); echo '</pre>';

			if($data['prepared']['error']){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CHATGPT_ERROR').' '.$data['prepared']['error_message'];
			}else{
				if($input['mass_generation'] && self::isDemo()){
					UTools::setSetting('alg_max_token', $actualToken + 1);
				}
				
				if($options['content_type'] == 'text'){
					if($data['prepared']['answer']){
						$result['answer'] = $data['prepared']['answer'];
						$result['used_tokens_cnt'] = intval($data['result']['usage']['total_tokens']);
						$result['used_tokens'] = Tools::calculateTokens($result['used_tokens_cnt'], $options['provider']);
					}else{
						$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
						if(isset($data['result']['detail']) && $data['result']['detail']){
							$result['error_message'] .= ': ' . $data['result']['detail'];
						}
					}
				}else{
					if($data['prepared']['image']){
						$result['answer'] = $data['prepared']['image'];
						$result['used_tokens_cnt'] = intval($data['result']['usage']['total_tokens']);
						$result['used_tokens'] = Tools::calculateTokens($result['used_tokens_cnt'], $options['provider']);
					}else{
						$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
					}
				}
			}
		}
		
		// echo '<pre>input '; print_r($input); echo '</pre>';
		// echo '<pre>'; print_r($result); echo '</pre>';
		
		return $result;
	}

	static function checkElementWriteAccess($iblockId, $elementId, $checkPermissions){
		if($iblockId <= 0 || $elementId <= 0){
			return [
				'result' => false,
				'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND'),
			];
		}

		$elementDb = \CIBlockElement::GetList([], [
			'ID' => $elementId,
			'IBLOCK_ID' => $iblockId,
		], false, ['nTopCount' => 1], ['ID']);

		if(!$elementDb->Fetch()){
			return [
				'result' => false,
				'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND'),
			];
		}

		if($checkPermissions){
			$permissionDb = \CIBlockElement::GetList([], [
				'ID' => $elementId,
				'IBLOCK_ID' => $iblockId,
				'CHECK_PERMISSIONS' => 'Y',
				'MIN_PERMISSION' => 'W',
			], false, ['nTopCount' => 1], ['ID']);

			$element = $permissionDb->Fetch();
			if(!$element){
				return [
					'result' => false,
					'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ACCESS_DENIED'),
				];
			}
		}

		return ['result' => true];
	}

	static function checkSectionWriteAccess($iblockId, $sectionId, $checkPermissions){
		if($iblockId <= 0 || $sectionId <= 0){
			return [
				'result' => false,
				'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND'),
			];
		}

		$sectionDb = \CIBlockSection::GetList([], [
			'ID' => $sectionId,
			'IBLOCK_ID' => $iblockId,
		], false, ['ID']);

		if(!$sectionDb->Fetch()){
			return [
				'result' => false,
				'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND'),
			];
		}

		if($checkPermissions){
			$permissionDb = \CIBlockSection::GetList([], [
				'ID' => $sectionId,
				'IBLOCK_ID' => $iblockId,
				'CHECK_PERMISSIONS' => 'Y',
				'MIN_PERMISSION' => 'W',
			], false, ['ID']);

			$element = $permissionDb->Fetch();
			if(!$element){
				return [
					'result' => false,
					'error_message' => Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ACCESS_DENIED'),
				];
			}
		}
		
		return ['result' => true];
	}
	
	static function saveToElement($params){
		Loader::includeModule('iblock');

		$params['ID'] = intval($params['ID']);
		$params['IBLOCK_ID'] = intval($params['IBLOCK_ID']);

		$accessCheck = self::checkElementWriteAccess($params['IBLOCK_ID'], $params['ID'], $params['check_permissions']);
		if(!$accessCheck['result']){
			return $accessCheck;
		}

		if($params['re_encoding']){
			$params['genresult'] = Encoding::convertFromUtf($params['genresult']);
		}
		
		$result = [
			'save_target_id' => $params['ID'],
			'genresult' => $params['genresult'],
			'savefield_type' => 'field',
			'savefield' => $params['savefield'],
		];
		
		if(strpos($result['savefield'], 'PROPERTY_') !== false){
			$result['savefield_type'] = 'property';
			$result['savefield'] = str_replace('PROPERTY_', '', $result['savefield']);
		}elseif(strpos($result['savefield'], 'SEO_') !== false){
			$result['savefield_type'] = 'seo';
			$result['savefield'] = str_replace('SEO_', '', $result['savefield']);
		}

		if($result['savefield_type'] == 'field'){
			$el = new CIBlockElement;

			$addTypeHtml = 0;

			if(in_array($result['savefield'], ['PREVIEW_TEXT_HTML', 'DETAIL_TEXT_HTML'])){
				$result['savefield'] = str_replace('_HTML', '', $result['savefield']);
				$addTypeHtml = 1;
			}
			
			if(in_array($result['savefield'], ['PREVIEW_TEXT', 'DETAIL_TEXT']) && $params['html']){
				$addTypeHtml = 1;
			}

			$updateData = [
				$result['savefield'] => $result['genresult']
			];
			
			if(in_array($result['savefield'], ['PREVIEW_PICTURE', 'DETAIL_PICTURE'])){
				$fileLink = $result['genresult'];
				if(Encoding::exSubstr($fileLink, 0, 8) == '/upload/'){
					$updateData[$result['savefield']] = CFile::MakeFileArray($fileLink);
				}
			}

			if($addTypeHtml){
				$updateData[$result['savefield'].'_TYPE'] = 'html';
			}

			$res = $el->Update($params['ID'], $updateData);
			if(!$res){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_SAVE_ERROR') . $res->LAST_ERROR;
			}else{
				if($result['savefield'] == 'NAME'){
					$ipropElementValues = new InheritedProperty\ElementValues($params['IBLOCK_ID'], $params['ID']);
					$ipropElementValues->clearValues();
				}
			}
		}
		
		if($result['savefield_type'] == 'property'){
			$propertyInfo = [];
			$properties = \CIBlockProperty::GetList([], ["CODE"=>$result['savefield'], "IBLOCK_ID"=>$params['IBLOCK_ID']]);
			if($prop_fields = $properties->GetNext(true, false)){
				$propertyInfo = $prop_fields;
			}
			
			$result['savefield_id'] = $propertyInfo['ID'];

			if($propertyInfo['PROPERTY_TYPE'] == 'L' && !$propertyInfo['USER_TYPE']){
				if($propertyInfo['MULTIPLE'] == 'Y'){
					$saveIDs = [];

					foreach(explode('|', $result['genresult']) as $expval){
						$saveIDs[] = Tools::makeIblockEnumVariant(trim($expval), $params['IBLOCK_ID'], $propertyInfo['ID']);
					}

					$saveData = [$result['savefield'] => $saveIDs];
				}else{
					$saveID = Tools::makeIblockEnumVariant($result['genresult'], $params['IBLOCK_ID'], $propertyInfo['ID']);
					$saveData = [$result['savefield'] => $saveID];
				}
			}elseif($propertyInfo['USER_TYPE'] == 'HTML'){
				$result['savefield_type'] = 'property_html';
				
				$saveData = [
					$result['savefield'] => [
						'VALUE' => ['TYPE'=>'HTML', 'TEXT'=>$result['genresult']]
					]
				];
			}elseif($propertyInfo['PROPERTY_TYPE'] == 'F'){
				$fileLink = $result['genresult'];
				if(Encoding::exSubstr($fileLink, 0, 8) == '/upload/'){
					$fileArray = CFile::MakeFileArray($fileLink);

					if($propertyInfo['MULTIPLE'] == 'Y'){
						$lockStandartSave = 1;

						$fileSaveArray = [];
						$res = \CIBlockElement::GetProperty($params['IBLOCK_ID'], $params['ID'], [], ["CODE" => $result['savefield']]);
						while ($ob = $res->Fetch()) {
							if ($ob["VALUE"]) {
								$fileSaveArray[$ob["PROPERTY_VALUE_ID"]] = [
									"VALUE" => $ob["VALUE"],
									"DESCRIPTION" => $ob["DESCRIPTION"],
								];
							}
						}

						$fileSaveArray['n0'] = ['VALUE' => $fileArray, 'DESCRIPTION' => ''];
						\CIBlockElement::SetPropertyValues($params['ID'], $params['IBLOCK_ID'], $fileSaveArray, $result['savefield']);
					}else{
						$saveData = [$result['savefield'] => $fileArray];
					}
				}
			}else{
				if($propertyInfo['MULTIPLE'] == 'Y'){
					$saveVals = [];

					foreach(explode('|', $result['genresult']) as $expval){
						$saveVals[] = trim($expval);
					}

					$saveData = [$result['savefield'] => $saveVals];
				}else{
					$saveData = [$result['savefield'] => $result['genresult']];
				}
			}

			// echo '<pre>'; print_r($saveData); echo '</pre>';
			// echo '<pre>'; print_r($propertyInfo); echo '</pre>';

			if(!$lockStandartSave){
				\CIBlockElement::SetPropertyValuesEx($params['ID'], $params['IBLOCK_ID'], $saveData);
			}
		}
		
		if($result['savefield_type'] == 'seo'){
			$ipropElementTemplates = new InheritedProperty\ElementTemplates($params['IBLOCK_ID'], $params['ID']);			
			$ipropElementTemplates->set([$result['savefield'] => $result['genresult']]);
			
			$ipropElementValues = new InheritedProperty\ElementValues($params['IBLOCK_ID'], $params['ID']);
			$ipropElementValues->clearValues();
		}

		foreach(GetModuleEvents(self::MODULE_ID, "afterSaveDataToElement", true) as $arEvent)
			ExecuteModuleEventEx($arEvent, [$result]);
		
		return $result;
	}
	
	// section
	static function getSectionBaseData($iblockId, $sectionId){
		$result = [];
		
		if(Loader::IncludeModule("iblock")){
			$domain = (CMain::IsHTTPS() ? "https://" : "http://") . $_SERVER["HTTP_HOST"];

			$db_list = CIBlockSection::GetList([$by=>$order], ['IBLOCK_ID' => $iblockId, 'ID'=>$sectionId], false, ['ID', 'NAME', 'DESCRIPTION', 'IBLOCK_ID', 'SECTION_PAGE_URL', 'PICTURE', 'UF_*']);
			while($ar_result = $db_list->GetNext(true, false)){
				$result = $ar_result;
				
				// echo '<pre>'; print_r($result); echo '</pre>';

				$result['DESCRIPTION_HTML'] = $result['DESCRIPTION'];

				$result['SECTION_PAGE_URL'] = (CMain::IsHTTPS() ? "https://" : "http://") . $_SERVER["HTTP_HOST"] . $result['SECTION_PAGE_URL'];
				
				if($result['PICTURE']){
					$result['PICTURE'] = $domain.CFile::GetPath($result['PICTURE']);
				}

				$ipropElementValues = new InheritedProperty\SectionValues($ar_result['IBLOCK_ID'], $ar_result['ID']);
				$values = $ipropElementValues->getValues();
				foreach($values as $key=>$val){
					$result['SEO_'.$key] = $val;
				}
			}
		}
		
		return $result;
	}
	
	static function checkSectionEmptySaveFiled($ibid, $sid, $field){
		$elementInfo = self::getSectionBaseData($ibid, $sid);
		return ['result' => ($elementInfo[$field] == ''), 'element_name' => $elementInfo['NAME']];
	}

	static function createSectionText($input){
		$result = [];
		
		$options = [
			'content_type' => 'text',
			'provider' => $input['provider'],
			'request_type' => 'generation',
			'object_type' => 'section',
		];
		
		if($input['type'] == 'KEYWORDS'){
			$options['role'] = 'system';
		}

		$elementInfo = self::getSectionBaseData($input['IBLOCK_ID'], $input['ID']);
		$result['element_name'] = $elementInfo['NAME'];
		
		// echo '<pre>'; print_r($input); echo '</pre>';
		// echo '<pre>elementInfo '; print_r($elementInfo); echo '</pre>';
		
		if(!$elementInfo[$input['from']] && !in_array($input['operation'], ['TEMPLATE', 'IMAGE'])){
			$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_EMPTY_FIELD_FROM_ERROR');
		}
		
		if($input['mass_generation'] && self::isDemo()){
			$actualToken = intval(UTools::getSetting('alg_max_token'));
			if($actualToken >= self::TOKEN){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_DEMO_MASS_GENERATE_LIMIT');
			}
		}
		
		if(!$result['error_message']){
			$result['question_show'] = (UTools::getSetting('show_query') == 'Y');
			$result['show_tokens'] = (UTools::getSetting('show_tokens') == 'Y');
			
			if($input['operation'] == 'TEMPLATE'){
				$input['template_section'] = Encoding::convertFromUtf($input['template_section']);
				$result['question'] = self::makeQuestionByTemplate($input['template_section'], $elementInfo);
			}elseif($input['operation'] == 'IMAGE'){
				$options['content_type'] = 'image';
				$options['input'] = $input;

				$input['template_image'] = Encoding::convertFromUtf($input['template_image']);
				$result['question'] = self::makeQuestionByTemplate($input['template_image'], $elementInfo);
			}else{
				$result['question'] = self::makeQuestion($input, $elementInfo[$input['from']]);
			}

			$result['content_type'] = $options['content_type'];
			
			foreach(GetModuleEvents(self::MODULE_ID, "modifySectionQuestionBeforeSend", true) as $arEvent)
				ExecuteModuleEventEx($arEvent, [&$result['question'], $input, $elementInfo]);
			
			if($input['preview']){
				return $result;
			}

			$data = self::callChatProvider($result['question'], $options);

			$input['question'] = $result['question'];
			
			foreach(GetModuleEvents(self::MODULE_ID, "modifySectionAnswer", true) as $arEvent)
				ExecuteModuleEventEx($arEvent, [&$data, $input, $elementInfo]);

			if($data['prepared']['error']){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CHATGPT_ERROR').' '.$data['prepared']['error_message'];
			}else{
				if($input['mass_generation'] && self::isDemo()){
					UTools::setSetting('alg_max_token', $actualToken + 1);
				}

				if($options['content_type'] == 'text'){
					if($data['prepared']['answer']){
						$result['answer'] = $data['prepared']['answer'];
						$result['used_tokens_cnt'] = intval($data['result']['usage']['total_tokens']);
						$result['used_tokens'] = Tools::calculateTokens($result['used_tokens_cnt'], $options['provider']);
					}else{
						$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
						if(isset($data['result']['detail']) && $data['result']['detail']){
							$result['error_message'] .= ': ' . $data['result']['detail'];
						}
					}
				}else{
					if($data['prepared']['image']){
						$result['answer'] = $data['prepared']['image'];
						$result['used_tokens_cnt'] = intval($data['result']['usage']['total_tokens']);
						$result['used_tokens'] = Tools::calculateTokens($result['used_tokens_cnt'], $options['provider']);
					}else{
						$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
					}
				}
			}
		}
		
		// echo '<pre>input '; print_r($input); echo '</pre>';
		// echo '<pre>'; print_r($result); echo '</pre>';
		
		return $result;
	}
	
	static function saveToSection($params){
		Loader::includeModule('iblock');

		$params['ID'] = intval($params['ID']);
		$params['IBLOCK_ID'] = intval($params['IBLOCK_ID']);

		$accessCheck = self::checkSectionWriteAccess($params['IBLOCK_ID'], $params['ID'], $params['check_permissions']);
		if(!$accessCheck['result']){
			return $accessCheck;
		}
		
		if($params['re_encoding']){
			$params['genresult'] = Encoding::convertFromUtf($params['genresult']);
		}
		
		$result = [
			'save_target_id' => $params['ID'],
			'genresult' => $params['genresult'],
			'savefield_type' => 'field',
			'savefield' => $params['savefield'],
		];
		
		if(strpos($result['savefield'], 'UF_') !== false){
			$result['savefield_type'] = 'section_uf';
		}elseif(strpos($result['savefield'], 'SEO_') !== false){
			$result['savefield_type'] = 'seo';
			$result['savefield'] = str_replace('SEO_', '', $result['savefield']);
		}
		
		if($result['savefield_type'] == 'field' || $result['savefield_type'] == 'section_uf'){
			$bs = new CIBlockSection;
			
			$addTypeHtml = 0;

			if(in_array($result['savefield'], ['DESCRIPTION_HTML'])){
				$result['savefield'] = str_replace('_HTML', '', $result['savefield']);
				$addTypeHtml = 1;
			}
			
			if(in_array($result['savefield'], ['DESCRIPTION']) && $params['html']){
				$addTypeHtml = 1;
			}

			$updateData = [
				$result['savefield'] => $result['genresult']
			];

			if(in_array($result['savefield'], ['PICTURE'])){
				$fileLink = $result['genresult'];
				if(Encoding::exSubstr($fileLink, 0, 8) == '/upload/'){
					$updateData[$result['savefield']] = CFile::MakeFileArray($fileLink);
				}
			}
			
			if($addTypeHtml){
				$updateData[$result['savefield'].'_TYPE'] = 'html';
			}

			$res = $bs->Update($params['ID'], $updateData);
			if(!$res){
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_SAVE_ERROR') . $res->LAST_ERROR;
			}else{
				if($result['savefield'] == 'NAME'){
					$ipropElementValues = new InheritedProperty\SectionValues($params['IBLOCK_ID'], $params['ID']);
					$ipropElementValues->clearValues();
				}
			}
		}
		
		if($result['savefield_type'] == 'seo'){
			$ipropElementTemplates = new InheritedProperty\SectionTemplates($params['IBLOCK_ID'], $params['ID']);			
			$ipropElementTemplates->set([$result['savefield'] => $result['genresult']]);
			
			$ipropElementValues = new InheritedProperty\SectionValues($params['IBLOCK_ID'], $params['ID']);
			$ipropElementValues->clearValues();
		}

		foreach(GetModuleEvents(self::MODULE_ID, "afterSaveDataToSection", true) as $arEvent)
			ExecuteModuleEventEx($arEvent, [$result]);
		
		return $result;
	}

	// tasks
	static function taskWorker($task_id){
		$log_path = '/logs_chatgpt/tasks/task_'.$task_id.'.txt';

		Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id, '= Start Iteration');

		$timeLimit = 8;
		$elementWorkLimit = (self::DEBUG) ? 2 : 10;
		
		$elements = Tasks\Element::getTaskWorkElements($task_id, 250);

		if(count($elements)){
			$genCount = 0;
			$start = microtime(true);

			$task = Tasks\Task::getTaskByID($task_id);
			
			foreach($elements as $element){
				$workTime = round(microtime(true) - $start, 2);
				if($workTime > $timeLimit) break;
				if($genCount >= $elementWorkLimit) break;

				Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id.' Work $element', $element);

				if(is_array($task)){
					if($task['UF_ETYPE'] == 'E'){
						$result = self::taskElementWork($task, $element);
					}elseif($task['UF_ETYPE'] == 'S'){
						$result = self::taskSectionWork($task, $element);
					}
				}

				if($result['error_message'] || $result['error_type']){
					if($result['error_type'] != 'skip_value'){
						$genCount++;
					}

					Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id.' error $result', $result);

					$finishTaskError = 0;

					$updateElement = [
						'UF_GENERATION_DATE' => date('d.m.Y H:i:s'),
						'UF_PARAMS' => (is_array($element['UF_PARAMS']) ? $element['UF_PARAMS'] : []),
					];
						
					$updateElement['UF_PARAMS']['error_type'] = $result['error_type'];
					$updateElement['UF_PARAMS']['error_message'] = $result['error_message'];

					if(in_array($result['error_type'], ['skip_value', 'not_found'])){
						$updateElement['UF_STATUS'] = 'skip';
					}elseif(in_array($result['error_type'], ['demo_limit', 'save_error'])){
						$updateElement['UF_STATUS'] = 'error';
						$finishTaskError = 1;
					}else{
						$errorCount = intval($element['UF_PARAMS']['error_count']) + 1;

						if($errorCount >= 5){
							$updateElement['UF_STATUS'] = 'error';
							$finishTaskError = 1;
						}
						
						$updateElement['UF_PARAMS']['error_count'] = $errorCount;
					}

					$r = Tasks\Element::update($element['ID'], $updateElement);

					if($finishTaskError){
						$updateTask = [
							'UF_STATUS' => 'stop_error',
							'UF_PARAMS' => $task['UF_PARAMS'],
						];

						$updateTask['UF_PARAMS']['error_type'] = $result['error_type'];
						$updateTask['UF_PARAMS']['error_message'] = $result['error_message'];
						
						$r = Tasks\Task::finishTask($task_id, $updateTask);
					}
				}else{
					$genCount++;

					Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id.' success $result', $result);

					$updateElement = [
						'UF_STATUS' => 'success',
						'UF_GENERATION_DATE' => date('d.m.Y H:i:s'),
						'UF_GENERATION_RESULT' => $result['generated_data'],
						'UF_VALUE_BACKUP' => $result['base_field_value'],
						'UF_PARAMS' => '',
					];
					$r = Tasks\Element::update($element['ID'], $updateElement);
				}
			}
		}else{
			Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id, 'finish task, no elements');

			Tasks\Task::finishTask($task_id, [
				'UF_STATUS' => 'finish',
			]);
		}
		
		Tools::writeModuleDebug($log_path, 'TaskWorker #'.$task_id, '= End Iteration');

		return 'CArturgolubevChatgpt::taskWorker('.$task_id.');';
	}
	
		static function taskElementWork($task, $element){
			$result = [];

			$options = [
				'content_type' => 'text',
				'provider' => $task['UF_PARAMS']['provider'],
				'request_type' => 'task_generation',
				'object_type' => 'element',
				'task_id' => $task['ID'],
			];

			$input = [
				'is_automatic_task' => 1,
			];
			
			$elementInfo = self::getElementBaseData($element['UF_ELEMENT']);
			if(!is_array($elementInfo) || !count($elementInfo)){
				$result['error_type'] = 'not_found';
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND');
			}

			$result['base_field_value'] = $elementInfo[$task['UF_PARAMS']['save_field']];

			if(self::checkDemoAccess()){
				$result['error_type'] = 'demo_limit';
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_DEMO_MASS_GENERATE_LIMIT');
			}

			Tools::writeModuleDebug(false, 'taskElementWork empty check base_field_value', $result['base_field_value']);

			if($task['UF_PARAMS']['save_only_empty'] == 'Y'){
				if($result['base_field_value']){
					$result['error_type'] = 'skip_value';
					$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_SKIP_BY_VALUE');
				}
			}
			
			if(!$result['error_message']){
				$input['files'] = $task['UF_PARAMS']['files'];
				$input['template_element'] = Encoding::convertFromUtf($task['UF_PROMPT']);
				$input['question'] = self::makeQuestionByTemplate($input['template_element'], $elementInfo);
				
				if($input['files'] && $elementInfo[$input['files']]){
					$result['files_vals'] = $elementInfo[$input['files']];
					$options['files'] = explode(', ', $elementInfo[$input['files']]);
				}

				foreach(GetModuleEvents(self::MODULE_ID, "modifyElementQuestionBeforeSend", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, [&$input['question'], $input, $elementInfo]);
				
				Tools::writeModuleDebug(false, 'taskElementWork before request input', $input);

				$data = self::callChatProvider($input['question'], $options);

				Tools::writeModuleDebug(false, 'taskElementWork after request data', $data);
				
				foreach(GetModuleEvents(self::MODULE_ID, "modifyElementAnswer", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, [&$data, $input, $elementInfo]);

				if($data['prepared']['error']){
					$result['error_type'] = $data['prepared']['error_type'];
					$result['error_message'] = $data['prepared']['error_message'];
				}else{
					self::checkDemoCounts();
					$result['generated_data'] = $data['prepared']['answer'];
				}
			}

			if(!$result['error_message']){
				$saveParams = [
					'genresult' => $result['generated_data'],
					'savefield' => $task['UF_PARAMS']['save_field'],
					'ID' => $element['UF_ELEMENT'],
					'IBLOCK_ID' => $task['UF_IBLOCK'],
				];

				Tools::writeModuleDebug(false, 'taskElementWork before save saveParams', $saveParams);

				$saveResult = self::saveToElement($saveParams);
				if($saveResult['error_message']){
					$result['error_type'] = 'save_error';
					$result['error_message'] = $saveResult['error_message'];
				}
			}
			
			Tools::writeModuleDebug(false, 'taskElementWork result', $result);

			return $result;
		}


		static function taskSectionWork($task, $element){
			// echo '<pre>task section '; print_r($task); echo '</pre>';
			// echo '<pre>element '; print_r($element); echo '</pre>';

			$result = [];

			$options = [
				'content_type' => 'text',
				'provider' => $task['UF_PARAMS']['provider'],
				'request_type' => 'task_generation',
				'object_type' => 'section',
				'task_id' => $task['ID'],
			];

			$input = [
				'is_automatic_task' => 1,
			];

			$elementInfo = self::getSectionBaseData($task['UF_IBLOCK'], $element['UF_ELEMENT']);
			if(!is_array($elementInfo) || !count($elementInfo)){
				$result['error_type'] = 'not_found';
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_ELEMENT_NOT_FOUND');
			}

			$result['base_field_value'] = $elementInfo[$task['UF_PARAMS']['save_field']];

			if(self::checkDemoAccess()){
				$result['error_type'] = 'demo_limit';
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_DEMO_MASS_GENERATE_LIMIT');
			}

			// echo '<pre>'; print_r($result); echo '</pre>';
			// echo '<pre>'; print_r($task); echo '</pre>';
			// die();

			if($task['UF_PARAMS']['save_only_empty'] == 'Y'){
				if($result['base_field_value']){
					$result['error_type'] = 'skip_value';
					$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_SKIP_BY_VALUE');
				}
			}

			if(!$result['error_message']){
				$input['template_section'] = Encoding::convertFromUtf($task['UF_PROMPT']);
				$input['question'] = self::makeQuestionByTemplate($input['template_section'], $elementInfo);

				foreach(GetModuleEvents(self::MODULE_ID, "modifySectionQuestionBeforeSend", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, [&$result['question'], $input, $elementInfo]);
				
				Tools::writeModuleDebug(false, 'createSectionText AutoTask', $input);
				
				$data = self::callChatProvider($input['question'], $options);

				foreach(GetModuleEvents(self::MODULE_ID, "modifySectionAnswer", true) as $arEvent)
					ExecuteModuleEventEx($arEvent, [&$data, $input, $elementInfo]);

				if($data['prepared']['error']){
					$result['error_type'] = $data['prepared']['error_type'];
					$result['error_message'] = $data['prepared']['error_message'];
				}else{
					self::checkDemoCounts();
					$result['generated_data'] = $data['prepared']['answer'];
				}
			}

			if(!$result['error_message']){
				$saveParams = [
					'genresult' => $result['generated_data'],
					'savefield' => $task['UF_PARAMS']['save_field'],
					'ID' => $element['UF_ELEMENT'],
					'IBLOCK_ID' => $task['UF_IBLOCK'],
				];

				$saveResult = self::saveToSection($saveParams);
				if($saveResult['error_message']){
					$result['error_type'] = 'save_error';
					$result['error_message'] = $saveResult['error_message'];
				}
			}

			return $result;
		}

	// events
	static function addActionMenu(&$list){
		$list = \Arturgolubev\Chatgpt\Ehandlers::addActionMenu($list);
	}
	
	static function onEpilog(){
		if(!Loader::IncludeModule(self::MODULE_ID) || !defined("ADMIN_SECTION")) return 0;
		
		$cur = UTools::GetCurPage();
		if(Tools::checkRights('question')){
			$element = ($cur == '/bitrix/admin/iblock_element_edit.php' ? 1 : 0);
			$section = ($cur == '/bitrix/admin/iblock_section_edit.php' ? 1 : 0);
			
			if($element || $section){
				\CJSCore::Init(["ag_chatgpt_base"]);
				
				$genOption = [
					'ENTITY_TYPE' => ($element ? 'element' : 'section'),
					'ID' => $_GET['ID'],
					'IBLOCK_ID' => $_GET['IBLOCK_ID'],
				];
				
				echo '<script>agcg.initElementButton('.CUtil::PhpToJSObject($genOption).');</script>';
			}
			
			$isSectionPage = ($cur == '/bitrix/admin/iblock_section_admin.php');
			$isCatalogPage = ($cur == '/bitrix/admin/iblock_list_admin.php' || $cur == '/bitrix/admin/cat_product_list.php' || $isSectionPage);
			$isCatalogElementPage = ($cur == '/bitrix/admin/iblock_element_admin.php' || $cur == '/bitrix/admin/cat_product_admin.php');
			
			if($isCatalogPage || $isCatalogElementPage){
				\CJSCore::Init(["ag_chatgpt_base", 'ag_chatgpt_tasks']);
				
				$workElements = Tools::getWorkElements([
					'isSectionPage' => $isSectionPage
				]);
				
				if(is_array($_POST["action"])){
					foreach($_POST["action"] as $action){
						if($action == "agcg_generate")
							$showGenerationWindow = 1;
						
						if($action == "agcg_queue")
							$showAddTaskWindow = 1;
					}
				}else{
					$action = $_POST["action"];

					if($action == 'agcg_generate')
						$showGenerationWindow = 1;
					
					if($action == 'agcg_queue')
						$showAddTaskWindow = 1;
				}
				
				if($showGenerationWindow){
					$demo = self::isDemo();
					$dcount = self::TOKEN - intval(UTools::getSetting('alg_max_token'));
					if($dcount < 0){
						$dcount = 0;
					}
					?>
						<script>
							var agcgInitParams = {
								demo: <?=intval($demo)?>,
								dcount: <?=$dcount?>,
								action_all_rows: <?=intval($workElements['action_all_rows'])?>,
								ibid: <?=IntVal($_GET["IBLOCK_ID"])?>,
								eids: <?=\CUtil::PhpToJSObject($workElements['elements'])?>,
								sids: <?=\CUtil::PhpToJSObject($workElements['sections'])?>,
								limits: "<?=(defined('AG_CHATGPT_UNLOCK_LIMITS') && AG_CHATGPT_UNLOCK_LIMITS == 'Y') ? 'N' : 'Y'?>",
							};
							top.agcg.initMassWork(agcgInitParams);
						</script>
					<?
				}

				if($showAddTaskWindow){
					?>
						<script>
							var agcgInitParams = {
								action_all_rows: <?=intval($workElements['action_all_rows'])?>,
								ibid: <?=IntVal($_GET["IBLOCK_ID"])?>,
								eids: <?=\CUtil::PhpToJSObject($workElements['elements'])?>,
								sids: <?=\CUtil::PhpToJSObject($workElements['sections'])?>,
								limits: "<?=(defined('AG_CHATGPT_UNLOCK_LIMITS') && AG_CHATGPT_UNLOCK_LIMITS == 'Y') ? 'N' : 'Y'?>",
							};
							top.agcg.initAddWindow(agcgInitParams);
						</script>
					<?
				}
			}
		}
	}
}
?>