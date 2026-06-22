<?php

$MESS['YANDEX_MARKET_EXPORT_TAG_NAME_TITLE'] = 'Название товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_NAME_DESCRIPTION'] = '
<p>Составляйте название по&nbsp;схеме: тип + бренд или производитель + модель + особенности, если есть (например, цвет, размер или вес) и&nbsp;количество в&nbsp;упаковке.</p>
<p>Не&nbsp;включайте в&nbsp;название условия продажи (например, &laquo;скидка&raquo;, &laquo;бесплатная доставка&raquo; <nobr>и т. д.</nobr>), эмоциональные характеристики (&laquo;хит&raquo;, &laquo;супер&raquo; <nobr>и т. д.</nobr>). Не&nbsp;пишите слова большими буквами&nbsp;&mdash; кроме устоявшихся названий брендов и&nbsp;моделей.</p>
<p>Оптимальная длина&nbsp;&mdash; 50&ndash;60 символов.</p>
<p><a href="https://yandex.ru/support/marketplace/assortment/fields/title.html" target="_blank">Рекомендации и&nbsp;правила</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_MARKETCATEGORYID_TITLE'] = 'Категория на Маркете';
$MESS['YANDEX_MARKET_EXPORT_TAG_MARKETCATEGORYID_DESCRIPTION'] = '
<p>В&nbsp;каталоге <nobr>1С-Битрикс</nobr> в&nbsp;свойстве &laquo;Яндекс. Маркет: Категория&raquo; выберите категорию для размещения товара на Маркете.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_CATEGORY_TITLE'] = 'Категория в магазине';
$MESS['YANDEX_MARKET_EXPORT_TAG_CATEGORY_DESCRIPTION'] = '
<p>Категория товара в&nbsp;вашем магазине. Значение будет использовано для определения категории товара на&nbsp;Маркете в&nbsp;случае, если вы&nbsp;не&nbsp;передали категорию в&nbsp;параметре marketCategoryId.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_PICTURES_TITLE'] = 'Картинки товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_PICTURES_DESCRIPTION'] = '
<p>Ссылки на&nbsp;изображения товара. Изображение по&nbsp;первой ссылке считается основным, остальные дополнительными.</p>
<p>Требования к&nbsp;ссылкам</p>
<ul>
<li>Ссылок может быть до&nbsp;30.</li>
<li>Указывайте ссылку целиком, включая протокол http или https.</li>
<li>Максимальная длина&nbsp;&mdash; 512 символов.</li>
<li>Русские буквы в&nbsp;URL можно.</li>
<li>Можно использовать прямые ссылки на&nbsp;изображения и&nbsp;на&nbsp;Яндекс Диск. Ссылки на&nbsp;Яндекс Диске нужно копировать с&nbsp;помощью функции Поделиться. Относительные ссылки и&nbsp;ссылки на&nbsp;другие облачные хранилища&nbsp;&mdash; не&nbsp;работают.</li>
</ul>
<p>
&#9989;https://<nobr>example-shop</nobr>.ru/images/sku12345.jpg<br />
&#9989;https://yadi.sk/i/NaBoRsimVOLov&nbsp;<br />
&#10060;/images/sku12345.jpg<br />
&#10060;https://www.dropbox.com/s/818f/tovar.jpg
</p>
<p>Ссылки на&nbsp;изображение должны быть постоянными. Нельзя использовать динамические ссылки, меняющиеся от&nbsp;выгрузки к&nbsp;выгрузке.</p>
<p>Если нужно заменить изображение, выложите новое изображение по&nbsp;новой ссылке, а&nbsp;ссылку на&nbsp;старое удалите. Если просто заменить изображение по&nbsp;старой ссылке, оно не&nbsp;обновится.</p>
<p><a href="https://yandex.ru/support/marketplace/ru/assortment/fields/images.html" target="_blank">Требования к&nbsp;изображениям</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_VIDEOS_TITLE'] = 'Видео товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_VIDEOS_DESCRIPTION'] = '
<p>Ссылка (URL) на&nbsp;видео товара.</p>
<p>Максимальное количество ссылок&nbsp;&mdash; 6.</p>
<p>Требования к&nbsp;ссылке</p>
<ul>
<li>Указывайте ссылку целиком, включая протокол http или https.</li>
<li>Максимальная длина&nbsp;&mdash; 512 символов.</li>
<li>Русские буквы в&nbsp;URL можно.</li>
<li>Можно использовать прямые ссылки на&nbsp;видео и&nbsp;на&nbsp;Яндекс Диск. Ссылки на&nbsp;Яндекс Диске нужно копировать с&nbsp;помощью функции Поделиться. Относительные ссылки и&nbsp;ссылки на&nbsp;другие облачные хранилища&nbsp;&mdash; не&nbsp;работают.</li>
</ul>
<p>
&#9989;https://<nobr>example-shop</nobr>.ru/video/sku12345.avi<br />
&#9989;https://yadi.sk/i/NaBoRsimVOLov<br />
&#10060;/video/sku12345.avi<br />
&#10060;https://www.dropbox.com/s/818f/<nobr>super-tovar</nobr>.avi
</p>
<p>Ссылки на&nbsp;видео должны быть постоянными. Нельзя использовать динамические ссылки, меняющиеся от&nbsp;выгрузки к&nbsp;выгрузке.</p>
<p>Если нужно заменить видео, выложите новое видео по&nbsp;новой ссылке, а&nbsp;ссылку на&nbsp;старое удалите. Если просто заменить видео по&nbsp;старой ссылке, оно не&nbsp;обновится.</p>
<p><a href="https://yandex.ru/support/marketplace/assortment/fields/video.html" target="_blank">Требования к&nbsp;видео</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_FIRSTVIDEOASCOVER_TITLE'] = 'Первое видео как обложка';
$MESS['YANDEX_MARKET_EXPORT_TAG_FIRSTVIDEOASCOVER_DESCRIPTION'] = '
<p>Использовать первое видео в&nbsp;карточке как видеообложку.</p>
<p>Выберите <code>Да</code>, чтобы первое видео использовалось как видеообложка, или <code>Нет</code>, чтобы видеообложка не&nbsp;отображалась в&nbsp;карточке товара.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_MANUALS_TITLE'] = 'Инструкции по использованию';
$MESS['YANDEX_MARKET_EXPORT_TAG_MANUALS_DESCRIPTION'] = '
<p>Список инструкций по&nbsp;использованию товара.</p>
<p>Максимальное количество инструкций&nbsp;&mdash; 6.</p>
<p>Если вы&nbsp;передадите пустое поле <code>manuals</code>, загруженные ранее инструкции удалятся.
<br>Инструкция по&nbsp;использованию товара.</p>
<p><em class="openapi-description-annotation">Max items:</em> <code>6</code></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_MANUALS_URL_TITLE'] = 'Ссылка на инструкцию';
$MESS['YANDEX_MARKET_EXPORT_TAG_MANUALS_TITLE_DESCRIPTION'] = 'Название инструкции';

$MESS['YANDEX_MARKET_EXPORT_TAG_VENDOR_TITLE'] = 'Название бренда или производителя';
$MESS['YANDEX_MARKET_EXPORT_TAG_VENDOR_DESCRIPTION'] = 'Должно быть записано так, как его пишет сам бренд.';

$MESS['YANDEX_MARKET_EXPORT_TAG_BARCODES_TITLE'] = 'Штрихкоды';
$MESS['YANDEX_MARKET_EXPORT_TAG_BARCODES_DESCRIPTION'] = '
<p>Указывайте в&nbsp;виде последовательности цифр. Подойдут коды <nobr>EAN-13</nobr>, <nobr>EAN-8</nobr>, <nobr>UPC-A</nobr>, <nobr>UPC-E</nobr> или Code 128.</p>
<p>Для книг указывайте ISBN.</p>
<p>Для товаров <a href="https://yastatic.net/s3/doc-binary/src/support/market/ru/yandex-market-list-for-gtin.xlsx" target="_blank" rel="noreferrer noopener">определенных категорий и&nbsp;торговых марок</a> штрихкод должен быть действительным кодом GTIN. Обратите внимание: внутренние штрихкоды, начинающиеся на&nbsp;2 или 02, и&nbsp;коды формата Code 128 не&nbsp;являются GTIN.</p>
<p><em>Пример:</em> <code>46012300000000</code></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_DESCRIPTION_TITLE'] = 'Описание товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_DESCRIPTION_DESCRIPTION'] = '
<p>Подробное описание товара: например, его преимущества и&nbsp;особенности.</p>
<p>Не&nbsp;давайте в&nbsp;описании инструкций по&nbsp;установке и&nbsp;сборке. Не&nbsp;используйте слова &laquo;скидка&raquo;, &laquo;распродажа&raquo;, &laquo;дешевый&raquo;, &laquo;подарок&raquo; (кроме подарочных категорий), &laquo;бесплатно&raquo;, &laquo;акция&raquo;, &laquo;специальная цена&raquo;, &laquo;новинка&raquo;, &laquo;new&raquo;, &laquo;аналог&raquo;, &laquo;заказ&raquo;, &laquo;хит&raquo;. Не&nbsp;указывайте никакой контактной информации и&nbsp;не&nbsp;давайте ссылок.</p>
<p>Можно использовать теги:</p>
<ul>
<li>&lt;h&gt;, &lt;h1&gt;, &lt;h2&gt; и&nbsp;так далее&nbsp;&mdash; для заголовков;</li>
<li>&lt;br&gt; и&nbsp;&lt;p&gt;&nbsp;&mdash; для переноса строки;</li>
<li>&lt;ol&gt;&nbsp;&mdash; для нумерованного списка;</li>
<li>&lt;ul&gt;&nbsp;&mdash; для маркированного списка;</li>
<li>&lt;li&gt;&nbsp;&mdash; для создания элементов списка (должен находиться внутри &lt;ol&gt; или &lt;ul&gt;);</li>
<li>&lt;div&gt;&nbsp;&mdash; поддерживается, но&nbsp;не&nbsp;влияет на&nbsp;отображение текста.</li>
</ul>
<p>Оптимальная длина&nbsp;&mdash; 400&ndash;600 символов.</p>
<p><a href="https://yandex.ru/support/marketplace/assortment/fields/description.html" target="_blank" rel="noreferrer noopener">Рекомендации и&nbsp;правила</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_MANUFACTURERCOUNTRIES_TITLE'] = 'Страна, где был произведен товар';
$MESS['YANDEX_MARKET_EXPORT_TAG_MANUFACTURERCOUNTRIES_DESCRIPTION'] = '
<p>Записывайте названия стран так, как они записаны в&nbsp;<a href="https://yastatic.net/s3/doc-binary/src/support/market/ru/countries.xlsx" target="_blank" rel="noreferrer noopener">списке</a>.</p>
<p><em>Example:</em> <code>Россия</code></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_WEIGHTDIMENSIONS_TITLE'] = 'Габариты упаковки';

$MESS['YANDEX_MARKET_EXPORT_TAG_WIDTH_TITLE'] = 'Ширина';
$MESS['YANDEX_MARKET_EXPORT_TAG_HEIGHT_TITLE'] = 'Высота';
$MESS['YANDEX_MARKET_EXPORT_TAG_LENGTH_TITLE'] = 'Длина';
$MESS['YANDEX_MARKET_EXPORT_TAG_WEIGHT_TITLE'] = 'Вес (брутто)';
$MESS['YANDEX_MARKET_EXPORT_TAG_WEIGHT_DESCRIPTION'] = 'Вес товара с учетом упаковки';

$MESS['YANDEX_MARKET_EXPORT_TAG_VENDORCODE_TITLE'] = 'Артикул товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_VENDORCODE_DESCRIPTION'] = 'Артикул товара от производителя';

$MESS['YANDEX_MARKET_EXPORT_TAG_TAGS_TITLE'] = 'Метки товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_TAGS_DESCRIPTION'] = '
<p>Метки товара, используемые магазином. Покупателям теги не&nbsp;видны. По&nbsp;тегам можно группировать и&nbsp;фильтровать разные товары в&nbsp;каталоге&nbsp;&mdash; например, товары одной серии, коллекции или линейки.</p>
<p>Максимальная длина тега 20 символов. У&nbsp;одного товара может быть максимум 10 тегов. Всего можно создать не&nbsp;больше 50 разных тегов.
<br></p>
<p><em>Example:</em> <code>до&nbsp;500&nbsp;рублей</code></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_SHELFLIFE_TITLE'] = 'Срок годности';
$MESS['YANDEX_MARKET_EXPORT_TAG_SHELFLIFE_DESCRIPTION'] = '
<p>Срок годности&nbsp;&mdash; период, по&nbsp;прошествии которого товар становится непригоден.</p>
<p>Указывайте срок, указанный на&nbsp;банке или упаковке. Текущая дата, дата поставки или дата отгрузки значения не&nbsp;имеет.</p>
<p>Обязательно указывайте срок, если он&nbsp;есть.</p>
<p>В&nbsp;комментарии укажите условия хранения. Например, <code>Хранить в&nbsp;сухом помещении</code>.</p>
';
$MESS['YANDEX_MARKET_EXPORT_TAG_TIMEUNIT_TITLE'] = 'Единица измерения';
$MESS['YANDEX_MARKET_EXPORT_TAG_COMMENT_TITLE'] = 'Комментарий';

$MESS['YANDEX_MARKET_EXPORT_TAG_LIFETIME_TITLE'] = 'Срок службы';
$MESS['YANDEX_MARKET_EXPORT_TAG_LIFETIME_DESCRIPTION'] = '
<p>Срок службы&nbsp;&mdash; период, в&nbsp;течение которого товар должен исправно выполнять свою функцию.</p>
<p>Обязательно указывайте срок, если он&nbsp;есть.</p>
<p>В&nbsp;комментарии укажите условия хранения. Например, <code>Использовать при температуре не&nbsp;ниже &#8722;10 градусов</code>.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_GUARANTEEPERIOD_TITLE'] = 'Гарантийный срок';
$MESS['YANDEX_MARKET_EXPORT_TAG_GUARANTEEPERIOD_DESCRIPTION'] = '
<p>Гарантийный срок&nbsp;&mdash; период, в&nbsp;течение которого можно бесплатно заменить или починить товар.</p>
<p>Обязательно указывайте срок, если он&nbsp;есть.</p>
<p>В&nbsp;комментарии опишите особенности гарантийного обслуживания. Например, <code>Гарантия на&nbsp;аккумулятор&nbsp;&mdash; 6&nbsp;месяцев</code>.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_CUSTOMSCOMMODITYCODE_TITLE'] = 'ТН ВЭД';
$MESS['YANDEX_MARKET_EXPORT_TAG_CUSTOMSCOMMODITYCODE_DESCRIPTION'] = '
<p>Код товара в&nbsp;единой Товарной номенклатуре внешнеэкономической деятельности (ТН&nbsp;ВЭД)&nbsp;&mdash; 10 или 14 цифр без пробелов.</p>
<p>Обязательно укажите, если он&nbsp;есть.</p>
<p><em class="openapi-description-annotation">Example:</em> <code>8517610008</code></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_CERTIFICATES_TITLE'] = 'Номера документов на товар';
$MESS['YANDEX_MARKET_EXPORT_TAG_CERTIFICATES_DESCRIPTION'] = '
<p>Номера документов на&nbsp;товар: сертификата, декларации соответствия <nobr>и т. п.</nobr></p>
<p>Передавать можно только номера документов, сканы которого загружены в&nbsp;кабинете продавца по&nbsp;<a href="https://yandex.ru/support/marketplace/assortment/restrictions/certificates.html" target="_blank" rel="noreferrer noopener">инструкции</a>.
<br></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_BOXCOUNT_TITLE'] = 'Количество грузовых мест';
$MESS['YANDEX_MARKET_EXPORT_TAG_BOXCOUNT_DESCRIPTION'] = '
<p>Количество грузовых мест.</p>
<p>Параметр используется, если товар представляет собой несколько коробок, упаковок и&nbsp;так далее. Например, кондиционер занимает два места&nbsp;&mdash; внешний и&nbsp;внутренний блоки в&nbsp;двух коробках.</p>
<p>Для товаров, занимающих одно место, не&nbsp;передавайте этот параметр.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_TYPE_TITLE'] = 'Особый тип товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_TYPE_DESCRIPTION'] = '
<p>Указывается, если товар:</p>
<ul>
<li>лекарство</li>
<li>бумажная или электронная книга</li>
<li>аудиокнига</li>
<li>музыка или видео</li>
<li>изготовляется на&nbsp;заказ</li>
</ul>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_DOWNLOADABLE_TITLE'] = 'Признак цифрового товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_DOWNLOADABLE_DESCRIPTION'] = '
<p>Признак цифрового товара. Укажите <code>Да</code>, если товар доставляется по&nbsp;электронной почте.</p>
<p><a href="ru/step-by-step/digital">Как работать с&nbsp;цифровыми товарами</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_ADULT_TITLE'] = 'Отметка 18+';
$MESS['YANDEX_MARKET_EXPORT_TAG_ADULT_DESCRIPTION'] = '<p>Параметр включает для товара пометку 18+. Устанавливайте ее&nbsp;только для товаров, которые относятся к&nbsp;удовлетворению сексуальных потребностей.</p>';

$MESS['YANDEX_MARKET_EXPORT_TAG_MARKETSKU_TITLE'] = 'Идентификатор карточки на Маркете';

$MESS['YANDEX_MARKET_EXPORT_TAG_ONLYPARTNERMEDIACONTENT_TITLE'] = 'Заменить изображения Маркета';
$MESS['YANDEX_MARKET_EXPORT_TAG_ONLYPARTNERMEDIACONTENT_DESCRIPTION'] = '
<p>Будут использоваться только переданные вами изображения товаров.</p>
<p>Значение по&nbsp;умолчанию&nbsp;&mdash; <code>Нет</code>. Если вы&nbsp;хотите заменить изображения, которые добавил Маркет, передайте значение <code>Да</code>.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_MIN_QUANTITY_TITLE'] = 'Минимальное количество';
$MESS['YANDEX_MARKET_EXPORT_TAG_MIN_QUANTITY_DESCRIPTION'] = '
<p>Минимальное количество единиц товара в&nbsp;заказе. Например, если указать 10, покупатель сможет добавить в&nbsp;корзину не&nbsp;меньше 10 единиц.</p>
<p>&#9888;&#65039; Если количество товара на&nbsp;складе меньше заданного, ограничение не&nbsp;сработает и&nbsp;покупатель сможет его заказать.</p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_STEP_QUANTITY_TITLE'] = 'Квант продажи';
$MESS['YANDEX_MARKET_EXPORT_TAG_STEP_QUANTITY_DESCRIPTION'] = '
<p>На&nbsp;сколько единиц покупатель сможет увеличить количество товара в&nbsp;корзине.</p>
<p>Например, если задать 5, покупатель сможет добавить к&nbsp;заказу только 5, 10, 15, &hellip; единиц товара.</p>
<p>&#9888;&#65039; Если количество товара на&nbsp;складе не&nbsp;дотягивает до&nbsp;кванта, ограничение не&nbsp;сработает и&nbsp;покупатель сможет заказать количество, не&nbsp;кратное кванту.</p>
<p>Настройка продажи квантами. <a href="https://yandex.ru/support/marketplace/ru/assortment/fields/quantum.html" target="_blank">Что это значит?</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_TAG_CONDITION_TITLE'] = 'Состояние уцененного товара';
$MESS['YANDEX_MARKET_EXPORT_TAG_CONDITION_DESCRIPTION'] = '
<p>Используется только для товаров, продаваемых с&nbsp;уценкой.</p>
<p><a href="https://yandex.ru/support/marketplace/assortment/restrictions/used-goods.html" target="_blank" rel="noreferrer noopener">Правила продажи уцененных товаров</a></p>
';

$MESS['YANDEX_MARKET_EXPORT_ATTRIBUTE_CONDITION_TYPE_TITLE'] = 'Тип уценки';
$MESS['YANDEX_MARKET_EXPORT_TAG_CONDITION_REASON_TITLE'] = 'Причина уценки';
$MESS['YANDEX_MARKET_EXPORT_TAG_CONDITION_REASON_DESCRIPTION'] = 'Описание товара. Подробно опишите дефекты, насколько они заметны и где их искать.';
$MESS['YANDEX_MARKET_EXPORT_TAG_CONDITION_QUALITY_TITLE'] = 'Внешний вид товара';

$MESS['YANDEX_MARKET_EXPORT_TAG_AGE_TITLE'] = 'Возрастное ограничение';
$MESS['YANDEX_MARKET_EXPORT_TAG_AGE_DESCRIPTION'] = '
<p>Если товар не&nbsp;предназначен для детей младше определенного возраста, укажите это.</p>
<p>Возрастное ограничение можно задавать в&nbsp;годах (с&nbsp;нуля, с&nbsp;6, 12, 16 или 18) или в&nbsp;месяцах (любое число от&nbsp;0 до&nbsp;12).</p>
';

$MESS['YANDEX_MARKET_EXPORT_ATTRIBUTE_AGEUNIT_TITLE'] = 'Единица измерения';

