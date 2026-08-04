<?
namespace Arturgolubev\Chatgpt;

use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Hl;

class Tools {
	const TIMEOUT = 180;
	
	// simple
	static function checkGlobalUser(){
		global $USER;
		if(!is_object($USER)){
			$USER = new \CUser();
		}
	}
	
	static function checkRights($dir){
		global $USER;
		
		self::checkGlobalUser();
		
		if($USER->IsAdmin()) return 1;

		if($dir != 'settings'){
			$arGroups = $USER->GetUserGroupArray();

			$dirGroups = explode(',', UTools::getSetting('rights_'.$dir));
			foreach($dirGroups as $dg){
				if(in_array($dg, $arGroups)){
					return 1;
				}
			}
		}
		
		return 0;
	}
	
	static function writeModuleDebug($path, $module, $data){
		if(\CArturgolubevChatgpt::LOG_DEBUG){
			// UTools::simpleDataLog($path, $module, $data);
			AddMessage2Log($data, 'ag.chatgpt '.$module, 0);
		}
	}
	
	static function checkHlTemplate(){
		include_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/arturgolubev.chatgpt/lib/installation.php';
		\agInstaHelperChatgpt::installTemplateHL();
	}

	static function getSavedTemplates($filter){
		$result = [];

		$hlID = UTools::getSetting('hl_id_templates');
		if($hlID && Loader::includeModule("highloadblock")){
			try {
				$edc = Hl::getDataClassByID($hlID);
				$rsData = $edc::getList([
					"select" => ["*"],
					"order" => ["UF_SORT" => "ASC"],
					"filter" => $filter
				]);
				while($arData = $rsData->Fetch()){
					$result[] = $arData;
				}
			} catch (\Bitrix\Main\SystemException $e) {
				// $error = true; //$e->getMessage();
				// echo '<pre>'; print_r($e->getMessage()); echo '</pre>';
			}
		}

		return $result;
	}

	static function makeIblockEnumVariant($value, $iblockID, $pid){
		$save_xml_id = \Cutil::translit($value, "ru", ["replace_space" => "_", "replace_other"=> "-"]);

		$db_enum_list = \CIBlockProperty::GetPropertyEnum($pid, [], Array("IBLOCK_ID"=>$iblockID, "VALUE"=>$value));
		if($ar_enum_list = $db_enum_list->GetNext()){
			$saveID = $ar_enum_list['ID'];
		}

		if(!$saveID){
			$db_enum_list = \CIBlockProperty::GetPropertyEnum($pid, [], Array("IBLOCK_ID"=>$iblockID, "XML_ID"=>$save_xml_id));
			if($ar_enum_list = $db_enum_list->GetNext()){
				$saveID = $ar_enum_list['ID'];
			}
		}

		if(!$saveID){
			$ibpenum = new \CIBlockPropertyEnum;
			$saveID = $ibpenum->Add([
				'PROPERTY_ID'=>$pid,
				'VALUE'=> $value,
				'XML_ID'=> $save_xml_id,
			]);
		}

		return $saveID;
	}

	static function remakeProxy(){
		$proxyList = UTools::explodeByEOL(UTools::getSetting('proxy'));
		if(count($proxyList) && $proxyList[0]){
			$newFormat = [];

			$arProxyNext = explode(' ', $proxyList[0]);

			if($arProxyNext[0]){
				$tmp = explode(':', $arProxyNext[0]);
				$newFormat['ip'] = ($tmp[0]) ? $tmp[0] : '';
				$newFormat['port'] = ($tmp[1]) ? $tmp[1] : '';

				UTools::setSetting('proxy_ip', $newFormat['ip']);
				UTools::setSetting('proxy_port', $newFormat['port']);
			}

			if($arProxyNext[1]){
				$tmp = explode(':', $arProxyNext[1]);
				$newFormat['login'] = ($tmp[0]) ? $tmp[0] : '';
				$newFormat['pass'] = ($tmp[1]) ? $tmp[1] : '';

				UTools::setSetting('proxy_login', $newFormat['login']);
				UTools::setSetting('proxy_password', $newFormat['pass']);
			}
			
			UTools::setSetting('proxy', '');
		}
	}

	static function getGuid4(){
		if (function_exists('com_create_guid') === true){
			return trim(com_create_guid(), '{}');
		}
	
		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}

	static function getTimeout(){
		$timeout = intval(UTools::getSetting('max_wait_time'));
		if(!$timeout) $timeout = self::TIMEOUT;

		return $timeout;
	}

	static function prepareAnswer($answ){
		if(mb_substr($answ, 0, 1) == '"' && mb_substr($answ, -1, 1) == '"'){
			$answ = mb_substr($answ, 1);
			$answ = mb_substr($answ, 0, -1);
		}

		$answ = str_replace(['```html', '```'], '', $answ);

		$markdown = UTools::getBoolSetting('auto_convert_markdown');
		if($markdown && (mb_strpos($answ, "#") !== false || mb_strpos($answ, "**") !== false)){
			$markdownParser = new \Arturgolubev\Chatgpt\Vendor\Parsedown();
			$markdownParser->setSafeMode(true);
			$answ = $markdownParser->text($answ);
		}
		
		return $answ;
	}

	static function prepareResult($options, $result){
		$prepared = [
			'content_type' => $options['content_type'],
		];

		$result_data = $result['result'];

		if(is_array($result_data['error'])){
			$prepared['error_type'] = 'generation_error';
			$prepared['error_message'] = $result_data['error']['message'] ? $result_data['error']['message'] : $result_data['error']['code'];
		}else{
			if(!$options['content_type'] || $options['content_type'] == 'text'){
				$choise = $result_data['choices'][0];

				$answ = $choise['message']['content'];
				if($answ){
					$prepared['answer'] = self::prepareAnswer($answ);
				}else{
					if(isset($result_data['detail']) && $result_data['detail']){
						$prepared['error_type'] = 'generation_answer_error';
						$prepared['error_message'] = $result_data['detail'];
					}elseif(isset($choise['finish_reason']) && $choise['finish_reason']){
						$prepared['error_type'] = 'generation_answer_error';

						if($choise['finish_reason'] == 'length'){
							$prepared['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_ERROR_REASON_LENGTH');
						}else{
							$prepared['error_message'] = 'finish_reason: '.$choise['finish_reason'];
						}
					}else{
						$prepared['error_type'] = 'generation_answer_error';
						$prepared['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_GENERAL_CREATE_ERROR');
					}
				}
			}
		}
		
		if($prepared['error_type']){
			$prepared['error'] = 1;
		}

		$result['prepared'] = $prepared;
		
		return $result;
	}

	static function getAiList(){
		$result = [];

		$gptKey = UTools::getSetting('api_key');
		$sberKey = UTools::getSetting('sber_authorization');
		$deepseekKey = UTools::getSetting('deepseek_api_key');
		$openAiCompatibleKey = UTools::getSetting('openai_compatible_api_key');

		if($gptKey || (!$gptKey && !$sberKey)){
			$result[] = 'chatgpt';
		}

		if($sberKey){
			$result[] = 'sber';
		}

		if($deepseekKey){
			$result[] = 'deepseek';
		}

		if($openAiCompatibleKey){
			$result[] = 'openai_compatible';
		}

		return $result;
	}
	
	static function calculateTokens($tokens, $provider){
		return Loc::getMessage("ARTURGOLUBEV_CHATGPT_MAIN_WRITE_TOKEN_PRICING", ['#tcount#' => $tokens]);
	}

	static function getWorkElements($params){
		$items = [
			'elements' => [],
			'sections' => [],
			'action_all_rows' => 0,
			'table_name' => '',
		];

		foreach($_POST["ID"] as $v){
			$first = substr($v, 0, 1);
			
			if($first == 'S'){
				$items['sections'][] = substr($v, 1);
			}elseif($params['isSectionPage']){
				$items['sections'][] = $v;
			}elseif($first == 'E'){
				$items['elements'][] = substr($v, 1);
			}else{
				$items['elements'][] = $v;
			}
		}

		if(is_array($_POST["action"])){
			foreach($_POST["action"] as $ak => $av){
				if(strpos($ak, 'action_all_rows') !== false){
					$items['action_all_rows'] = 1;
					$items['table_name'] = str_replace('action_all_rows_', '', $ak);
				}
			}
		}

		if($items['action_all_rows']){
			$lAdmin = new \CAdminUiList($items['table_name'], []);

			if($lAdmin->IsGroupActionToAll()){
				$arFilter = self::getIbGlobalFilter($lAdmin, [
					'sTableID' => $items['table_name'],
				]);

				$rsData = \CIBlockElement::GetList($arOrder, $arFilter, false, false, array('ID'));
				while ($arRes = $rsData->Fetch()){
					if(!in_array($arRes['ID'], $items['elements']))
						$items['elements'][] = $arRes['ID'];
				}

			}
		}

		return $items;
	}

	static function fillInputByRequest($params){

		// images
		$params['template_image'] = htmlspecialcharsbx($_POST['template_image']);
		$params['size'] = htmlspecialcharsbx($_POST['size']);
		$params['quality'] = htmlspecialcharsbx($_POST['quality']);
		$params['output_format'] = htmlspecialcharsbx($_POST['output_format']);

		return $params;
	}


	static function getIbGlobalFilter($lAdmin, $params){
		$boolSKU = false;
		$boolSKUFiltrable = false;

		$iblockID = $_REQUEST['IBLOCK_ID'];

		$bCatalog = Loader::includeModule("catalog");
		if($bCatalog){
			$arCatalog = \CCatalogSKU::GetInfoByIBlock($iblockID);
			if (empty($arCatalog)){
				$bCatalog = false;
			}else{
				if (\CCatalogSKU::TYPE_PRODUCT == $arCatalog['CATALOG_TYPE'] || \CCatalogSKU::TYPE_FULL == $arCatalog['CATALOG_TYPE']){
					if (\CIBlockRights::UserHasRightTo($arCatalog['IBLOCK_ID'], $arCatalog['IBLOCK_ID'], "iblock_admin_display")){
						$boolSKU = true;
					}
				}
			}

			if($boolSKU){
				$iterator = \Bitrix\Iblock\PropertyTable::getList([
					'select' => ['*'],
					'filter' => [
						'=IBLOCK_ID' => $arCatalog['IBLOCK_ID'],
						'!=ID' => $arCatalog['SKU_PROPERTY_ID'],
						'!=PROPERTY_TYPE' => \Bitrix\Iblock\PropertyTable::TYPE_FILE,
						'=ACTIVE' => 'Y',
						'=FILTRABLE' => 'Y'
					],
					'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
				]);
				if($arProp = $iterator->fetch()){
					$boolSKUFiltrable = true;
				}
				
				$propertySKUManager = new \Bitrix\Iblock\Helpers\Filter\PropertyManager($arCatalog["IBLOCK_ID"]);
				$propertySKUFilterFields = $propertySKUManager->getFilterFields();
			}
		}

		$filterFields = array(
			array(
				"id" => "NAME",
				"name" => GetMessage("IBLOCK_FIELD_NAME"),
				"filterable" => "?",
				"quickSearch" => "?",
				"default" => true
			),
			array(
				"id" => "ID",
				"name" => rtrim(GetMessage("IBLOCK_FILTER_FROMTO_ID"), ":"),
				"type" => "number",
				"filterable" => ""
			)
		);
		$filterFields[] = array(
			"id" => "SECTION_ID",
			"name" => GetMessage("IBLOCK_FIELD_SECTION_ID"),
			"type" => "list",
			"items" => $sectionItems,
			"filterable" => "",
			"default" => true
		);
		$filterFields[] = array(
			"id" => "INCLUDE_SUBSECTIONS",
			"name" => GetMessage("IBLOCK_INCLUDING_SUBSECTIONS"),
			"type" => "checkbox",
			"filterable" => "",
			"default" => true
		);
		$filterFields[] = array(
			"id" => "DATE_MODIFY_FROM",
			"name" => GetMessage("IBLOCK_FIELD_TIMESTAMP_X"),
			"type" => "date",
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "MODIFIED_USER_ID",
			"name" => GetMessage("IBLOCK_FIELD_MODIFIED_BY"),
			"type" => "custom_entity",
			"selector" => array("type" => "user"),
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "DATE_CREATE",
			"name" => GetMessage("IBLOCK_EL_ADMIN_DCREATE"),
			"type" => "date",
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "CREATED_USER_ID",
			"name" => rtrim(GetMessage("IBLOCK_EL_ADMIN_WCREATE"), ":"),
			"type" => "custom_entity",
			"selector" => array("type" => "user"),
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "DATE_ACTIVE_FROM",
			"name" => GetMessage("IBEL_A_ACTFROM"),
			"type" => "date",
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "DATE_ACTIVE_TO",
			"name" => GetMessage("IBEL_A_ACTTO"),
			"type" => "date",
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "ACTIVE",
			"name" => GetMessage("IBLOCK_FIELD_ACTIVE"),
			"type" => "list",
			"items" => array(
				"Y" => GetMessage("IBLOCK_YES"),
				"N" => GetMessage("IBLOCK_NO")
			),
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "SEARCHABLE_CONTENT",
			"name" => rtrim(GetMessage("IBLOCK_EL_ADMIN_DESC"), ":"),
			"filterable" => "?"
		);
		$filterFields[] = array(
			"id" => "CODE",
			"name" => GetMessage("IBEL_A_CODE"),
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "EXTERNAL_ID",
			"name" => GetMessage("IBEL_A_EXTERNAL_ID"),
			"filterable" => ""
		);
		$filterFields[] = array(
			"id" => "TAGS",
			"name" => GetMessage("IBEL_A_TAGS"),
			"filterable" => "?"
		);
		if ($bCatalog){
			$filterFields[] = array(
				"id" => "CATALOG_TYPE",
				"name" => GetMessage("IBEL_CATALOG_TYPE"),
				"type" => "list",
				"items" => $productTypeList,
				"params" => array("multiple" => "Y"),
				"filterable" => ""
			);
			$filterFields[] = array(
				"id" => "CATALOG_BUNDLE",
				"name" => GetMessage("IBEL_CATALOG_BUNDLE"),
				"type" => "list",
				"items" => array(
					"Y" => GetMessage("IBLOCK_YES"),
					"N" => GetMessage("IBLOCK_NO")
				),
				"filterable" => ""
			);
			$filterFields[] = array(
				"id" => "CATALOG_AVAILABLE",
				"name" => GetMessage("IBEL_CATALOG_AVAILABLE"),
				"type" => "list",
				"items" => array(
					"Y" => GetMessage("IBLOCK_YES"),
					"N" => GetMessage("IBLOCK_NO")
				),
				"filterable" => ""
			);
			$filterFields[] = [
				"id" => "QUANTITY",
				"name" => GetMessage("IBEL_CATALOG_QUANTITY_EXT"),
				"type" => "number",
				"filterable" => ""
			];
			$filterFields[] = array(
				"id" => "MEASURE",
				"name" => GetMessage("IBEL_CATALOG_MEASURE_TITLE"),
				"type" => "list",
				"items" => $measureList,
				"params" => array("multiple" => "Y"),
				"filterable" => ""
			);
		}
		
		$propertyManager = new \Bitrix\Iblock\Helpers\Filter\PropertyManager($iblockID);
		$filterFields = array_merge($filterFields, $propertyManager->getFilterFields());

		$arFilter = [
			"IBLOCK_ID" => $iblockID,
			"SHOW_NEW" => "Y",
			"CHECK_PERMISSIONS" => "Y",
			"MIN_PERMISSION" => "R",
		];

		$lAdmin->AddFilter($filterFields, $arFilter);
		$propertyManager->AddFilter($params['sTableID'], $arFilter);

		$arSubQuery = [];
		if ($boolSKU)
		{
			$filterFields = array_merge($filterFields, $propertySKUFilterFields);
			if ($boolSKUFiltrable)
			{
				$arSubQuery = array("IBLOCK_ID" => $arCatalog["IBLOCK_ID"]);
				$lAdmin->AddFilter($propertySKUFilterFields, $arSubQuery);
				$propertySKUManager->AddFilter($sTableID, $arSubQuery);
			}
		}
		if (isset($arFilter["SECTION_ID"]))
		{
			$find_section_section = (int)$arFilter["SECTION_ID"];
		}
		
		if (!empty($arFilter[">=DATE_MODIFY_FROM"]))
		{
			$arFilter["DATE_MODIFY_FROM"] = $arFilter[">=DATE_MODIFY_FROM"];
			$arFilter["DATE_MODIFY_TO"] = $arFilter["<=DATE_MODIFY_FROM"];
			unset($arFilter[">=DATE_MODIFY_FROM"]);
			unset($arFilter["<=DATE_MODIFY_FROM"]);
		}
		
		if ($boolSKU && 1 < sizeof($arSubQuery))
		{
			$arFilter["ID"] = CIBlockElement::SubQuery("PROPERTY_".$arCatalog["SKU_PROPERTY_ID"], $arSubQuery);
		}
		
		$emptySectionId =
			$find_section_section === ''
			|| $find_section_section === null
			|| (int)$find_section_section < 0
		;
		
		if ($emptySectionId)
		{
			unset($arFilter['SECTION_ID']);
			unset($arFilter['INCLUDE_SUBSECTIONS']);
		}

		return $arFilter;
	}
}