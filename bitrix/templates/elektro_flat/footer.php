<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);?>
							</div>
                            <?if($APPLICATION->GetCurPage(true)== SITE_DIR."index.php" &&
                                (in_array("VENDORS", $arSetting["HOME_PAGE"]["VALUE"]))):?>
                                <?$APPLICATION->IncludeComponent("bitrix:main.include", "",
                                    array(
                                        "AREA_FILE_SHOW" => "file",
                                        "PATH" => SITE_DIR . "include/vendors_bottom.php",
                                        "AREA_FILE_RECURSIVE" => "N",
                                        "EDIT_MODE" => "html",
                                    ),
                                    false,
                                    array("HIDE_ICONS" => "Y")
                                );?>
					    	<?endif;?>
						</main>

						<?if(!CSite::InDir('/news/')):?>
							<?$APPLICATION->IncludeComponent("bitrix:main.include", "",
								array(
									"AREA_FILE_SHOW" => "file",
									"PATH" => SITE_DIR."include/news_bottom.php",
									"AREA_FILE_RECURSIVE" => "N",
									"EDIT_MODE" => "html",
								),
								false,
								array("HIDE_ICONS" => "Y")
							);?>
						<?endif;?>
						<?if(!CSite::InDir('/reviews/')):?>
							<?$APPLICATION->IncludeComponent("bitrix:main.include", "",
								array(
									"AREA_FILE_SHOW" => "file",
									"PATH" => SITE_DIR."include/reviews_bottom.php",
									"AREA_FILE_RECURSIVE" => "N",
									"EDIT_MODE" => "html",
								),
								false,
								array("HIDE_ICONS" => "Y")
							);?>
						<?endif;?>
					</div>
					<?$APPLICATION->IncludeComponent("bitrix:subscribe.form", "bottom",
						array(
							"USE_PERSONALIZATION" => "Y",
							"PAGE" => SITE_DIR."personal/mailings/",
							"SHOW_HIDDEN" => "N",
							"CACHE_TYPE" => "Y",
							"CACHE_TIME" => "36000000",
							"CACHE_NOTES" => ""
						),
						false
					);?>
				</div>
			</div>
			<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/viewed_products.php"), false);?>
			<footer>
				<div class="center<?=($arSetting['SITE_BACKGROUND']['VALUE'] == 'Y' ? ' inner' : '');?>">
					<div class="footer_menu_soc_pay">
						<div class="footer_menu">
							<?$APPLICATION->IncludeComponent("bitrix:menu", "bottom",
								array(
									"ROOT_MENU_TYPE" => "footer1",
									"MENU_CACHE_TYPE" => "A",
									"MENU_CACHE_TIME" => "36000000",
									"MENU_CACHE_USE_GROUPS" => "Y",
									"MENU_CACHE_GET_VARS" => array(),
									"MAX_LEVEL" => "1",
									"CHILD_MENU_TYPE" => "",
									"USE_EXT" => "N",
									"ALLOW_MULTI_SELECT" => "N",
									"CACHE_SELECTED_ITEMS" => "N"
								),
								false
							);?>
							<?$APPLICATION->IncludeComponent("bitrix:menu", "bottom",
								array(
									"ROOT_MENU_TYPE" => "footer2",
									"MENU_CACHE_TYPE" => "A",
									"MENU_CACHE_TIME" => "36000000",
									"MENU_CACHE_USE_GROUPS" => "Y",
									"MENU_CACHE_GET_VARS" => array(),
									"MAX_LEVEL" => "1",
									"CHILD_MENU_TYPE" => "",
									"USE_EXT" => "N",
									"ALLOW_MULTI_SELECT" => "N",
									"CACHE_SELECTED_ITEMS" => "N"
								),
								false
							);?>
							<?$APPLICATION->IncludeComponent("bitrix:menu", "bottom",
								array(
									"ROOT_MENU_TYPE" => "footer3",
									"MENU_CACHE_TYPE" => "A",
									"MENU_CACHE_TIME" => "36000000",
									"MENU_CACHE_USE_GROUPS" => "Y",
									"MENU_CACHE_GET_VARS" => array(),
									"MAX_LEVEL" => "1",
									"CHILD_MENU_TYPE" => "",
									"USE_EXT" => "N",
									"ALLOW_MULTI_SELECT" => "N",
									"CACHE_SELECTED_ITEMS" => "N"
								),
								false
							);?>
							<?$APPLICATION->IncludeComponent("bitrix:menu", "bottom",
								array(
									"ROOT_MENU_TYPE" => "footer4",
									"MENU_CACHE_TYPE" => "A",
									"MENU_CACHE_TIME" => "36000000",
									"MENU_CACHE_USE_GROUPS" => "Y",
									"MENU_CACHE_GET_VARS" => array(),
									"MAX_LEVEL" => "1",
									"CHILD_MENU_TYPE" => "",
									"USE_EXT" => "N",
									"ALLOW_MULTI_SELECT" => "N",
									"CACHE_SELECTED_ITEMS" => "N"
								),
								false
							);?>
						</div>
						<div class="footer_soc_pay">
							<!--
<div class="footer_soc">
								<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/join_us.php"), false, array("HIDE_ICONS" => "Y"));?>
							</div>
-->
							<div class="footer_pay" style="margin-top: 0px;">
								<?global $arPayIcFilter;
								$arPayIcFilter = array();?>
								<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/payments_icons.php"), false, array("HIDE_ICONS" => "Y"));?>
							</div>
						</div>
					</div>
					<div class="footer-bottom">
						<div class="footer-bottom__blocks">
							<div class="footer-bottom__block-wrap fb-left">
								<div class="footer-bottom__block footer-bottom__copyright">
									<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/copyright.php"), false);?>
								</div>
								<div class="footer-bottom__block footer-bottom__links">
									<?$APPLICATION->IncludeComponent("bitrix:menu", "bottom",
										array(
											"ROOT_MENU_TYPE" => "bottom",
											"MENU_CACHE_TYPE" => "A",
											"MENU_CACHE_TIME" => "36000000",
											"MENU_CACHE_USE_GROUPS" => "Y",
											"MENU_CACHE_GET_VARS" => array(),
											"MAX_LEVEL" => "1",
											"CHILD_MENU_TYPE" => "",
											"USE_EXT" => "N",
											"ALLOW_MULTI_SELECT" => "N",
											"CACHE_SELECTED_ITEMS" => "N"
										),
										false
									);?>
								</div>
							</div>
						</div>
						<div class="footer-bottom__blocks">
							<div class="footer-bottom__block-wrap fb-right">
								<div class="footer-bottom__block footer-bottom__counter">
									<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/counter_1.php"), false);?>
								</div>
								<div class="footer-bottom__block footer-bottom__counter">
									<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/counter_2.php"), false);?>
								</div>
								<div class="footer-bottom__block footer-bottom__design">
									<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/developer.php"), false);?>
								</div>
							</div>
						</div>
					</div>

				</div>
			</footer>
			<?if($arSetting["SITE_BACKGROUND"]["VALUE"] == "Y"){?>
				</div>
			<?}else{?>
			    </div>
			<?}?>
		</div>
	</div>
	<div class="<?=($arSetting['CATALOG_LOCATION']['VALUE'] == 'HEADER') ? ' clvh' : ''?><?=($arSetting['CART_LOCATION']['VALUE'] == 'TOP') ? ' clvt' : ''?><?=($arSetting['CART_LOCATION']['VALUE'] == 'RIGHT') ? ' clvr' : ''?><?=($arSetting['CART_LOCATION']['VALUE'] == 'LEFT') ? ' clvl' : ''?>">
	<div class="foot_panel_all">
						<div id="for-quick-view-footer"   class="foot_panel">
							<div class="foot_panel_1">
								<?$APPLICATION->IncludeComponent("bitrix:system.auth.form", "login",
									array(
										"REGISTER_URL" => SITE_DIR."personal/private/",
										"FORGOT_PASSWORD_URL" => SITE_DIR."personal/private/",
										"PROFILE_URL" => SITE_DIR."personal/private/",
										"SHOW_ERRORS" => "N"
									 ),
									 false,
									 array("HIDE_ICONS" => "Y")
								);?>
								<?$APPLICATION->IncludeComponent("bitrix:main.include", "",
									array(
										"AREA_FILE_SHOW" => "file",
										"PATH" => SITE_DIR."include/footer_compare.php"
									),
									false,
									array("HIDE_ICONS" => "Y")
								);?>
								<?$APPLICATION->IncludeComponent("altop:sale.basket.delay", ".default",
									array(
										"PATH_TO_DELAY" => SITE_DIR."personal/cart/?delay=Y",
									),
									false,
									array("HIDE_ICONS" => "Y")
								);?>
							</div>
							<div class="foot_panel_2">
								<?$APPLICATION->IncludeComponent("bitrix:sale.basket.basket.line", ".default",
									array(
										"PATH_TO_BASKET" => SITE_DIR."personal/cart/",
										"PATH_TO_ORDER" => SITE_DIR."personal/order/make/",
										"HIDE_ON_BASKET_PAGES" => "N",
									),
									false,
									array("HIDE_ICONS" => "Y")
								);?>
							</div>
						</div>
					</div>
					</div>

<script>
(function() {
	function vilmedFixImages() {
		document.querySelectorAll('img[width="0"], img[height="0"]').forEach(function(img) {
			if (img.classList.contains('no-lazy') || img.closest('.catalog-detail-pictures')) {
				var fallback = img.closest('.more_photo') ? 86 : 390;
				if (img.getAttribute('width') === '0') {
					img.setAttribute('width', String(fallback));
				}
				if (img.getAttribute('height') === '0') {
					img.setAttribute('height', String(fallback));
				}
				return;
			}
			if (img.getAttribute('width') === '0') {
				img.removeAttribute('width');
			}
			if (img.getAttribute('height') === '0') {
				img.removeAttribute('height');
			}
		});
	}

	function vilmedApplyLazy() {
		document.querySelectorAll('img').forEach(function(el) {
			if (el.classList.contains('no-lazy') || el.getAttribute('loading') || el.getAttribute('fetchpriority') === 'high') {
				return;
			}
			if (el.closest('.logo, header, .left-menu, .catalog-section-child, .ym-advanced-informer, .slider, .anythingslider, .slick-slider')) {
				return;
			}
			el.setAttribute('loading', 'lazy');
		});
	}

	function init() {
		vilmedFixImages();
		vilmedApplyLazy();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
</script>

<script>
(function() {
	function vilmedKabinetHasLoginPopup() {
		var kabinet = document.getElementById("kabinet");
		return !!(kabinet && kabinet.querySelector(".login_anch") && kabinet.querySelector(".pop-up.login"));
	}

	function vilmedBindKabinetPopup() {
		if (window.__vilmedKabinetPopupBound) {
			return;
		}
		window.__vilmedKabinetPopupBound = true;

		document.addEventListener("click", function(e) {
			var kabinet = document.getElementById("kabinet");
			if (!kabinet) {
				return;
			}

			if (e.target.closest("#kabinet .login_anch")) {
				e.preventDefault();
				var loginBody = document.querySelector(".login_body");
				var loginPopup = document.querySelector(".pop-up.login");
				if (loginBody) {
					loginBody.style.display = "block";
				}
				if (loginPopup) {
					loginPopup.style.display = "block";
				}
				return;
			}

			if (e.target.closest(".login_close") || e.target.classList.contains("login_body")) {
				e.preventDefault();
				document.querySelectorAll(".login_body, .pop-up.login").forEach(function(el) {
					el.style.display = "none";
				});
			}
		});
	}

	function vilmedLoadKabinetLine() {
		var kabinet = document.getElementById("kabinet");
		if (!kabinet) {
			return;
		}
		if (kabinet.querySelector(".personal")) {
			vilmedBindKabinetPopup();
			return;
		}
		if (vilmedKabinetHasLoginPopup()) {
			vilmedBindKabinetPopup();
			return;
		}

		BX.ajax({
			url: "/ajax/kabinet_line.php",
			method: "GET",
			dataType: "html",
			onsuccess: function(result) {
				if (!result || !String(result).replace(/\s/g, "")) {
					return;
				}

				var wrapper = document.createElement("div");
				wrapper.innerHTML = result;
				var source = wrapper.querySelector("#kabinet") || wrapper.querySelector(".kabinet");
				if (source) {
					kabinet.innerHTML = source.innerHTML;
				}
				vilmedBindKabinetPopup();
			}
		});
	}

	vilmedBindKabinetPopup();

	var runKabinet = function() {
		if (window.requestIdleCallback) {
			requestIdleCallback(vilmedLoadKabinetLine, {timeout: 2500});
		} else {
			vilmedLoadKabinetLine();
		}
	};

	if (document.readyState === "complete") {
		runKabinet();
	} else {
		window.addEventListener("load", runKabinet, {once: true});
	}
})();
</script>

<script>
(function() {
	function vilmedNeedsAdd2BasketFallback() {
		if (!document.querySelector('form.add2basket_form[action*="add2basket.php"]')) {
			return false;
		}
		if (typeof JCCatalogItem === "undefined" && typeof JCCatalogBigdataItem === "undefined") {
			return true;
		}
		var html = document.documentElement.innerHTML;
		return html.indexOf("new JCCatalogItem") === -1 && html.indexOf("new JCCatalogBigdataItem") === -1;
	}

	function vilmedBindAdd2BasketFallback() {
		if (window.__vilmedAdd2BasketBound || !vilmedNeedsAdd2BasketFallback()) {
			return;
		}
		window.__vilmedAdd2BasketBound = true;

		document.addEventListener("click", function(e) {
			var btn = e.target.closest('a[name="add2basket"], button[name="add2basket"]');
			if (!btn || btn.disabled || btn.classList.contains("ppp")) {
				return;
			}

			var form = btn.closest("form.add2basket_form");
			if (!form) {
				return;
			}

			var action = form.getAttribute("action") || "";
			if (action.indexOf("add2basket.php") === -1) {
				return;
			}

			e.preventDefault();

			var params = {};
			form.querySelectorAll("input").forEach(function(input) {
				if (input.name) {
					params[input.name] = input.value;
				}
			});
			params.sessid = BX.bitrix_sessid();

			BX.ajax.post(action, params, function() {
				var siteDir = BX.message("SITE_DIR") || "/";
				BX.ajax.post(siteDir + "ajax/basket_line.php", "", function(data) {
					if (typeof refreshCartLine === "function") {
						refreshCartLine(data);
					}
				});
				BX.ajax.post(siteDir + "ajax/delay_line.php", "", function(data) {
					var delayLine = BX.findChild(document.body, {className: "delay_line"}, true, false);
					if (delayLine) {
						delayLine.innerHTML = data;
					}
				});

				var addedText = BX.message("ADDITEMINCART_ADDED") || "ДОБАВЛЕНО";
				BX.addClass(btn, "ppp");
				BX.adjust(btn, {
					props: {disabled: btn.tagName === "BUTTON"},
					html: "<i class='fa fa-check'></i><span>" + addedText + "</span>"
				});
			});
		});

		document.addEventListener("click", function(e) {
			var plus = e.target.closest('[id^="quantity_plus_"]');
			var minus = e.target.closest('[id^="quantity_minus_"]');
			var control = plus || minus;
			if (!control) {
				return;
			}

			var form = control.closest("form.add2basket_form");
			if (!form) {
				return;
			}

			var input = form.querySelector('input[name="quantity"]');
			if (!input) {
				return;
			}

			e.preventDefault();
			var cur = parseFloat(String(input.value).replace(",", "."));
			if (isNaN(cur)) {
				cur = 1;
			}
			cur = plus ? cur + 1 : Math.max(1, cur - 1);
			input.value = cur;
		});
	}

	function vilmedInitAdd2BasketFallback() {
		if (typeof BX === "undefined" || typeof BX.ajax === "undefined") {
			return;
		}
		vilmedBindAdd2BasketFallback();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", vilmedInitAdd2BasketFallback);
	} else {
		vilmedInitAdd2BasketFallback();
	}
})();
</script>


<script type="text/javascript">window._ab_id_=163177</script>
<script>
window.addEventListener("load", function() {
	var s = document.createElement("script");
	s.async = true;
	s.src = "https://cdn.botfaqtor.ru/one.js";
	document.body.appendChild(s);
}, {once: true});
</script>

<!-- Roistat Counter Start -->
<script>
window.addEventListener('load', function() {
    (function(w, d, s, h, id) {
        w.roistatProjectId = id; w.roistatHost = h;
        var p = d.location.protocol == "https:" ? "https://" : "http://";
        var u = /^.*roistat_visit=[^;]+(.*)?$/.test(d.cookie) ? "/dist/module.js" : "/api/site/1.0/"+id+"/init?referrer="+encodeURIComponent(d.location.href);
        var js = d.createElement(s); js.charset="UTF-8"; js.async = 1; js.src = p+h+u; var js2 = d.getElementsByTagName(s)[0]; js2.parentNode.insertBefore(js, js2);
    })(window, document, 'script', 'cloud.roistat.com', 'f7d48e1186929411cb056d0471bcc8eb');
});
</script>
<!-- Roistat Counter End -->

</body>
</html>
