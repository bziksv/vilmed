<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

global $APPLICATION;

if(empty($arResult))
	return "";

$showNavChain = false;
$strReturn = "";

$itemSize = count($arResult);
for($index = 0; $index < $itemSize; $index++) {
	$title = htmlspecialcharsex($arResult[$index]["TITLE"]);

	$nextRef = ($index < $itemSize-2 && $arResult[$index+1]["LINK"] <> "" ? " itemref='breadcrumb_".($index + 1)."'" : "");
	$child = ($index > 0 ? " itemprop='child'" : "");
	$arrow = ($index > 0 ? "<span class='breadcrumb__arrow'></span>" : "");

	if($arResult[$index]["LINK"] <> "" && $index != $itemSize-1) {
		$ul = "";
		$dropdown = "";
		$find = "/catalog/";
		if(strpos($arResult[$index]["LINK"], $find) >= 0 && strlen($arResult[$index]["LINK"]) > strlen($find) && isProductDetail()){
			$IBLOCK_ID = 24;
			$code = substr($arResult[$index]["LINK"], strlen($find), -1);

			$arSection = getSubSecions($IBLOCK_ID, $code);
			if($showNavChain === false && $arSection["SECTION"]["UF_SHOW_NAV_CHAIN_SECTIONS"])
				$showNavChain = true;
				
			if($arSection["SECTIONS"] && $showNavChain) {
				$dropdown = "dropdown";
				$ul = "<ul class='breadcrumb-dropdown'>";
				foreach($arSection["SECTIONS"] as $section) {
					$name = $section["NAME"];
					$url = $section["SECTION_PAGE_URL"];
					
					$ul .= "<li><a href='".$url."'>".$name."</a></li>";
				}
				$ul .= "</ul>";				
			}
		}
		
		$strReturn .= "<div class='breadcrumb__item ".$dropdown."' id='breadcrumb_".$index."' itemscope='' itemtype='".(CMain::IsHTTPS()? 'https' : 'http')."://data-vocabulary.org/Breadcrumb'".$child.$nextRef.">".$arrow."<a class='breadcrumb__link' href='".$arResult[$index]["LINK"]."' title='".$title."' itemprop='url'>".($index == 0 ? "<i class='fa fa-home breadcrumb__icon_main'></i>" : "")."<span class='".($index == 0 ? "breadcrumb__title_main" : "breadcrumb__title")."' itemprop='title'>".$title."</span></a>".$ul."</div>";
	} else {
		$strReturn .= "<div class='breadcrumb__item'>".$arrow.($index == 0 ? "<i class='fa fa-home breadcrumb__icon_main'></i>" : "")."<span class='".($index == 0 ? "breadcrumb__title_main" : "breadcrumb__title")."'>".$title."</span></div>";
	}
}

return $strReturn;