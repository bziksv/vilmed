<?
use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Tools;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

$module_id = 'arturgolubev.chatgpt';
Loader::IncludeModule($module_id);
CJSCore::Init(["ag_chatgpt_base", 'ag_chatgpt_tasks']);

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/options.php");
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/admin/automatic_tasks.php");

$APPLICATION->SetTitle(Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_TITLE")); 

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$action = htmlspecialchars($_REQUEST['action']);
$task_id = intval($_REQUEST['id']);

if(Loader::IncludeModule($module_id)):?>
	<?if(Tools::checkRights('tasks')):
			$page = 'list';

			if($action == 'tasks_edit' || $action == 'tasks_new'){
				$page = 'edit';
			}

			$pagePath = 'tasks/tasks_'.$page.'.php';

			// echo '<pre>action: '; print_r($action); echo '</pre>';
			// echo '<pre>task_id: '; print_r($task_id); echo '</pre>';
			// echo '<pre>page: '; print_r($pagePath); echo '</pre>';
			
			include $pagePath;
		?>
	<?else:?>
		<?=Loc::getMessage('ARTURGOLUBEV_CHATGPT_RIGHTS_ERROR')?>
	<?endif;?>
<?else:
	CAdminMessage::ShowMessage(["DETAILS"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_DEMO_IS_EXPIRED"), "HTML"=>true]);
endif;

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');?>