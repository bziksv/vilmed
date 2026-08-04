<?
CJSCore::RegisterExt("ag_chatgpt_base", [
	"lang" => "/bitrix/modules/arturgolubev.chatgpt/lang/".LANGUAGE_ID."/js/jscore.php",
	"js" => ["/bitrix/js/arturgolubev.chatgpt/js/script.js"],
	"css" => ["/bitrix/js/arturgolubev.chatgpt/css/styles.css"],
]);

CJSCore::RegisterExt("ag_chatgpt_tasks", [
	"js" => ["/bitrix/js/arturgolubev.chatgpt/js/tasks.js"],
]);