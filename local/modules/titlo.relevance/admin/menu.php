<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/** @global CUser $USER */
/** @global CMain $APPLICATION */
if (!$USER->IsAdmin()) {
	return false;
}

// CSS иконок меню (на случай, если OnProlog ещё не отработал)
if (is_object($APPLICATION)) {
	$rel = '/local/modules/titlo.relevance/admin/css/titlo-menu-icons.css';
	$abs = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $rel;
	$v = is_file($abs) ? (string) filemtime($abs) : '1';
	$APPLICATION->SetAdditionalCSS($rel . '?v=' . $v);
}

return [
	[
		'parent_menu' => 'global_menu_services',
		'sort' => 500,
		'text' => Loc::getMessage('TITLO_RELEVANCE_MENU'),
		'title' => Loc::getMessage('TITLO_RELEVANCE_MENU'),
		'icon' => 'titlo_relevance_menu_icon',
		'page_icon' => 'titlo_relevance_page_icon',
		'items_id' => 'menu_titlo_relevance',
		'items' => [
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_GUIDE'),
				'url' => 'titlo_relevance_guide.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_guide.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_DUPS'),
				'url' => 'titlo_relevance_duplicates.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_duplicates.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_SECTION_DUPS'),
				'url' => 'titlo_relevance_section_duplicates.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_section_duplicates.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_NAMES'),
				'url' => 'titlo_relevance_names.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_names.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_SECTION_NAMES'),
				'url' => 'titlo_relevance_section_names.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_section_names.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_SINGLE'),
				'url' => 'titlo_relevance_single.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_single.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_AUTO_PRODUCTS'),
				'url' => 'titlo_relevance_auto_products.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_auto_products.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_AUTO_SECTIONS'),
				'url' => 'titlo_relevance_auto_sections.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_auto_sections.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_WORK_HISTORY'),
				'url' => 'titlo_relevance_work_history.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_work_history.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_PROMPTS'),
				'url' => 'titlo_relevance_prompts.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_prompts.php'],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_SETTINGS'),
				'url' => 'settings.php?lang=' . LANGUAGE_ID . '&mid=titlo.relevance',
				'more_url' => [],
			],
			[
				'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL'),
				'title' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL'),
				'url' => 'titlo_relevance_useful_services.php?lang=' . LANGUAGE_ID,
				'more_url' => ['titlo_relevance_useful_services.php'],
				'icon' => 'titlo_relevance_useful_icon',
				'items_id' => 'menu_titlo_useful_services',
				'items' => [
					[
						'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL_AUDIT'),
						'url' => 'titlo_relevance_useful_services.php?lang=' . LANGUAGE_ID . '&go=site-audit',
						'more_url' => ['titlo_relevance_useful_services.php'],
						'icon' => 'titlo_relevance_useful_audit_icon',
					],
					[
						'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL_CHECKLIST'),
						'url' => 'titlo_relevance_useful_services.php?lang=' . LANGUAGE_ID . '&go=seo-checklist',
						'more_url' => ['titlo_relevance_useful_services.php'],
						'icon' => 'titlo_relevance_useful_checklist_icon',
					],
					[
						'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL_SITE_MON'),
						'url' => 'titlo_relevance_useful_services.php?lang=' . LANGUAGE_ID . '&go=site-monitoring',
						'more_url' => ['titlo_relevance_useful_services.php'],
						'icon' => 'titlo_relevance_useful_site_mon_icon',
					],
					[
						'text' => Loc::getMessage('TITLO_RELEVANCE_MENU_USEFUL_POS_MON'),
						'url' => 'titlo_relevance_useful_services.php?lang=' . LANGUAGE_ID . '&go=monitoring-positions',
						'more_url' => ['titlo_relevance_useful_services.php'],
						'icon' => 'titlo_relevance_useful_pos_mon_icon',
					],
				],
			],
		],
	],
];
