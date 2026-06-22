<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/dev_tools_auth.php';
vilmedRequireAdmin();
?>

<?if(!CModule::IncludeModule("sale") || !CModule::IncludeModule("catalog") || !CModule::IncludeModule("iblock"))
    return;

$IBLOCK_ID = 24;
$IBLOCK_ID_SKU = 16;
$CODE_ENUM_SKU_PROP = "PROP2";

$arSelect = Array("ID", "IBLOCK_ID", "NAME","PROPERTY_*");
$res = CIBlockElement::GetList(Array(), Array("IBLOCK_ID" => $IBLOCK_ID), false, false, $arSelect);
while($ob = $res->GetNextElement()){
    $arFields = $ob->GetFields();
    $arProps = $ob->GetProperties();
    //Если нет торговых предложении.
    if(!CCatalogSKU::IsExistOffers($arFields['ID'], $arFields['IBLOCK_ID'])){
        $arPropSku = array();
        $property_enums = CIBlockPropertyEnum::GetList(Array(), Array("IBLOCK_ID" => $IBLOCK_ID_SKU, "CODE" => $CODE_ENUM_SKU_PROP));
        while($enum_fields = $property_enums->GetNext())
        {
            $arPropSku[$enum_fields['ID']] = $enum_fields['VALUE'];
            $PROPERTY_ID_SKU = $enum_fields['PROPERTY_ID'];
        }

        if(!$PROPERTY_ID_SKU){
            $properties = CIBlockProperty::GetList(Array(), Array("IBLOCK_ID" => $IBLOCK_ID_SKU, "CODE" => $CODE_ENUM_SKU_PROP));
            if ($prop_fields = $properties->GetNext())
            {
                $PROPERTY_ID_SKU = $prop_fields['ID'];
            }
        }

        foreach($arProps['ARTICLS']['DESCRIPTION'] as $k => $d){

            $prop_enum_sku = null;
            if ($prop_key = array_search($d, $arPropSku)) {
                $prop_enum_sku = $prop_key;
            }else{
                $ibpenum = new CIBlockPropertyEnum;
                if($PropID = $ibpenum->Add(Array('PROPERTY_ID' => $PROPERTY_ID_SKU, 'VALUE' => $d)))
                    $prop_enum_sku = $PropID;
            }
            if($PROPERTY_ID_SKU && $prop_enum_sku){

                $intSKUIBlock = $IBLOCK_ID_SKU; // ID инфоблока предложений (должен быть торговым каталогом)
                $arCatalog = CCatalog::GetByID($intSKUIBlock);
                if (!$arCatalog)
                    return;

                $intProductIBlock = $arCatalog['PRODUCT_IBLOCK_ID']; // ID инфоблока товаров
                $intSKUProperty = $arCatalog['SKU_PROPERTY_ID']; // ID свойства в инфоблоке предложений типа "Привязка к товарам (SKU)"
                $obElement = new CIBlockElement();
                $intProductID = $arFields['ID']; // товар, получили ID

                if ($intProductID)
                {

                    $arProp = [];
                    $arProp[$intSKUProperty] = $intProductID;
                    $arProp["ARTNUMBER"] = $arProps['ARTICLS']['VALUE'][$k];
                    $arProp[$PROPERTY_ID_SKU] = Array("VALUE" => $prop_enum_sku );
                    $arFieldsSKU = array(
                        'NAME' => 'Товар',
                        'IBLOCK_ID' => $intSKUIBlock,
                        'ACTIVE' => 'Y',
                        'PROPERTY_VALUES' => $arProp
                    );
                    if($intOfferID = $obElement->Add($arFieldsSKU)){
                        $arFieldsPrice = Array(
                            "PRODUCT_ID" => $intOfferID,
                            "CATALOG_GROUP_ID" => 1,
                            "PRICE" => $arProps['PRICES']['VALUE'][$k],
                            "CURRENCY" => "RUB",
                        );
                        if(CPrice::Add($arFieldsPrice)){
                            CCatalogProduct::Add([
                                'ID' => $intOfferID,
                                'AVAILABLE' => 'Y',
                                'TYPE' => \Bitrix\Catalog\ProductTable::TYPE_OFFER
                            ]);
                        }
                    }
                    var_dump($intOfferID);
                }
            }
        }
    }

}
