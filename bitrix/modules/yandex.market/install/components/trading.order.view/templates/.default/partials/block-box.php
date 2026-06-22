<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Localization\Loc;
use Yandex\Market\Trading\Entity as TradingEntity;
use Yandex\Market\Ui\Assets;

/** @var array $arResult */
/** @var array $arParams */

if (isset($arResult['BASKET']['COLUMNS']['CIS']))
{
	Assets::loadMessages([
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_MODAL_TITLE',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_REQUIRED',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_SUMMARY_EMPTY',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_SUMMARY_WAIT',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_SUMMARY_OPTIONAL',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CIS_SUMMARY_READY',
	]);
}

if (isset($arResult['BASKET']['COLUMNS']['DIGITAL']))
{
	Assets::loadMessages([
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_REQUIRED',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_SUMMARY_WAIT',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_DIGITAL_SUMMARY_READY',
	]);
}

$baseInputName = 'YAMARKET_ORDER[BOX]';
$allowItemsEdit = isset($arResult['ORDER_ACTIONS'][TradingEntity\Operation\Order::ITEM]);
$allowBoxEdit = $allowItemsEdit && isset($arResult['ORDER_ACTIONS'][TradingEntity\Operation\Order::BOX]);
$columns = $arResult['BASKET']['COLUMNS'];
$columnsCount = count($arResult['BASKET']['COLUMNS']) + 1;

if ($allowItemsEdit)
{
	$columns['EDIT'] = '&nbsp;';
	++$columnsCount;

	include __DIR__ . '/basket-modal.php';

	Assets::loadMessages([
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_DELETE',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CANCEL_DELETE',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_SPLIT',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_CANCEL_SPLIT',
		'T_TRADING_ORDER_VIEW_BASKET_ITEM_PART',
		'T_TRADING_ORDER_VIEW_BOX_DELETE_PART_BY_MERGE',
	]);
}

if ($allowBoxEdit || $allowItemsEdit)
{
	?>
	<input type="hidden" name="YAMARKET_ORDER[BOX_INITIAL_COUNT]" value="<?= count($arResult['BOX']) ?>" />
	<input type="hidden" name="YAMARKET_ORDER[SHIPMENT_ID]" value="<?= isset($arResult['SHIPMENT'][0]['ID']) ? $arResult['SHIPMENT'][0]['ID'] : '' ?>" />
	<?php
}

?>
<h2 class="yamarket-section-title"><?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BOX_TITLE') ?></h2>
<div class="yamarket-basket-wrapper js-yamarket-order__field" data-plugin="OrderView.BoxCollection" data-name="BOX">
	<table class="yamarket-basket-table adm-s-order-table-ddi-table adm-s-bus-ordertable-option">
		<thead>
			<tr>
				<td class="tal">&nbsp;</td>
				<?php
				foreach ($columns as $columnTitle)
				{
					?>
					<td class="tal"><?= $columnTitle ?></td>
					<?php
				}
				?>
			</tr>
		</thead>
		<?php
		$boxIndex = 0;
		$boxCount = count($arResult['BOX']);

		foreach ($arResult['BOX'] as $box)
		{
			$boxInputName = sprintf($baseInputName . '[%s]', $boxIndex);
			$itemIndex = 0;
			$hasPreviousBox = ($boxIndex > 0);
			$hasNextBox = ($boxIndex < $boxCount - 1);
			$hasFewBasketItems = count($box['ITEMS']) > 1;

			?>
			<tbody class="js-yamarket-box" data-plugin="OrderView.Box">
				<tr></tr><?php // hack for bitrix css ?>
				<tr>
					<td class="yamarket-basket-box <?= !$allowBoxEdit && count($arResult['BOX']) === 1 ? 'is--hidden' : '' ?>" colspan="<?= $columnsCount ?>">
						<span class="js-yamarket-box__title">
							<?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BOX') ?>
							<span class="js-yamarket-box__number">&numero;<?= $box['NUMBER'] ?></span>
						</span>
						<?php
						if ($allowBoxEdit)
						{
							?>
							<button
								class="yamarket-box-delete js-yamarket-box__delete"
								type="button"
								title="<?= Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BOX_DELETE') ?>"
								<?= count($arResult['BOX']) <= 1 ? 'disabled' : '' ?>
							>
								<svg class="yamarket-box-delete__icon" viewBox="0 0 14 15">
									<path d="M13.126 1.406v.933a.47.47 0 0 1-.47.469H.47A.47.47 0 0 1 0 2.338v-.932c0-.259.21-.47.47-.47h3.512L4.258.39A.76.76 0 0 1 4.884 0h3.353c.262.014.5.16.63.389l.277.548h3.516a.47.47 0 0 1 .466.47zM.939 3.75h11.25l-.626 9.932A1.424 1.424 0 0 1 10.16 15H2.963a1.424 1.424 0 0 1-1.4-1.318L.94 3.75z" fill="currentColor" fill-rule="evenodd"/>
								</svg>
							</button>
							<?php
						}
						?>
					</td>
				</tr>
			</tbody>
			<tbody class="js-yamarket-box__child" data-plugin="OrderView.Basket" data-name="ITEMS">
				<tr></tr><?php // hack for bitrix css ?>
				<?php
				foreach ($box['ITEMS'] as $item)
				{
					$itemInputName = sprintf($boxInputName . '[ITEMS][%s]', $itemIndex);
					$isItemPartial = !empty($item['PARTIAL_COUNT']['CURRENT']);
					$basketItem = $arResult['BASKET']['ITEMS'][$item['BASKET_KEY']];

					?>
					<tr
						class="bdb-line yamarket-basket-item <?= $isItemPartial ? 'is--partial' : '' ?> js-yamarket-basket-item"
						data-plugin="OrderView.BasketItem"
						data-id="<?= $item['ID'] ?>"
					>
						<td class="tal for--move"><?php
							?><input
								class="js-yamarket-basket-item__data is--persistent"
								type="hidden"
								name="<?= $itemInputName . '[ID]' ?>"
								value="<?= htmlspecialcharsbx($item['ID']) ?>"
								data-name="ID"
							/><?php

							?><input
								class="js-yamarket-basket-item__data is--persistent"
								type="hidden"
								name="<?= $itemInputName . '[INITIAL_BOX]' ?>"
								value="<?= $boxIndex ?>"
								data-name="INITIAL_BOX"
							/><?php

							if ($allowBoxEdit)
							{
								?><button class="yamarket-basket-move js-yamarket-basket-item__down" type="button" <?= $isItemPartial || (!$hasFewBasketItems && !$hasNextBox && $item['COUNT'] <= 1) ? 'disabled' : '' ?>>
									<svg  class="yamarket-basket-move__icon" viewBox="0 0 11.314 16.657">
										<path d="M4.657 0h2v12.828l3.242-3.242L11.314 11l-5.657 5.657L0 11l1.414-1.414 3.243 3.242V0Z" fill="currentColor"/>
									</svg>
								</button><?php
								?><button class="yamarket-basket-move js-yamarket-basket-item__up" type="button" <?= $isItemPartial || !$hasPreviousBox ? 'disabled' : '' ?>>
									<svg class="yamarket-basket-move__icon" viewBox="0 0 11.314 17.416">
										<path d="M11.314 5.67 9.896 7.081l-3.255-3.27-.014 13.605-2-.002.014-13.568-3.23 3.215L0 5.644 5.67 0l5.644 5.67Z" fill="currentColor"/>
									</svg>
								</button><?php
							}
							else
							{
								echo $basketItem['INDEX'];
							}
						?></td>
						<?php
						foreach ($columns as $column => $columnTitle)
						{
							$columnValue = null;
							$columnFormatted = '&mdash;';

							if (isset($item[$column]))
							{
								$columnValue = $item[$column];
								$columnFormatted = $columnValue;
							}
							else if (isset($basketItem[$column]))
							{
								$columnFormattedKey = $column . '_FORMATTED';

								$columnValue = $basketItem[$column];
								$columnFormatted = isset($basketItem[$columnFormattedKey]) ? $basketItem[$columnFormattedKey] : $columnValue;
							}

							switch ($column)
							{
								case 'CIS':
									include __DIR__ . '/basket-column-cis.php';
									break;

								case 'DIGITAL':
									include __DIR__ . '/basket-column-digital.php';
									break;

								case 'COUNT':
									?>
									<td class="tal for--<?= mb_strtolower($column) ?>">
										<?php
										if ($allowItemsEdit)
										{
											?>
											<input
												class="js-yamarket-basket-item__data is--persistent"
												type="hidden"
												name="<?= $itemInputName . '[INITIAL_COUNT]' ?>"
												value="<?= (float)$columnValue ?>"
												data-name="INITIAL_COUNT"
											/>
											<input
												class="js-yamarket-basket-item__data"
												type="hidden"
												name="<?= $itemInputName . '[OFFSET]' ?>"
												value="<?= (float)$item['OFFSET'] ?>"
												data-name="OFFSET"
											/>
											<input
												class="adm-input yamarket-basket-item__count js-yamarket-basket-item__data"
												type="number"
												name="<?= $itemInputName . '[COUNT]' ?>"
												value="<?= (float)$columnValue ?>"
												min="1"
												max="<?= (float)$columnValue ?>"
												step="1"
												data-name="COUNT"
											/>
											<?php
											foreach (['CURRENT', 'TOTAL'] as $partialKey)
											{
												?>
												<input
													class="js-yamarket-basket-item__data"
													type="hidden"
													name="<?= sprintf('%s[PARTIAL_%s]', $itemInputName, $partialKey) ?>"
													value="<?= isset($item['PARTIAL_COUNT'][$partialKey]) ? (int)$item['PARTIAL_COUNT'][$partialKey] : '' ?>"
													data-name="PARTIAL_<?= $partialKey ?>"
												/>
												<?php
											}
											?>
											<div class="yamarket-basket-item__part js-yamarket-basket-item__data" data-name="PARTIAL_NAME"><?php
												if (!empty($item['PARTIAL_COUNT']['CURRENT']))
												{
													echo Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_PART') . sprintf(
														' %s/%s',
														$item['PARTIAL_COUNT']['CURRENT'],
														$item['PARTIAL_COUNT']['TOTAL']
													);
												}
											?></div>
											<?php
										}
										else
										{
											echo sprintf(
												'%s %s',
												(float)$columnValue,
												Loc::getMessage('YANDEX_MARKET_T_TRADING_ORDER_VIEW_BASKET_ITEM_MEASURE')
											);
										}
										?>
									</td>
									<?php
									break;

								case 'EDIT':
									?>
									<td class="tal for--<?= mb_strtolower($column) ?>">
										<?php
										if ($allowItemsEdit)
										{
											?>
											<input class="js-yamarket-basket-item__data" type="hidden" name="<?= $itemInputName . '[DELETE]' ?>" value="" data-name="DELETE" />
											<?php
										}
										?>
										<button class="yamarket-basket-more js-yamarket-basket-item__more" type="button">
											<svg class="yamarket-basket-more__icon" viewBox="0 0 14 3.5">
												<path d="M3.5 1.75C3.5 2.71653 2.7165 3.5 1.75 3.5C0.783501 3.5 0 2.71653 0 1.75C0 0.783475 0.783501 0 1.75 0C2.7165 0 3.5 0.783475 3.5 1.75L3.5 1.75Z" fill="currentColor" />
												<path d="M8.75 1.75C8.75 2.71653 7.96653 3.5 7 3.5C6.03347 3.5 5.25 2.71653 5.25 1.75C5.25 0.783475 6.03347 0 7 0C7.96653 0 8.75 0.783475 8.75 1.75L8.75 1.75Z" fill="currentColor" />
												<path d="M12.25 3.5C13.2165 3.5 14 2.71653 14 1.75C14 0.783475 13.2165 0 12.25 0C11.2835 0 10.5 0.783475 10.5 1.75C10.5 2.71653 11.2835 3.5 12.25 3.5L12.25 3.5Z" fill="currentColor" />
											</svg>
										</button>
									</td>
									<?php
									break;

								case 'SUBSIDY':
									$hasPromos = !empty($basketItem['PROMOS']);

									?>
									<td class="tal for--<?= mb_strtolower($column) ?>">
										<?php
										if ($columnValue !== null || !$hasPromos)
										{
											echo $columnFormatted;
										}

										if ($hasPromos)
										{
											foreach ($basketItem['PROMOS'] as $promo)
											{
												echo sprintf('<div>%s</div>', $promo);
											}
										}
										?>
									</td>
									<?php
									break;

								default:
									?>
									<td class="tal for--<?= mb_strtolower($column) ?> js-yamarket-basket-item__data" data-name="<?= $column ?>"><?= $columnFormatted ?></td>
									<?php
									break;
							}
						}
						?>
					</tr>
					<?php

					++$itemIndex;
				}
				?>
			</tbody>
			<?php

			++$boxIndex;
		}

		if (!empty($arResult['BASKET']['SUMMARY']))
		{
			?>
			<tfoot>
			<tr>
				<td class="yamarket-basket-summary js-yamarket-order__area" data-type="basketSummary" colspan="<?= $columnsCount ?>">
					<?php
					$isFirstSummaryItem = true;

					foreach ($arResult['BASKET']['SUMMARY'] as $summaryItem)
					{
						echo $isFirstSummaryItem ? '' : '<br />';
						echo $summaryItem['NAME'] . ': ' . $summaryItem['VALUE'];

						$isFirstSummaryItem = false;
					}
					?>
				</td>
			</tr>
			</tfoot>
			<?php
		}
		?>
	</table>
</div>
