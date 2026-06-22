<?php
namespace Yandex\Market\Migration\V285;

use Yandex\Market\Config;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading;
use Yandex\Market\Ui\Admin\Path;
use Yandex\Market\Ui;

/** @noinspection PhpUnused */
class ReserveDeprecatedNotify
{
	use Concerns\HasMessage;

	public static function apply()
	{
		$tradings = static::tradingsWithReserveStocks();
		$links = static::compileLinks($tradings);

		if (empty($tradings)) { return; }

		$notify = self::getMessage('NOTIFY', null, '');

		if ($notify === '') { return; }

		\CAdminNotify::Add([
			'MODULE_ID' => Config::getModuleName(),
			'NOTIFY_TYPE' => \CAdminNotify::TYPE_NORMAL,
			'MESSAGE' => $notify . ' ' . $links,
			'TAG' => 'YAMARKET_DEPRECATE_RESERVE',
		]);
	}

	protected static function tradingsWithReserveStocks()
	{
		$result = [];

		$tradings = Trading\Setup\Model::loadList([
			'filter' => [
				'=ACTIVE' => Trading\Setup\Table::BOOLEAN_Y,
				'=TRADING_SERVICE' => Trading\Service\Manager::SERVICE_MARKETPLACE,
			],
		]);

		foreach ($tradings as $trading)
		{
			$options = $trading->wakeupService()->getOptions();

			if (!($options instanceof Trading\Service\Marketplace\Options)) { continue; }
			if (!$options->getValue('CAMPAIGN_ID')) { continue; }

			if ($options->getStocksBehavior() === Trading\Service\Marketplace\Options::STOCKS_WITH_RESERVE)
			{
				$result[] = $trading;
			}
		}

		return $result;
	}

	/**
	 * @noinspection HtmlUnknownTarget
	 * @param Trading\Setup\Model[] $tradings
	 */
	protected static function compileLinks(array $tradings)
	{
		if (empty($tradings)) { return []; }

		if (count($tradings) === 1)
		{
			return sprintf(
				'<a href="%s">%s</a>',
				static::setupLink(reset($tradings)),
				self::getMessage('LINK_ONE')
			);
		}

		$behaviors = array_unique(array_map(
			static function(Trading\Setup\Model $trading) { return $trading->getBehaviorCode(); },
			$tradings
		));

		if (count($behaviors) === count($tradings))
		{
			$links = array_map(
				static function(Trading\Setup\Model $trading) {
					return sprintf(
						'<a href="%s">%s</a>',
						static::setupLink($trading),
						$trading->getService()->getInfo()->getTitle('BEHAVIOR')
					);
				},
				$tradings
			);

			return implode(', ', $links);
		}

		$links = array_filter(array_map(
			static function(Trading\Setup\Model $trading) {
				$options = $trading->wakeupService()->getOptions();

				if (!($options instanceof Trading\Service\Marketplace\Options)) { return ''; }

				return sprintf(
					'<a href="%s">%s</a>',
					static::setupLink($trading),
					$options->getCampaignId()
				);
			},
			$tradings
		));

		return self::getMessage('LINK_FEW') . ' ' . implode(', ', $links);
	}

	/**
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpUndefinedNamespaceInspection
	 */
	protected static function setupLink(Trading\Setup\Model $trading)
	{
		return Path::getModuleUrl('trading_edit', [
			'lang' => LANGUAGE_ID,
			'service' => Ui\Service\Manager::TYPE_MARKETPLACE,
			'id' => $trading->getId(),
			'YANDEX_MARKET_ADMIN_TRADING_EDIT_active_tab' => 'tab1',
		]);
	}
}