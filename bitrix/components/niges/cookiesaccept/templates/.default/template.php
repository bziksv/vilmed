<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
if (method_exists($this, 'setFrameMode')) {
	$this->setFrameMode(true);
	$ncaSettings=false;
}
?>

<?$arResult["ITEMS"][0] = "1445";

if ($arResult["ITEMS"][0]) {
	ob_start();
?>
	<div id="nca-cookiesaccept-line" class="nca-cookiesaccept-line style-<?=$arResult["SETSTYLE"]?> <?if ($arResult["HIDE_MOBILE"] == "Y") :?>nca-hidden-mobile<? endif; ?> <?if ($arResult["HIDE_PC"] == "Y") :?>nca-hidden-pc<? endif; ?>">
		<div id="nca-nca-position-<?=$arResult["POSITION"]?>"id="nca-bar" class="nca-bar nca-style- nca-animation-<?=$arResult["ANIMATION"]?> nca-position-<?=$arResult["POSITION"]?>">
			<div class="nca-cookiesaccept-line-text"><?=htmlspecialchars_decode($arResult["MAINTEXT"])?></div> 
			<div><button type="button" id="nca-cookiesaccept-line-accept-btn" onclick="ncaCookieAcceptBtn()" ><?=$arResult["TEXTBTN"]?></button></div>
		</div>
	</div>
<?
$html = preg_replace("/\s+/", " ", ob_get_contents());
ob_end_clean();

$APPLICATION->AddHeadString('<script type="text/javascript">
if (window == window.top) {
	document.addEventListener("DOMContentLoaded", function() {
		var div = document.createElement("div"); div.innerHTML = \''.$html.'\';
		document.body.appendChild(div);
	});
}
function ncaCookieAcceptBtn(){ 
	var alertWindow = document.getElementById("nca-cookiesaccept-line"); alertWindow.remove();
	var cookie_string = "NCA_COOKIE_ACCEPT_'.intval($arResult["TEXTVER"]).'" + "=" + escape("Y"); 
	var expires = new Date((new Date).getTime() + (1000 * 60 * 60 * 24 * 1500)); 
	cookie_string += "; expires=" + expires.toUTCString(); 
	cookie_string += "; path=" + escape ("/"); 
	document.cookie = cookie_string; 	
}
function ncaCookieAcceptCheck(){
	var closeCookieValue = "N"; 
	var value = "; " + document.cookie;
	var parts = value.split("; " + "NCA_COOKIE_ACCEPT_'.intval($arResult["TEXTVER"]).'" + "=");
	if (parts.length == 2) { 
		closeCookieValue = parts.pop().split(";").shift(); 
	}
	if(closeCookieValue != "Y") { 
		/*document.head.insertAdjacentHTML("beforeend", "<style>#nca-cookiesaccept-line {display:flex}</style>")*/
	} else { 
		document.head.insertAdjacentHTML("beforeend", "<style>#nca-cookiesaccept-line {display:none}</style>")
	}
}
ncaCookieAcceptCheck();
</script>');

ob_start();
?>

<style>
.nca-cookiesaccept-line {    
	box-sizing: border-box !important;
	margin: 0 !important; 
	border: none !important;
    width: 100% !important;
    min-height: 10px !important;
    max-height: 250px !important;
    display: block;
	clear: both !important; 
  
	padding: <?=$arResult["PADDINGSIZE"]?>px !important;
	position: fixed;

	<?if ($arResult["TOPORBOTTOM"] == "1") :?>
		top: 0px !important;
	<?else :?>
		bottom: 0px !important;
	<?endif;?>

	opacity: <?=$arResult["BTNOPACITY"]/100?>;
	transform: translateY(<?=$arResult["TOP"]?>%);
	z-index: <?=$arResult["ZINDEX"]?>; 
}

.nca-cookiesaccept-line > div {
	display: flex; 
	align-items: center;
}
.nca-cookiesaccept-line > div > div {
	padding-left: 5%;
	padding-right: 5%;
}
.nca-cookiesaccept-line a {
	color: inherit;
	text-decoration:underline;
}

@media screen and (max-width:767px) {
	.nca-cookiesaccept-line > div > div {
		padding-left: 1%;
		padding-right: 1%;
	}
}
</style>
	<?
	$css = preg_replace("/\s+/", " ", ob_get_contents());
	ob_end_clean();

	$APPLICATION->AddHeadString($css);
}
?>