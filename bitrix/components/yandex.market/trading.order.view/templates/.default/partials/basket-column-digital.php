<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Yandex\Market;
use Bitrix\Main\Type;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Localization\Loc;

/** @var array $basketItem */
/** @var string $column */
/** @var string $itemInputName */

$itemDigital = isset($basketItem['DIGITAL']) ? array_values($basketItem['DIGITAL']) : [];
$itemDigitalFilledCount = count(array_filter(array_column($itemDigital, 'CODE')));
$itemDigitalStatus = ($itemDigitalFilledCount >= $basketItem['COUNT'] ? 'READY' : 'WAIT');

?>
<td
	class="tal for--<?= Market\Data\TextString::toLower($column); ?> js-yamarket-basket-item__field"
	data-plugin="OrderView.BasketItemDigitalSummary"
	data-name="DIGITAL"
	data-count="<?= (int)$basketItem['COUNT'] ?>"
	data-lang='<?= Json::encode([
		'MODAL_TITLE' => $basketItem['NAME'],
	]) ?>'
>
	<a class="yamarket-digital-summary js-yamarket-basket-item-digital__summary" href="#" data-status="<?= $itemDigitalStatus ?>"><?php
		echo Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_SUMMARY_' . $itemDigitalStatus);
	?></a>
	<div class="is--hidden js-yamarket-basket-item-digital__modal">
		<table class="js-yamarket-basket-item-digital__field" data-plugin="OrderView.BasketItemDigital" width="100%">
			<?php
			// items

			for ($digitalIndex = 0; $digitalIndex < $basketItem['COUNT']; ++$digitalIndex)
			{
				$digitalInputName = sprintf($itemInputName . '[DIGITAL][ITEM][%s]', $digitalIndex);
				$digitalValue = isset($itemDigital[$digitalIndex]) ? $itemDigital[$digitalIndex] : null;

				if ($basketItem['COUNT'] > 1)
				{
					?>
					<tr class="heading">
						<td colspan="2"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_GROUP', [ '#NUMBER#' => $digitalIndex + 1 ]) ?></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td class="adm-detail-content-cell-l" width="40%"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_CODE') ?></td>
					<td class="adm-detail-content-cell-r" width="60%">
						<input
							class="yamarket-digital-table__input js-yamarket-basket-item-digital__input"
							type="text"
							name="<?= $digitalInputName . '[CODE]' ?>"
							value="<?= isset($digitalValue['CODE']) ? htmlspecialcharsbx($digitalValue['CODE']) : '' ?>"
							size="30"
							data-name="<?= sprintf('[ITEM][%s][CODE]', $digitalIndex) ?>"
						/>
					</td>
				</tr>
				<tr>
					<td class="adm-detail-content-cell-l" width="40%"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_ACTIVATE_TILL') ?></td>
					<td class="adm-detail-content-cell-r" width="60%">
						<?php
						$activateTillValue = isset($digitalValue['ACTIVATE_TILL']) && $digitalValue['ACTIVATE_TILL'] instanceof Type\DateTime ? $digitalValue['ACTIVATE_TILL']->toString() : null;
						?>
						<div class="adm-input-wrap adm-input-wrap-calendar">
							<input
								class="adm-input adm-input-calendar yamarket-digital-table__input js-yamarket-basket-item-digital__input"
								type="text"
								name="<?= $digitalInputName . '[ACTIVATE_TILL]' ?>"
								value="<?= htmlspecialcharsbx($activateTillValue) ?>"
								data-name="<?= "[ITEM][{$digitalIndex}][ACTIVATE_TILL]" ?>"
								size="23"
							/>
							<span class="adm-calendar-icon" onclick="BX.calendar({node:this, field: this.previousElementSibling, bTime: false, bHideTime: true});"></span>
						</div>
					</td>
				</tr>
				<?php
			}

			// additional

			if ($basketItem['COUNT'] > 1)
			{
				?>
				<tr class="heading">
					<td colspan="2"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_ADDITIONAL') ?></td>
				</tr>
				<?php
			}
			?>
			<tr>
				<td class="adm-detail-content-cell-l" width="40%" valign="top"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_SLIP') ?></td>
				<td class="adm-detail-content-cell-r" width="60%">
					<textarea
						class="yamarket-digital-digital__input js-yamarket-basket-item-digital__input"
						name="<?= $itemInputName . '[DIGITAL][SLIP]' ?>"
						rows="5"
						cols="40"
						data-name="SLIP"
					><?= isset($itemDigital[0]['SLIP']) ? htmlspecialcharsbx($itemDigital[0]['SLIP']) : '' ?></textarea>
				</td>
			</tr>
		</table>
	</div>
</td>
