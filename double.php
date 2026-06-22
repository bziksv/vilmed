<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/dev_tools_auth.php';
vilmedRequireAdmin();
?>

<?if(!CModule::IncludeModule("sale") ||
    !CModule::IncludeModule("catalog") ||
    !CModule::IncludeModule("iblock"))
    return;

if(intval($_REQUEST["IBLOCK_ID"]) <= 0)
    die('Укажите IBLOCK_ID!');

$IBLOCK_ID = $_REQUEST["IBLOCK_ID"];
$arElement = [];
$arSection = [];

$arSelect = Array("ID", "NAME", "CODE");
$arFilter = Array("IBLOCK_ID" => IntVal($IBLOCK_ID));
$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
while($ob = $res->GetNextElement())
{
    $arFields = $ob->GetFields();
    $arElement[$arFields['CODE']][] = $arFields;
}

$dElem = [];
foreach ($arElement as $el){
    if(count($el) > 1)
        $dElem[] = $el;
}

$arFilter = Array('IBLOCK_ID' => IntVal($IBLOCK_ID));
$db_list = CIBlockSection::GetList([], $arFilter, false, $arSelect);
while($ar_result = $db_list->GetNext())
{
    $arSection[$ar_result['CODE']][] = $ar_result;
}


$dSect = [];
foreach ($arSection as $sect){
    if(count($sect) > 1)
        $dSect[] = $sect;
}
?>

<h1>Разделы</h1>
<table border="1">
    <? foreach($dSect as $s): ?>
    <tr>
        <? foreach($s as $d): ?>
        <td><?=$d['ID']?></td>
        <td><?=$d['NAME']?></td>
        <td><?=$d['CODE']?></td>
        <? endforeach; ?>
    </tr>
    <? endforeach; ?>
</table>

<h1>Элементы</h1>
<table border="1">
    <? foreach($dElem as $s): ?>
        <tr>
            <? foreach($s as $d): ?>
                <td><?=$d['ID']?></td>
                <td><?=$d['NAME']?></td>
                <td><?=$d['CODE']?></td>
            <? endforeach; ?>
        </tr>
    <? endforeach; ?>
</table>

<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");?>
