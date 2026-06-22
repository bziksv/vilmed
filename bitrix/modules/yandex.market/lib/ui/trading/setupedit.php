<?php
namespace Yandex\Market\Ui\Trading;

use Yandex\Market;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Component;
use Yandex\Market\Glossary;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Catalog;
use Yandex\Market\Trading\Business;
use Yandex\Market\Trading\Campaign;
use Yandex\Market\Trading\Setup;
use Yandex\Market\Trading\Service;
use Yandex\Market\Ui\Admin;
use Yandex\Market\Ui\Reference\UserException;
use Yandex\Market\Data\TextString;
use Bitrix\Main;

class SetupEdit extends Market\Ui\Reference\Form
{
	use Concerns\HasMessage;

	const ACTION_DELETE = 'delete';
	const ACTION_ACTIVATE = 'activate';
	const ACTION_DEACTIVATE = 'deactivate';
	const ACTION_DEPRECATE = 'deprecate';
	const ACTION_PUSH_STOCKS = 'pushStocks';
	const ACTION_PUSH_PRICES = 'pushPrices';
	const ACTION_SYNCHRONIZE = 'synchronize';
	const ACTION_RESET = 'reset';

	private $trading;
	private $messages = [];

	public function setTitle()
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('TITLE'));
	}

	public function hasRequest()
	{
		return ($this->getRequestAction() !== null);
	}

	public function canEdit()
	{
		return $this->getTrading()->isInstalled();
	}

	private function getRequestAction()
	{
		return $this->request->get('action');
	}

	public function processRequest()
	{
		try
		{
			$this->checkSession();
			$this->checkWriteAccess();

			switch ($this->getRequestAction())
			{
				case static::ACTION_RESET:
					$this->processReset();
					break;

				case static::ACTION_SYNCHRONIZE:
					$this->processSynchronize();
					break;

				case static::ACTION_DELETE:
					$this->processDelete();
					break;

				case static::ACTION_DEPRECATE:
					$this->processDeprecate();
					break;

				case static::ACTION_ACTIVATE:
					$this->processActivate();
					break;

				case static::ACTION_DEACTIVATE:
					$this->processDeactivate();
					break;

				case static::ACTION_PUSH_STOCKS:
					$this->processPush('push/stocks');
					break;

				case static::ACTION_PUSH_PRICES:
					$this->processPush('push/prices');
					break;
			}
		}
		catch (Main\SystemException $exception)
		{
			$this->handleException($exception);
		}
	}

	private function processReset()
	{
		global $APPLICATION;

		$trading = $this->getTrading();

		$trading->deactivate();
		$trading->reset();
		$trading->save();

		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			$catalog = $trading->getBusiness()->getCatalog();

			if ($catalog !== null)
			{
				$catalog->deactivate();
				$catalog->reset();
				$catalog->save();
			}
		}

		LocalRedirect($APPLICATION->GetCurPageParam('', [
			'action',
			'filter',
			'sessid',
		]));
	}

	private function processSynchronize()
	{
		global $APPLICATION;

		$this->getTrading()->getBusiness()->getCampaignRepository()->synchronize(true);

		LocalRedirect($APPLICATION->GetCurPageParam('', [
			'action',
			'filter',
			'sessid',
		]));
	}

	private function processDelete()
	{
		$trading = $this->getTrading();
		$tradingId = $trading->getId();
		$broken = ($this->checkBroken($trading) !== null);

		if ($trading->isActive())
		{
			$trading->deactivate();

			if (!$broken)
			{
				$trading->uninstall();
			}

			$trading->save();
		}

		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			$business = $trading->getBusiness();
			$catalog = $business->getCatalog();

			if ($catalog !== null && !$catalog->isNew())
			{
				$catalog->deactivate();
				$catalog->save();

				if ($business->getSalesBoostCollection()->count() === 0) // business will not be deleted with active boost
				{
					$catalog->delete();
				}
			}
		}

		$trading->delete();

		if (
			!$broken
			&& $trading->getServiceCode() === Service\Manager::SERVICE_MARKETPLACE
			&& $trading->getBusiness()->getTradingCollection()->exceptItemId($tradingId)->count() === 0
		)
		{
			LocalRedirect(Admin\Path::getModuleUrl('trading_connect', [
				'lang' => LANGUAGE_ID,
			]));
		}

		LocalRedirect(Admin\Path::getModuleUrl('trading_list', [
			'lang' => LANGUAGE_ID,
			'business' => $trading->getBusinessId(),
		]));
	}

	private function processDeprecate()
	{
		global $APPLICATION;

		$trading = $this->getTrading();
		$migrateService = Service\Migration::getDeprecateUse($trading->getServiceCode());

		Assert::notNull($migrateService, 'migrateService');

		$this->applyMigration($trading, Service\Manager::createProvider($migrateService));

		LocalRedirect($APPLICATION->GetCurPageParam('', [
			'action',
			'filter',
			'sessid',
		]));
	}

	private function applyMigration(Setup\Model $trading, Service\Reference\Provider $service)
	{
		$connection = Setup\Table::getEntity()->getConnection();

		try
		{
			$connection->startTransaction();

 			$trading->migrate($service);
			$trading->save();

			$connection->commitTransaction();
		}
		catch (Main\SystemException $exception)
		{
			$connection->rollbackTransaction();

			throw $exception;
		}
	}

	private function processActivate()
	{
		global $APPLICATION;

		$trading = $this->getTrading();
		$filter = $this->request->get('filter');

		if (
			($filter === null || $filter === Glossary::SERVICE_CATALOG)
			&& $trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			$catalog = $trading->getBusiness()->getCatalog();

			if ($catalog !== null)
			{
				$catalog->activate();
				$catalog->save();
			}
		}

		if ($filter === null || $filter === Glossary::SERVICE_TRADING)
		{
			$trading->install();
			$trading->activate();
			$trading->save();
		}

		LocalRedirect($APPLICATION->GetCurPageParam('', [
			'action',
			'filter',
			'sessid',
		]));
	}

	private function processDeactivate()
	{
		global $APPLICATION;

		$trading = $this->getTrading();
		$filter = $this->request->get('filter');

		if (
			($filter === null || $filter === Glossary::SERVICE_CATALOG)
			&& $trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS
		)
		{
			$catalog = $trading->getBusiness()->getCatalog();

			if ($catalog !== null)
			{
				$catalog->deactivate();
				$catalog->save();
			}
		}

		if ($filter === null || $filter === Glossary::SERVICE_TRADING)
		{
			$trading->uninstall();
			$trading->deactivate();
			$trading->save();
		}

		LocalRedirect($APPLICATION->GetCurPageParam('', [
			'action',
			'filter',
			'sessid',
		]));
	}

	private function processPush($path)
	{
		$type = mb_strtoupper($path);
		$type = str_replace('/', '_', $type);

		Market\Trading\State\PushAgent::refresh((string)$this->getTrading()->getId(), $path, true);

		$this->messages[] = [
			'TYPE' => 'OK',
			'MESSAGE' => self::getMessage('ACTION_PUSH_SUCCESS', [
				'#TYPE#' => self::getMessage('ACTION_' . $type),
			]),
			'DETAILS' => self::getMessage('ACTION_PUSH_SUCCESS_DETAILS'),
		];
	}

	public function handleException(\Exception $exception)
	{
		\CAdminMessage::ShowMessage([
			'TYPE' => 'ERROR',
			'MESSAGE' => $exception->getMessage(),
			'DETAILS' => $exception instanceof UserException ? $exception->getDetails() : null,
			'HTML' => $exception instanceof UserException ? $exception->needHtml() : false,
		]);

		$this->handleMigration($exception);
	}

	private function handleMigration(\Exception $exception)
	{
		if (!Market\Migration\Controller::canRestore($exception)) { return; }

		echo sprintf(
			'<a class="adm-btn" href="%s">%s</a><br /><br />',
			Admin\Path::getModuleUrl('migration'),
			self::getMessage('GO_RESTORE')
		);
	}

	public function show()
	{
		$trading = $this->getTrading();

		if ($trading->isDeprecated())
		{
			$this->showDeprecated($trading->getServiceCode());
			return;
		}

		$brokenError = $this->checkBroken($trading);

		if ($brokenError !== null)
		{
			$this->showBroken($brokenError);
			return;
		}

		if ($trading->isInstalled())
		{
			$this->checkNavigationBusiness($trading);
		}

		$this->showEditForm($trading);
		$this->showCheckAnnouncement();
	}

	private function showDeprecated($serviceCode)
	{
		global $APPLICATION;

		$newServiceCode = Service\Migration::getDeprecateUse($serviceCode);
		$action = $newServiceCode !== null ? self::ACTION_DEPRECATE : self::ACTION_DELETE;
		$query = [
			'action' => $action,
			'sessid' => bitrix_sessid(),
		];

		\CAdminMessage::ShowMessage([
			'TYPE' => 'ERROR',
			'MESSAGE' => self::getMessage('SERVICE_DEPRECATED', [ '#SERVICE#' => $serviceCode ]),
			'DETAILS' => self::getMessage('SERVICE_DEPRECATED_DETAILS', [
				'#URL#' => $APPLICATION->GetCurPageParam(http_build_query($query), array_keys($query)),
				'#ACTION#' => TextString::lcfirst(self::getMessage('ACTION_' . mb_strtoupper($action))),
			]),
			'HTML' => true,
		]);
	}

	private function checkBroken(Setup\Model $trading)
	{
		if ($trading->getServiceCode() !== Service\Manager::SERVICE_MARKETPLACE)
		{
			return null;
		}

		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			if ($trading->getBusinessId() > 0) { return null; }

			return new Main\Error(self::getMessage('BROKEN_BUSINESS'));
		}

		if ($trading->getCampaignId() > 0) { return null; }

		return new Main\Error(self::getMessage('BROKEN_CAMPAIGN'));
	}

	private function showBroken(Main\Error $error)
	{
		global $APPLICATION;

		$action = self::ACTION_DELETE;
		$query = [
			'action' => $action,
			'sessid' => bitrix_sessid(),
		];

		\CAdminMessage::ShowMessage([
			'TYPE' => 'ERROR',
			'MESSAGE' => $error->getMessage(),
			'DETAILS' => self::getMessage('BROKEN_DETAILS', [
				'#URL#' => $APPLICATION->GetCurPageParam(http_build_query($query), array_keys($query)),
				'#ACTION#' => TextString::lcfirst(self::getMessage('ACTION_' . mb_strtoupper($action))),
			]),
			'HTML' => true,
		]);
	}

	private function checkNavigationBusiness(Setup\Model $trading)
	{
		global $APPLICATION;

		$businessId = Menu::castQueryBusiness($trading->getBusiness());

		if ($businessId === Menu::extractBusinessId($this->request)) { return; }

		LocalRedirect($APPLICATION->GetCurPageParam(
			http_build_query([
				'business' => $businessId,
				'id' => $trading->getId(),
			]),
			[ 'business', 'action', 'sessid' ]
		));
	}

	private function showEditForm(Setup\Model $trading)
	{
		$business = $trading->getBusiness();

		$this->actualizeBusinessCampaigns($trading, $business);

		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS)
		{
			$this->setBusinessTitle($business);
			$this->showBusinessForm($business, $trading);
			return;
		}

		$campaign = $trading->getCampaign();
		
		Assert::notNull($campaign, '$trading->getCampaign()');

		$this->setCampaignTitle($campaign);
		$this->showCampaignForm($business, $trading);
	}

	private function actualizeBusinessCampaigns(Setup\Model $trading, Business\Model $business)
	{
		if (!$trading->isActive() || $this->request->isPost()) { return; }

		$actualized = $business->getCampaignRepository()->actualize();

		if ($actualized->isSuccess()) { return; }

		$this->messages[] = [
			'TYPE' => 'ERROR',
			'MESSAGE' => self::getMessage('CAMPAIGN_ACTUALIZE_FAIL'),
			'DETAILS' => self::getMessage('CAMPAIGN_ACTUALIZE_FAIL_DETAILS', [
				'#ERROR#' => implode('<br>', $actualized->getErrorMessages()),
			]),
			'HTML' => true,
		];
	}

	private function setBusinessTitle(Business\Model $business)
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('BUSINESS_TITLE', [
			'#ID#' => $business->getId(),
			'#NAME#' => $business->getName(),
		]));
	}

	private function showBusinessForm(Business\Model $business, Setup\Model $trading)
	{
		global $APPLICATION;

		$catalog = $trading->getCatalog();

		$this->testCatalogNeedSubmit($business, $trading, $catalog);
		$canActivate = $this->testCanGlobalActivateBusiness($trading, $catalog);

		$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
			'FORM_ID' => 'YANDEX_MARKET_ADMIN_TRADING_EDIT',
			'PROVIDER' => Component\Compound\EditForm::class,
			'CONTEXT_MENU' => $this->getBusinessFormContextMenu($business, $trading),
			'PRIMARY' => array_filter([
				'BUSINESS' => $business->getId(),
				'CATALOG' => $catalog !== null ? $catalog->getId() : null,
				'TRADING' => $trading->getId(),
			]),
			'MESSAGES' => $this->messages,
			'BUTTONS' => array_filter([
				[
					'BEHAVIOR' => 'save',
					'NAME' => $canActivate ? self::getMessage('SAVE_AND_ACTIVATE_BUTTON') : self::getMessage('SAVE_BUTTON'),
				],
				$canActivate ? [
					'BEHAVIOR' => 'apply',
					'NAME' => self::getMessage('SAVE_DRAFT'),
				] : null,
				$trading->isInstalled() ? [
					'NAME' => self::getMessage('RESET_BUTTON'),
					'ATTRIBUTES' => [
						'name' => 'action',
						'value' => self::ACTION_RESET,
						'onclick' => 'if (!confirm("' . addslashes(self::getMessage('RESET_BUTTON_CONFIRM')) . '")) { return false; }',
					],
				] : null,
			]),
			'ALLOW_SAVE' => $this->isAuthorized($this->getWriteRights()),
			'SAVE_PARTIALLY' => true,
			'SAVE_URL' => $this->buildSaveUrl($business),
			'CHILDREN' => [
				'BUSINESS' => [
					'PROVIDER' => Component\Business\EditForm::class,
					'BUSINESS' => $business,
					'TRADING' => $trading,
					'TABS' => [
						[
							'name' => self::getMessage('TAB_COMMON'),
							'sort' => 1000,
						],
					],
				],
				'CATALOG' => [
					'PROVIDER' => Component\Catalog\EditForm::class,
					'BUSINESS' => $business,
					'BUSINESS_ID' => $business->getId(),
					'SKU_MAP_FIELD' => '@BUSINESS[PRODUCT_SKU_FIELD]',
					'CAN_ACTIVATE' => $canActivate,
					'TABS' => [
						[
							'id' => 'catalog',
							'name' => self::getMessage('TAB_CATALOG'),
							'layout' => 'catalog',
							'fields' => [
								'BUSINESS',
								'LOG_LEVEL',
								'AUTOUPDATE',
								'REFRESH_PERIOD',
								'REFRESH_TIME',
								'PRODUCT.IBLOCK_ID',
								'PRICE_ENABLE',
								'PRODUCT.PRICE_SEGMENT',
								'STOCK_ENABLE',
								'PRODUCT.STOCK_SEGMENT',
								'OFFER_ENABLE',
								'PRODUCT.OFFER_SEGMENT',
								'CARD_ENABLE',
								'PRODUCT.CARD_SEGMENT',
								'PRODUCT.FILTER',
								'PRODUCT.EXPORT_ALL',
							],
							'sort' => 2000,
						],
					],
				],
				'CATEGORY' => [
					'PROVIDER' => Component\CatalogCategory\EditForm::class,
					'API_KEY_FIELD' => 'BUSINESS[API_KEY]',
					'SKU_MAP_FIELD' => '@BUSINESS[PRODUCT_SKU_FIELD]',
					'TABS' => [
						[
							'id' => 'category',
							'name' => self::getMessage('TAB_CATEGORY'),
							'fields' => [
								'CATEGORY',
							],
							'sort' => 2100,
						],
					],
				],
				'TRADING' => [
					'PROVIDER' => Component\TradingSettings\EditForm::class,
					'TRADING' => $trading,
					'CAN_ACTIVATE' => $canActivate,
				],
			],
			'PRODUCT_PARAM_FIELDS' => [
				'CATALOG.PRICE_ENABLE',
				'CATALOG.PRODUCT.PRICE_SEGMENT',
				'CATALOG.STOCK_ENABLE',
				'CATALOG.PRODUCT.STOCK_SEGMENT',
				'CATALOG.OFFER_ENABLE',
				'CATALOG.PRODUCT.OFFER_SEGMENT',
				'CATALOG.CARD_ENABLE',
				'CATALOG.PRODUCT.CARD_SEGMENT',
			],
			'PRODUCT_FILTER_FIELDS' => [
				'CATALOG.PRODUCT.FILTER',
				'CATALOG.PRODUCT.EXPORT_ALL',
			],
			'AJAX_RELOADER' => [
				'BUSINESS[PRODUCT_SKU_FIELD]' => [
					'SIGNIFICANT' => [ 'IBLOCK' ],
					'TARGET' => [
						'tab_catalog',
						'tab_category',
					],
				],
			],
		]);
	}

	private function testCatalogNeedSubmit(Business\Model $business, Setup\Model $trading, Catalog\Setup\Model $catalog = null)
	{
		if (
			$catalog === null
			|| $trading->isNew()
			|| $catalog->isNew()
			|| $catalog->wasSubmitted()
			|| !$catalog->isActive()
			|| !$catalog->canDoSomething()
		)
		{
			return;
		}

		$baseQuery = [ 'lang' => LANGUAGE_ID ];
		$baseQuery += Menu::compileQuery($business);

		$this->messages[] = [
			'TYPE' => 'OK',
			'MESSAGE' => self::getMessage('NEED_SUBMIT_CATALOG'),
			'DETAILS' => self::getMessage('NEED_SUBMIT_CATALOG_DETAILS', [
				'#EXPORT_URL#' => Admin\Path::getModuleUrl('catalog_run', $baseQuery + [
					'id' => $catalog->getId(),
					'autostart' => 'Y',
				]),
			]),
			'HTML' => true,
		];
	}

	private function testCanGlobalActivateBusiness(Setup\Model $trading, Catalog\Setup\Model $catalog = null)
	{
		global $APPLICATION;

		$status = [
			Glossary::SERVICE_TRADING => $trading->isActive(),
			Glossary::SERVICE_CATALOG => ($catalog !== null && $catalog->isActive()),
		];

		if ($catalog !== null && !$catalog->canDoSomething())
		{
			unset($status[Glossary::SERVICE_CATALOG]);
		}

		$negative = array_diff_key($status, array_filter($status));

		if (empty($negative)) { return false; }
		if ($trading->isNew()) { return true; }

		if (count($negative) === 1)
		{
			$inactive = key($negative);

			$this->messages[] = [
				'TYPE' => 'ERROR',
				'MESSAGE' => self::getMessage('INACTIVE_' . mb_strtoupper($inactive)),
				'DETAILS' => self::getMessage('INACTIVE_' . mb_strtoupper($inactive) . '_DETAILS', [
					'#ACTIVATE_URL#' => $APPLICATION->GetCurPageParam(
						http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_ACTIVATE, 'filter' => $inactive]),
						[ 'sessid', 'action', 'filter' ]
					),
				]),
				'HTML' => true,
			];

			return false;
		}

		$this->messages[] = [
			'TYPE' => 'ERROR',
			'MESSAGE' => self::getMessage('INACTIVE_BUSINESS'),
			'DETAILS' => self::getMessage('INACTIVE_BUSINESS_DETAILS', [
				'#ACTIVATE_URL#' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_ACTIVATE]),
					[ 'sessid', 'action', 'filter' ]
				),
			]),
			'HTML' => true,
		];

		return true;
	}

	private function getBusinessFormContextMenu(Business\Model $business, Setup\Model $trading)
	{
		if ($business->isNew()) { return []; }

		$baseQuery = [ 'lang' => LANGUAGE_ID ];
		$baseQuery += Menu::compileQuery($business);

		return array_filter([
			$this->getBusinessFormContextMenuCatalogItem($business, $baseQuery),
			$this->getBusinessFormContextMenuListItem($trading, $baseQuery),
			$this->getBusinessFormContextMenuControlItem($business, $trading),
		]);
	}

	private function getBusinessFormContextMenuControlItem(Business\Model $business, Setup\Model $trading)
	{
		global $APPLICATION;

		if (!$trading->isInstalled()) { return null; }

		$catalog = $business->getCatalog();

		$tradingActive = $trading->isActive();
		$catalogActive = ($catalog !== null && $catalog->canDoSomething() && $catalog->isActive());
		$activatePrefix = null;

		$result = [
			'TEXT' => self::getMessage('MENU_CONTROL'),
			'MENU' => [],
		];

		$result['MENU'][] = [
			'TEXT' => self::getMessage('SYNCHRONIZE_BUSINESS'),
			'LINK' => $APPLICATION->GetCurPageParam(
				http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_SYNCHRONIZE]),
				[ 'sessid', 'action', 'filter' ]
			),
		];

		$result['MENU'][] = [ 'SEPARATOR' => true ];

		if ($tradingActive === $catalogActive)
		{
			$activatePrefix = 'TOGGLE_';

			if ($tradingActive)
			{
				$result['MENU'][] = [
					'TEXT' => self::getMessage('DEACTIVATE_BUSINESS'),
					'LINK' => $APPLICATION->GetCurPageParam(
						http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_DEACTIVATE]),
						[ 'sessid', 'action', 'filter' ]
					),
				];
			}
			else
			{
				$result['MENU'][] = [
					'TEXT' => self::getMessage('ACTIVATE_BUSINESS'),
					'LINK' => $APPLICATION->GetCurPageParam(
						http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_ACTIVATE]),
						[ 'sessid', 'action', 'filter' ]
					),
				];
			}
		}

		if ($tradingActive)
		{
			$result['MENU'][] = [
				'TEXT' => self::getMessage(($activatePrefix ?: 'DEACTIVATE_') . 'TRADING'),
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_DEACTIVATE, 'filter' => Glossary::SERVICE_TRADING]),
					[ 'sessid', 'action', 'filter' ]
				),
			];
		}
		else
		{
			$result['MENU'][] = [
				'TEXT' => self::getMessage(($activatePrefix ?: 'ACTIVATE_') . 'TRADING'),
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_ACTIVATE, 'filter' => Glossary::SERVICE_TRADING]),
					[ 'sessid', 'action', 'filter' ]
				),
			];
		}

		if ($catalogActive)
		{
			$result['MENU'][] = [
				'TEXT' => self::getMessage(($activatePrefix ?: 'DEACTIVATE_') . 'CATALOG'),
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_DEACTIVATE, 'filter' => Glossary::SERVICE_CATALOG]),
					[ 'sessid', 'action', 'filter' ]
				),
			];
		}
		else if ($catalog !== null)
		{
			$result['MENU'][] = [
				'TEXT' => self::getMessage(($activatePrefix ?: 'ACTIVATE_') . 'CATALOG'),
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_ACTIVATE, 'filter' => Glossary::SERVICE_CATALOG]),
					[ 'sessid', 'action', 'filter' ]
				),
			];
		}

		$result['MENU'][] = [
			'TEXT' => self::getMessage('DELETE_BUSINESS'),
			'LINK' => $APPLICATION->GetCurPageParam(
				http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_DELETE]),
				[ 'sessid', 'action', 'filter' ]
			),
			'ONCLICK' => 'if (!confirm("' . addslashes(self::getMessage('DELETE_BUSINESS_CONFIRM')) . '")) { return false; }',
		];

		return !empty($result['MENU']) ? $result : null;
	}

	private function getBusinessFormContextMenuListItem(Setup\Model $trading, array $baseQuery)
	{
		if ($trading->isNew()) { return null; }

		return [
			'LINK' => Admin\Path::getModuleUrl('trading_list', $baseQuery),
			'TEXT' => self::getMessage('MENU_BUSINESS_LIST'),
		];
	}

	private function getBusinessFormContextMenuCatalogItem(Business\Model $business, array $baseQuery)
	{
		$catalog = $business->getCatalog();

		if ($catalog === null || $catalog->isNew()) { return null; }

		return [
			'LINK' => Admin\Path::getModuleUrl('catalog_run', $baseQuery + [
				'id' => $catalog->getId(),
			]),
			'TEXT' => self::getMessage('MENU_CATALOG_RUN'),
		];
	}

	private function setCampaignTitle(Campaign\Model $campaign)
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('CAMPAIGN_TITLE', [
			'#ID#' => $campaign->getId(),
			'#NAME#' => $campaign->getName(),
			'#PLACEMENT#' => $campaign->getPlacement(),
		]));
	}

	private function showCampaignForm(Business\Model $business, Setup\Model $trading)
	{
		global $APPLICATION;

		$canActivate = !$trading->isActive();

		$APPLICATION->IncludeComponent('yandex.market:admin.form.edit', '', [
			'FORM_ID' => 'YANDEX_MARKET_ADMIN_TRADING_EDIT',
			'PROVIDER' => Component\Compound\EditForm::class,
			'CONTEXT_MENU' => $this->getCampaignFormContextMenu($business, $trading),
			'PRIMARY' => array_filter([
				'BUSINESS' => $business->isNew() ? null : $business->getId(),
				'TRADING' => $trading->getId(),
			]),
			'MESSAGES' => $this->messages,
			'BUTTONS' => array_filter([
				[
					'BEHAVIOR' => 'save',
					'NAME' => $canActivate ? self::getMessage('SAVE_AND_ACTIVATE_BUTTON') : self::getMessage('SAVE_BUTTON'),
				],
				$canActivate ? [
					'BEHAVIOR' => 'apply',
					'NAME' => self::getMessage('SAVE_DRAFT'),
				] : null,
				$trading->isInstalled() ? [
					'NAME' => self::getMessage('RESET_BUTTON'),
					'ATTRIBUTES' => [
						'name' => 'action',
						'value' => self::ACTION_RESET,
						'onclick' => 'if (!confirm("' . addslashes(self::getMessage('RESET_BUTTON_CONFIRM')) . '")) { return false; }',
					],
				] : null,
			]),
			'ALLOW_SAVE' => $this->isAuthorized($this->getWriteRights()),
			'SAVE_PARTIALLY' => true,
			'SAVE_URL' => $this->buildSaveUrl($business),
			'CHILDREN' => [
				'BUSINESS' => [
					'PROVIDER' => Component\Business\EditForm::class,
					'BUSINESS' => $business,
					'TRADING' => $trading,
					'TABS' => [
						[
							'name' => self::getMessage('TAB_COMMON'),
							'sort' => 1000,
						],
					],
				],
				'TRADING' => [
					'PROVIDER' => Component\TradingSettings\EditForm::class,
					'TRADING' => $trading,
					'CAN_ACTIVATE' => $canActivate,
				],
			],
		]);
	}

	private function buildSaveUrl(Business\Model $business)
	{
		global $APPLICATION;

		$query = [ 'lang' => LANGUAGE_ID ];
		$query += Menu::compileQuery($business);

		return $APPLICATION->GetCurPageParam(http_build_query($query), [
			'lang',
			'business',
			'connect',
			'connectCampaign',
		]);
	}

	private function getCampaignFormContextMenu(Business\Model $business, Setup\Model $trading)
	{
		return array_filter([
			$this->getCampaignFormContextMenuListItem($business),
			$this->getCampaignFormContextSyncItem($trading),
			$this->getCampaignFormContextMenuDeactivateItem($trading),
		]);
	}

	private function getCampaignFormContextMenuListItem(Business\Model $business)
	{
		$query = [ 'lang' => LANGUAGE_ID ];
		$query += Menu::compileQuery($business);

		return [
			'ICON' => 'btn_list',
			'LINK' => Admin\Path::getModuleUrl('trading_list', $query),
			'TEXT' => self::getMessage('MENU_LIST'),
		];
	}

	private function getCampaignFormContextSyncItem(Setup\Model $trading)
	{
		global $APPLICATION;

		if (!$trading->isInstalled() || !$trading->isActive()) { return null; }

		$options = $trading->wakeupService()->getOptions();

		if (!($options instanceof Service\Marketplace\Options)) { return null; }

		$result = [
			'TEXT' => self::getMessage('MENU_SYNC'),
			'MENU' => [],
		];

		if ($options->usePushStocks())
		{
			$result['MENU'][] = [
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_PUSH_STOCKS]),
					[ 'sessid', 'action', 'filter' ]
				),
				'TEXT' => self::getMessage('MENU_PUSH_STOCKS'),
			];
		}

		if ($options->usePushPrices())
		{
			$result['MENU'][] = [
				'LINK' => $APPLICATION->GetCurPageParam(
					http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_PUSH_PRICES]),
					[ 'sessid', 'action', 'filter' ]
				),
				'TEXT' => self::getMessage('MENU_PUSH_PRICES'),
			];
		}

		return !empty($result['MENU']) ? $result : null;
	}

	private function getCampaignFormContextMenuDeactivateItem(Setup\Model $trading)
	{
		global $APPLICATION;

		if (!$trading->isInstalled() || !$trading->isActive()) { return null; }

		return [
			'TEXT' => self::getMessage('DEACTIVATE_CAMPAIGN'),
			'LINK' => $APPLICATION->GetCurPageParam(
				http_build_query(['sessid' => bitrix_sessid(), 'action' => static::ACTION_DEACTIVATE]),
				[ 'sessid', 'action', 'filter' ]
			),
		];
	}

	private function showCheckAnnouncement()
	{
		Market\Ui\Checker\Announcement::show();
	}

	/** @return Setup\Model */
	public function getTrading()
	{
		if ($this->trading === null)
		{
			$this->trading = $this->resolveTrading();
		}

		return $this->trading;
	}

	private function resolveTrading()
	{
		$id = Market\Data\Number::castInteger($this->request->get('id'));

		if ($id !== null)
		{
			return $this->bootCompatibleTrading(Setup\Model::loadById($id));
		}

		$businessId = Menu::extractBusinessId($this->request);
		$connectBusiness = $this->request->get('connect');

		if ($connectBusiness !== null)
		{
			if (empty($_SESSION[Component\TradingConnect\EditForm::SESSION_KEY][$connectBusiness]['BUSINESS']['ID']))
			{
				throw new UserException(self::getMessage('MISSING_SESSION_CONNECT'), self::getMessage('MISSING_SESSION_CONNECT_DETAILS', [
					'#CONNECT_URL#' => Admin\Path::getModuleUrl('trading_connect'),
					'#CHECK_URL#' => Admin\Path::getPageUrl('site_checker', [
						'tabControl_active_tab' => 'edit1',
					]),
				]));
			}

			return $this->buildConnectBusinessTrading($_SESSION[Component\TradingConnect\EditForm::SESSION_KEY][$connectBusiness]);
		}

		$connectCampaign = $this->request->get('connectCampaign');

		if ($connectCampaign !== null)
		{
			if (empty($_SESSION[Component\TradingSetup\EditForm::SESSION_KEY][$connectCampaign]['CAMPAIGN_ID']))
			{
				throw new UserException(self::getMessage('MISSING_SESSION_CONNECT'), self::getMessage('MISSING_SESSION_CONNECT_DETAILS', [
					'#CONNECT_URL#' => Admin\Path::getModuleUrl('trading_setup', Menu::compileQuery($businessId)),
					'#CHECK_URL#' => Admin\Path::getPageUrl('site_checker', [
						'tabControl_active_tab' => 'edit1',
					]),
				]));
			}

			return $this->buildConnectCampaignTrading($_SESSION[Component\TradingSetup\EditForm::SESSION_KEY][$connectCampaign]);
		}

		if ($businessId > 0)
		{
			$business = Business\Model::loadById($businessId);
			$tradingCollection = $business->getTradingCollection();
			$trading = $tradingCollection->getByBehavior(Service\Manager::BEHAVIOR_BUSINESS);

			if ($trading !== null)
			{
				return $trading;
			}

			if ($tradingCollection->count() > 0)
			{
				throw new UserException(self::getMessage('NEED_CHOOSE_SETUP', [
					'#LIST_URL#' => Admin\Path::getModuleUrl('trading_list', Menu::compileQuery($businessId)),
				]), null, true);
			}

			throw new UserException(self::getMessage('NEED_CREATE', [
				'#CREATE_URL#' => Admin\Path::getModuleUrl('trading_connect'),
			]));
		}

		throw new UserException(self::getMessage('NEED_CHOOSE_SETUP_OR_CREATE', [
			'#LIST_URL#' => Admin\Path::getModuleUrl('trading_list', Menu::compileQuery($businessId)),
			'#CREATE_URL#' => Admin\Path::getModuleUrl('trading_connect'),
		]));
	}

	private function buildConnectBusinessTrading(array $connect)
	{
		$exists = Business\Model::loadList([
			'filter' => [ '=ID' => $connect['BUSINESS']['ID'] ],
		]);

		$businessOptionValues = array_intersect_key($connect, [
			'API_KEY' => true,
		]);

		if (!empty($exists))
		{
			$business = reset($exists);
			$business->getOptions()->extendValues($businessOptionValues);
			$business->setField('CAMPAIGN', $connect['CAMPAIGN']);
			$business->setField('SITE_ID', $connect['SITE_ID']);
		}
		else
		{
			$business = new Business\Model();
			$business->setFields([
				'ID' => $connect['BUSINESS']['ID'],
				'NAME' => $connect['BUSINESS']['NAME'],
				'ACTIVE' => Setup\Table::BOOLEAN_N,
				'SITE_ID' => $connect['SITE_ID'],
				'SETTINGS' => array_map(
					static function($name) use ($businessOptionValues) { return [ 'NAME' => $name, 'VALUE' => $businessOptionValues[$name] ]; },
					array_keys($businessOptionValues)
				),
				'TRADING' => [],
				'CAMPAIGN' => $connect['CAMPAIGN'],
			]);
		}

		$tradingCollection = $business->getTradingCollection();
		$trading = $tradingCollection->getByBehavior(Service\Manager::BEHAVIOR_BUSINESS);

		if ($trading !== null)
		{
			$trading->setField('SITE_ID', $connect['SITE_ID']);
		}
		else
		{
			$trading = new Setup\Model([
				'TRADING_SERVICE' => Service\Manager::SERVICE_MARKETPLACE,
				'TRADING_BEHAVIOR' => Service\Manager::BEHAVIOR_BUSINESS,
				'ACTIVE' => Setup\Table::BOOLEAN_N,
				'BUSINESS_ID' => $connect['BUSINESS']['ID'],
				'CAMPAIGN_ID' => 0,
				'SITE_ID' => $connect['SITE_ID'],
				'SETTINGS' => [],
			]);

			$tradingCollection->addItem($trading);
			$trading->setParentCollection($tradingCollection);
			$trading->setParent($business);
		}

		return $trading;
	}

	private function bootCompatibleTrading(Setup\Model $trading)
	{
		if ($trading->getServiceCode() !== Service\Manager::SERVICE_MARKETPLACE)
		{
			return $trading;
		}

		$this->bootCompatibleBusinessAndCampaignId($trading);
		$this->bootCompatibleBusiness($trading);
		$this->bootCompatibleCampaign($trading);

		return $trading;
	}

	private function bootCompatibleBusinessAndCampaignId(Setup\Model $trading)
	{
		if ($trading->getBehaviorCode() === Service\Manager::BEHAVIOR_BUSINESS) { return; }

		$needBusiness = ($trading->getBusinessId() === 0);
		$needCampaign = ($trading->getCampaignId() === 0);

		if (!$needBusiness && !$needCampaign) { return; }

		$businessId = (int)$trading->getSettings()->getValue('BUSINESS_ID');
		$campaignId = (int)$trading->getSettings()->getValue('CAMPAIGN_ID');

		if ($needBusiness && $businessId > 0)
		{
			$trading->setField('BUSINESS_ID', $businessId);
		}

		if ($needCampaign && $campaignId > 0)
		{
			$trading->setField('CAMPAIGN_ID', $campaignId);
		}
	}

	private function bootCompatibleBusiness(Setup\Model $trading)
	{
		$businessId = $trading->getBusinessId();

		if ($businessId > 0 && Business\Table::getRow([ 'filter' => [ '=ID' => $businessId ] ]) !== null)
		{
			return;
		}

		$business = new Business\Model([
			'NAME' => self::getMessage('UNKNOWN_BUSINESS'),
			'SITE_ID' => $trading->getSiteId(),
			'ACTIVE' => Market\Ui\UserField\BooleanType::VALUE_N,
			'TRADING_ID' => $trading->getId(),
			'CAMPAIGN' => [],
			'SETTINGS' => [],
		]);

		if ($businessId > 0)
		{
			$business->setField('ID', $businessId);
		}

		$trading->injectBusiness($business);
	}

	private function bootCompatibleCampaign(Setup\Model $trading)
	{
		$campaignId = $trading->getCampaignId();

		if ($trading->getCampaignId() === 0) { return; }

		$campaignCollection = $trading->getBusiness()->getCampaignCollection();
		$campaign = $campaignCollection->getItemById($campaignId);

		if ($campaign !== null) { return; }

		$campaign = new Campaign\Model();
		$campaign->setFields([
			'ID' => $campaignId,
			'NAME' => self::getMessage('UNKNOWN_CAMPAIGN'),
			'PLACEMENT' => Market\Trading\Campaign\Placement::toPlacement($trading->getBehaviorCode()),
			'UNKNOWN' => true,
		]);

		$campaignCollection->addItem($campaign);
		$campaign->setParentCollection($campaignCollection);
	}

	private function buildConnectCampaignTrading(array $campaignConnect)
	{
		$exists = Setup\Model::loadList([
			'filter' => [ '=CAMPAIGN_ID' => $campaignConnect['CAMPAIGN_ID'] ],
			'order' => [ 'ACTIVE' => 'DESC', 'ID' => 'ASC' ],
			'limit' => 1,
		]);

		if (!empty($exists))
		{
			return reset($exists);
		}

		$campaign = Business\Model::loadById($campaignConnect['BUSINESS_ID'])
              ->getCampaignCollection()
              ->getItemById($campaignConnect['CAMPAIGN_ID']);

		if ($campaign === null)
		{
			throw new Main\SystemException("campaign {$campaignConnect['CAMPAIGN_ID']} not found for business {$campaignConnect['BUSINESS_ID']}");
		}

		return new Setup\Model([
			'TRADING_SERVICE' => Service\Manager::SERVICE_MARKETPLACE,
			'TRADING_BEHAVIOR' => Market\Trading\Campaign\Placement::toBehavior($campaign->getPlacement()),
			'ACTIVE' => Setup\Table::BOOLEAN_N,
			'BUSINESS_ID' => $campaignConnect['BUSINESS_ID'],
			'CAMPAIGN_ID' => $campaignConnect['CAMPAIGN_ID'],
			'SITE_ID' => $campaignConnect['SITE_ID'],
			'CODE' => isset($campaignConnect['URL_ID']) ? $campaignConnect['URL_ID'] : '',
			'SETTINGS' => [],
		]);
	}
}