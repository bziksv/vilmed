<?php

$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_TAB_COMMON'] = 'Общие настройки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_CAMPAIGN_ID'] = 'Номер магазина';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_CAMPAIGN_ID_PLACEHOLDER'] = '21579827';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_CAMPAIGN_ID_DESCRIPTION'] = 'Скопируйте сюда ID кампании на той же странице';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_INCOMING_REQUEST_GROUP'] = 'Получение запросов от Маркета';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_MODE'] = 'Способ работы';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_MODE_DESCRIPTION'] = 'Отметьте Получать запросы от Маркета на вкладке Получение запросов от Маркета (Настройки &rarr; Модули и API), если ваш сайт готов стабильно отвечать.';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_MODE_PULL'] = 'Загружать заказы на агентах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_MODE_PUSH'] = 'Получать запросы от Маркета';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_TOKEN'] = 'Токен для запросов';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_TOKEN_PLACEHOLDER'] = '3D000001B86C3C97';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_TOKEN_DESCRIPTION'] = 'Скопируйте сюда &laquo;Авторизационный токен&raquo; с вкладки Получение запросов от Маркета (Настройки &rarr; Модули и API).';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_INCOMING_URL'] = 'Адрес для запросов';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_YANDEX_INCOMING_URL_DESCRIPTION'] = 'Укажите значение отсюда в &laquo;URL для запросов API&raquo; на той же странице (Настройки &rarr; Модули и API, вкладка Получение запросов от Маркета).';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_GROUP_ORDER'] = 'Оплата и доставка';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PERSON_TYPE'] = 'Тип плательщика';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PAY_SYSTEM'] = 'Платежная система (#TYPE#)';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PROFILE_ID'] = 'Свойства по умолчанию';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PROFILE_ID_HELP'] = 'Используются для расчета заказа и заполнения обязательных значений';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_DELIVERY_ID'] = 'Служба доставки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_ACCEPT_OLD_PRICE'] = 'Если цена товара изменилась с момента запроса цены<br /> до момента оформления заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_ACCEPT_OLD_PRICE_DECLINE'] = 'Не оформлять заказ';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_ACCEPT_OLD_PRICE_MODIFY'] = 'Оформлять заказ со старой ценой';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_GROUP_PROPERTY'] = 'Свойства заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_EXTERNAL_ID_FIELDS'] = 'Поля заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_EXTERNAL_ID_FIELD_ACCOUNT_NUMBER'] = 'Номер заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_EXTERNAL_ID_PROPERTIES'] = 'Свойства заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_ACCOUNT_NUMBER_TEMPLATE'] = 'Шаблон номера';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_ACCOUNT_NUMBER_TEMPLATE_HELP'] = 'Для подстановки номера заказа используйте&nbsp;&mdash; {id}. Номер магазина&nbsp;&mdash; {campaignId}';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_TAB_STORE'] = 'Данные о&nbsp;ценах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_STORE_TRACE'] = 'Ограничивать по фактическому наличию';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_RATIO_SOURCE'] = 'Коэффициент упаковки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_RATIO_SOURCE_HELP'] = 'Выберите свойство, в котором храните количество единиц в упаковке. Остаток товара будет разделен на значение выбранного коэффициента.';

$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_STORE'] = 'Откуда брать данные об остатках';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_STORE_DESCRIPTION'] = '
Теперь выберите поля, из&nbsp;которых маркетплейс будет получать данные об&nbsp;остатках ваших товаров:
<ol>
<li>Общее количество товаров из&nbsp;каталога указано в&nbsp;разделе &laquo;Контент&raquo; &rarr; Торговые предложения &rarr; каждый товар из&nbsp;вашего <nobr>прайс-листа</nobr> на&nbsp;маркетплейсе &rarr; вкладка &laquo;Торговый каталог&raquo; &rarr; вкладка &laquo;Параметры&raquo; &rarr; поле &laquo;Доступное количество&raquo;<br /><br /></li>
<li>Если вы&nbsp;добавили в&nbsp;<nobr>&laquo;1С-Битрикс&raquo;</nobr> ваши склады, вы&nbsp;можете выбрать, остатки на&nbsp;каких складах сделать доступными для заказа на&nbsp;маркетплейсе. Но&nbsp;отгружать заказы из&nbsp;маркетплейса с&nbsp;того склада, который вы&nbsp;указали в&nbsp;личном кабинете. Доступное количество товаров на&nbsp;ваших складах указано в&nbsp;разделе &laquo;Контент&raquo; &rarr; Торговые предложения &rarr; каждый товар из&nbsp;вашего <nobr>прайс-листа</nobr> на&nbsp;маркетплейсе &rarr; вкладка &laquo;Торговый каталог&raquo; &rarr; вкладка &laquo;Склады&raquo; &rarr; поле &laquo;Количество товара&raquo; напротив каждого склада.</li>
</ol>
Ваши данные об&nbsp;остатках должны быть актуальными, чтобы на&nbsp;витрине маркетплейса отображались только товары в&nbsp;наличии.
';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_PRICE_SOURCE'] = 'Выбор цены';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_PRICE_SOURCE_NO_VALUE'] = 'По умолчанию';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_PRICE_TYPE'] = 'Типы цен';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_PRODUCT_PRICE_DISCOUNT'] = 'Рассчитывать скидки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_GROUP_STATUS_IN'] = 'Маркетплейс может передавать вам статусы:';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_GROUP_STATUS_OUT'] = 'Вы можете передавать маркетплейсу статусы:';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_SYNC_STATUS_OUT'] = 'Загружать изменения из Маркета';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_SYNC_STATUS_OUT_HELP'] = 'Если изменяете статусы в&nbsp;приложении или личном кабинете Маркета, отметьте опцию, чтобы в&nbsp;заказе 1С-Битрикс автоматически были установлены статусы из&nbsp;группы &laquo;#GROUP#&raquo;';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_TAB_STATUS'] = 'Статусы заказов';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_LOG_LEVEL_ERROR'] = 'Ошибки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_LOG_LEVEL_WARNING'] = 'Предупреждения';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_LOG_LEVEL_INFO'] = 'Информация';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_LOG_LEVEL_DEBUG'] = 'Отладка';
$MESS['YANDEX_MARKET_TRADING_SERVICE_COMMON_OPTIONS_LOG_LEVEL_NO_VALUE'] = 'Отключить';