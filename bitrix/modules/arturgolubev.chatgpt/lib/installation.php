<?
if (!class_exists('agInstaHelperChatgpt')){
	class agInstaHelperChatgpt {
		const MODULE_ID = 'arturgolubev.chatgpt';

		static function IncludeAdminFile($m, $p){
			global $APPLICATION, $DOCUMENT_ROOT;
			$APPLICATION->IncludeAdminFile($m, $DOCUMENT_ROOT.$p);
		}
		
		static function installTemplateHL(){
			IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".self::MODULE_ID."/installation.php");
			\Bitrix\Main\Loader::includeModule("highloadblock");

			// templates 
			$entityName = 'AGChatGpt';
			$entityTable = 'ag_chatgpt_templates';
			$ruName = GetMessage('ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_NAME');
			
			$entityID = self::_checkHlStructure_addhl($entityName, $entityTable, $ruName);

			if($entityID){
				$entityObject = 'HLBLOCK_'.$entityID;
				
				$arEntityFields = [
					'UF_NAME'=> [
						'ENTITY_ID' => $entityObject,
						'FIELD_NAME' => 'UF_NAME',
						'USER_TYPE_ID' => 'string',
						'MANDATORY' => 'Y',
						"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_NAME"), 'en'=>'NAME'], 
						"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_NAME"), 'en'=>'NAME'],
						"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_NAME"), 'en'=>'NAME'], 
						"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
						"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
					],
					'UF_SORT'=> [
						'ENTITY_ID' => $entityObject,
						'FIELD_NAME' => 'UF_SORT',
						'USER_TYPE_ID' => 'integer',
						'MANDATORY' => '',
						"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_SORT"), 'en'=>'SORT'], 
						"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_SORT"), 'en'=>'SORT'],
						"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_SORT"), 'en'=>'SORT'], 
						"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
						"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
					],
					'UF_TEMPLATE'=> [
						'ENTITY_ID' => $entityObject,
						'FIELD_NAME' => 'UF_TEMPLATE',
						'USER_TYPE_ID' => 'string',
						'MANDATORY' => 'Y',
						"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TEMPLATE"), 'en'=>'TEMPLATE'], 
						"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TEMPLATE"), 'en'=>'TEMPLATE'],
						"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TEMPLATE"), 'en'=>'TEMPLATE'], 
						"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
						"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						"SETTINGS" => ['ROWS'=>'4'],
					],
					'UF_TYPE'=> [
						'ENTITY_ID' => $entityObject,
						'FIELD_NAME' => 'UF_TYPE',
						'USER_TYPE_ID' => 'enumeration',
						'MANDATORY' => 'N',
						'MULTIPLE' => 'Y',
						"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TYPE"), 'en'=>'TYPE'], 
						"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TYPE"), 'en'=>'TYPE'],
						"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TYPE"), 'en'=>'TYPE'], 
						"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
						"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						"SETTINGS" => ['LIST_HEIGHT'=>'5'],
					],
				];

				// echo '<pre>'; print_r($arEntityFields); echo '</pre>';

				$fieldsMap = self::_checkHlStructure_addhlprops($entityID, $arEntityFields);

				self::_checkHlStructure_addEnums($entityObject, 'UF_TYPE', [
					['XML_ID' => 'sections', 'VALUE' => GetMessage('ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TYPE_SECTIONS')],
					['XML_ID' => 'elements', 'VALUE' => GetMessage('ARTURGOLUBEV_CHATGPT_INSTALL_HL_TEMPLATE_PROP_TYPE_ELEMENTS')],
				]);

				self::setOption('hl_id_templates', $entityID);
			}

			// autoloaders 
			if(true){
				$entityName = 'AGChatGptTasks';
				$entityTable = 'ag_chatgpt_tasks';
				$ruName = GetMessage('ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_NAME');

				$entityID = self::_checkHlStructure_addhl($entityName, $entityTable, $ruName);

				if($entityID){
					$entityObject = 'HLBLOCK_'.$entityID;
					$arEntityFields = [
						'UF_NAME'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_NAME',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'Y',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_NAME"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_NAME"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_NAME"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_IBLOCK'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_IBLOCK',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'Y',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_IBLOCK"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_IBLOCK"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_IBLOCK"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_ETYPE'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_ETYPE',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'Y',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_ETYPE"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_ETYPE"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_ETYPE"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_STATUS'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_STATUS',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_PROMPT'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_PROMPT',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PROMPT"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PROMPT"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PROMPT"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
							"SETTINGS" => ['ROWS'=>'4'],
						],
						'UF_PARAMS'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_PARAMS',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
							"SETTINGS" => ['ROWS'=>'4'],
						],
					];

					$fieldsMap = self::_checkHlStructure_addhlprops($entityID, $arEntityFields);

					self::setOption('hl_id_tasks', $entityID);
				}

				$entityName = 'AGChatGptTaskElements';
				$entityTable = 'ag_chatgpt_task_elements';
				$ruName = GetMessage('ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASK_ELEMENTS_NAME');

				$entityID = self::_checkHlStructure_addhl($entityName, $entityTable, $ruName);

				
				if($entityID){
					$entityObject = 'HLBLOCK_'.$entityID;
					$arEntityFields = [
						'UF_TASK'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_TASK',
							'USER_TYPE_ID' => 'integer',
							'MANDATORY' => 'Y',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASK_PROP_NAME"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASK_PROP_NAME"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASK_PROP_NAME"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_ELEMENT'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_ELEMENT',
							'USER_TYPE_ID' => 'integer',
							'MANDATORY' => 'Y',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_ELEMENT_PROP_NAME"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_ELEMENT_PROP_NAME"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_ELEMENT_PROP_NAME"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_STATUS'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_STATUS',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_STATUS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_GENERATION_DATE'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_GENERATION_DATE',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_DATE_PROP_STATUS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_DATE_PROP_STATUS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_DATE_PROP_STATUS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
						],
						'UF_GENERATION_RESULT'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_GENERATION_RESULT',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_RESULT_PROP_STATUS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_RESULT_PROP_STATUS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_GENERATION_RESULT_PROP_STATUS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
							"SETTINGS" => ['ROWS'=>'4'],
						],
						'UF_VALUE_BACKUP'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_VALUE_BACKUP',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_VALUE_BACKUP_PROP_STATUS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_VALUE_BACKUP_PROP_STATUS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_VALUE_BACKUP_PROP_STATUS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
							"SETTINGS" => ['ROWS'=>'4'],
						],
						'UF_PARAMS'=> [
							'ENTITY_ID' => $entityObject,
							'FIELD_NAME' => 'UF_PARAMS',
							'USER_TYPE_ID' => 'string',
							'MANDATORY' => 'N',
							"EDIT_FORM_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'], 
							"LIST_COLUMN_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'],
							"LIST_FILTER_LABEL" => ['ru'=> GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_HL_TASKS_PROP_PARAMS"), 'en'=>'NAME'], 
							"ERROR_MESSAGE" => ['ru'=>'', 'en'=>''], 
							"HELP_MESSAGE" => ['ru'=>'', 'en'=>''],
							"SETTINGS" => ['ROWS'=>'4'],
						],
					];

					$fieldsMap = self::_checkHlStructure_addhlprops($entityID, $arEntityFields);

					self::setOption('hl_id_task_elements', $entityID);
				}
			}
		}

		static function _checkHlStructure_addhl($entityName, $tableName, $name){
			$result = \Bitrix\Highloadblock\HighloadBlockTable::getList([
				'filter'=> [
					'=NAME'=> $entityName
				]
			]);
			if($row = $result->fetch()){
				$entityID = $row['ID'];
			}
			
			if(!$entityID){
				$arHlParams = [
					'NAME' => $entityName,
					'TABLE_NAME' => $tableName, 
				];
				
				$result = \Bitrix\Highloadblock\HighloadBlockTable::add($arHlParams);
				if($result->isSuccess()){
					$entityID = $result->getId();

					\Bitrix\Highloadblock\HighloadBlockLangTable::add(array(
						'ID' => $entityID, 
						'LID' => 'ru', 
						'NAME' => $name
					));
				}else{
					$errors = $result->getErrorMessages();
					echo '<pre>add hlblock errors'; print_r($errors); echo '</pre>';
				}
			}
			
			return $entityID;
		}

		static function _checkHlStructure_addEnums($entityObject, $field, $enums){
			$fieldID = 0;

			$rsData = \CUserTypeEntity::GetList( array($by=>$order), array('ENTITY_ID' => $entityObject, 'FIELD_NAME' => $field));
			while($arRes = $rsData->Fetch()){
				$fieldID = $arRes['ID'];
			}

			if($fieldID){
				$existEnums = [];

				$rsEnum = CUserFieldEnum::GetList(array(), array(
					'USER_FIELD_ID' => $fieldID
				));
				while($arEnum = $rsEnum->Fetch()){
					$existEnums[$arEnum['XML_ID']] = 1;
				}

				foreach($enums as $enum){
					if($existEnums[$enum['XML_ID']]){
						continue;
					}

					$obEnum = new \CUserFieldEnum;
					$obEnum->SetEnumValues($fieldID, array(
						'n0'=> $enum
					));
				}
			}
		}

		static function _checkHlStructure_addhlprops($entityID, $arEntityFields){
			$arFindedUserTypes = self::getHlEntityFields($entityID);
			
			foreach($arEntityFields as $entityField){
				if($arFindedUserTypes[$entityField['FIELD_NAME']]) continue;	
				
				$obUserField  = new \CUserTypeEntity;
				$ID = $obUserField->Add($entityField);
				
				$arFindedUserTypes[$entityField['FIELD_NAME']] = $ID;
			}

			return $arFindedUserTypes;
		}

		// main 
		static function getHlEntityFields($entityID){
			$arFindedUserTypes = [];

			$rsData = \CUserTypeEntity::GetList( array($by=>$order), array('ENTITY_ID' => 'HLBLOCK_'.$entityID));
			while($arRes = $rsData->Fetch()){
				$arFindedUserTypes[$arRes['FIELD_NAME']] = 1;
			}

			return $arFindedUserTypes;
		}
		
		static function setOption($name, $value){
			$old = COption::GetOptionString(self::MODULE_ID, $name);
			if(!$old || $old != $value){
				COption::SetOptionString(self::MODULE_ID, $name, $value);
			}
		}

		// install
		static function addGadgetToDesctop($gadget_id){
			if(!defined("NO_INSTALL_MWATCHER") && class_exists('CUserOptions')){
				$desctops = \CUserOptions::GetOption('intranet', '~gadgets_admin_index', false, false);
				if(is_array($desctops) && !empty($desctops[0])){
					$skip = 0;
					foreach($desctops[0]['GADGETS'] as $gid => $gsett){
						if(strstr($gid, $gadget_id)) $skip = 1;
					}
					
					if(!$skip){
						foreach($desctops[0]['GADGETS'] as $gid => $gsett){
							if($gsett['COLUMN'] == 0){
								$desctops[0]['GADGETS'][$gid]['ROW']++;
							}
						}
						
						$gid_new = $gadget_id."@".rand();
						$desctops[0]['GADGETS'][$gid_new] = array('COLUMN' => 0, 'ROW' => 0);
						
						\CUserOptions::SetOption('intranet', '~gadgets_admin_index', $desctops, false, false);
					}
				}
			}
		}
	}
}
?>