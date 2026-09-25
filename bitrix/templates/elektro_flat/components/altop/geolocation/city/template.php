<?if(!defined("B_PROLOG_INCLUDED")||B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Localization\Loc;

// Динамическая область композита — город/телефон зависят от cookie.
// ID НЕ должен быть "geolocation": иначе Bitrix подменяет innerHTML у #geolocation,
// а .telephone остаётся снаружи → после AJAX два номера в шапке.
$frame = $this->createFrame("vilmed_geo", true)->begin();

if($arParams["USE_GEOLOCATION"] == "Y"):
	$phpCity = !empty($arParams["GEOLOCATION_CITY"]) ? (string)$arParams["GEOLOCATION_CITY"] : "";
?>
	<div id="geolocation" class="geolocation">
		<button type="button" id="geolocationChangeCity" class="geolocation__link"><i class="fa fa-map-marker" aria-hidden="true"></i><span class="geolocation__value"><?=($phpCity !== "" ? htmlspecialcharsbx($phpCity) : Loc::getMessage("GEOLOCATION_POSITIONING"));?></span></button>
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

		(function() {
			var phpCity = <?=CUtil::PhpToJSObject($phpCity)?>;

			function vilmedGetCookie(name) {
				var m = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/([.$?*|{}()[\]\\/+^])/g, "\\$1") + "=([^;]*)"));
				return m ? decodeURIComponent(m[1].replace(/\+/g, " ")) : "";
			}

			// Bitrix пишет BITRIX_SM_GEOLOCATION_CITY — без префикса cookie «не видна» в document.cookie
			function vilmedGetGeoCityCookie() {
				var prefixes = ["BITRIX_SM_", "BITRIX_", ""];
				for (var i = 0; i < prefixes.length; i++) {
					var v = vilmedGetCookie(prefixes[i] + "GEOLOCATION_CITY");
					if (v) {
						return v;
					}
				}
				return "";
			}

			function vilmedApplyKnownCity(city) {
				if (!city) {
					return false;
				}
				var el = document.querySelector("#geolocation .geolocation__value");
				if (el) {
					el.textContent = city;
				}
				return true;
			}

			// Уже есть город (cookie или PHP) — не гоняем Yandex, иначе затрёт выбранную Москву.
			if (vilmedApplyKnownCity(phpCity || vilmedGetGeoCityCookie())) {
				BX.bind(BX("geolocationChangeCity"), "click", BX.delegate(BX.CityChange, BX));
				return;
			}

			<?if(!$arResult['is_bot']) {?>
			<?if($arParams["MODE_OPERATION"] == "BITRIX") {?>
				var geolocation = {
					country: <?=CUtil::PhpToJSObject($arResult["countryName"])?>,
					region: <?=CUtil::PhpToJSObject($arResult["regionName"])?>,
					city: <?=CUtil::PhpToJSObject($arResult["cityName"])?>
				};
				(function() {
					var run = function() {
						if (vilmedApplyKnownCity(vilmedGetGeoCityCookie())) {
							return;
						}
						BX.Geolocation(geolocation);
					};
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
				window.addEventListener("load", function() {
					if (vilmedApplyKnownCity(vilmedGetGeoCityCookie())) {
						return;
					}
					BX.loadYandexMaps(function() {
						ymaps.ready(BX.GeolocationYandex);
					});
				});
			<?}?>
			<?}?>

			BX.bind(BX("geolocationChangeCity"), "click", BX.delegate(BX.CityChange, BX));
		})();
	</script>

    <div class="geolocation__popup" id="geolocation__popup">
        <? include_once('popup.php'); ?>
    </div>
<?else:?>
	<div class="telephone"><?=(!empty($arResult["CONTACTS"]) ? $arResult["CONTACTS"] : "");?></div>
<?endif;

$frame->end();
?>
