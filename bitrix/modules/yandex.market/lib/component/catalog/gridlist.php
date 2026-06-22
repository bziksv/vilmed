<?php
namespace Yandex\Market\Component\Catalog;

use Yandex\Market\Catalog;
use Yandex\Market\Component;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Ui;
use Yandex\Market\Utils\ServerStamp;

class GridList extends Component\Model\GridList
{
	public function processPostAction($action, $data)
	{
		if ($action === 'reinstall')
		{
			$this->processReinstall($data);
			return;
		}

		parent::processPostAction($action, $data);
	}

	protected function processReinstall($data)
	{
		global $APPLICATION;

		$model = $this->getModelClass();
		$successUrl = $APPLICATION->GetCurPageParam('', [ 'postAction' ]);

		$setupList = $model::loadList(array_diff_key($data, [
			'select' => true,
			'limit' => true,
			'offset' => true,
			'order' => true,
		]));

		/** @var Catalog\Setup\Model $setup */
		foreach ($setupList as $setup)
		{
			Assert::typeOf($setup,  Catalog\Setup\Model::class, 'setup');

			$setup->updateListener();
		}

		ServerStamp\Facade::reset();
		\CAdminNotify::DeleteByTag(Catalog\Agent\Processor::NOTIFY_DISABLED);

		LocalRedirect($successUrl);
	}

	public function getDefaultFilter()
	{
		return
			parent::getDefaultFilter()
			+ Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'));
	}

	public function getFields(array $select = [])
	{
		$fields = parent::getFields($select);
		$fields['BUSINESS']['SETTINGS']['FILTER'] = Ui\Trading\Menu::businessFilter($this->getComponentParam('BUSINESS_ID'), 'ID');

		return $fields;
	}
}