<?
use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
	\Arturgolubev\Chatgpt\Tools;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

$module_id = 'arturgolubev.chatgpt';
Loader::IncludeModule($module_id);
CJSCore::Init(["ag_chatgpt_base"]);

IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/options.php");

$APPLICATION->SetTitle(Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_PAGE_TITLE")); 

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$aiList = Tools::getAiList();

if(Loader::IncludeModule($module_id)):?>
	<div class="agcg_adm_page">
		<?if(Tools::checkRights('question')):?>
			<?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_PAGE_TEXT")?>
			
			<div class="agcg_askpage_form">
				<form class="input-form js-ask-form">
					<input type="hidden" name="action" value="simle-request" />
					
					<div class="input-field">
						<div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM")?></div>
						<div class="input-field">
							<select class="js-ask-system" name="provider">
								<?if(in_array('chatgpt', $aiList)):?><option value="chatgpt"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_CHATGTP")?></option><?endif;?>
								<?if(in_array('sber', $aiList)):?><option value="sber"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_SBER")?></option><?endif;?>
								<?if(in_array('deepseek', $aiList)):?><option value="deepseek"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_DEEPSEEK")?></option><?endif;?>
								<?if(in_array('openai_compatible', $aiList)):?><option value="openai_compatible"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_OPENAI_COMPATIBLE")?></option><?endif;?>
							</select>
						</div>
					</div>
					
					<div class="input-field">
						<div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_QUERY")?></div>
						<div class="input-field"><textarea name="question" class="js-ask-area"></textarea></div>
					</div>
					
					<div class="input-buttons">
						<div class="input-button input-button-colored js-ask-send"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_SEND_QUERY")?></div>
					</div>
				</form>
			</div>
			
			<div class="agcg_askpage_result">
				<div class="title"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_RESULT_AREA")?></div>
				<div class="js-ask-result"></div>
			</div>
			
			<script>
				document.addEventListener("DOMContentLoaded", function(){
					agcg.initAskPage();
				});
			</script>
		<?else:?>
			<?=Loc::getMessage('ARTURGOLUBEV_CHATGPT_RIGHTS_ERROR')?>
		<?endif;?>
	</div>
	
<?else:
	CAdminMessage::ShowMessage(array("DETAILS"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_DEMO_IS_EXPIRED"), "HTML"=>true));
endif;

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');?>