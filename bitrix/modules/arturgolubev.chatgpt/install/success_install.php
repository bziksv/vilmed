<?if(!check_bitrix_sessid()) return;?>

<?echo CAdminMessage::ShowNote(GetMessage("ARTURGOLUBEV_CHATGPT_INSTALL_SUCCESS", array("#MOD_NAME#"=>GetMessage("arturgolubev.chatgpt_MODULE_NAME"))));?>

<h3><?=GetMessage("ARTURGOLUBEV_CHATGPT_WHAT_DO");?></h3>

<div><?=GetMessage("ARTURGOLUBEV_CHATGPT_WHAT_DO_TEXT");?></div>