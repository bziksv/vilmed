<?php

$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_COMMON'] = 'Общие настройки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_BUSINESS_ID'] = 'Номер бизнеса';

$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_ORDER'] = 'Оплата и доставка';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_ORDER_DESCRIPTION'] = 'Маркетплейс не&nbsp;будет использовать информацию из&nbsp;следующих полей. Но&nbsp;вам всё равно нужно выбрать значения, чтобы заказы с&nbsp;маркетплейса можно было оформить в&nbsp;<nobr>&laquo;1С-Битрикс&raquo;</nobr>. Чтобы обрабатывать заказы без лишних действий, укажите стандартные варианты системы.';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PAY_SYSTEM'] = 'Платежная система (#TYPE#)';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_CASHBOX_CHECK'] = 'Печать чеков в 1С-Битрикс';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_CASHBOX_CHECK_HELP'] = 'Маркет <a href="https://yandex.ru/support/market/order/documents.html#documents__receipt-receive" target="_blank">формирует и высылает</a> кассовый чек.';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_BASKET_SUBSIDY_INCLUDE'] = 'Включать субсидии в стоимость товара';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_SUBSIDY_PAY_SYSTEM_ID'] = 'Отдельная оплата субсидии';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_SUBSIDY_PAY_SYSTEM_ID_HELP'] = 'При приеме заказа создавать <a href="https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=42&LESSON_ID=7296&LESSON_PATH=3912.4580.4836.7292.7296" target="_blank">оплату</a> на сумму субсидий маркетплейса, чтобы разделить платеж пользователя и компенсацию.';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_SUBSIDY_PAY_SYSTEM_ID_NO_VALUE'] = '---Не создавать---';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_DELIVERY_ID'] = 'Служба доставки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_ORDER_PROPERTY'] = 'Информация о заказе';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_PROPERTY_BUYER'] = 'Покупатель';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_COURIER_PROPERTY'] = 'Курьер (Экспресс)';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_STORE'] = 'Источники данных о&nbsp;товарах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_STATUS'] = 'Статусы заказов';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_STATUS_NOTE'] = '
<p>По умолчанию статусам заказов на маркетплейсе соответствуют стандартные статусы заказов в &laquo;1С-Битрикс&raquo;.</p>
<p>Менять эти настройки нужно только в случае, если вы используете кастомные статусы вместо стандартных. Также вы можете указать соответствия статусам DELIVERY и PICKUP, если хотите отслеживать передачу заказов в доставку и их прибытие в пункты самовывоза в общем списке заказов.</p>
';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_TRACK_RETURN'] = 'Отслеживать получение возврата';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_TRACK_RETURN_HELP'] = 'После выдачи возврата магазину для заказа будет установлен статус, выбранный для&nbsp;Заказ&nbsp;отменен.';

$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_SELF_TEST'] = 'Самопроверка';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_STOCKS_BEHAVIOR'] = 'Учет остатков';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_STOCKS_BEHAVIOR_HELP'] = '
<p>При запросе остатков будет учтены резервы по&nbsp;заказам, принятым в&nbsp;1С-Битрикс.</p>
<ul>
<li>Только доступные&nbsp;&mdash; за&nbsp;вычетом заказов, ожидающих резервирования.</li>
<li>Без изменений&nbsp;&mdash; без дополнительных изменений.</li>
</ul>
<p>Обратите внимание, при резервировании остаток списывается только из&nbsp;&laquo;Общего количества&raquo;, остаток на&nbsp;складе уменьшается после отгрузки заказа.</p>
<p>Поэтому при передаче остатков со&nbsp;склада, для варианта &laquo;Только доступные&raquo; будет дополнительно вычтен остаток по&nbsp;зарезервированным заказам.</p>
';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_STOCKS_BEHAVIOR_PLAIN'] = 'Без изменений';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_STOCKS_BEHAVIOR_ONLY_AVAILABLE'] = 'Только доступные';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_PUSH_DATA'] = 'Отправка изменений';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_PUSH_STOCKS'] = 'Автоматически передавать данные об остатках';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_PUSH_STOCKS_HELP'] = 'Агент проверит измененные товары и отправит новые остатки маркетплейсу';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_PUSH_PRICES'] = 'Автоматически передавать данные о ценах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_USE_PUSH_PRICES_HELP'] = 'Агент проверит измененные цены и отправит маркетплейсу';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRICES_MODE'] = 'Устанавливать цену';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRICES_MODE_HELP'] = 'Если используете только основную цену на Маркете, выберите вариант &laquo;Для бизнеса&raquo;.';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRICES_MODE_CAMPAIGN'] = 'Для магазина';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRICES_MODE_BUSINESS'] = 'Для бизнеса';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRODUCT_FEED'] = 'Проверять присутствие в прайс-листах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_PRODUCT_FEED_HELP'] = 'При формировании запроса к маркетплейсу будут исключены товары, отсутствующие в выбранных прайс-листах';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_GROUP_STATUS_SHIPMENT'] = 'Изменять заказ &laquo;1С-Битрикс&raquo; при&nbsp;отгрузке:';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_STATUS_SHIPMENT_CONFIRM'] = 'Подтверждение отгрузки';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_ORDER_ACCEPT_WITH_ERRORS'] = 'Принимать заказы с ошибкой';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_ORDER_ACCEPT_WITH_ERRORS_HELP'] = '
<p>Если сталкиваетсь с&nbsp;проблемой обновления остатков или доступа к&nbsp;товарам 1С-Битрикс, на&nbsp;время технических проблем отметьте данную опцию.</p>
<p>Заказ будет принят, даже если не&nbsp;удалось найти остаток или доступ к&nbsp;товару запрещен. Администратору будет необходимо вручную добавить товар в&nbsp;отгрузку заказа 1С-Битрикс.</p>
';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_ORDER'] = 'Параметры заказа';
$MESS['YANDEX_MARKET_TRADING_SERVICE_MARKETPLACE_OPTIONS_TAB_DELIVERY_AND_PAYMENT'] = 'Оплата и доставка';
