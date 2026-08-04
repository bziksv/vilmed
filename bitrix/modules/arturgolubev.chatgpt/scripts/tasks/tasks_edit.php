<?
use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc;

use Bitrix\Main\Grid\Panel\Snippet; //?

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools,
    \Arturgolubev\Chatgpt\Tasks;

$isEdit = ($action == 'tasks_edit' && $task_id);

if($isEdit){
    $APPLICATION->SetTitle(Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TITLE", ['#id#' => $task_id])); 
}else{
    $APPLICATION->SetTitle(Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_NEW_TITLE")); 
}

if($isEdit){
    $taskData = Tasks\Task::getTaskByID($task_id);
    if(!is_array($taskData)){
        LocalRedirect('/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php?lang='.LANG);
    }
}

$aiList = Tools::getAiList();

$iblock_id = intval($_REQUEST['iblock_id']);
$entity_type = htmlspecialchars($_REQUEST['entity_type']);

if(!$iblockID){
    $iblocks = Tasks\Logic::getIblockList();
}

if($isEdit){
    $aMenu = [
        [
            "TEXT"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_DELETE_TASK"),
            "TITLE"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_DELETE_TASK"),
            "LINK_PARAM"=>"",
            "LINK"=>'javascript:agcg.taskEditDelete('.$task_id.');',
        ]
    ];

    $context = new CAdminContextMenu($aMenu);
    $context->Show();
}

if($task_id){
    $elDelAction = $_REQUEST['action_button_agcg_task_elements_list'];
    if($elDelAction == 'delete' && Tools::checkRights('tasks')){
        $IDS = [];

        if(is_array($_REQUEST['ID'])){
            foreach($_REQUEST['ID'] as $id){
                $IDS[] = intval($id);
            }
        }

        if(count($IDS)){
            Tasks\Element::deleteTaskElements($IDS);
        }
    }
}

// CArturgolubevChatgpt::taskWorker($task_id); echo 'TEST START ACTIVE!'; // todo
?>

<div class="tasks-description agcg_adm_page"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_TESTMODE_NOTIFY")?></div>

<div class="agcg_adm_page agcg_tasks_edit">
    <?if(!$isEdit && !$iblock_id):?>
        <div class="tasks-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_PREPARE_DESCRIPTION")?></div>

        <form class="input-form js-task-prepare">
            <input type="hidden" name="action" value="<?=$action?>" />
            
            <div class="input-field">
                <div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK")?></div>
                <div class="input-field">
                    <select name="iblock_id" required>
                        <option value=""><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_NEED_SELECT")?></option>

                        <?foreach($iblocks as $iblock):?>
                            <option value="<?=intval($iblock['ID'])?>">[<?=htmlspecialcharsbx($iblock['TYPE_ID'])?>] <?=htmlspecialcharsbx($iblock['NAME'])?></option>
                        <?endforeach;?>
                    </select>
                </div>
            </div>

            <div class="input-field">
                <div class="input-label"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_ENTITY")?></div>
                <div class="input-field">
                    <select name="entity_type" class="">
                        <option value="E"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_E")?></option>
                        <option value="S"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_FIELD_IBLOCK_S")?></option>
                    </select>
                </div>
            </div>

            <div class="input-buttons">
                <input type="submit" class="" value="<?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_SEND")?>">
            </div>
        </form>
    <?else:?>

        <?
        $startOptions = [
            'action' => $action,
            'id' => $task_id,
            'iblock_id' => $iblock_id,
            'entity_type' => $entity_type,
        ];
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function(){
                agcg.initTaskEditPage(<?=CUtil::PhpToJSObject($startOptions)?>);
            });
        </script>
        
        <div class="agcg-tabs">
            <div class="agcg-tab-buttons">
                <button class="agcg-active" data-tab="agcg-tab1"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TAB_TASK")?></button>
                <button data-tab="agcg-tab2"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_TAB_ELEMENTS")?></button>
            </div>
            <div class="agcg-tab-content agcg-active" id="agcg-tab1">
                <div class="task-edit-block">
                    <form class="js-task-edit-form">
                        <?foreach($startOptions as $key=>$val):?>
                            <input type="hidden" name="<?=$key?>" value="<?=$val?>" />
                        <?endforeach?>

                        <div class="js-task-edit-form-fields task-edit-form-fields">
                            <div class=""><span class="lds-dual-ring"></span></div>
                        </div>

                        <div class="input-buttons">
                            <div class="input-button input-button-colored js-taskedit-save"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_SAVE")?></div>
                            
                            <?if($isEdit):
                                // echo '<pre>'; print_r($taskData['UF_STATUS']); echo '</pre>';
                            ?>
                                <?if(in_array($taskData['UF_STATUS'], ['new'])):?>
                                    <div class="input-button input-button-colored js-taskedit-start"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_START")?></div>
                                <?endif;?>

                                <?if(in_array($taskData['UF_STATUS'], ['work'])):?>
                                    <div class="input-button input-button-colored js-taskedit-stop"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP")?></div>
                                <?endif;?>
                                
                                <?if(in_array($taskData['UF_STATUS'], ['stop'])):?>
                                    <div class="input-button input-button-colored js-taskedit-start"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_CONTINUE")?></div>
                                    <br><br><br>
                                    <div class="input-button input-button-colored js-taskedit-restart"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP_RESTART")?></div>
                                    <div class="input-button-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_STOP_RESTART_DESCR")?></div>
                                <?endif;?>
                                
                                <?if(in_array($taskData['UF_STATUS'], ['stop_error'])):?>
                                    <div class="input-button input-button-colored js-taskedit-start"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_RESTART")?></div>
                                <?endif;?>
                                
                                <?if(in_array($taskData['UF_STATUS'], ['finish'])):?>
                                    <br><br><br>
                                    <div class="input-button input-button-colored js-taskedit-start"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_START")?></div>
                                    <div class="input-button-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_START_DESCR")?></div>

                                    <br>
                                    <div class="input-button input-button-colored js-taskedit-restart"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_RESTART")?></div>
                                    <div class="input-button-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_BUTTON_FINISH_RESTART_DESCR")?></div>
                                <?endif;?>
                            <?endif;?>
                        </div>
                        <div class="results"></div>

                        <?if($isEdit && $taskData['UF_STATUS'] == 'work'):
                            $agent = Tasks\Logic::getTaskAgent($taskData['ID']);
                        ?>
                            <div class="" style="margin-top: 40px; line-height: 24px;">
                                <?if(is_array($agent)):
                                    $last = '-';
                                    if($agent['LAST_EXEC']){
                                        $dateTime = new \Bitrix\Main\Type\DateTime($agent['LAST_EXEC']);
                                        $last = $dateTime->format("d M H:i");
                                    }
                                    
                                    $next = '-';
                                    if($agent['NEXT_EXEC']){
                                        $dateTime = new \Bitrix\Main\Type\DateTime($agent['NEXT_EXEC']);
                                        $next = $dateTime->format("d M H:i");
                                    }
                                    
                                    if($agent['RETRY_COUNT'] > 1){
                                        $agent['RETRY_COUNT'] .= ' '.Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_ERR_REPEATS");
                                    }

                                ?>
                                    <?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_INFO", [
                                        '#last#' => $last, 
                                        '#next#' => $next, 
                                        '#active#' => $agent['ACTIVE'], 
                                        '#exec#' => $agent['RUNNING'], 
                                        '#errs#' => $agent['RETRY_COUNT'], 
                                    ])?>
                                <?else:?>
                                    <?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_EDIT_AGENT_INFO_NOT_FOUND")?>
                                <?endif;?>
                            </div>
                        <?endif;?>
                    </form>
                </div>
            </div>
            <div class="agcg-tab-content" id="agcg-tab2">
                <?if(!$isEdit):?>
                    <div class="tasks-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_ADEDDED_ON_NEW")?></div>
                <?else:?>
                    <div class="tasks-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_ADEDDED_ON_EDIT")?></div>

                    <?
                    $list_id = 'agcg_task_elements_list';

                    $grid_options = new Bitrix\Main\Grid\Options($list_id);
                    $sort = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
                    $nav_params = $grid_options->GetNavParams();

                    $nav = new Bitrix\Main\UI\PageNavigation($list_id);
                    $nav->allowAllRecords(false)
                        ->setPageSize($nav_params['nPageSize'])
                        ->initFromUri();


                    $filterOption = new Bitrix\Main\UI\Filter\Options($list_id);
                    $filterData = $filterOption->getFilter([]);

                    $elements = Tasks\Grid::listTaskElements($task_id, $sort, $nav, $filterData);
                    // echo '<pre>'; print_r($elements); echo '</pre>';

                    $snippet = new Snippet();
                    $removeButton = $snippet->getRemoveButton();
                    ?>

                    <div class="agcg_tasks_elements_list">
                        <?
                        $APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [ 
                            'GRID_ID' => $list_id, 
                            'COLUMNS' => [ 
                                ['id' => 'ID', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ID"), 'sort' => 'ID', 'default' => true], 
                                ['id' => 'ELEMENT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ELEMENT"), 'sort' => 'ELEMENT', 'default' => true], 
                                ['id' => 'STATUS_FORMAT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_STATUS"), 'sort' => 'STATUS', 'default' => true], 
                                ['id' => 'ERROR_TEXT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_ERROR_TEXT"), 'sort' => false, 'default' => true], 
                                ['id' => 'GENERATION_DATE', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_GENERATION_DATE"), 'sort' => false, 'default' => false], 
                                ['id' => 'GENERATION_RESULT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_GENERATION_RESULT"), 'sort' => false, 'default' => false], 
                            ], 
                            'ROWS' => $elements,
                            'NAV_OBJECT' => $nav, 
                            'AJAX_MODE' => 'Y', 
                            'AJAX_OPTION_JUMP' => 'N', 
                            'AJAX_OPTION_HISTORY' => 'N',
                            'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''), 
                            'PAGE_SIZES' => [ 
                                ['NAME' => '20', 'VALUE' => '20'], 
                                ['NAME' => '50', 'VALUE' => '50'], 
                                ['NAME' => '100', 'VALUE' => '100'],
                                ['NAME' => '500', 'VALUE' => '500'],
                            ], 
                            'SHOW_ROW_CHECKBOXES' => true, 
                            'SHOW_CHECK_ALL_CHECKBOXES' => true, 
                            'SHOW_ROW_ACTIONS_MENU' => true, 
                            'SHOW_GRID_SETTINGS_MENU' => true, 
                            'SHOW_NAVIGATION_PANEL' => true, 
                            'SHOW_PAGINATION' => true, 
                            'SHOW_SELECTED_COUNTER' => true, 
                            'SHOW_TOTAL_COUNTER' => true, 
                            'SHOW_PAGESIZE' => true, 
                            'SHOW_ACTION_PANEL' => true, 
                            'ALLOW_COLUMNS_SORT' => true, 
                            'ALLOW_COLUMNS_RESIZE' => true, 
                            'ALLOW_HORIZONTAL_SCROLL' => true, 
                            'ALLOW_SORT' => true, 
                            'ALLOW_PIN_HEADER' => true, 
                            'ACTION_PANEL' => [
                                'GROUPS' => [
                                    [
                                        'ITEMS' => [
                                            $removeButton
                                        ], 
                                    ],     
                                ],
                            ],
                        ]);?>
                    </div>

                <?endif;?>
            </div>
        </div>
    <?endif;?>
</div>