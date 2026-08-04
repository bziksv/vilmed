<?
use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Tools,
	\Arturgolubev\Chatgpt\Tasks;

use \Arturgolubev\Chatgpt\FormConstructor,
	\Arturgolubev\Chatgpt\Tasks\FormTasksConstructor;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC','Y');
// define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);
define('PERFMON_STOP', true);

set_time_limit(0); 
@ignore_user_abort(true);
define("LANG", "ru"); 

$module_id = 'arturgolubev.chatgpt';

if(!Loader::IncludeModule($module_id)){
	die("Module ".$module_id." is not installed");
}

if(!defined("BX_UTF")){
	$_REQUEST = \Bitrix\Main\Text\Encoding::convertEncoding($_REQUEST, "UTF-8", "windows-1251");
}

$result = array(
	"action" => htmlspecialcharsbx($_REQUEST["action"]),
	"error" => 0,
	"error_message" => '',
);

if(!Tools::checkRights('question') && !Tools::checkRights('tasks')){
	$result['error_message'] = 'Rights error';
}

if(!check_bitrix_sessid()){
	$result['error_message'] = 'Rights error';
}

if(!$result['error_message']){
	// forms
	if($result["action"] == 'get_element_form_content'){	
		echo FormConstructor::makeElementFormHtml($_POST);
		die();
	}

	if($result["action"] == 'get_section_form_content'){	
		echo FormConstructor::makeSectionFormHtml($_POST);
		die();
	}

	if($result["action"] == 'get_tasks_form_content'){	
		echo FormTasksConstructor::makeTasksHtml($_POST);
		die();
	}
	
	// tasks
	if($result["action"] == 'tasks_new'){
		$data = Tasks\Task::createTask($_POST);
		if($data['error_message']){
			$result['error_message'] = $data['error_message'];
		}else{
			$result['new_id'] = $data['id'];
		}
	}
	if($result["action"] == 'tasks_edit'){
		$data = Tasks\Task::updateTask($_POST);
		if($data['error_message']){
			$result['error_message'] = $data['error_message'];
		}
	}
	if($result["action"] == 'tasks_addlist'){
		$result['list'] = Tasks\Task::getTasksAddList($_POST);
	}
	if($result["action"] == 'tasks_add_elements'){
		$data = Tasks\Element::getAddElementsToTask($_POST);
		if($data['error_message']){
			$result['error_message'] = $data['error_message'];
		}else{
			$result['counts'] = $data;
		}
	}

	// simple
	if($result["action"] == 'create_element_text'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);

		$params = [
			'ID' => intval($_POST['ID']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),

			'preview' => intval($_POST['preview']),
			
			'provider' => htmlspecialcharsbx($_POST['provider']),
			'operation' => htmlspecialcharsbx($_POST['operation']),
			'type' => htmlspecialcharsbx($_POST['type']),
			'for' => htmlspecialcharsbx($_POST['for']),
			'from' => htmlspecialcharsbx($_POST['from']),
			
			'template_element' => htmlspecialcharsbx($_POST['template_element']),
			'files' => htmlspecialcharsbx($_POST['files']),

			'lang' => htmlspecialcharsbx($_POST['lang']),
			'length' => intval($_POST['length']),
			'html' => ($_POST['html'] == 'Y'),

			'additionals' => htmlspecialcharsbx($_POST['additionals']),

			'keynum' => 0,
		];
		
		$params = Tools::fillInputByRequest($params);
		
		if($params['ID']){
			$data = CArturgolubevChatgpt::createElementText($params);
			
			$result['question'] = $data['question'];
			$result['files_vals'] = $data['files_vals'];
			$result['question_show'] = $data['question_show'];
			$result['show_tokens'] = $data['show_tokens'];
				
			if($data['error_message']){
				$result['error_message'] = $data['error_message'];
			}else{
				$result['answer'] = $data['answer'];
				$result['content_type'] = $data['content_type'];
				$result['used_tokens'] = $data['used_tokens'];
				$result['used_tokens_cnt'] = $data['used_tokens_cnt'];
				
				$result['save_fields'] = FormConstructor::getElementFieldsToSave($params['IBLOCK_ID'], $params['operation'], $params['type']);
			}
		}
	}

	if($result["action"] == 'create_section_text'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);
		
		$params = [
			'ID' => intval($_POST['ID']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),

			'preview' => intval($_POST['preview']),
			
			'provider' => htmlspecialcharsbx($_POST['provider']),
			'operation' => htmlspecialcharsbx($_POST['operation']),
			'type' => htmlspecialcharsbx($_POST['type']),
			'for' => htmlspecialcharsbx($_POST['for']),
			'from' => htmlspecialcharsbx($_POST['from']),
			
			'template_section' => $_POST['template_section'],
			
			'length' => intval($_POST['length']),
			'html' => ($_POST['html'] == 'Y'),

			'additionals' => htmlspecialcharsbx($_POST['additionals']),

			'keynum' => 0,
		];
		
		$params = Tools::fillInputByRequest($params);

		if($params['ID']){
			$data = CArturgolubevChatgpt::createSectionText($params);
			
			$result['question'] = $data['question'];
			$result['question_show'] = $data['question_show'];
			$result['show_tokens'] = $data['show_tokens'];
				
			if($data['error_message']){
				$result['error_message'] = $data['error_message'];
			}else{
				$result['answer'] = $data['answer'];
				$result['content_type'] = $data['content_type'];
				$result['used_tokens'] = $data['used_tokens'];
				$result['used_tokens_cnt'] = $data['used_tokens_cnt'];

				$result['save_fields'] = FormConstructor::getSectionFieldsToSave($params['IBLOCK_ID'], $params['operation'], $params['type']);
			}
		}
	}

	if($result["action"] == 'save_answer_to_element'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);

		$params = [
			'ID' => intval($_POST['ID']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),
			'savefield' => htmlspecialcharsbx($_POST['savefield']),
			'genresult' => $_POST['genresult'],
			'html' => ($_POST['html'] == 'Y'),
			're_encoding' => 1,
			'check_permissions' => 1,
		];
		
		if($params['ID'] && $params['savefield'] && $params['genresult']){		
			$data = CArturgolubevChatgpt::saveToElement($params);
			if($data['error_message']){
				$result['error_message'] = $data['error_message'];
			}else{
				$result['genresult'] = $data['genresult'];
				$result['savefield'] = $data['savefield'];
				$result['savefield_type'] = $data['savefield_type'];
				$result['savefield_id'] = $data['savefield_id'];
			}
		}
	}

	if($result["action"] == 'save_answer_to_section'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);
		
		$params = [
			'ID' => intval($_POST['ID']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),
			'savefield' => htmlspecialcharsbx($_POST['savefield']),
			'genresult' => $_POST['genresult'],
			'html' => ($_POST['html'] == 'Y'),
			're_encoding' => 1,
			'check_permissions' => 1,
		];
		
		if($params['ID'] && $params['savefield'] && $params['genresult']){
			$data = CArturgolubevChatgpt::saveToSection($params);
			if($data['error_message']){
				$result['error_message'] = $data['error_message'];
			}else{
				$result['genresult'] = $data['genresult'];
				$result['savefield'] = $data['savefield'];
				$result['savefield_type'] = $data['savefield_type'];
				$result['savefield_id'] = $data['savefield_id'];
			}
		}
	}


	if($result["action"] == 'mass_create_elements'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);
		
		$params = [
			'mass_generation' => 1,
			
			'ID' => intval($_POST['eid']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),
			
			'provider' => htmlspecialcharsbx($_POST['provider']),
			'operation' => htmlspecialcharsbx($_POST['operation']),
			'type' => htmlspecialcharsbx($_POST['type']),
			'for' => htmlspecialcharsbx($_POST['for']),
			'from' => htmlspecialcharsbx($_POST['from']),
			
			'template_element' => htmlspecialcharsbx($_POST['template_element']),
			'files' => htmlspecialcharsbx($_POST['files']),
			
			'lang' => htmlspecialcharsbx($_POST['lang']),
			'length' => intval($_POST['length']),
			'html' => ($_POST['html'] == 'Y'),

			'additionals' => htmlspecialcharsbx($_POST['additionals']),
			
			'save_only_empty' => ($_POST['save_only_empty'] == 'Y'),
			'savefield' => htmlspecialcharsbx($_POST['mass_save_field']),
			'keynum' => 0,
			'check_permissions' => 1,
		];
		
		$params = Tools::fillInputByRequest($params);
		
		if($params['save_only_empty']){
			$res = CArturgolubevChatgpt::checkElementEmptySaveFiled($params['IBLOCK_ID'], $params['ID'], $params['savefield']);
			if(!$res['result']){
				$result['element_name'] = $res['element_name'];
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_MASS_NO_EMPTY_SAVE_FIELD').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_MASS_SKIPPED');
			}
		}
		
		
		if(!$result['error_message']){
			$res = CArturgolubevChatgpt::createElementText($params);
			
			$result['gpt_model'] = UTools::getSetting('alg_model');
			
			$result['element_name'] = $res['element_name'];
			$result['question'] = $res['question'];
			$result['question_show'] = $res['question_show'];
			$result['show_tokens'] = $res['show_tokens'];
				
			if($res['error_message']){
				$result['error_message'] = $res['error_message'];
			}else{
				$params['genresult'] = $result['answer'] = $res['answer'];
				$result['used_tokens_cnt'] = $res['used_tokens_cnt'];
				
				$res = CArturgolubevChatgpt::saveToElement($params);
				if($res['error_message']){
					$result['error_message'] = $res['error_message'];
				}
			}
		}
	}

	if($result["action"] == 'mass_create_sections'){
		CArturgolubevChatgpt::applyDefaultVals($_POST, 0);
		
		$params = [
			'mass_generation' => 1,
			
			'ID' => intval($_POST['eid']),
			'IBLOCK_ID' => intval($_POST['IBLOCK_ID']),
			
			'provider' => htmlspecialcharsbx($_POST['provider']),
			'operation' => htmlspecialcharsbx($_POST['operation']),
			'type' => htmlspecialcharsbx($_POST['type']),
			'for' => htmlspecialcharsbx($_POST['for']),
			'from' => htmlspecialcharsbx($_POST['from']),
			
			'template_section' => $_POST['template_section'],
			
			'length' => intval($_POST['length']),
			'html' => ($_POST['html'] == 'Y'),
			
			'additionals' => htmlspecialcharsbx($_POST['additionals']),

			'save_only_empty' => ($_POST['save_only_empty'] == 'Y'),
			'savefield' => htmlspecialcharsbx($_POST['mass_save_field']),
			'keynum' => 0,
			'check_permissions' => 1,
		];
		
		$params = Tools::fillInputByRequest($params);
		
		if($params['save_only_empty']){
			$res = CArturgolubevChatgpt::checkSectionEmptySaveFiled($params['IBLOCK_ID'], $params['ID'], $params['savefield']);
			if(!$res['result']){
				$result['element_name'] = $res['element_name'];
				$result['error_message'] = Loc::getMessage('ARTURGOLUBEV_CHATGPT_MASS_NO_EMPTY_SAVE_FIELD').' '.Loc::getMessage('ARTURGOLUBEV_CHATGPT_MASS_SKIPPED');
			}
		}
		
		if(!$result['error_message']){
			$res = CArturgolubevChatgpt::createSectionText($params);
			
			$result['gpt_model'] = UTools::getSetting('alg_model');

			$result['element_name'] = $res['element_name'];
			$result['question'] = $res['question'];
			$result['question_show'] = $res['question_show'];
			$result['show_tokens'] = $res['show_tokens'];
				
			if($res['error_message']){
				$result['error_message'] = $res['error_message'];
			}else{
				$params['genresult'] = $result['answer'] = $res['answer'];
				$result['used_tokens_cnt'] = $res['used_tokens_cnt'];
				
				$res = CArturgolubevChatgpt::saveToSection($params);
				if($res['error_message']){
					$result['error_message'] = $res['error_message'];
				}
			}
		}
		
		// echo '<pre>'; print_r($_POST); echo '</pre>';
		// echo '<pre>'; print_r($params); echo '</pre>';
	}

	if($result["action"] == 'sleep20'){
		sleep(20);
	}
}

if($result['error_message']){
	$result['error'] = 1;
}

// echo '<pre>'; print_r($result); echo '</pre>';
echo \Bitrix\Main\Web\Json::encode($result);