<?
use \Bitrix\Main\Config\Option;

$module_id = 'arturgolubev.chatgpt';

global $USER;
if(!is_object($USER)){
	$USER = new \CUser();
}

$showSetting = $showGenerate = $showTasks = 0;

if($USER->IsAuthorized()){
	if($USER->IsAdmin()){
		$showSetting = $showGenerate = $showTasks = 1;
	}else{
		$arGroups = $USER->GetUserGroupArray();
		$dirGroups = explode(',', Option::get($module_id, 'rights_question'));
		foreach($dirGroups as $dg){
			if(in_array($dg, $arGroups)){
				$showGenerate = 1;
			}
		}

		$dirGroups = explode(',', Option::get($module_id, 'rights_tasks'));
		foreach($dirGroups as $dg){
			if(in_array($dg, $arGroups)){
				$showTasks = 1;
			}
		}
	}
}

// echo '<pre>showSetting '; print_r($showSetting); echo '</pre>';
// echo '<pre>showGenerate '; print_r($showGenerate); echo '</pre>';

if($showSetting || $showGenerate){
	IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/arturgolubev.chatgpt/menu.php");

	$arGeneration = [
		[
			'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_ASK"),
			'more_url' => [],
			'url' => '/bitrix/admin/arturgolubev_chatgpt_ask_chatgpt.php?lang='.LANG,
			'icon' => '',
		],
		[
			'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_IMAGE"),
			'more_url' => [],
			'url' => '/bitrix/admin/arturgolubev_chatgpt_complection_chatgpt.php?lang='.LANG,
			'icon' => '',
		]
	];

	$arSubmenu = [];

	if($showSetting){
		$arSubmenu[] = [
			'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_SETTINGS"),
			'more_url' => [],
			'url' => '/bitrix/admin/settings.php?lang='.LANG.'&mid=arturgolubev.chatgpt',
			'icon' => 'sys_menu_icon',
		];
	}

	if($showGenerate){
		$arSubmenu[] = [
			'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_SIMPLE_GENERATION"),
			'items_id' => 'arcg_icon_gen',
			'icon' => '',
			'items' => $arGeneration
		];
	}

	if($showTasks){
		$arSubmenu[] = [
			'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_AUTOMATIC_TASKS"),
			'more_url' => [
				'/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php'
			],
			'url' => '/bitrix/admin/arturgolubev_chatgpt_automatic_tasks.php?lang='.LANG,
			'icon' => '',
		];
	}

	/* $arSubmenu[] = [
		'text' => GetMessage("ARTURGOLUBEV_CHATGPT_SUBMENU_GENERATE_ELEMENTS"),
		'more_url' => [],
		'url' => '/bitrix/admin/arturgolubev_chatgpt_generate_elements.php?lang='.LANG,
		'icon' => '',
	]; */

	
	if($showSetting){
		$hl = Option::get($module_id, 'hl_id_templates');
		if($hl){
			$arSubmenu[] = [
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_MY_TEMPLATES"),
				'more_url' => [
					'/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID='.$hl.'&lang='.LANG
				],
				'url' => '/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID='.$hl.'&lang='.LANG,
				'icon' => '',
			];
		}
	}



	$arSubmenu[] = [
		'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS"),
		'icon' => 'update_marketplace',
		'items' => [
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_INSTALLATION"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_GENERATION"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/chapter0266/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_SERVICES"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/lesson231/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_ROUTERS"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/lesson276/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_EVENTS"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/chapter0212/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_API"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/chapter0250/", "_blank");void(0);',
				'icon' => '',
			],[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_PROBLEMS"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/chapter0227/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_FAQ"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course35/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_BUY"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course1/chapter064/", "_blank");void(0);',
				'icon' => '',
			],
			[
				'text' => GetMessage("ARTURGOLUBEV_CHATGPT_DOCUMENTATIONS_SUPPORT"),
				'more_url' => [],
				'url' => 'javascript: window.open("https://arturgolubev.ru/knowledge/course1/", "_blank");void(0);',
				'icon' => '',
			],
		]
	];

	$aMenu = [
		'parent_menu' => 'global_menu_services',
		'section' => 'arturgolubev_chatgpt',
		'sort' => 1,
		'text' => GetMessage("ARTURGOLUBEV_CHATGPT_MENU_MAIN"),
		'icon' => 'arturgolubev_chatgpt_icon_main',
		'items_id' => 'arcg_icon_main',
		'items' => $arSubmenu,
	];


	return $aMenu;
}