<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) { die(); }

use Bitrix\Main\Localization\Loc;
use Yandex\Market\Ui\UserField;

/** @var Yandex\Market\Components\AdminFormEdit $component */
/** @var array $fields */
/** @var array $arParams */
/** @var array $arResult */
/** @var bool $isActiveTab */

$paramFields = [];
$filterFields = [];
$commonFields = [];

foreach ($fields as $name)
{
	$field = $component->getField($name);
	$code = $field['FIELD_GROUP'] ?: $field['FIELD_NAME'];

	if (in_array($code, $arParams['PRODUCT_PARAM_FIELDS'], true))
	{
		$paramFields[] = $name;
	}
	else if (in_array($code, $arParams['PRODUCT_FILTER_FIELDS'], true))
	{
		$filterFields[] = $name;
	}
	else
	{
		$commonFields[] = $name;
	}
}

if (empty($paramFields))
{
	echo '<tr><td colspan="2">';
	CAdminMessage::ShowMessage(Loc::getMessage('YANDEX_MARKET_T_ADMIN_FORM_EDIT_NEED_SELECT_PRODUCT_MAP'));
	echo '</td></tr>';

	return;
}

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
if (!empty($paramFields))
{
	$specialFields = $paramFields;

	include __DIR__ . '/special-catalog-segment.php';
}

if (!empty($filterFields))
{
	$specialFields = $filterFields;

	include __DIR__ . '/special-product-filter.php';
}