<?
$APPLICATION->IncludeComponent(
	"altop:geolocation",
	"city",
	array(
		"IBLOCK_TYPE" => "content",
		"IBLOCK_ID" => "7",
		"SHOW_CONFIRM" => "Y",
		"SHOW_DEFAULT_LOCATIONS" => "Y",
		"SHOW_TEXT_BLOCK" => "Y",
		"SHOW_TEXT_BLOCK_TITLE" => "Y",
		"TEXT_BLOCK_TITLE" => "",
		"CACHE_TYPE" => "N",
		"CACHE_TIME" => "36000000",
		"COOKIE_TIME" => "36000000",
		"COMPONENT_TEMPLATE" => ".default",
		// VILMED: AUTO клал блок в статику композита без cookie → JS «определить город»
		// снова показывал CityConfirm на каждом заходе. DYNAMIC читает cookie на хите.
		"COMPOSITE_FRAME_MODE" => "Y",
		"COMPOSITE_FRAME_TYPE" => "DYNAMIC",
		"MODE_OPERATION" => "YANDEX",
	),
	false
);?>
