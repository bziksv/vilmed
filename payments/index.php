<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "МИНИМАЛЬНАЯ СУММА ОТГРУЗКИ: на оборудование - 5 000₽ на инструментарий и расходные материалы - 10 000₽");
$APPLICATION->SetPageProperty("title", "Способы оплаты в интернет-магазине Вилмед");
$APPLICATION->SetTitle("Способы оплаты");?><h2 style="text-align: center;">МИНИМАЛЬНАЯ СУММА ОТГРУЗКИ 10 000₽</h2>
<br>
<!--<h2 style="text-align: center;">на оборудование - 5 000₽</h2>
<h2 style="text-align: center;">на инструментарий и расходные материалы - 10 000₽</h2-->
 <?$APPLICATION->IncludeComponent(
	"bitrix:catalog.section.list",
	"payments",
	Array(
		"ADD_SECTIONS_CHAIN" => "N",
		"CACHE_GROUPS" => "Y",
		"CACHE_TIME" => "36000000",
		"CACHE_TYPE" => "A",
		"COUNT_ELEMENTS" => "N",
		"IBLOCK_ID" => "10",
		"IBLOCK_TYPE" => "content",
		"SECTION_CODE" => "",
		"SECTION_FIELDS" => array(),
		"SECTION_ID" => "",
		"SECTION_URL" => "",
		"SECTION_USER_FIELDS" => array(),
		"SHOW_PARENT_NAME" => "",
		"TOP_DEPTH" => "2",
		"VIEW_MODE" => ""
	)
);?>
<p>
	 Для заказа Вы можете прислать список оборудования на почту: <a href="mailto:info@vilmed.ru">info@vilmed.ru</a>&nbsp;или самостоятельно сформировать заявку через сайт (добавляя товары в корзину).&nbsp;
</p>
<p>
	 Далее на электронную почту придет письмо с сформированным КП или счетом от нашего менеджера.
</p>
 <br>
<p>
	 В случае оплаты счета без заключения индивидуального договора, вы соглашаетесь с условиями договора оферты: <a target="_blank" href="https://vilmed.ru/upload/oferta.pdf">https://vilmed.ru/upload/oferta.pdf</a> <br>
</p><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>