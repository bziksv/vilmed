<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

/** @var Yandex\Market\Components\AdminFormEdit $component */
/** @var array $fields */
/** @var array $arParams */

$productFields = [];
$commonFields = [];

foreach ($fields as $name)
{
	$field = $component->getField($name);
	$code = $field['FIELD_GROUP'] ?: $field['FIELD_NAME'];

	if (in_array($code, $arParams['PRODUCT_FILTER_FIELDS'], true))
	{
		if (!empty($field['DEPEND_HIDDEN'])) { continue; }

		$productFields[] = $name;
	}
	else
	{
		$commonFields[] = $name;
	}
}

if (empty($productFields))
{
	$fields = $commonFields;

	include __DIR__ . '/tab-default.php';
}
else
{
	if (!empty($commonFields))
	{
		$fields = $commonFields;

		?>
		<tr>
			<td class="b-form-section-holder" colspan="2">
				<div class="b-form-section fill--primary position--top">
					<table class="adm-detail-content-table edit-table" width="100%">
						<?php
						include __DIR__ . '/tab-default.php';
						?>
					</table>
				</div>
			</td>
		</tr>
		<?php
	}

	$specialFields = $productFields;

	include __DIR__ . '/special-product-filter.php';
}