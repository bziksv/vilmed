<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Yandex\Market;
use Yandex\Market\Ui\UserField\Helper\Attributes;

/** @var array $arParams */
/** @var array $arResult */
/** @var CMain $APPLICATION */
/** @var $component \Yandex\Market\Components\AdminFormEdit */

if (!empty($arResult['CONTEXT_MENU']))
{
	$context = new CAdminContextMenu($arResult['CONTEXT_MENU']);
	$context->Show();
}

if ($component->hasErrors())
{
	$component->showErrors();
}

$component->showMessages();

$tabControl = new \CAdminTabControl($arParams['FORM_ID'], $arResult['TABS'], false, true);

include __DIR__ . '/check-javascript.php';

if ($arParams['USE_METRIKA'] === 'Y')
{
	Market\Metrika::load();
}

Market\Ui\Library::load('jquery');

Market\Ui\Assets::loadPlugin('admin', 'css');
Market\Ui\Assets::loadPlugin('grain', 'css');
Market\Ui\Assets::loadPlugin('base', 'css');

Market\Ui\Assets::loadPluginCore();
Market\Ui\Assets::loadFieldsCore();

$formAttributes = [
	'class' => [ 'yamarket-form' ],
	'method' => 'POST',
	'action' => !empty($arParams['~FORM_ACTION_URI']) ? $arParams['~FORM_ACTION_URI'] : $APPLICATION->GetCurPageParam(),
	'enctype' => 'multipart/form-data',
	'novalidate' => true,
	'data-plugin' => [],
];

if (!isset($arParams['NOTIFY_UNSAVED']) || $arParams['NOTIFY_UNSAVED'] !== 'N')
{
	Market\Ui\Extension::load('@Ui.Form.NotifyUnsaved');

	$formAttributes['class'][] = 'js-plugin';
	$formAttributes['data-plugin'][] = 'Ui.Form.NotifyUnsaved';
	$formAttributes['data-changed'] = $arResult['HAS_REQUEST'] ? 'true' : null;
}

if (!empty($arParams['AJAX_RELOADER']))
{
	Market\Ui\Extension::load('@Ui.Form.AjaxReloader');

	$formAttributes['class'][] = 'js-plugin';
	$formAttributes['data-plugin'][] = 'Ui.Form.AjaxReloader';
}

?>
<form <?= Attributes::stringify($formAttributes) ?>>
	<?php
	if ($arParams['FORM_BEHAVIOR'] === 'steps')
	{
		?>
		<input type="hidden" name="STEP" value="<?= $arResult['STEP'] ?>" />
		<?php
	}

	$tabControl->Begin();

	echo bitrix_sessid_post();

	foreach ($arResult['TABS'] as $tab)
	{
		$isTabAjaxReloaderTarget = isset($arResult['AJAX_RELOADER_TARGET'][$tab['DIV']]);
		$isActiveTab = ($arParams['FORM_BEHAVIOR'] !== 'steps' || $tab['STEP'] === $arResult['STEP']);
		$tabLayout = $tab['LAYOUT'] ?: 'default';
		$fields = $tab['FIELDS'];

		if ($isTabAjaxReloaderTarget)
		{
			ob_start();
		}

		$tabControl->BeginNextTab([ 'showTitle' => false ]);

		if ($isActiveTab && isset($tab['DATA']['METRIKA_GOAL']))
		{
			Market\Metrika::reachGoal($tab['DATA']['METRIKA_GOAL']);
		}

		include __DIR__ . '/hidden.php';
		include __DIR__ . '/tab-' . $tabLayout . '.php';

		$tabControl->EndTab();

		if ($isTabAjaxReloaderTarget)
		{
			$arResult['AJAX_RELOADER_CONTENT'][$tab['DIV']] = ob_get_flush();
		}
	}

	$tabControl->Buttons();

	include __DIR__ . '/buttons.php';

	$tabControl->End();
	?>
</form>
<?php
if ($arParams['FORM_BEHAVIOR'] === 'steps')
{
	?>
	<script>
		<?php
		foreach ($arResult['TABS'] as $tab)
		{
			if ($tab['STEP'] === $arResult['STEP']) { continue; }

			echo "{$arParams['FORM_ID']}.DisableTab('{$tab['DIV']}');";
		}
		?>
	</script>
	<?php
}
