<?
use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc;

global $APPLICATION;

echo '<pre>'; print_r($id); echo '</pre>';
?>
<?if($id > 0):?>
    <?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_PAGE_EDIT", ['#id#' => $id])?>
<?else:?>
    <?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_PAGE_CREATE")?>
<?endif;?>
<br>
<br>

<?/*
<div class="input-buttons">
    <a class="input-button input-button-colored" href="<?=$APPLICATION->GetCurPageParam('', ['action']);?>"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_BUTTON_TO_LIST")?></a>
</div>


<div class="agcg_askpage_form">
    <form class="input-form js-tasks-edit-form">
        <input type="hidden" name="action" value="tasks-edit" />

        <?if($id):?>
            <input type="hidden" name="id" value="<?=$id?>">
        <?endif;?>

        <div class="input-field">
            <div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_FIELD_IBLOCK")?></div>
            <div class="input-field">
                <select name="iblock">
                    <?if($gptKey || (!$gptKey && !$sberKey)):?><option value="chatgpt"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_CHATGTP")?></option><?endif;?>
                    <?if($sberKey):?><option value="sber"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_SBER")?></option><?endif;?>
                </select>
            </div>
        </div>
        
        <div class="input-field">
            <div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM")?></div>
            <div class="input-field">
                <select class="js-ask-system" name="provider">
                    <?if($gptKey || (!$gptKey && !$sberKey)):?><option value="chatgpt"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_CHATGTP")?></option><?endif;?>
                    <?if($sberKey):?><option value="sber"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_ASK_INPUT_SYSTEM_SBER")?></option><?endif;?>
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
    //document.addEventListener("DOMContentLoaded", function(){
        //agcg.initAskPage();
    //});
</script>
*/?>