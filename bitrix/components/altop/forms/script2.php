<?define("NOT_CHECK_PERMISSIONS", true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Context;

if(!Loader::IncludeModule("iblock"))
    return;

if (!check_bitrix_sessid()) {
    http_response_code(403);
    die();
}

Loc::loadMessages(__FILE__);

$allowedExtensions = ["jpg", "jpeg", "gif", "bmp", "png", "doc", "txt", "rtf", "pdf"];

$request = Context::getCurrent()->getRequest();
$files = $request->getFileList();
$html = "";

for ($i = 0; $i < count($files); $i++) {
    $id_d = rand();
    $value = $request->getFile("fil".$i);
    if (empty($value) || !is_array($value) || empty($value["tmp_name"])) {
        continue;
    }

    $ext = strtolower(pathinfo($value["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        $html .= "<div id='file_wrap_".$id_d."' class='user-fileinput-item'>".Loc::getMessage('ERROR_LOAD_FILE')."
                <span class='user-btn-del' id='del_".$id_d."' onclick='delBlock(this)'>&nbsp;</span></div>";
        continue;
    }

    $arr_file = Array(
        "name" => $value["name"],
        "size" => $value["size"],
        "tmp_name" => $value["tmp_name"],
        "type" => $value["type"],
        "old_file" => "",
        "del" => "Y",
        "MODULE_ID" => "iblock");
    $fid = CFile::SaveFile($arr_file, "forms");
    if($fid > 0){
        $html .= "<div id='file_wrap_".$id_d."' class='user-fileinput-item'>
               <span class='user-btn-del' id='del_".$id_d."' onclick='delBlock(this)'>&nbsp;</span>
                <div class='user-fileinput-item-name'>".htmlspecialcharsbx($value["name"])."</div>
                <input type='hidden' name='PHOTO[n".$id_d."][id]' value='".(int)$fid."'>
                <input type='hidden' name='PHOTO[n".$id_d."][tmp_name]' value='".htmlspecialcharsbx(CFile::GetPath($fid))."'>
               </div>";
    } else {
        $html .= "<div id='file_wrap_".$id_d."' class='user-fileinput-item'>".Loc::getMessage('ERROR_LOAD_FILE')."
                <span class='user-btn-del' id='del_".$id_d."' onclick='delBlock(this)'>&nbsp;</span></div>";
    }
}

echo $html;

?>
