<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market\Ui\Assets;
use Yandex\Market\Ui\UserField;
use Bitrix\Main\Localization\Loc;

Assets::loadMessages([
	'T_TRADING_ORDER_VIEW_BASKET_ITEM_SPLIT_TITLE',
	'T_TRADING_ORDER_VIEW_BASKET_ITEM_MOVE_TITLE',
	'T_TRADING_ORDER_VIEW_BASKET_CONFIRM_MODAL_TITLE',
	'T_TRADING_ORDER_VIEW_BASKET_CONFIRM_MODAL_TITLE_WITH_REASON',
	'T_TRADING_ORDER_VIEW_BASKET_CONFIRM_ITEM_CHANGE',
]);

?>
<script type="text/html" id="yamarket-basket-split-modal">
	<table class="edit-table" width="100%">
		<tr>
			<td class="adm-detail-content-cell-l" width="40%" align="right" valign="middle">
				<?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_SPLIT_COUNT') ?>
			</td>
			<td class="adm-detail-content-cell-r" width="60%">
				<input class="adm-input" type="number" name="SPLIT_COUNT" value="2" min="2" max="30" />
			</td>
		</tr>
	</table>
</script>
<script type="text/html" id="yamarket-basket-move-modal">
	<table class="edit-table" width="100%">
		<tr>
			<td class="adm-detail-content-cell-l" width="40%" align="right" valign="middle">
				<?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_MOVE_COUNT') ?>
			</td>
			<td class="adm-detail-content-cell-r" width="60%">
				<input class="adm-input" type="number" name="MOVE_COUNT" min="1" />
			</td>
		</tr>
		<tr>
			<td class="adm-detail-content-cell-l" width="40%" align="right" valign="middle">
				<?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_MOVE_BOX') ?>
			</td>
			<td class="adm-detail-content-cell-r" width="60%">
				<select name="MOVE_BOX">
					<option value=""><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_MOVE_BOX_NEW') ?></option>
				</select>
			</td>
		</tr>
	</table>
</script>
<div class="js-yamarket-order__field" data-plugin="OrderView.BasketConfirmSummary" data-name="BASKET_CONFIRM">
	<div class="is--hidden js-yamarket-basket-confirm-summary__modal">
		<div class="js-yamarket-basket-confirm-summary__field" data-plugin="OrderView.BasketConfirmForm">
			<input class="js-yamarket-basket-confirm-form__input" type="hidden" name="YAMARKET_ORDER[BASKET_CONFIRM][ALLOW_REMOVE]" value="" data-name="ALLOW_REMOVE" />
			<table class="edit-table" width="100%">
				<?php
				if (!empty($arResult['ITEMS_CHANGE_REASON']))
				{
					?>
					<tr>
						<td class="adm-detail-content-cell-l" width="40%" align="right" valign="middle">
							<strong><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_CONFIRM_REASON') ?></strong>
						</td>
						<td class="adm-detail-content-cell-r" width="60%">
							<?= UserField\View\Select::getControl($arResult['ITEMS_CHANGE_REASON'], null, [
								'class' => 'js-yamarket-basket-confirm-form__input',
								'name' => 'YAMARKET_ORDER[BASKET_CONFIRM][REASON]',
								'data-name' => 'REASON',
							]) ?>
						</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td class="adm-detail-content-cell-l" width="40%" align="right" valign="top">
						<strong><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_CONFIRM_PRODUCTS') ?></strong>
					</td>
					<td class="adm-detail-content-cell-r js-yamarket-basket-confirm-form__products" width="60%"></td>
				</tr>
			</table>
			<?php
			echo BeginNote();
			echo Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_CONFIRM_FORM_INTRO');
			echo EndNote();
			?>
		</div>
	</div>
</div>