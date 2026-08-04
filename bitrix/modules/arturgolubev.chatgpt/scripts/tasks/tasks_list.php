<?
use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc;

use \Arturgolubev\Chatgpt\Unitools as UTools,
    \Arturgolubev\Chatgpt\Tools,
    \Arturgolubev\Chatgpt\Tasks;
?>

<?
Tools::checkHlTemplate();

$aMenu = [
    [
        "TEXT"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_MENU_ADD_NEW"),
        "TITLE"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_MENU_ADD_NEW"),
        "LINK_PARAM"=>"",
        "LINK"=>"/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php?action=tasks_new&lang=".LANG,
    ]
];

$context = new CAdminContextMenu($aMenu);
$context->Show();

$list_id = 'agcg_tasks_list';

$grid_options = new Bitrix\Main\Grid\Options($list_id);
$sort = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$nav_params = $grid_options->GetNavParams();

$nav = new Bitrix\Main\UI\PageNavigation($list_id);
$nav->allowAllRecords(false)
    ->setPageSize($nav_params['nPageSize'])
    ->initFromUri();

/* $ui_filter = [
    ['id' => 'PROP_SITE_ID', 'name' => Loc::getMessage("ARTURGOLUBEV_ABANDONED_PAGE_COLUMN_PROP_SITE_ID"), 'type'=>'text', 'default' => true],
    ['id' => 'PROP_NAME', 'name' => Loc::getMessage("ARTURGOLUBEV_ABANDONED_PAGE_COLUMN_PROP_NAME"), 'type'=>'text', 'default' => true],
    ['id' => 'PROP_PHONE', 'name' => Loc::getMessage("ARTURGOLUBEV_ABANDONED_PAGE_COLUMN_PROP_PHONE"), 'type'=>'text', 'default' => true],
    ['id' => 'PROP_EMAIL', 'name' => Loc::getMessage("ARTURGOLUBEV_ABANDONED_PAGE_COLUMN_PROP_EMAIL"), 'type'=>'text', 'default' => true],
];

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $list_id,
    'GRID_ID' => $list_id,
    'FILTER' => $ui_filter,
    'ENABLE_LIVE_SEARCH' => false,
    'ENABLE_LABEL' => true,
    'DISABLE_SEARCH' => true,
]); */

$filterOption = new Bitrix\Main\UI\Filter\Options($list_id);
$filterData = $filterOption->getFilter([]);

$elements = Tasks\Grid::listTasks($sort, $nav, $filterData);
// echo '<pre>'; print_r($elements); echo '</pre>';
?>

<?
if (!(defined("BX_CRONTAB_SUPPORT") && BX_CRONTAB_SUPPORT===true)){
    \CAdminMessage::ShowMessage(["DETAILS"=>Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_CRON_ERROR_BODY"), "MESSAGE" => '', "HTML"=>true]);
}
?>

<div class="tasks-description agcg_adm_page"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_TESTMODE_NOTIFY")?></div>

<div class="agcg_adm_page agcg_tasks_list">
    <div class="tasks-description"><?=Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_DESCRIPTION")?></div>

    <?
    $APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [ 
        'GRID_ID' => $list_id, 
        'COLUMNS' => [ 
            ['id' => 'ID', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ID"), 'sort' => 'ID', 'default' => true], 
            ['id' => 'NAME', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_NAME"), 'sort' => 'NAME', 'default' => true], 
            ['id' => 'STATUS_FORMAT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_STATUS"), 'sort' => 'STATUS', 'default' => true], 
            ['id' => 'IBLOCK', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_IBLOCK"), 'sort' => 'IBLOCK', 'default' => true], 
            ['id' => 'PROMPT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_PROMPT"), 'sort' => 'PROMPT', 'default' => true], 
            ['id' => 'ETYPE_FORMAT', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ETYPE"), 'sort' => 'ETYPE', 'default' => true], 
            ['id' => 'ELEMENTS', 'name' => Loc::getMessage("ARTURGOLUBEV_CHATGPT_TASKS_LIST_TABLE_FIELD_ELEMENTS"), 'sort' => false, 'default' => true], 
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
        'SHOW_ROW_CHECKBOXES' => false, 
        'SHOW_CHECK_ALL_CHECKBOXES' => false, 
        'SHOW_ROW_ACTIONS_MENU'     => true, 
        'SHOW_GRID_SETTINGS_MENU'   => true, 
        'SHOW_NAVIGATION_PANEL'     => true, 
        'SHOW_PAGINATION'           => true, 
        'SHOW_SELECTED_COUNTER'     => true, 
        'SHOW_TOTAL_COUNTER'        => true, 
        'SHOW_PAGESIZE'             => true, 
        'SHOW_ACTION_PANEL'         => false, 
        'ALLOW_COLUMNS_SORT'        => true, 
        'ALLOW_COLUMNS_RESIZE'      => true, 
        'ALLOW_HORIZONTAL_SCROLL'   => true, 
        'ALLOW_SORT'                => true, 
        'ALLOW_PIN_HEADER'          => true, 
    ]);?>
</div>