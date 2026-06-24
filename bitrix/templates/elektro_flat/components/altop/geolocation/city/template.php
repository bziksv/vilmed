<?if(!defined("B_PROLOG_INCLUDED")||B_PROLOG_INCLUDED!==true)die();

//$frame = $this->createFrame("geolocation")->begin("");

use Bitrix\Main\Localization\Loc;

if($arParams["USE_GEOLOCATION"] == "Y"):?>
	<div id="geolocation" class="geolocation">
		<a id="geolocationChangeCity" class="geolocation__link" href="javascript:void(0);"><i class="fa fa-map-marker" aria-hidden="true"></i><span class="geolocation__value"><?=(!empty($arParams["GEOLOCATION_CITY"]) ? $arParams["GEOLOCATION_CITY"] : Loc::getMessage("GEOLOCATION_POSITIONING"));?></span></a>
	</div>
	<div class="telephone"><?=(!empty($arResult["CONTACTS"]) ? $arResult["CONTACTS"] : "");?></div>

	<script type="text/javascript">
		//JS_MESSAGE//
		BX.message({
			GEOLOCATION_POSITIONING: "<?=Loc::getMessage('GEOLOCATION_POSITIONING')?>",
			GEOLOCATION_NOT_DEFINED: "<?=Loc::getMessage('GEOLOCATION_NOT_DEFINED')?>",
			GEOLOCATION_YOUR_CITY: "<?=Loc::getMessage('GEOLOCATION_YOUR_CITY')?>",
			GEOLOCATION_YES: "<?=Loc::getMessage('GEOLOCATION_YES')?>",
			GEOLOCATION_CHANGE_CITY: "<?=Loc::getMessage('GEOLOCATION_CHANGE_CITY')?>",
			GEOLOCATION_POPUP_WINDOW_TITLE: "<?=Loc::getMessage('GEOLOCATION_POPUP_WINDOW_TITLE')?>",
			GEOLOCATION_COMPONENT_PATH: "<?=$this->__component->__path?>",
			GEOLOCATION_COMPONENT_TEMPLATE: "<?=$this->GetFolder();?>",
			GEOLOCATION_PARAMS: <?=CUtil::PhpToJSObject($arParams["PARAMS_STRING"])?>,
			GEOLOCATION_SHOW_CONFIRM: "<?=$arParams['SHOW_CONFIRM']?>"
		});

		<?if(empty($arParams["GEOLOCATION_CITY"]) && !$arResult['is_bot']) {?>
			//GEOLOCATION//
			<?if($arParams["MODE_OPERATION"] == "BITRIX") {?>
				var geolocation = {
					country: <?=CUtil::PhpToJSObject($arResult["countryName"])?>,
					region: <?=CUtil::PhpToJSObject($arResult["regionName"])?>,
					city: <?=CUtil::PhpToJSObject($arResult["cityName"])?>
				};
				// VILMED perf: defer geolocation XHR off the critical path (city + delivery still resolve).
				(function() {
					var run = function() { BX.Geolocation(geolocation); };
					var schedule = function() {
						if ("requestIdleCallback" in window) {
							requestIdleCallback(run, {timeout: 3000});
						} else {
							setTimeout(run, 1200);
						}
					};
					if (document.readyState === "complete") {
						schedule();
					} else {
						window.addEventListener("load", schedule, {once: true});
					}
				})();
			<?} else {?>
				window.addEventListener('load', function() {
					BX.loadYandexMaps(function() {
						ymaps.ready(BX.GeolocationYandex);
					});
				});
			<?}?>
		<?}?>

		//CHANGE_CITY//
		BX.bind(BX("geolocationChangeCity"), "click", BX.delegate(BX.CityChange, BX));
	</script>

    <div class="geolocation__popup" id="geolocation__popup">
        <? include_once('popup.php'); ?>
    </div>
<?else:?>
	<div class="telephone"><?=(!empty($arResult["CONTACTS"]) ? $arResult["CONTACTS"] : "");?></div>
<?endif;

//$frame->end();
?>
