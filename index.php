<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("title", "Медицинское оборудование и товары | Интернет магазин ВИЛМЕД");
$APPLICATION->SetPageProperty("description", "Интернет магазин медицинского оборудования ВИЛМЕД: гарантия качества по выгодным ценам! Огромный выбор товаров и оборудования с доставкой по всей России ☎ +7 (499) 113-02-79");
    $APPLICATION->SetTitle("Медицинское оборудование ");
    global $arSetting;
if(in_array("CONTENT", $arSetting["HOME_PAGE"]["VALUE"])):?><h1>Интернет-магазин профессионального медицинского оборудования «ВИЛМЕД»</h1>
 Мы поставляем более 15 000 единиц медицинского товара. В каталоге собраны все популярные зарубежные бренды: Kawe, Heine, Pentax, Unicos, Riester, Shin Nippon. А также отечественные производители: Зенит, Зомз, Медтехника и другие.<br>
 <br>
 Каталог удобно разделен на направления от Ветеринарии, Лор, Офтальмологии, вплоть до Хирургии и Эндоскопии.&nbsp;<br>
 <br>
 Помимо оборудования, мы предлагаем медицинский инструментарий, как хирургический, так и диагностический разной ценовой политики.<br>
 <br>
 Наша цель: реализация медицинского оборудования, для улучшения оказываемых медицинских услуг населению нашей страны.<br>
 Делая запрос нам, вы можете быть уверены в получении выгодного предложения.<br>
 <br>
 За время нашей работы, мы сделали более 1 000 поставок по всей России от Калининграда до Курил.&nbsp;&nbsp;<br>
 <br>
 Наши клиенты: врачи частной практики, крупные компании медицинской направленности, так и организации, в которых организована врачебная поддержка работникам предприятий.<br>
 <br>
 <br>
 <br>
 <br>
 <br>
 <br>
 <br>
<!--
 <style>
.accordion {
    background-color: #eee;
    color: #444;
    cursor: pointer;
    padding: 18px;
    width: 100%;
    border: none;
    text-align: left;
    outline: none;
    font-size: 15px;
    transition: 0.4s;
}

.active, .accordion:hover {
    background-color: #ccc; 
}

.panel {
    padding: 0 18px;
    display: none;
    background-color: white;
    overflow: hidden;
}
</style>
<h2>Accordion</h2>
 <button class="accordion">Section 1</button>
<div class="panel">
	<p>
		Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
	</p>
</div>
 <button class="accordion">Section 2</button>
<div class="panel">
	<p>
		Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
	</p>
</div>
 <button class="accordion">Section 3</button>
<div class="panel">
	<p>
		Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
	</p>
</div>
<script>
var acc = document.getElementsByClassName("accordion");
var i;

for (i = 0; i < acc.length; i++) {
    acc[i].addEventListener("click", function() {
        this.classList.toggle("active");
        var panel = this.nextElementSibling;
        if (panel.style.display === "block") {
            panel.style.display = "none";
        } else {
            panel.style.display = "block";
        }
    });
}
</script>

--><?endif;
    //CANONICAL
    $pageUrl = $APPLICATION->GetCurPageParam();
    $query_str = parse_url($pageUrl);
    
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
    $protocol = 'https://';
    else
    $protocol = 'http://';
    
    parse_str($query_str['query'], $query_params);
    if(!empty($query_params)){
        $APPLICATION->AddHeadString("<link rel='canonical' href='".$protocol.$_SERVER['HTTP_HOST'].$query_str["path"]."'>");
    }
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>