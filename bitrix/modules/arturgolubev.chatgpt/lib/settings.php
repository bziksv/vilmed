<?
namespace Arturgolubev\Chatgpt; //2.8.0

use \Bitrix\Main\Loader,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option;

class Settings {
	static function getSites(){
		$result = [];
		
		$rsSites = \CSite::GetList($by="sort", $order="asc", []);
		while($arRes = $rsSites->Fetch()){
			$result[] = array(
				"ID" => $arRes["ID"],
				"NAME" => $arRes["NAME"],
			);
		}
		
		return $result;
	}

	static function getModuleUpdatesInfo($module_id){
		$obCache = new \CPHPCache();
		if($obCache->InitCache(300, 'ag_module_getdata'.$module_id, '/check_mp/ag_module_getdata')){
			$vars = $obCache->GetVars();
			$fullModuleInfo = $vars['fullModuleInfo'];
		}elseif($obCache->StartDataCache()){
			require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/update_client_partner.php");
			$fullModuleInfo = \CUpdateClientPartner::GetUpdatesList($errorMessage, LANG, "Y", [$module_id], ["fullmoduleinfo" => "Y"]);
			if($fullModuleInfo["ERROR"] || $errorMessage){
				$obCache->AbortDataCache();
			}else{
				$obCache->EndDataCache(['fullModuleInfo' => $fullModuleInfo]);
			}
		}

		$marketModuleInfo = false;
		if(is_array($fullModuleInfo) && is_array($fullModuleInfo['MODULE'])){
			foreach($fullModuleInfo["MODULE"] as $k=>$v){
				if($v["@"]["ID"] == $module_id){
					$marketModuleInfo = $v['@'];
					// $marketModuleInfo['UPDATES'] = $v['#'];

					if(is_array($v["#"])){
						$marketModuleInfo['HAVE_UPDATES'] = 1;
					}else{
						$marketModuleInfo['HAVE_UPDATES'] = 0;
					}
				}
			}
		}

		return $marketModuleInfo;
	}

	static function checkUpdatesAgent($module_id){
		$agentName = '\\'.__NAMESPACE__.'\\Settings::checkModuleUpdates("'.$module_id.'");';

		$rsAgent = \CAgent::GetList([], ['NAME' => $agentName]);
		if(!$rsAgent->Fetch()){
			\CAgent::AddAgent(
				$agentName,
				$module_id,
				'N',
				86400*30,
				date('d.m.Y H:i:s', strtotime('+1 minutes')), "Y", date('d.m.Y H:i:s', strtotime('+1 minutes')), 100
			);
		}
	}
	
	static function checkModuleUpdates($module_id){
		if(Loader::includeModule($module_id)){
			$marketModuleInfo = self::getModuleUpdatesInfo($module_id);
			if(is_array($marketModuleInfo)){
				if($marketModuleInfo['HAVE_UPDATES']){
					IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/install/index.php");
					IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$module_id."/options.php");

					$messageCode = ToUpper(str_replace('.', '_', $module_id));
					$messageCode .= ($marketModuleInfo['UPDATE_END'] == 'Y' ? '_AUTOCHECK_UPDATES_NOTIFY2' : '_AUTOCHECK_UPDATES_NOTIFY');

					\CAdminNotify::Add([
						"MESSAGE" => Loc::getMessage($messageCode, ["#MODULE_NAME#" => Loc::getMessage($module_id.'_MODULE_NAME')]),
						"TAG" => $module_id.'_autocheck_updates',
						"MODULE_ID" => $module_id,
						'ENABLE_CLOSE' => 'Y'
					]);
				}
			}
		}

		return '\\'.__NAMESPACE__.'\\Settings::checkModuleUpdates("'.$module_id.'");';
	}

	static function checkModuleDemoEx($module_id){
		$moduleIdExplode = explode('.', $module_id);
		$messageCode = 'ARTURGOLUBEV_'.ToUpper($moduleIdExplode[1]).'_CHECKDEMO_';

		$moduleStatus = Loader::includeSharewareModule($module_id);

		$messageReplace = [
			'#module_id#' => $module_id,
			'#date_diff#' => '?',
			'#date_to#' => '?',
		];

		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest(); 
		$rq = $request->toArray();
		if($rq['checkperiod'] == 'Y'){
			if(!check_bitrix_sessid()){
				return [
					'type' => 'session_expire',
					'status' => 'exit',
					'message' => '',
				];
			}

			$message = '';
			
			$marketModuleInfo = self::getModuleUpdatesInfo($module_id);
			if(is_array($marketModuleInfo)){
				$messageReplace['#date_diff#'] = ceil((strtotime($marketModuleInfo['DATE_TO']) - strtotime(date('d.m.Y'))) / 86400);
				$messageReplace['#date_to#'] = $marketModuleInfo['DATE_TO'];

				if($messageReplace['#date_diff#'] < 1){
					$messageReplace['#date_diff#'] = 1;
				}
			}

			if(!$message && $moduleStatus == Loader::MODULE_DEMO){
				$message = Loc::getMessage($messageCode.'DEMO', $messageReplace);
			}
			
			if(!$message && is_array($marketModuleInfo) && $marketModuleInfo['UPDATE_END'] == 'Y'){
				if($marketModuleInfo['HAVE_UPDATES']){
					$message = Loc::getMessage($messageCode.'PROD_UPDATES', $messageReplace);
				}else{
					$message = Loc::getMessage($messageCode.'PROD', $messageReplace);
				}
			}

			if(!$message && is_array($marketModuleInfo) && $marketModuleInfo['HAVE_UPDATES']){
				$message = Loc::getMessage($messageCode.'HAVE_UPDATES', $messageReplace);
			}

			Option::set($module_id, 'license_message', $message);

			return [
				'type' => 'check_success',
				'status' => 'exit',
				'message' => $message,
			];
		}

		if(!$moduleStatus || $moduleStatus == Loader::MODULE_DEMO_EXPIRED){
			$demo_over = 1;
			$showMessage = Loc::getMessage($messageCode.'EXPIRED', $messageReplace);
		}else{
			$showMessage = Option::get($module_id, 'license_message');
		}

		$html = '
			<div class="module-license-info js-module-license-info" '.($showMessage ? '' : 'style="display: none;"').'>
				<div class="module-license-info-icon"></div>
				<div class="module-license-info-text js-module-license-info-text">
					'.($showMessage ? $showMessage : '').'
				</div>
			</div>
		';

		if(!$demo_over){
			$html .= '<script>
				BX.ready(function(){
					var url = document.location.pathname + document.location.search;

					BX.ajax({
						method: "POST",
						url: url,
						data: {checkperiod: "Y", sessid: BX.bitrix_sessid()},
						dataType: "html",
						timeout: 30,
						async: true,
					
						processData: false,
						scriptsRunFirst: false,
						emulateOnload: false,
						start: true,
						cache: false,
						
						onsuccess: function(data){
							var obj = JSON.parse(data);
							// console.log("GetLicense SUCCESS", data, obj);
							
							var el = document.querySelector(".js-module-license-info"), elText = document.querySelector(".js-module-license-info-text");
							if(obj.message){
								elText.innerHTML = obj.message;
								el.style.display = "flex";
							}else{
								el.style.display = "none";
							}
						},
						onfailure: function(){
							// console.log("GetLicense FAILED");
						},
					});
				});
			</script>';
		}

		echo $html;
	}

	static function checkPhpVerison($r1, $mess_name){
		$arVersion = explode('.', phpversion());

		if($arVersion[0] < $r1){
			\CAdminMessage::ShowMessage(["MESSAGE" => Loc::getMessage($mess_name, ['#req#' => $r1.'.0.0', '#cur#' => phpversion()]), 'HTML' => true, 'TYPE' => 'ERROR']);
		}
	}

	static function checkModuleDemo($module_id, $text){
		if(!Loader::includeModule($module_id)){
			\CAdminMessage::ShowMessage(["MESSAGE" => $text, 'HTML' => true, 'TYPE' => 'ERROR']);
		}
	}
	
	static function showInitUI(){
		if (class_exists('\Bitrix\Main\UI\Extension')) {
		   \Bitrix\Main\UI\Extension::load("ui.hint");
		   ?>
			<script>BX.ready(function() {BX.UI.Hint.init(BX('adm-workarea')); });</script>
			<style>.simple-hint {display: none !important;}</style>
			<?
		}
		?>
		<style>
			.module-license-info {
				padding: 15px 15px;
				margin-top: 20px;
				margin-bottom: 20px;
				background-color: #fffdde;
				border-radius: 5px;
				display: flex;
				align-items: center;
				font-size: 14px;
				line-height: 1.3;
			}
			.module-license-info-icon {
				width: 33px;
				min-width: 33px;
				height: 33px;
				background: url(/bitrix/panel/main/images/bx-admin-sprite-big1.png) no-repeat 0 -1445px;
				margin-right: 10px;
			}

			#bx-admin-prefix .adm-detail-content-item-block table {
				font-size: 14px;
			}
			#bx-admin-prefix .adm-workarea .heading td {
				font-size: 16px;
			}
			#bx-admin-prefix form .adm-info-message {
				color:#111;
				background:#fff;
				border: 1px solid #bbb;
				padding: 10px 15px;
				margin-top: 0;
				text-align: left;
			}
			#bx-admin-prefix .adm-info-message-red .adm-info-message {
				color: #111;
			}
			#bx-admin-prefix form .adm-detail-content-wrap select, #bx-admin-prefix form  .adm-detail-content-wrap input[type=text] {
				max-width: 350px;
				min-width: 200px;
			}
			#bx-admin-prefix .help_note_wrap .adm-info-message {
				display: block;
				color: #111;
				font-size: 14px;
				line-height: 20px;
				background: #fff;
				border-color: #f5f9f9;
			}
			#bx-admin-prefix .help_note_wrap .adm-info-message .title {
				font-weight: bold;
				font-size: 15px;
			}
			#bx-admin-prefix .help_note_wrap .adm-info-message p {
				margin: 5px 0;
			}
			
			#bx-admin-prefix option:checked {
				background-color: #0078d7;
				color: #fff;
			}
		</style>
		<?
	}
	
	static function AdmSettingsSaveOption($module_id, $arOption)
	{
		if(!is_array($arOption) || isset($arOption["note"]))
			return false;

		if($arOption[3][0] == "statictext" || $arOption[3][0] == "statichtml")
			return false;

		$arControllerOption = \CControllerClient::GetInstalledOptions($module_id);

		if(isset($arControllerOption[$arOption[0]]))
			return false;

		$name = $arOption[0];
		
		if(!isset($_REQUEST[$name])){
			if($arOption[3][0] <> 'checkbox' && $arOption[3][0] <> "multiselectbox"){
				return false;
			}
		}

		$val = $_REQUEST[$name];

		if($arOption[3][0] == "checkbox" && $val != "Y")
			$val = "N";
		if($arOption[3][0] == "multiselectbox"){
			$val = is_array($val) ? $val : array($val);
			foreach($val as $k => $v){
				if($v && !isset($arOption[3][1][$v])){
					unset($val[$k]);
				}
			}
			$val = implode(",", $val);
		}

		Option::set($module_id, $name, $val);

		return null;
	}
	
	static function showSettingsList($module_id, $arOptions, $tab){
		$settingList = $arOptions[$tab["OPTIONS"]];
		
		if(!is_array($settingList)) return 0;
		foreach ($settingList as $Option) {
			// echo '<pre>'; print_r($Option); echo '</pre>';
			if(!is_array($Option)){
				?><tr class="heading"><td colspan="2"><?=$Option?></td></tr><?
			}elseif(isset($Option["note"])){
				?><tr><td colspan="2" align="center">
					<?echo BeginNote('align="center"');?>
					<?=$Option["note"]?>
					<?echo EndNote();?>
				</td></tr>
			<?}elseif($Option["3"]["0"] == 'statictext' || $Option["3"]["0"] == 'statichtml'){
				?>
				<tr>
					<td class="adm-detail-valign-top adm-detail-content-cell-l" width="50%">
						<?=$Option["1"]?>
					</td>
					<td class="adm-detail-content-cell-r" width="50%">
						<?=$Option["2"]?>
						<?if($Option[5]):?>
							<div style="margin-top: 4px; font-size: 13px;"><?=$Option[5]?></div>
						<?endif;?>
					</td>
				</tr>
				<?
			}elseif($Option["3"]["0"] == 'calendar'){
				\CJSCore::Init(['date']);
				$value = Option::get($module_id, $Option[0],  $Option[2]);
				?>
					<tr>
						<td class="adm-detail-content-cell-l" width="50%"><?=$Option["1"]?><a name="opt_<?=$Option["0"]?>"></a></td>
						<td class="adm-detail-content-cell-r" width="50%">
							<?$pickID = "pick_date_".$Option["0"];?>
							
							<input autocomplete="off" type="text" id="<?=$pickID?>" size="" maxlength="255" value="<?=htmlspecialcharsbx($value)?>" name="<?=htmlspecialcharsbx($Option["0"])?>" onclick="BX.calendar({node: this, field: this, bTime: false});">
						</td>
					</tr>
				<?
			}elseif($Option["3"]["0"] == 'datetime'){
				\CJSCore::Init(['date']);
				$value = Option::get($module_id, $Option[0],  $Option[2]);
				?>
					<tr>
						<td class="adm-detail-content-cell-l" width="50%"><?=$Option["1"]?><a name="opt_<?=$Option["0"]?>"></a></td>
						<td class="adm-detail-content-cell-r" width="50%">
							<?$pickID = "pick_date_".$Option["0"];?>
							
							<input autocomplete="off" type="text" id="<?=$pickID?>" size="" maxlength="255" value="<?=htmlspecialcharsbx($value)?>" name="<?=htmlspecialcharsbx($Option["0"])?>" onclick="BX.calendar({node: this, field: this, bTime: true});">
						</td>
					</tr>
				<?
			}elseif($Option["3"]["0"] == 'colorbox'){
				\CJSCore::Init(['color_picker']);
				$value = Option::get($module_id, $Option[0]);
				?>
					<tr>
						<td class="adm-detail-content-cell-l" width="50%"><?=$Option["1"]?><a name="opt_<?=$Option["0"]?>"></a></td>
						<td class="adm-detail-content-cell-r" width="50%">
							<?$pickID = "pick_color_".$Option["0"];?>
							
							<input autocomplete="off" type="text" id="<?=$pickID?>" size="" maxlength="255" value="<?=htmlspecialcharsbx($value)?>" name="<?=htmlspecialcharsbx($Option["0"])?>">
							
							<script type="text/javascript">
							BX.ready(function() {
								var element = BX('<?=$pickID?>');

								BX.bind(element, 'focus', function () {
									new BX.ColorPicker({
										bindElement: element,
										defaultColor: '',
										allowCustomColor: true,
										onColorSelected: function (item) {
											element.value = item;
										},
										popupOptions: {
											angle: true,
											autoHide: true,
											closeByEsc: true,
										}
									}).open();
								});
							  });
							</script>
						</td>
					</tr>
				<?
			}elseif($Option["3"]["0"] == 'filepath'){
				$value = Option::get($module_id, $Option[0], $Option[2]);
				?>
					<tr>
						<td class="adm-detail-content-cell-l" width="50%"><?=$Option["1"]?><a name="opt_<?=$Option["0"]?>"></a></td>
						<td class="adm-detail-content-cell-r" width="50%">
							<?$opt = strtolower("filepath_".$Option["0"]);?>
							
							<?
							\CAdminFileDialog::ShowScript(Array(
								'event' => 'BX_FD_'.$opt,
								'arResultDest' => Array('FUNCTION_NAME' => 'BX_FD_ONRESULT_'.$opt),
								'arPath' => [],
								'select' => 'F',
								'operation' => 'O',
								'showUploadTab' => true,
								'showAddToMenuTab' => false,
								'fileFilter' => '',
								'allowAllFiles' => true,
								'SaveConfig' => true
							));
							?>
							
							<input autocomplete="off" type="text" id="visual_inp_<?=$opt?>" size="" maxlength="512" value="<?=htmlspecialcharsbx($value)?>" name="<?=htmlspecialcharsbx($Option["0"])?>">
							<input value="<?=Loc::getMessage("MAIN_SELECT")?>" type="button" onclick="window.BX_FD_<?=$opt?>();" />
							
							<script>
								BX.ready(function() {
									if (BX("bx_fd_input_<?=($opt)?>"))
										BX("bx_fd_input_<?=($opt)?>").onclick = window.BX_FD_<?=($opt)?>;
								});
								
								window.BX_FD_ONRESULT_<?=$opt?> = function(filename, filepath)
								{
									var oInput = BX("visual_inp_<?=$opt?>");
									if (typeof filename == "object")
										oInput.value = filename.src;
									else
										oInput.value = (filepath + "/" + filename).replace(/\/\//ig, '/');
								}
							</script>
						</td>
					</tr>
				<?
			}else{
				// __AdmSettingsDrawRow($module_id, $Option);
				self::drawRow($module_id, $Option);
			}
		}
	}
	
	static function drawRow($module_id, $Option)
	{
		$arControllerOption = \CControllerClient::GetInstalledOptions($module_id);

		if ($Option[0] != ""){
			$val = Option::get($module_id, $Option[0], $Option[2]);
		}else{
			$val = $Option[2];
		}
		?>
			<?$rid = preg_replace('/[^a-zA-Z0-9_]+/i', '', $Option[0]);?>
			<tr id="input_row_<?=$rid?>">
				<?self::drawLable($Option);?>
				<?self::drawInput($Option, $arControllerOption, $Option[0], $val);?>
			</tr>
		<?
	}
	
	static function drawInput($Option, $arControllerOption, $fieldName, $val)
	{
		$type = $Option[3];
		$disabled = array_key_exists(4, $Option) && $Option[4] == 'Y' ? ' disabled' : '';
		$bottom_text = array_key_exists(5, $Option) ? $Option[5] : '';

		$inputID = preg_replace('/[^a-zA-Z0-9_]+/i', '', htmlspecialcharsbx($fieldName));
		?><td width="50%"><?
		if($type[0]=="checkbox"):
			?><input id="<?=$inputID?>" autocomplete="off" type="checkbox" <?if(isset($arControllerOption[$Option[0]]))echo ' disabled title="'.Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT").'"';?> name="<?=htmlspecialcharsbx($fieldName)?>" value="Y"<?if($val=="Y")echo" checked";?><?=$disabled?><?if($type[2]<>'') echo " ".$type[2]?>><?
		elseif($type[0]=="text" || $type[0]=="password"):
			$maxlength = ($type['maxlength'] > 0) ? $type['maxlength'] : 512;
		
			?><input id="<?=$inputID?>" autocomplete="off" type="<?echo $type[0]?>"<?if(isset($arControllerOption[$Option[0]]))echo ' disabled title="'.Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT").'"';?> size="<?echo $type[1]?>" maxlength="<?=$maxlength?>" value="<?echo htmlspecialcharsbx($val)?>" name="<?=htmlspecialcharsbx($fieldName)?>"<?=$disabled?><?=($type[0]=="password" || $type["noautocomplete"]? ' autocomplete="new-password"':'')?>><?
		elseif($type[0]=="selectbox"):
			$arr = $type[1];
			if(!is_array($arr))
				$arr = [];
			?><select id="<?=$inputID?>" autocomplete="off" name="<?=htmlspecialcharsbx($fieldName)?>" <?if(isset($arControllerOption[$Option[0]]))echo ' disabled title="'.Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT").'"';?> <?=$disabled?>><?
			foreach($arr as $key => $v):
				?><option value="<?echo $key?>"<?if($val==$key)echo" selected"?>><?echo htmlspecialcharsbx($v)?></option><?
			endforeach;
			?></select><?
		elseif($type[0]=="multiselectbox"):
			$arr = $type[1];
			if(!is_array($arr))
				$arr = [];
			$arr_val = explode(",",$val);
			?><select id="<?=$inputID?>" autocomplete="off" size="5" <?if(isset($arControllerOption[$Option[0]]))echo ' disabled title="'.Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT").'"';?> multiple name="<?=htmlspecialcharsbx($fieldName)?>[]"<?=$disabled?>><?
			foreach($arr as $key => $v):
				?><option value="<?echo $key?>"<?if(in_array($key, $arr_val)) echo " selected"?>><?echo htmlspecialcharsbx($v)?></option><?
			endforeach;
			?></select><?
		elseif($type[0]=="textarea"):
			?><textarea id="<?=$inputID?>" autocomplete="off" <?if(isset($arControllerOption[$Option[0]]))echo ' disabled title="'.Loc::getMessage("MAIN_ADMIN_SET_CONTROLLER_ALT").'"';?> rows="<?echo $type[1]?>" cols="<?echo $type[2]?>" name="<?=htmlspecialcharsbx($fieldName)?>"<?=$disabled?>><?echo htmlspecialcharsbx($val)?></textarea><?
		elseif($type[0]=="html"):
			echo self::showHtmlEditor($fieldName, $val, $Option[3][1]);
		endif;?>
			<?if($bottom_text):?>
				<div style="margin-top: 4px; font-size: 13px;"><?=$bottom_text?></div>
			<?endif;?>
		</td><?
	}
	
	static function showHtmlEditor($name, $value, $cfg){
		if(Loader::includeModule("fileman")){
			$id  = preg_replace("/[^a-z0-9]/i" ,'' ,$name);
			
			if(!is_array($cfg) || empty($cfg))
			{
				$cfg = array('Bold','Italic','Underline','RemoveFormat','CreateLink','DeleteLink','Image','Video','BackColor','ForeColor','JustifyLeft','JustifyCenter','JustifyRight','JustifyFull','InsertOrderedList','InsertUnorderedList','Outdent','Indent','StyleList','HeaderList','FontList','FontSizeList');
			}
			
			ob_start();
			echo   '<input type="hidden" name="'  .  $name  .  '[TYPE]" value="html">' ;
			$LHE  =  new  \CLightHTMLEditor;
			$LHE ->Show(array(
				'id'  =>  $id ,
				'width'  =>  '100%' ,
				'height'  =>  '250px' ,
				'inputName'  =>  $name ,
				'content'  => htmlspecialchars_decode($value),
				'bUseFileDialogs'  =>  false ,
				'bFloatingToolbar'  =>  false ,
				'bArisingToolbar'  =>  false ,
				'toolbarConfig'  =>  $cfg,
			));
			$s  = ob_get_contents();
			ob_end_clean();
			
			return $s;
		}else{
			return '<textarea name="'.$name.'" style="width: 100%; height: 250px;">'.htmlspecialcharsbx($value).'</textarea>';
		}
	}
	
	
	static function drawLable($Option){
		$type = $Option[3];
		?>
			<td<?if ($type[0]=="multiselectbox" || $type[0]=="html" || $type[0]=="textarea" || $type[0]=="statictext" ||
			$type[0]=="statichtml") echo ' class="adm-detail-valign-top"'?> width="50%"><?
			if ($type[0]=="checkbox")
				echo "<label for='".htmlspecialcharsbx($Option[0])."'>".$Option[1]."</label>";
			else
				echo $Option[1];
			?><a name="opt_<?=htmlspecialcharsbx($Option[0])?>"></a></td>
		<?
	}
}