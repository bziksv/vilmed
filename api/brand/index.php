<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/dev_tools_auth.php';
vilmedRequireAdmin();

@set_time_limit(3000000);
ini_set('max_execution_time', 3000000);

CModule::IncludeModule("iblock");

$IBLOCK_ID_CATALOG = 24;
$IBLOCK_ID_BRAND = 13;

try {

    $arSelect = Array("ID", "IBLOCK_ID", "NAME","PROPERTY_*");
    $arFilter = Array("IBLOCK_ID" => $IBLOCK_ID_CATALOG);
    $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
    while($ob = $res->GetNextElement()){
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();

        if($arProps['MANUFACTURER']['PROPERTY_TYPE'] != 'E')
            throw new Exception('Ошибка: Укажите свойству "Производитель" тип "Привязка к элементам в виде списка". К ИБ c ID: '.$IBLOCK_ID_BRAND);

        if($arProps['CML2_ATTRIBUTES']){

            foreach($arProps['CML2_ATTRIBUTES']['DESCRIPTION'] as $key => $val){

                if(preg_match('/Бренд/', $val)){
                    if($brandName = $arProps['CML2_ATTRIBUTES']['VALUE'][$key]){

                        $resBrand = CIBlockElement::GetList(Array(), ['IBLOCK_ID' => $IBLOCK_ID_BRAND, 'NAME' => trim($brandName)], false, false, ['ID', 'NAME']);
                        if($obBrand = $resBrand->fetch()){

                            if($obBrand['ID'])
                                CIBlockElement::SetPropertyValuesEx($arFields['ID'], $IBLOCK_ID_CATALOG, ['MANUFACTURER' => $obBrand['ID']]);
                        }
                    }
                }
            }
        }
    }
    print 'Success!';
} catch (Exception $e) {

    echo $e->getMessage(), "\n";
}
