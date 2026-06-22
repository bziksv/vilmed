<?php
namespace Yandex\Market\Ui\Iblock;

use Bitrix\Main;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Reference\Event;
use Yandex\Market\Ui;

/** @noinspection PhpUnused */
class CategoryMassiveEdit extends Event\Regular
{
	use Concerns\HasMessage;

	public static function getHandlers()
	{
		return [
			[
				'module' => 'main',
				'event' => 'OnAdminListDisplay',
				'sort' => 1000,
			],
		];
	}

	/** @noinspection PhpUnused */
	public static function OnAdminListDisplay(\CAdminList $list)
	{
		if (!self::isTargetList($list)) { return; }

		$iblockId = (int)Main\Application::getInstance()->getContext()->getRequest()->get('IBLOCK_ID');
		$categoryProperty = self::categoryProperty($iblockId);

		if ($categoryProperty === null) { return; }

		Ui\Extension::load('@Ui.Admin.MassiveEdit');

		$list->arActions['ym_change_category'] = [
			'name' => $categoryProperty['NAME'],
			'action' => sprintf(
				'BX.YandexMarket.Ui.Admin.MassiveEdit.open("%s", "%s", %s)',
				$list instanceof \CAdminUiList ? 'Ui' : 'Table',
				htmlspecialcharsbx($list->table_id),
				Main\Web\Json::encode([
					'url' => self::componentUrl(),
					'iblockId' => $iblockId,
					'prefixSelected' => self::isOnlySectionList($list) ? 'S' : null,
					'lang' => [
						'MODAL_TITLE' => self::getMessage('MODAL_TITLE'),
						'FOR_ALL_NOT_SUPPORTED' => self::getMessage('FOR_ALL_NOT_SUPPORTED'),
					],
				])
			),
		];
	}

	private static function isTargetList(\CAdminList $list)
	{
		return (
			mb_strpos($list->table_id, 'tbl_iblock_element_') === 0 // elements
			|| mb_strpos($list->table_id, 'tbl_iblock_list_') === 0 // sections and elements
			|| mb_strpos($list->table_id, 'tbl_product_admin_') === 0 // catalog product
			|| self::isOnlySectionList($list)
		);
	}

	private static function isOnlySectionList(\CAdminList $list)
	{
		return (
			mb_strpos($list->table_id, 'tbl_iblock_section_') === 0
			|| mb_strpos($list->table_id, 'tbl_catalog_section_') === 0
		);
	}

	private static function categoryProperty($iblockId)
	{
		return
			CategoryValue\FieldRepository::field($iblockId)
			?: CategoryValue\PropertyRepository::property($iblockId);
	}

	private static function componentUrl()
	{
		$componentName = 'yandex.market:admin.massive.category';
		$component = new \CBitrixComponent();

		if ($component->initComponent($componentName))
		{
			$path = $component->getPath();
		}
		else
		{
			$path = '/bitrix/components/' . implode('/', explode(':', $componentName));
		}

		return $path . '/ajax.php?' . http_build_query([
			'lang' => LANGUAGE_ID,
		]);
	}
}