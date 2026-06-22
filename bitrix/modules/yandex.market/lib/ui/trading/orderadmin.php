<?php
namespace Yandex\Market\Ui\Trading;

use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui;
use Yandex\Market\Trading;
use Bitrix\Main;

class OrderAdmin extends Ui\Reference\Page
{
	use Concerns\HasMessage;

	protected function getReadRights()
	{
		return Ui\Access::RIGHTS_PROCESS_TRADING;
	}

	public function setTitle()
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('TITLE'));
	}

	public function show()
	{
		$setupCollection = $this->getSetupCollection();
		$adminUrls = $this->getAdminUrlList($setupCollection);
		$mergedUrls = $this->mergeUrlList($adminUrls);

		if (count($mergedUrls) === 1)
		{
			LocalRedirect(reset($mergedUrls));
		}

		$this->showUrlList($setupCollection, $adminUrls);
	}

	protected function showUrlList(Trading\Setup\Collection $collection, array $urls)
	{
		echo '<ul>';

		foreach ($urls as $setupId => $url)
		{
			$setup = $collection->getItemById($setupId);

			if ($setup === null)
			{
				throw new Main\SystemException(sprintf('cant find setup with id %s', $setupId));
			}

			echo sprintf(
				'<li><a href="%s">%s</a></li>',
				htmlspecialcharsbx($url),
				$setup->getService()->getInfo()->getTitle()
			);
		}

		echo '</ul>';
	}

	protected function getSetupCollection()
	{
		$behavior = (string)$this->request->get('behavior');
		$filter = [ '=ACTIVE' => Trading\Setup\Table::BOOLEAN_Y ];
		$filter += Menu::businessFilter(Menu::extractBusinessId($this->request));

		if ($behavior !== '')
		{
			$filter['=TRADING_BEHAVIOR'] = [
				$behavior,
				Trading\Service\Manager::BEHAVIOR_BUSINESS,
			];
		}

		$collection = Trading\Setup\Collection::loadByFilter([
			'filter' => $filter,
		]);

		if (count($collection) === 0)
		{
			throw new Main\ObjectNotFoundException(self::getMessage('SETUP_NOT_FOUND'));
		}

		return $collection;
	}

	protected function getAdminUrlList(Trading\Setup\Collection $collection)
	{
		$result = [];

		/** @var Trading\Setup\Model $setup */
		foreach ($collection as $setup)
		{
			$platform = $setup->getPlatform();
			$orderRegistry = $setup->getEnvironment()->getOrderRegistry();

			$result[$setup->getId()] = $orderRegistry->getAdminListUrl($platform);
		}

		return $result;
	}

	protected function mergeUrlList(array $urls)
	{
		$pageGroups = $this->groupUrlListByPage($urls);
		$result = [];

		foreach ($pageGroups as $page => $pageUrls)
		{
			$result[] = $page . '?' . $this->mergeUrlListQuery($pageUrls);
		}

		return $result;
	}

	protected function groupUrlListByPage(array $urls)
	{
		$result = [];

		foreach ($urls as $url)
		{
			$queryPosition = mb_strpos($url, '?');
			$page = ($queryPosition !== false)
				? mb_substr($url, 0, $queryPosition)
				: $url;

			if (!isset($result[$page])) { $result[$page] = []; }

			$result[$page][] = $url;
		}

		return $result;
	}

	protected function mergeUrlListQuery(array $urls)
	{
		$query = [];

		foreach ($urls as $url)
		{
			$urlQueryString = parse_url($url, PHP_URL_QUERY);
			parse_str($urlQueryString, $urlQuery);

			if (empty($urlQuery)) { continue; }

			foreach ($urlQuery as $key => $value)
			{
				if (!isset($query[$key]))
				{
					$query[$key] = $value;
				}
				else if ($query[$key] === $value)
				{
					// nothing
				}
				else
				{
					if (!is_array($query[$key]))
					{
						$query[$key] = (array)$query[$key];
					}

					if (!in_array($value, $query[$key], true))
					{
						$query[$key][] = $value;
					}
				}
			}
		}

		return http_build_query($query);
	}
}