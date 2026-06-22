<?php
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;

if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/prolog.php");
$moduleId = 'esol.importxml';
$moduleFilePrefix = 'esol_import_xml';
$moduleJsId = str_replace('.', '_', $moduleId);
$moduleJsId2 = $moduleJsId;
$moduleDemoExpiredFunc = $moduleJsId2.'_demo_expired';
$moduleShowDemoFunc = $moduleJsId2.'_show_demo';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
CJSCore::Init(array($moduleJsId));
IncludeModuleLangFile(__FILE__);

include_once(dirname(__FILE__).'/../install/demo.php');
if (call_user_func($moduleDemoExpiredFunc)) {
	require ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	call_user_func($moduleShowDemoFunc);
	require ($DOCUMENT_ROOT."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$oProfile = \Bitrix\EsolImportxml\Profile::getInstance();
$arProfiles = $oProfile->GetList();
$logger = new \Bitrix\EsolImportxml\Logger(false);

$sTableID = "tbl_esol_importxml_view_stat";
$oSort = new CAdminSorting($sTableID, "ID", "DESC");
$lAdmin = new CAdminList($sTableID, $oSort);

$arFilterFields = array(
	"find",
	"find_type",
	"find_exec_id",
	"find_timestamp_x_1",
	"find_timestamp_x_2",
	"find_profile_id",
	"find_item_id",
	"find_user_id",
);

$arFilter = array();
$lAdmin->InitFilter($arFilterFields);
InitSorting();

$find = $_REQUEST["find"];
$find_exec_id = $_REQUEST["find_exec_id"];
$find_type = $_REQUEST["find_type"];
$find_profile_id = $_REQUEST["find_profile_id"];
$find_timestamp_x_1 = $_REQUEST["find_timestamp_x_1"];
$find_timestamp_x_2 = $_REQUEST["find_timestamp_x_2"];
$find_item_id = $_REQUEST["find_item_id"];

if(strlen($find_profile_id) > 0) $arFilter['PROFILE_ID'] = $find_profile_id;
if(strlen($find_exec_id) > 0) $arFilter['PROFILE_EXEC_ID'] = $find_exec_id;
if(strlen($find_timestamp_x_1) > 0) $arFilter['>=DATE_EXEC'] = $find_timestamp_x_1;
if(strlen($find_timestamp_x_2) > 0) $arFilter['<=DATE_EXEC'] = $find_timestamp_x_2;
if(!is_array($find_type) && strlen($find_type) > 0) $arFilter['TYPE'] = $find_type;
elseif(is_array($find_type))
{
	$find_type = array_diff($find_type, array('NOT_REF'));
	if(!empty($find_type)) $arFilter['TYPE'] = $find_type;
}
if(strlen(trim($find_item_id)) > 0)
{
	$find_item_id = trim($find_item_id);
	if(is_numeric($find_item_id)) $arFilter['ENTITY_ID'] = $find_item_id;
	else $arFilter['?IBLOCK_ELEMENT.NAME'] = $find_item_id;
}
if(strlen($find_user_id) > 0) $arFilter['PROFILE_EXEC.RUNNED_BY'] = $find_user_id;


if(($arID = $lAdmin->GroupAction()))
{
	$removedCnt = 0;
	if($_REQUEST['action_target']=='selected')
	{
		$arID = Array();
		$dbResultList = \Bitrix\EsolImportxml\ProfileExecStatTable::getList(array('filter'=>$arFilter, 'select'=>array('ID')));
		while($arResult = $dbResultList->Fetch())
			$arID[] = $arResult['ID'];
	}

	foreach ($arID as $ID)
	{
		if(strlen($ID) <= 0)
			continue;

		switch ($_REQUEST['action'])
		{
			case "delete":
				$dbRes = \Bitrix\EsolImportxml\ProfileExecStatTable::delete($ID);
				if($dbRes->isSuccess())
				{
					$removedCnt++;
				}				
				else
				{
					$error = '';
					if($dbRes->getErrors())
					{
						foreach($dbRes->getErrors() as $errorObj)
						{
							$error .= $errorObj->getMessage().'. ';
						}
					}
					if($error)
						$lAdmin->AddGroupError($error, $ID);
					else
						$lAdmin->AddGroupError(GetMessage("ESOL_IX_ERROR_DELETING_TYPE"), $ID);
				}
				break;
		}
	}
	
	if($removedCnt > 0)
	{
		/*
		$dbRes = \Bitrix\EsolImportxml\ProfileExecTable::getList(array(
			'select' => array(
				'ID', 
				'PROFILE_EXEC_STAT_CNT'
			),
			'runtime' => array(
				'PROFILE_EXEC_STAT_CNT' => array(
					"data_type" => "integer",
					"expression" => array("COUNT(%s)", 'PROFILE_EXEC_STAT.ID')
				)
			),
			'filter' => array('PROFILE_EXEC_STAT_CNT'=>0)
		));
		while($arProfileExec = $dbRes->Fetch())
		{
			\Bitrix\EsolImportxml\ProfileExecTable::delete($arProfileExec['ID']);
		}
		*/
	}
}

	
$usePageNavigation = true;
if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel')
{
	$usePageNavigation = false;
}
else
{
	$navSize = CAdminResult::GetNavSize(
		$sTableID,
		array('nPageSize' => 20, 'sNavID' => $APPLICATION->GetCurPage())
	);
	$navyParams = CDBResult::GetNavParams($navSize);
	
	if($navyParams['SHOW_ALL'])
	{
		$showAllVar = "SHOWALL_".($GLOBALS['NavNum']+1);
		if(!isset($_REQUEST[$showAllVar]) || $_REQUEST[$showAllVar]!='1')
		{
			$navyParams['SHOW_ALL'] = false;
			//if(array_key_exists('SESS_ALL', $navyParams)){}
		}
	}
	
	if ($navyParams['SHOW_ALL'])
	{
		$usePageNavigation = false;
	}
	else
	{
		$navyParams['PAGEN'] = (int)$navyParams['PAGEN'];
		$navyParams['SIZEN'] = (int)$navyParams['SIZEN'];
	}
}

$getListParams = array(
	'order'=>array(ToUpper($by) => ToUpper($order)), 
	'filter'=>$arFilter, 
	'select'=>array(
		'ID', 
		'DATE_EXEC', 
		'PROFILE_ID', 
		//'PROFILE_NAME'=>'PROFILE.NAME', 
		'PROFILE_EXEC_ID', 
		'TYPE', 
		'ENTITY_ID', 
		//'RUNNED_BY_USER'=>'PROFILE_EXEC.RUNNED_BY_USER.LOGIN', 
		//'RUNNED_BY_USER_ID'=>'PROFILE_EXEC.RUNNED_BY_USER.ID', 
		'FIELDS',
		//'IBLOCK_ELEMENT_ID'=>'IBLOCK_ELEMENT.ID',
		//'IBLOCK_ELEMENT_NAME'=>'IBLOCK_ELEMENT.NAME',
		//'IBLOCK_ELEMENT_IBLOCK_ID'=>'IBLOCK_ELEMENT.IBLOCK_ID',
		//'IBLOCK_ELEMENT_IBLOCK_TYPE_ID'=>'IBLOCK_ELEMENT.IBLOCK.IBLOCK_TYPE_ID',
		//'IBLOCK_SECTION_ID'=>'IBLOCK_SECTION.ID',
		//'IBLOCK_SECTION_NAME'=>'IBLOCK_SECTION.NAME',
		//'IBLOCK_SECTION_IBLOCK_ID'=>'IBLOCK_SECTION.IBLOCK_ID',
		//'IBLOCK_SECTION_IBLOCK_TYPE_ID'=>'IBLOCK_SECTION.IBLOCK.IBLOCK_TYPE_ID',
	)
);
if(!$usePageNavigation)
{
	$getListParams['select']['PROFILE_NAME'] = 'PROFILE.NAME';
	$getListParams['select']['RUNNED_BY_USER_LOGIN'] = 'PROFILE_EXEC.RUNNED_BY_USER.LOGIN';
	$getListParams['select']['RUNNED_BY_USER_ID'] = 'PROFILE_EXEC.RUNNED_BY_USER.ID';
}
if(!$usePageNavigation || !class_exists('\Bitrix\Iblock\ElementTable'))
{
	$getListParams['select']['IBLOCK_ELEMENT_ID'] = 'IBLOCK_ELEMENT.ID';
	$getListParams['select']['IBLOCK_ELEMENT_NAME'] = 'IBLOCK_ELEMENT.NAME';
	$getListParams['select']['IBLOCK_ELEMENT_IBLOCK_ID'] = 'IBLOCK_ELEMENT.IBLOCK_ID';
	$getListParams['select']['IBLOCK_ELEMENT_IBLOCK_TYPE_ID'] = 'IBLOCK_ELEMENT.IBLOCK.IBLOCK_TYPE_ID';
}
if(!$usePageNavigation || !class_exists('\Bitrix\Iblock\SectionTable'))
{
	$getListParams['select']['IBLOCK_SECTION_ID'] = 'IBLOCK_SECTION.ID';
	$getListParams['select']['IBLOCK_SECTION_NAME'] = 'IBLOCK_SECTION.NAME';
	$getListParams['select']['IBLOCK_SECTION_IBLOCK_ID'] = 'IBLOCK_SECTION.IBLOCK_ID';
	$getListParams['select']['IBLOCK_SECTION_IBLOCK_TYPE_ID'] = 'IBLOCK_SECTION.IBLOCK.IBLOCK_TYPE_ID';
}

if ($usePageNavigation)
{
	$getListParams['limit'] = $navyParams['SIZEN'];
	$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
}

if ($usePageNavigation)
{
	$countQuery = new Query(\Bitrix\EsolImportxml\ProfileExecStatTable::getEntity());
	$countQuery->addSelect(new ExpressionField('CNT', 'COUNT(1)'));
	$countQuery->setFilter($getListParams['filter']);
	$totalCount = $countQuery->setLimit(null)->setOffset(null)->exec()->fetch();
	unset($countQuery);
	$totalCount = (int)$totalCount['CNT'];
	if ($totalCount > 0)
	{
		$totalPages = ceil($totalCount/$navyParams['SIZEN']);
		if ($navyParams['PAGEN'] > $totalPages)
			$navyParams['PAGEN'] = $totalPages;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
	}
	else
	{
		$navyParams['PAGEN'] = 1;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = 0;
	}
}
$rsData = new CAdminResult(\Bitrix\EsolImportxml\ProfileExecStatTable::getList($getListParams), $sTableID);

if ($usePageNavigation)
{
	$rsData->NavStart(array('nPageSize' => $getListParams['limit'], 'bShowAll' => true, 'NavShowAll'=>$navyParams['SHOW_ALL']), $navyParams['SHOW_ALL'], $navyParams['PAGEN']);
	$rsData->NavRecordCount = $totalCount;
	$rsData->NavPageCount = $totalPages;
	$rsData->NavPageNomer = $navyParams['PAGEN'];
}
else
{
	$rsData->NavStart();
}

$lAdmin->NavText($rsData->GetNavPrint(GetMessage("ESOL_IX_EVENTLOG_LIST_PAGE")));

$arHeaders = array(
	array(
		"id" => "ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_ID"),
		"sort" => "ID",
		"default" => true,
		"align" => "right",
	),
	array(
		"id" => "DATE_EXEC",
		"content" => GetMessage("ESOL_IX_EVENTLOG_TIMESTAMP_X"),
		"sort" => "DATE_EXEC",
		"default" => true,
		"align" => "right",
	),
	array(
		"id" => "PROFILE_ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_PROFILE_ID"),
		"default" => true,
	),
	array(
		"id" => "PROFILE_EXEC_ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_PROFILE_EXEC_ID"),
		"default" => true,
	),
	array(
		"id" => "TYPE",
		"content" => GetMessage("ESOL_IX_EVENTLOG_TYPE"),
		"default" => true,
	),
	array(
		"id" => "ENTITY_ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_ITEM_ID"),
		"default" => true,
	),
	array(
		"id" => "RUNNED_BY",
		"content" => GetMessage("ESOL_IX_EVENTLOG_USER_ID"),
		"default" => true,
	),
	array(
		"id" => "UID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_UID"),
	),
	array(
		"id" => "FIELDS",
		"content" => GetMessage("ESOL_IX_EVENTLOG_DESCRIPTION"),
		"default" => true,
	),
);

$lAdmin->AddHeaders($arHeaders);

$arUsersCache = array();
$arGroupsCache = array();
$arForumCache = array("FORUM" => array(), "TOPIC" => array(), "MESSAGE" => array());

$arRecords = $arPageProfiles = $arProfileExecs = $arElements = $arSections = $arPageProfilesIds = $arProfileExecsIds = $arElementsIds = $arSectionsIds = array();
while($arr = $rsData->NavNext())
{
	if($usePageNavigation)
	{
		if($arr['PROFILE_ID'] > 0 && !in_array($arr['PROFILE_ID'], $arPageProfilesIds)) $arPageProfilesIds[] = $arr['PROFILE_ID'];
		if($arr['PROFILE_EXEC_ID'] > 0 && !in_array($arr['PROFILE_EXEC_ID'], $arProfileExecsIds)) $arProfileExecsIds[] = $arr['PROFILE_EXEC_ID'];
		if(strpos($arr['TYPE'], 'ELEMENT_')===0 && $arr['ENTITY_ID'] > 0 && !in_array($arr['ENTITY_ID'], $arElementsIds)) $arElementsIds[] = $arr['ENTITY_ID'];
		elseif(strpos($arr['TYPE'], 'SECTION_')===0 && $arr['ENTITY_ID'] > 0 && !in_array($arr['ENTITY_ID'], $arSectionsIds)) $arSectionsIds[] = $arr['ENTITY_ID'];
	}
	$arRecords[] = $arr;
}
if(!empty($arPageProfilesIds))
{
	$dbRes = \Bitrix\EsolImportxml\ProfileTable::getList(array('filter'=>array('ID'=>$arPageProfilesIds), 'select'=>array('ID', 'NAME')));
	while($arr = $dbRes->Fetch())
	{
		$arPageProfiles[$arr['ID']] = $arr;
	}
}
if(!empty($arProfileExecsIds))
{
	$dbRes = \Bitrix\EsolImportxml\ProfileExecTable::getList(array('filter'=>array('ID'=>$arProfileExecsIds), 'select'=>array('ID', 'RUNNED_BY_USER_LOGIN'=>'RUNNED_BY_USER.LOGIN', 'RUNNED_BY_USER_ID'=>'RUNNED_BY_USER.ID')));
	while($arr = $dbRes->Fetch())
	{
		$arProfileExecs[$arr['ID']] = $arr;
	}
}
if(!empty($arElementsIds) && class_exists('\Bitrix\Iblock\ElementTable'))
{
	$dbRes = \Bitrix\Iblock\ElementTable::getList(array('filter'=>array('ID'=>$arElementsIds), 'select'=>array('ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_TYPE_ID'=>'IBLOCK.IBLOCK_TYPE_ID')));
	while($arr = $dbRes->Fetch())
	{
		$arElements[$arr['ID']] = $arr;
	}
}
if(!empty($arSectionsIds) && class_exists('\Bitrix\Iblock\SectionTable'))
{
	$dbRes = \Bitrix\Iblock\SectionTable::getList(array('filter'=>array('ID'=>$arSectionsIds), 'select'=>array('ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_TYPE_ID'=>'IBLOCK.IBLOCK_TYPE_ID')));
	while($arr = $dbRes->Fetch())
	{
		$arSections[$arr['ID']] = $arr;
	}
}


//while($db_res = $rsData->NavNext(true, "a_"))
foreach($arRecords as $arItem)
{
	if(array_key_exists($arItem['PROFILE_ID'], $arPageProfiles))
	{
		$arItem['PROFILE_NAME'] = $arPageProfiles[$arItem['PROFILE_ID']]['NAME'];
	}
	if(array_key_exists($arItem['PROFILE_EXEC_ID'], $arProfileExecs))
	{
		$arItem['RUNNED_BY_USER_LOGIN'] = $arProfileExecs[$arItem['PROFILE_EXEC_ID']]['RUNNED_BY_USER_LOGIN'];
		$arItem['RUNNED_BY_USER_ID'] = $arProfileExecs[$arItem['PROFILE_EXEC_ID']]['RUNNED_BY_USER_ID'];
	}
	
	$row =& $lAdmin->AddRow($arItem['ID'], $arItem);
	
	$elink = '';
	$arFields = \Bitrix\EsolImportxml\Utils::Unserialize(htmlspecialcharsback($arItem['FIELDS']));
	if(!is_array($arFields)) $arFields = array();
	$arUidFields = array();
	if(isset($arFields['FILTER'])) $arUidFields = array_diff_key($arFields['FILTER'], array('IBLOCK_ID'=>'','CHECK_PERMISSIONS'=>''));
	else foreach($arFields as $k=>$v) if(strpos($k, 'FILTER_')===0) $arUidFields[] = (is_array($v) ? implode(', ', $v) : $v);
	if($arItem['TYPE'])
	{
		if($arItem['TYPE']=='ELEMENT_SKIPPED')
		{
			//$arFieldsFilter = (isset($arFields['FILTER']) ? $arFields['FILTER'] : $arFields);
			$arFieldsFields = (isset($arFields['FIELDS']) ? $arFields['FIELDS'] : array());
			$arItem['FIELDS'] = '<b>'.GetMessage("ESOL_IX_EVENTLOG_REASON_SKIP").': '.sprintf(GetMessage("ESOL_IX_EVENTLOG_REASON_SKIP_".$arFields['REASON']), $logger->GetFieldLangName($arFields['FIELD_NAME'], $arFields['FIELDS']['IE_IBLOCK_ID'])).'</b>';
			if(!empty($arFieldsFields)) $arItem['FIELDS'] .= $logger->GetElementDescription($arFieldsFields, true);
		}
		elseif($arItem['TYPE']=='ELEMENT_NOT_FOUND')
		{
			$arFieldsFilter = (isset($arFields['FILTER']) ? $arFields['FILTER'] : $arFields);
			$arFieldsFields = (isset($arFields['FIELDS']) ? $arFields['FIELDS'] : array());
			$arItem['FIELDS'] = '<b>'.GetMessage("ESOL_IX_EVENTLOG_FILTER_FIELDS").'</b>'.$logger->GetElementDescriptionArray($arFieldsFilter);
			if(!empty($arFieldsFields)) $arItem['FIELDS'] .= $logger->GetElementDescription($arFieldsFields);
		}
		elseif(strpos($arItem['TYPE'], 'ELEMENT_')===0 && $arItem['ENTITY_ID'] > 0)
		{
			if(array_key_exists($arItem['ENTITY_ID'], $arElements))
			{
				foreach($arElements[$arItem['ENTITY_ID']] as $k=>$v)
				{
					$arItem['IBLOCK_ELEMENT_'.$k] = $v;
				}
			}
			if($arItem['IBLOCK_ELEMENT_ID'])
			{
				$elink = '[<a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID='.$arItem['IBLOCK_ELEMENT_IBLOCK_ID'].'&type='.$arItem['IBLOCK_ELEMENT_IBLOCK_TYPE_ID'].'&ID='.$arItem['IBLOCK_ELEMENT_ID'].'&lang='.LANGUAGE_ID.'">'.$arItem['IBLOCK_ELEMENT_ID'].'</a>] '.$arItem['IBLOCK_ELEMENT_NAME'];
			}
			
			if(strlen($arItem['FIELDS']))
			{
				$arItem['FIELDS'] = $logger->GetElementDescription($arItem['FIELDS']);
			}
		}
		elseif(strpos($arItem['TYPE'], 'SECTION_')===0 && $arItem['ENTITY_ID'] > 0)
		{
			if(array_key_exists($arItem['ENTITY_ID'], $arSections))
			{
				foreach($arSections[$arItem['ENTITY_ID']] as $k=>$v)
				{
					$arItem['IBLOCK_SECTION_'.$k] = $v;
				}
			}
			if($arItem['IBLOCK_SECTION_ID'])
			{
				$elink = '[<a href="/bitrix/admin/iblock_section_edit.php?IBLOCK_ID='.$arItem['IBLOCK_SECTION_IBLOCK_ID'].'&type='.$arItem['IBLOCK_SECTION_IBLOCK_TYPE_ID'].'&ID='.$arItem['IBLOCK_SECTION_ID'].'&lang='.LANGUAGE_ID.'">'.$arItem['IBLOCK_SECTION_ID'].'</a>] '.$arItem['IBLOCK_SECTION_NAME'];
			}
			
			if(strlen($arItem['FIELDS']))
			{
				$arItem['FIELDS'] = $logger->GetSectionDescription($arItem['FIELDS'], $arItem['IBLOCK_SECTION_IBLOCK_ID']);
			}
		}
	}
	
	$row->AddViewField("DATE_EXEC", $arItem['DATE_EXEC']);
	$row->AddViewField("PROFILE_ID", '<a href="/bitrix/admin/'.$moduleFilePrefix.'.php?lang=ru&PROFILE_ID='.($arItem['PROFILE_ID'] - 1).'">'.$arItem['PROFILE_NAME'].'</a>');
	$row->AddViewField("PROFILE_EXEC_ID", $arItem['PROFILE_EXEC_ID']);
	$row->AddViewField("TYPE", GetMessage("ESOL_IX_EVENTLOG_IBLOCK_".$arItem['TYPE']));
	$row->AddViewField("ENTITY_ID", $elink);
	$row->AddViewField("RUNNED_BY", ($arItem['RUNNED_BY_USER_ID'] ? '[<a href="user_edit.php?lang='.LANG.'&ID='.$arItem['RUNNED_BY_USER_ID'].'">'.$arItem['RUNNED_BY_USER_ID'].'</a>] '.$arItem['RUNNED_BY_USER_LOGIN'] : ''));
	$row->AddViewField("UID", implode(', ', $arUidFields));
	$row->AddViewField("FIELDS", '');
	if(strlen($arItem['FIELDS']))
	{
		if(strncmp("==", $arItem['FIELDS'], 2)===0)
			$FIELDS = htmlspecialcharsbx(base64_decode(substr($arItem['FIELDS'], 2)));
		else
			$FIELDS = $arItem['FIELDS'];
		//htmlspecialcharsback for <br> <BR> <br/>
		$FIELDS = preg_replace("#(&lt;)(\\s*br\\s*/{0,1})(&gt;)#is", "<\\2>", $FIELDS);
		$row->AddViewField("FIELDS", $FIELDS);
	}
	else
	{
		$row->AddViewField("FIELDS", '');
	}
	
	$arActions = array();
	$arActions[] = array("ICON"=>"delete", "TEXT"=>GetMessage("ESOL_IX_LOG_RECORD_DELETE"), "ACTION"=>"if(confirm('".GetMessageJS('ESOL_IX_LOG_RECORD_DELETE_CONFIRM')."')) ".$lAdmin->ActionDoGroup($arItem['ID'], "delete"));

	$row->AddActions($arActions);
}

$lAdmin->AddFooter(
	array(
		array(
			"title" => GetMessage("MAIN_ADMIN_LIST_SELECTED"),
			"value" => $rsData->SelectedRowsCount()
		),
		array(
			"counter" => true,
			"title" => GetMessage("MAIN_ADMIN_LIST_CHECKED"),
			"value" => "0"
		),
	)
);

$lAdmin->AddGroupActionTable(
	array(
		"delete" => GetMessage("MAIN_ADMIN_LIST_DELETE"),
	)
);


$aContext = array();
$lAdmin->AddAdminContextMenu($aContext);

$APPLICATION->SetTitle(GetMessage("ESOL_IX_EVENTLOG_PAGE_TITLE"));
$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/prolog_admin_after.php");

if (!call_user_func($moduleDemoExpiredFunc)) {
	call_user_func($moduleShowDemoFunc);
}
?>
<form name="find_form" id="filter_find_form" method="GET" action="<?echo $APPLICATION->GetCurPage()?>?">
<input type="hidden" name="lang" value="<?echo LANG?>">
<?
$arFilterNames = array(
	"find_exec_id" => GetMessage("ESOL_IX_EVENTLOG_PROFILE_EXEC_ID"),
	"find_timestamp_x" => GetMessage("ESOL_IX_EVENTLOG_TIMESTAMP_X"),
	"find_type" => GetMessage("ESOL_IX_EVENTLOG_TYPE"),
	"find_item_id" => GetMessage("ESOL_IX_EVENTLOG_ITEM_ID"),
	"find_user_id" => GetMessage("ESOL_IX_EVENTLOG_USER_ID"),
);

$oFilter = new CAdminFilter($sTableID."_filter", $arFilterNames);
$oFilter->Begin();
?>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_PROFILE_ID")?>:</td>
	<td>
		<select name="find_profile_id" >
			<option value=""><?echo GetMessage("ESOL_IX_ALL"); ?></option>
			<?
			foreach($arProfiles as $k=>$profile)
			{
				$key = $k + 1;
				?><option value="<?echo $key;?>" <?if($find_profile_id==$key){echo 'selected';}?>><?echo $profile; ?></option><?
			}
			?>
		</select>
	</td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_PROFILE_EXEC_ID")?>:</td>
	<td><input type="text" name="find_exec_id" size="47" value="<?echo htmlspecialcharsbx($find_exec_id)?>"></td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_TIMESTAMP_X")?>:</td>
	<td><?echo CAdminCalendar::CalendarPeriod("find_timestamp_x_1", "find_timestamp_x_2", $find_timestamp_x_1, $find_timestamp_x_2, false, 15, true)?></td>
</tr>
<?
$arSiteDropdown = array("reference" => array(
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_ADD"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_UPDATE"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_DELETE"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_FOUND"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_NOT_FOUND"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_ELEMENT_SKIPPED"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_SECTION_ADD"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_SECTION_UPDATE"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_SECTION_DELETE"),
	GetMessage("ESOL_IX_EVENTLOG_IBLOCK_SECTION_NOT_FOUND")
), "reference_id" => array(
	'ELEMENT_ADD',
	'ELEMENT_UPDATE',
	'ELEMENT_DELETE',
	'ELEMENT_FOUND',
	'ELEMENT_NOT_FOUND',
	'ELEMENT_SKIPPED',
	'SECTION_ADD',
	'SECTION_UPDATE',
	'SECTION_DELETE',
	'SECTION_NOT_FOUND'
));
if(!in_array($find_type, $arSiteDropdown['reference_id'])) $find_type = '';
?>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_TYPE")?>:</td>
	<td><?echo SelectBoxMFromArray("find_type[]", $arSiteDropdown, $find_type, GetMessage("ESOL_IX_ALL"), "");?></td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_ITEM_ID")?>:</td>
	<td><input type="text" name="find_item_id" size="47" value="<?echo htmlspecialcharsbx($find_item_id)?>"></td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_USER_ID")?>:</td>
	<td><input type="text" name="find_user_id" size="47" value="<?echo htmlspecialcharsbx($find_user_id)?>"></td>
</tr>
<?
$oFilter->Buttons(array("table_id"=>$sTableID, "url"=>$APPLICATION->GetCurPage(), "form"=>"find_form"));
$oFilter->End();
?>
</form>
<?

$lAdmin->DisplayList();

/*echo BeginNote();
echo GetMessage("ESOL_IX_EVENTLOG_BOTTOM_NOTE");
echo EndNote();*/

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
