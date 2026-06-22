<?php

$MESS['YANDEX_MARKET_CATALOG_SEGMENT_STOCKS_TAG_COUNT_SETTINGS_PACK_RATIO'] = 'Коэффициент упаковки';
$MESS['YANDEX_MARKET_CATALOG_SEGMENT_STOCKS_TAG_COUNT_SETTINGS_PACK_RATIO_HELP'] = 'Выберите свойство, в котором храните количество единиц в упаковке. Остаток товара будет разделен на значение выбранного коэффициента.';
$MESS['YANDEX_MARKET_CATALOG_SEGMENT_STOCKS_TAG_COUNT_SETTINGS_USE_RESERVE'] = 'Только доступные';
$MESS['YANDEX_MARKET_CATALOG_SEGMENT_STOCKS_TAG_COUNT_SETTINGS_USE_RESERVE_HELP'] = '
<p>При запросе остатков будет учтены резервы по&nbsp;заказам, принятым в&nbsp;1С-Битрикс.</p>
<ul>
<li>Только доступные&nbsp;&mdash; за&nbsp;вычетом заказов, ожидающих резервирования.</li>
<li>Без изменений&nbsp;&mdash; без дополнительных изменений.</li>
</ul>
<p>Обратите внимание, при резервировании остаток списывается только из&nbsp;&laquo;Общего количества&raquo;, остаток на&nbsp;складе уменьшается после отгрузки заказа.</p>
<p>Поэтому при передаче остатков со&nbsp;склада, для варианта &laquo;Только доступные&raquo; будет дополнительно вычтен остаток по&nbsp;зарезервированным заказам.</p>
';