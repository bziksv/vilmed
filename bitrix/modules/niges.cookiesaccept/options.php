<?php

IncludeModuleLangFile(__FILE__);
if (!$USER->IsAdmin()) {
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

$APPLICATION->SetTitle(GetMessage("Nastrojka_resheniya"));

require ($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$moduleCode = 'cookiesaccept';



	$l = CLang::GetList($by = "sort", $order = "asc");
	while($l_arr = $l->Fetch()) {
		$arSites[$l_arr['LID']] = $l_arr;
	}

	if (count($arSites) == 1) {
		foreach ($arSites as $id => $arSite) {
			$_REQUEST["SITE_ID"] = $arSite["LID"];
		}
	}

	if (isset($_REQUEST["SITE_ID"])) {

		$siteId = htmlspecialcharsbx($_REQUEST["SITE_ID"]);
		if ($_REQUEST['set_'.$moduleCode.'_props']) {


			if (isset($_REQUEST["ACTIVE"])) {
				COption::SetOptionString("niges.cookiesaccept","ACTIVE",htmlspecialcharsbx($_REQUEST["ACTIVE"]),false,$siteId);
			} else {
				COption::SetOptionString("niges.cookiesaccept","ACTIVE",'N',false,$siteId);
			}
			if (isset($_REQUEST["NCA_PROP"])) {
				foreach ($_REQUEST["NCA_PROP"] as $name => $value) {
					COption::SetOptionString("niges.cookiesaccept",htmlspecialcharsbx($name),htmlspecialcharsbx($value),false,$siteId);
				}
			}
			if (!isset($_REQUEST["NCA_PROP"]["HIDE_MOBILE"])) {
				COption::SetOptionString("niges.cookiesaccept",'HIDE_MOBILE','N',false,$siteId);
			}
			if (!isset($_REQUEST["NCA_PROP"]["HIDE_PC"])) {
				COption::SetOptionString("niges.cookiesaccept",'HIDE_PC','N',false,$siteId);
			}
			if (!isset($_REQUEST["NCA_PROP"]["FIXED"])) {
				COption::SetOptionString("niges.cookiesaccept",'FIXED','N',false,$siteId);
			}
            if (!isset($_REQUEST["NCA_PROP"]["NOINDEX"])) {
                COption::SetOptionString("niges.cookiesaccept",'NOINDEX','N',false,$siteId);
			}

            if (!isset($_REQUEST["NCA_PROP"]["BTNSHADOW"])) {
                COption::SetOptionString("niges.cookiesaccept",'BTNSHADOW','N',false,$siteId);
            }


			if (isset($_REQUEST["NCA_SOCNET"])) {
				foreach ($_REQUEST["NCA_SOCNET"] as $name => $value) {
					COption::SetOptionString("niges.cookiesaccept",htmlspecialcharsbx($name),htmlspecialcharsbx($value),false,$siteId);
				}
			}
			if (isset($_REQUEST["NCA_SOCNET_SORT"])) {
				COption::SetOptionString("niges.cookiesaccept",'SORT_ICO',serialize($_REQUEST["NCA_SOCNET_SORT"]),false,$siteId);
			}
		}

		// $arSort = @unserialize(COption::GetOptionString("niges.cookiesaccept", "SORT_ICO", false, $siteId));
		// foreach ($arSocNet as $name => $arItem) {
		// 	$arSocNet[$name]['SORT'] = $arSort[$name];
		// }

		// if (!function_exists('socSort')) {
		// 	function socSort($a, $b) {
		// 	    if ($a['SORT'] == $b['SORT']) {
		// 	        return 0;
		// 	    }
		// 	    return ($a['SORT'] < $b['SORT']) ? -1 : 1;
		// 	}
		// }

		// uasort($arSocNet, "socSort");
	}


	?>
	<div class="adm-detail-content-wrap">
		<form method="post" action="">
			<div class="adm-detail-content" id="edit1">
				<div class="adm-detail-title"><?=GetMessage('Nastrojka_resheniya')?></div>
				<div class="adm-detail-content-item-block">
					<table class="adm-detail-content-table edit-table" id="edit1_edit_table">
						<tbody>
							<?if (!isset($siteId)) :?>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										 <?=GetMessage('Vyberite_sajt')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<select name="SITE_ID">
											<? foreach ($arSites as $lid => $arVal) :?>
												<option
													value="<?=$arVal["LID"]?>"
													<?if ($siteId == $arVal["LID"]) :?> selected <?endif;?>
												>
													[<?=$arVal["LID"]?>] <?=$arVal["NAME"];?>
												</option>
											<?endforeach;?>
										</select>
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<div class="adm-info-message-wrap">
											<div class="adm-info-message">
												<?=GetMessage('Najmite_sohranit')?>
											</div>
										</div>
									</td>
								</tr>
							<?else:?>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Sajt')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="hidden" name="SITE_ID" value="<?=htmlspecialcharsbx($_REQUEST["SITE_ID"])?>">
										<b>[<?=$siteId?>] <?=$arSites[$_REQUEST["SITE_ID"]]['NAME']?></b>
									</td>
								</tr>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Aktivnost')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="checkbox" name="ACTIVE" value="Y" <?if (COption::GetOptionString("niges.".$moduleCode, "ACTIVE", false, $siteId) == 'Y'):?>checked<?endif;?>>
									</td>
								</tr>


								<tr class="heading">
									<td colspan="2"><?=GetMessage('Soderzh')?></td>
								</tr>


								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Osn_text_ver')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="text" value="<?=COption::GetOptionString("niges.".$moduleCode, "TEXTVER", "1", $siteId)?>" name="NCA_PROP[TEXTVER]" >
									</td>
								</tr>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Osn_text')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<textarea rows="5" cols="100" name="NCA_PROP[MAINTEXT]" ><?echo COption::GetOptionString("niges.".$moduleCode, "MAINTEXT", GetMessage('main_text_ex'), $siteId) ?></textarea>
									</td>
								</tr>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Osn_text_btn')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="text" value="<?=COption::GetOptionString("niges.".$moduleCode, "TEXTBTN", GetMessage('Osn_text_btn_val'), $siteId)?>" name="NCA_PROP[TEXTBTN]" >
									</td>
								</tr>



								<tr class="heading">
									<td colspan="2"><?=GetMessage('Obschie_nastrojki')?></td>
								</tr>


								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Polozhenie')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
											<label style="margin-top: 5px; display: block; margin-right: 15px;">
												 <input type="radio" value="1" name="NCA_PROP[TOPORBOTTOM]" <?if (COption::GetOptionString("niges.".$moduleCode, "TOPORBOTTOM", false, $siteId) == "1") :?>checked<?endif;?>>
													 <?=GetMessage('Polozhenie_sv')?>
											</label>
											<label style="margin-top: 5px; display: block; margin-right: 15px;">
												 <input type="radio" value="2" name="NCA_PROP[TOPORBOTTOM]" <?if (COption::GetOptionString("niges.".$moduleCode, "TOPORBOTTOM", false, $siteId) == "2" || COption::GetOptionString("niges.".$moduleCode, "TOPORBOTTOM", false, $siteId) == "") :?>checked<?endif;?>>
													 <?=GetMessage('Polozhenie_sn')?>
											</label>  
									</td>
								</tr>
								<!-- <tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Otstup_sverhu')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="number" min="-100" max="100" value="<?=COption::GetOptionString("niges.".$moduleCode, "TOP", "0", $siteId)?>" name="NCA_PROP[TOP]" >
									</td>
								</tr> 
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Zakrepit_polozhenie')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="checkbox" name="NCA_PROP[FIXED]" value="Y" <?if (COption::GetOptionString("niges.".$moduleCode, "FIXED", false, $siteId) == 'Y'):?>checked<?endif;?>>
									</td>
								</tr>-->
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Zindex')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="number" min="-1" max="9999999" value="<?=COption::GetOptionString("niges.".$moduleCode, "ZINDEX", 99999, $siteId)?>" name="NCA_PROP[ZINDEX]" >
									</td>
								</tr>

								<tr style="display:none">
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Skryvat_na_mobilnyx_ustrojstvax')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="checkbox" name="NCA_PROP[HIDE_MOBILE]" value="Y" <?if (COption::GetOptionString("niges.".$moduleCode, "HIDE_MOBILE", false, $siteId) == 'Y'):?>checked<?endif;?>>
									</td>
								</tr>
								<tr style="display:none">
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Skryvat_na_pk')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="checkbox" name="NCA_PROP[HIDE_PC]" value="Y" <?if (COption::GetOptionString("niges.".$moduleCode, "HIDE_PC", false, $siteId) == 'Y'):?>checked<?endif;?>>
									</td>
								</tr> 


								<tr class="heading">
									<td colspan="2"><?=GetMessage('Nastrojki_vneshnego_vida')?></td>
								</tr>

								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Style')?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-1.png" alt="">
												 <br>
												 <input type="radio" value="1" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "1" || COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "") :?>checked<?endif;?>>
													 <?=GetMessage('Style_1')?>
											</label>
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-2.png" alt="">
												 <br>
												 <input type="radio" value="2" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "2") :?>checked<?endif;?>>
													 <?=GetMessage('Style_2')?>
											</label> 

											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-3.png" alt="">
												 <br>
												 <input type="radio" value="3" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "3") :?>checked<?endif;?>>
													 <?=GetMessage('Style_3')?>
											</label>
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-4.png" alt="">
												 <br>
												 <input type="radio" value="4" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "4") :?>checked<?endif;?>>
													 <?=GetMessage('Style_4')?>
											</label>
											
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-5.png" alt="">
												 <br>
												 <input type="radio" value="5" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "5") :?>checked<?endif;?>>
													 <?=GetMessage('Style_5')?>
											</label>
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-6.png" alt="">
												 <br>
												 <input type="radio" value="6" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "6") :?>checked<?endif;?>>
													 <?=GetMessage('Style_6')?>
											</label>
											
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-7.png" alt="">
												 <br>
												 <input type="radio" value="7" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "7") :?>checked<?endif;?>>
													 <?=GetMessage('Style_7')?>
											</label>
											<label style="margin-top: 15px; display: block; margin-right: 15px;">
												<img style="height: 62px; border: 1px solid #a9a9a9; border-radius: 4px;" src="/bitrix/components/niges/<?=$moduleCode?>/templates/.default/images/style-option-8.png" alt="">
												 <br>
												 <input type="radio" value="8" name="NCA_PROP[SETSTYLE]" <?if (COption::GetOptionString("niges.".$moduleCode, "SETSTYLE", false, $siteId) == "8") :?>checked<?endif;?>>
													 <?=GetMessage('Style_8')?>
											</label>
									</td>
								</tr>

								<tr style="display:none">
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Effekt_pri_navedenii')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<select name="NCA_PROP[ANIMATION]">
											<option value="none" <?=(COption::GetOptionString("niges.".$moduleCode, "ANIMATION", false, $siteId) == 'none') ? 'selected': ''; ?>><?=GetMessage('Bez_animacii')?></option>
											<option value="shake" <?=(COption::GetOptionString("niges.".$moduleCode, "ANIMATION", false, $siteId) == 'shake') ? 'selected': ''; ?>><?=GetMessage('Podergivanie')?></option>
											<option value="shift" <?=(COption::GetOptionString("niges.".$moduleCode, "ANIMATION", false, $siteId) == 'shift') ? 'selected': ''; ?>><?=GetMessage('Sdvig')?></option>
										</select>
									</td>
								</tr>

								<tr>
									<td width="50%" class="adm-detail-content-cell-hr"><!-- Sizes --></td><td width="50%" class="adm-detail-content-cell-hr"></td>
								</tr>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Razmer')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="number" max="250" min="10" value="<?=COption::GetOptionString("niges.".$moduleCode, "PADDINGSIZE", 12, $siteId)?>" name="NCA_PROP[PADDINGSIZE]" >
									</td>
								</tr>




								<tr>
									<td width="50%" class="adm-detail-content-cell-hr"><!-- Opacity --></td><td width="50%" class="adm-detail-content-cell-hr"></td>
								</tr>
								<tr>
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Prozrachnost')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="number" min="0" max="100" value="<?=COption::GetOptionString("niges.".$moduleCode, "BTNOPACITY", 100, $siteId)?>" name="NCA_PROP[BTNOPACITY]" >
									</td>
								</tr>

								<tr>
									<td width="50%" class="adm-detail-content-cell-hr"><!-- Shadow --></td><td width="50%" class="adm-detail-content-cell-hr"></td>
								</tr>
								<tr style="display:none">
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Ten_knopok')?>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="checkbox" name="NCA_PROP[BTNSHADOW]" value="Y" <?if (COption::GetOptionString("niges.".$moduleCode, "BTNSHADOW", false, $siteId) == 'Y'):?>checked<?endif;?>>
									</td>
								</tr>

								<tr style="display:none">
									<td width="50%" class="adm-detail-content-cell-l">
										<?=GetMessage('Cvet_teni')?>
										<?$btnShadowColor=COption::GetOptionString("niges.".$moduleCode, "BTNSHADOWCOLOR", "#A8A8A8", $siteId)?>
										<span class="inputcolor" style="background-color:<?=$btnShadowColor?>"><?=$btnShadowColor?></span>
									</td>
									<td width="50%" class="adm-detail-content-cell-r">
										<input type="text" value="<?=$btnShadowColor?>" name="NCA_PROP[BTNSHADOWCOLOR]" >
									</td>
								</tr>



							<?endif;?>
						</tbody>
					</table>

					<div style='padding:20px 0px 0; line-height:1.5; text-align:center; margin-top:25px; border-top: 1px solid #e2e8ea;'><?=GetMessage('Bolshe_vozm')?> <a href='/bitrix/admin/update_system_market.php?module=niges.cookiesacceptpro&lang=ru' target='_blank'><?=GetMessage('Bolshe_vozm_anch')?></a> </div>

				</div>
			</div>
			<div class="adm-detail-content-btns">
				<?if (isset($siteId)) :?>
					<input class="adm-btn-save" type="submit" name="set_<?=$moduleCode?>_props" value="<?=GetMessage('Sohranit')?>">
					<input type="button" value="<?=GetMessage('Otmenit')?>" name="cancel" onclick="window.location='niges.<?=$moduleCode?>_setting.php?lang=ru'" title="<?=GetMessage('Ne_soxranyat_i_vernutsya')?>">
				<?else:?>
					<input class="adm-btn-save" type="submit" name="set_site" value="<?=GetMessage('Vibrat');?>">
				<?endif;?>
			</div>
		</form>
		<style>
			.inputcolor {display: inline-block;
				border: 1px solid #9ea7b1;
				box-sizing: border-box;
				height: 27px;
				line-height: 26px;
				width: 70px;
				letter-spacing: -.3px;
				font-size: 13px;
				color: #ffffff;
				text-align: center;
				border-radius: 4px;
				text-transform: uppercase;
				text-shadow: 1px 1px 1px #000000;}
		.adm-detail-content-cell-hr	{ border-bottom: 8px solid #f5f9f9; border-top: 8px solid #f5f9f9; background: #e0e8ea; height: 1px; padding: 0px; }
		</style>
	</div>