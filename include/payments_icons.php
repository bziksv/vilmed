<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
// VILMED: способы оплаты в подвале — современные иконки (как на карточке товара:
// безналичный расчёт / QR-СБП / банковские карты) вместо устаревших картинок
// VISA/MasterCard/«квитанция» из инфоблока 11.?>
<div class="payment_methods vilmed-footer-pay">
	<div class="h3">Способы оплаты</div>
	<div class="vilmed-footer-pay__list">
		<a class="vilmed-footer-pay__item" href="/payments/" title="Безналичный расчёт по счёту">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 2 7v2h20V7L12 2zM4 11v7H3v2h18v-2h-1v-7h-2v7h-3v-7h-2v7H9v-7H7v7H6v-7H4z"/></svg>
			<span>Безнал</span>
		</a>
		<a class="vilmed-footer-pay__item" href="/payments/" title="Оплата по QR-коду (СБП)">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm-2 8h8v8H3v-8zm2 2v4h4v-4H5zM13 3h8v8h-8V3zm2 2v4h4V5h-4zm-2 8h2v2h-2v-2zm2 2h2v2h-2v-2zm2-2h2v2h-2v-2zm0 4h2v2h-2v-2zm2-2h2v2h-2v-2zm0 4h2v2h-2v-2z"/></svg>
			<span>QR (СБП)</span>
		</a>
		<a class="vilmed-footer-pay__item" href="/payments/" title="Банковские карты">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
			<span>Карты</span>
		</a>
	</div>
</div>
