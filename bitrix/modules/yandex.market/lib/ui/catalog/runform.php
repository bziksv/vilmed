<?php
/** @noinspection DuplicatedCode */
namespace Yandex\Market\Ui\Catalog;

use Bitrix\Main;
use Yandex\Market\Data;
use Yandex\Market\Error;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Result;
use Yandex\Market\State;
use Yandex\Market\Ui;
use Yandex\Market\Catalog as CatalogService;
use Yandex\Market\Logger\Trading\Table as LoggerTable;
use Yandex\Market\Utils;

class RunForm extends Ui\Reference\RunForm
{
	use Concerns\HasMessage;

	protected $catalogVariants = [];

	public function setTitle()
	{
		global $APPLICATION;

		$APPLICATION->SetTitle(self::getMessage('TITLE'));
	}

	protected function getWriteRights()
	{
		return Ui\Access::RIGHTS_PROCESS_EXPORT;
	}

	protected function processRun()
	{
        /** @var CatalogService\Setup\Model $setup */
		list($setup, $reset, $initTime, $step, $offset) = $this->bootRunContext();

		if ($step === null)
		{
			$setup->handleRefresh(false);
		}

		$processor = $this->createProcessor($setup, $initTime, $step, $offset);
		$processResult = $this->processorStart($processor, $reset);

		if (!$processResult->isSuccess())
		{
			return $this->runErrorResponse($processResult);
		}

		if (!$processResult->isFinished())
		{
			return $this->runProgressResponse($processor, $processResult, $setup, $reset, $initTime);
		}

        $setup->updateListener();

		return $this->runFinishMessage($setup, $initTime);
	}

	protected function bootRunContext()
	{
        $setup = CatalogService\Setup\Model::loadById((int)$this->request->getPost('SETUP_ID'));
        $reset = ($this->request->getPost('RESET') === 'Y');

		if ($this->request->getPost('INIT_TIME') !== null)
		{
			$initTime = Data\Type\CanonicalDateTime::createFromTimestamp($this->request->getPost('INIT_TIME'));
			$step = $this->request->getPost('STEP');
			$offset = $this->request->getPost('STEP_OFFSET');
		}
		else
		{
			$initTime = new Data\Type\CanonicalDateTime();
			$step = null;
			$offset = null;
		}

		return [$setup, $reset, $initTime, $step, $offset];
	}

	protected function createProcessor(CatalogService\Setup\Model $setup, Data\Type\CanonicalDateTime $initTime, $step = null, $offset = null)
	{
		return new CatalogService\Run\Processor($setup, [
            'step' => $step,
            'stepOffset' => $offset,
            'timeLimit' => $this->request->getPost('TIME_LIMIT'),
            'initTime' => $initTime,
            'startTime' => microtime(true),
            'progressCount' => true,
        ]);
	}

	protected function processorStart(CatalogService\Run\Processor $processor, $reset)
	{
		try
		{
			return $processor->run($reset ? Data\Run\Processor::ACTION_FULL : Data\Run\Processor::ACTION_REFRESH);
		}
		catch (Data\Run\PauseException $exception)
		{
			$result = new Result\StepProcessor();
			$result->addWarning(new Error\Base($exception->getMessage()));

			$result->setStep($exception->getStep());
			$result->setStepOffset($exception->getOffset());
			$result->setTimeout($exception->getTimeout());
			$result->setTotal(1);

			return $result;
		}
	}

	protected function runErrorResponse(Result\StepProcessor $processResult)
	{
		$errorMessage = $processResult->hasErrors()
			? implode('<br />', $processResult->getErrorMessages())
			: self::getMessage('ERROR_UNDEFINED');

		$adminMessage = new \CAdminMessage(array(
			'TYPE' => 'ERROR',
			'MESSAGE' => self::getMessage('ERROR_TITLE'),
			'DETAILS' => $errorMessage,
			'HTML' => true,
		));

		return [
			'status' => 'error',
			'message' => $adminMessage->Show(),
		];
	}

	protected function runProgressResponse(
        CatalogService\Run\Processor $processor,
		Result\StepProcessor $processResult,
        CatalogService\Setup\Model $setup,
        $reset,
		Main\Type\DateTime $initTime
	)
	{
		$progressMessage = '';

		foreach ($processor->steps() as $step)
		{
			if ($step->getName() !== $processResult->getStep()) { continue; }

			$readyCount = $processResult->getStepReadyCount();

			$progressMessage = '<p>';
			$progressMessage .= self::getMessage('PROGRESS_STEP_' . Utils\Name::screamingSnakeCase($step->getName()));

			if ($readyCount !== null)
			{
				$suffix = ($step->getName() === 'submitter' ? '_QUERY' : '');

				$progressMessage .= self::getMessage('PROGRESS_READY_COUNT', [
					'#COUNT#' => (int)$readyCount,
					'#LABEL#' => Utils::sklon($readyCount, [
						self::getMessage('PROGRESS_READY_COUNT_LABEL_1' . $suffix),
						self::getMessage('PROGRESS_READY_COUNT_LABEL_2' . $suffix),
						self::getMessage('PROGRESS_READY_COUNT_LABEL_5' . $suffix),
					]),
				]);
			}

			if ($processResult->hasWarnings())
			{
				$progressMessage .= ': ' . implode(', ', $processResult->getWarningMessages());
			}

			$progressMessage .= '...';
			$progressMessage .= '</p>';

			break;
		}

		if ($processResult->getTimeout() > 0)
		{
			$progressMessage .= sprintf('<small>%s</small>', self::getMessage('PROGRESS_TIMEOUT', [
				'#TIMEOUT#' => $processResult->getTimeout(),
			]));
		}

		$adminMessage = new \CAdminMessage(array(
			'TYPE' => 'PROGRESS',
			'MESSAGE' => self::getMessage('PROGRESS_TITLE'),
			'DETAILS' => $progressMessage,
			'HTML' => true,
		));

		return [
			'status' => 'progress',
			'message' => $adminMessage->Show(),
			'state' => [
				'sessid' => bitrix_sessid(),
				'SETUP_ID' => $setup->getId(),
				'INIT_TIME' => $initTime->getTimestamp(),
				'STEP' => $processResult->getStep(),
				'STEP_OFFSET' => $processResult->getStepOffset(),
				'TIMEOUT' => $processResult->getTimeout(),
                'RESET' => $reset ? 'Y' : 'N',
			],
		];
	}

	protected function runFinishMessage(CatalogService\Setup\Model $setup, Data\Type\CanonicalDateTime $initTime)
	{
		$offerStat = $this->offerStat($setup);
		$queueStat = $this->queueStat($setup, $initTime);

		if (empty($offerStat))
		{
			$message = new \CAdminMessage(self::getMessage('OFFERS_NOT_FOUND'));
		}
		else if (!$this->hasSuccessOffer($offerStat))
		{
			$message = new \CAdminMessage(self::getMessage('OFFERS_ONLY_ERROR'));
		}
		else if ($this->onlyErrorQueue($queueStat))
		{
			$message = new \CAdminMessage(self::getMessage('QUEUE_ONLY_ERROR'));
		}
		else
		{
			/** @noinspection HtmlUnknownTarget */
			$message = new \CAdminMessage(array(
				'TYPE' => 'OK',
				'MESSAGE' => self::getMessage('SUCCESS_TITLE'),
				'DETAILS' => sprintf('<a class="b-link-complex" href="%s" target="_blank">
						<svg class="b-icon size--small b-link-complex__icon" width="10" height="10">
							<use xlink:href="/bitrix/images/yandex.market/yml-actions.svg#launch"></use>
						</svg>
						<span class="b-link-complex__target">%s</span>
					</a>',
					"https://partner.market.yandex.ru/business/{$setup->getBusinessId()}/assortment",
					self::getMessage('PARTNER_ASSORTMENT')
				),
				'HTML' => true,
			));
		}

		return [
			'status' => 'ok',
			'message' => <<<HTML
				<div class="b-admin-message-list compensate--spacing message-width--auto">
					{$message->Show()}
				</div>
				{$this->offerMessage($offerStat)}
				{$this->queueMessage($queueStat + $this->archiveStat($setup, $initTime))}
				{$this->logMessage($setup, $initTime)}
HTML
		];
	}

	protected function offerStat(CatalogService\Setup\Model $setup)
	{
		$offersSort = array_flip([
			CatalogService\Run\Storage\OfferTable::STATUS_SUCCESS,
			CatalogService\Run\Storage\OfferTable::STATUS_DUPLICATE,
			CatalogService\Run\Storage\OfferTable::STATUS_ERROR,
			CatalogService\Run\Storage\OfferTable::STATUS_DELETE,
		]);

		$offers = array_column(CatalogService\Run\Storage\OfferTable::getList([
			'filter' => [ '=CATALOG_ID' => $setup->getId() ],
			'select' => [ 'STATUS', 'CNT' ],
			'group' => [ 'STATUS' ],
			'runtime' => [ new Main\Entity\ExpressionField('CNT', 'COUNT(1)') ],
		])->fetchAll(), 'CNT', 'STATUS');

		uksort($offers, static function($aStatus, $bStatus) use ($offersSort) {
			$aSort = isset($offersSort[$aStatus]) ? $offersSort[$aStatus] : 10;
			$bSort = isset($offersSort[$bStatus]) ? $offersSort[$bStatus] : 10;

			if ($aSort === $bSort) { return 0; }

			return ($aSort < $bSort ? -1 : 1);
		});

		return $offers;
	}

	protected function hasSuccessOffer(array $offerStat)
	{
		return !empty($offerStat[CatalogService\Run\Storage\OfferTable::STATUS_SUCCESS]);
	}

	protected function offerMessage(array $offerStat)
	{
		$partials = [];
		$unit = self::getMessage('OFFER_UNIT');

		foreach ($offerStat as $status => $cnt)
		{
			$statusTitle = self::getMessage('OFFER_STATUS_' . $status);

			$partials[] = <<<HTML
				<div class="spacing--1x4">{$statusTitle}: {$cnt} {$unit}</div>
HTML;
		}

		if (empty($partials)) { return ''; }

		$text = implode('', $partials);

		return <<<HTML
			<div class="b-admin-text-message spacing--1x1">{$text}</div>
HTML;
	}

	protected function queueStat(CatalogService\Setup\Model $setup, Data\Type\CanonicalDateTime $initTime)
	{
		$endpointsSort = array_flip([
			CatalogService\Glossary::ENDPOINT_OFFER,
			CatalogService\Glossary::ENDPOINT_CARD,
			CatalogService\Glossary::ENDPOINT_TERMS,
			CatalogService\Glossary::ENDPOINT_PRICE,
			CatalogService\Glossary::ENDPOINT_STOCKS,
		]);

		$endpoints = Utils\ArrayHelper::groupBy(CatalogService\Run\Storage\QueueTable::getList([
			'filter' => [ '=CATALOG_ID' => $setup->getId(), '>=TIMESTAMP_X' => $initTime ],
			'select' => [ 'ENDPOINT', 'STATUS', 'CNT' ],
			'group' => [ 'ENDPOINT', 'STATUS' ],
			'runtime' => [ new Main\Entity\ExpressionField('CNT', 'COUNT(DISTINCT(%s))', 'SKU') ],
		])->fetchAll(), 'ENDPOINT');
		$endpoints = array_intersect_key($endpoints, $endpointsSort);

		uksort($endpoints, static function($aEndpoint, $bEndpoint) use ($endpointsSort) {
			$aSort = isset($endpointsSort[$aEndpoint]) ? $endpointsSort[$aEndpoint] : 10;
			$bSort = isset($endpointsSort[$bEndpoint]) ? $endpointsSort[$bEndpoint] : 10;

			if ($aSort === $bSort) { return 0; }

			return ($aSort < $bSort ? -1 : 1);
		});

		return array_map(
			static function(array $rows) { return array_column($rows, 'CNT', 'STATUS'); },
			$endpoints
		);
	}

	protected function archiveStat(CatalogService\Setup\Model $setup, Data\Type\CanonicalDateTime $initTime)
	{
		$stat = array_column(CatalogService\Run\Storage\QueueTable::getList([
			'filter' => [
				'=CATALOG_ID' => $setup->getId(),
				'=ENDPOINT' => CatalogService\Glossary::ENDPOINT_ARCHIVE,
				'=CAMPAIGN_ID' => 0,
				'=PAYLOAD' => Main\Web\Json::encode([ 'value' => true ]),
				'>=TIMESTAMP_X' => $initTime,
			],
			'select' => [ 'STATUS', 'CNT' ],
			'group' => [ 'STATUS' ],
			'runtime' => [ new Main\Entity\ExpressionField('CNT', 'COUNT(1)') ],
		])->fetchAll(), 'CNT', 'STATUS');

		if (empty($stat)) { return []; }

		return [
			CatalogService\Glossary::ENDPOINT_ARCHIVE => $stat,
		];
	}

	protected function onlyErrorQueue(array $queueStat)
	{
		$hasErrors = false;

		foreach ($queueStat as $endpointStat)
		{
			if (!empty($endpointStat[CatalogService\Run\Storage\QueueTable::STATUS_SUCCESS]))
			{
				return false;
			}

			if (!empty($endpointStat[CatalogService\Run\Storage\QueueTable::STATUS_ERROR]))
			{
				$hasErrors = true;
			}
		}

		return $hasErrors;
	}

	protected function queueMessage(array $endpoints)
	{
		$partials = [];
		$unitTitle = self::getMessage('ENDPOINT_UNIT');

		foreach ($endpoints as $endpoint => $endpointStat)
		{
			$endpointTitle = self::getMessage('ENDPOINT_' . mb_strtoupper($endpoint));
			$totalCount = isset($endpointStat[CatalogService\Run\Storage\QueueTable::STATUS_SUCCESS])
				? (int)$endpointStat[CatalogService\Run\Storage\QueueTable::STATUS_SUCCESS]
				: 0;
			$additionalCounts = array_diff_key($endpointStat, [
				CatalogService\Run\Storage\QueueTable::STATUS_SUCCESS => true,
			]);
			$additionalPartials = [];

			foreach ($additionalCounts as $queueStatus => $queueCount)
			{
				$statusTitle = self::getMessage('ENDPOINT_STATUS_' . $queueStatus);

				$additionalPartials[] = "{$statusTitle} - {$queueCount}";
			}

			$additionalPart = !empty($additionalPartials) ? '(' . implode(', ', $additionalPartials) . ')' : '';

			$partials[] = <<<HTML
				<div class="spacing--1x4">{$endpointTitle}: {$totalCount} {$unitTitle} {$additionalPart}</div>
HTML;
		}

		if (empty($partials)) { return ''; }

		$title = self::getMessage('QUEUE_STAT');
		$text = implode('', $partials);

		return <<<HTML
			<div class="b-admin-text-message spacing--1x1">
				<em>{$title}</em>
				{$text}
			</div>
HTML;
	}

	protected function logMessage(CatalogService\Setup\Model $setup, Data\Type\CanonicalDateTime $initTime)
	{
		$exists = LoggerTable::getRow([
			'filter' => [
				'=SETUP_TYPE' => CatalogService\Glossary::SERVICE_SELF,
				'=SETUP_ID' => $setup->getId(),
				'>=TIMESTAMP_X' => $initTime,
			],
			'select' => [ 'ID' ],
		]);

		if (!$exists) { return ''; }

		$text = self::getMessage('LOG_URL');
		$url = Ui\Admin\Path::getModuleUrl('trading_log', [
			'business' => $setup->getBusinessId(),
			'set_filter' => 'Y',
			'apply_filter' => 'Y',
			'find_setup' => "CATALOG_SETUP:{$setup->getId()}",
		]);

		return <<<HTML
			<div class="b-admin-text-message spacing--1x1">
				<a href="{$url}">{$text}</a>
			</div>
HTML;

	}

	protected function processStop()
	{
		return [
			'status' => 'ok',
		];
	}

	protected function getTabControlId()
	{
		return 'YANDEX_MARKET_ADMIN_SALES_CATALOG_RUN';
	}

	public function preload()
	{
        $query = CatalogService\Setup\Table::getList([
			'filter' => Ui\Trading\Menu::businessFilter(Ui\Trading\Menu::extractBusinessId()),
            'select' => [ 'ID', 'BUSINESS_ID', 'BUSINESS_NAME' => 'BUSINESS.NAME' ],
        ]);

		while ($row = $query->fetch())
		{
            $this->catalogVariants[] = [
                'ID' => $row['ID'],
                'NAME' => "[{$row['BUSINESS_ID']}] {$row['BUSINESS_NAME']}",
            ];
		}
	}

	protected function showFormBody()
	{
		$this->showSetupField();
		$this->showTimeField();
        $this->showResetField();
	}

	protected function showSetupField()
	{
		$selected = (int)$this->request->get('id');

		?>
		<tr>
			<td width="40%" align="right" valign="middle"><?= self::getMessage('FIELD_SETUP') ?></td>
			<td width="60%">
				<select name="SETUP_ID">
					<?php
					foreach ($this->catalogVariants as $variant)
					{
						/** @noinspection HtmlUnknownAttribute */
						echo sprintf(
							'<option value="%s" %s>%s</option>',
							$variant['ID'],
                            $selected === $variant['ID'] ? 'selected' : '',
							Utils::htmlEscape($variant['NAME'])
						);
					}
					?>
				</select>
			</td>
		</tr>
		<?php
	}

    protected function showResetField()
    {
        $selected = (int)$this->request->get('id');

        ?>
        <tr>
            <td width="40%" align="right" valign="middle">
                <span class="b-icon icon--question indent--right b-tag-tooltip--holder">
					<span class="b-tag-tooltip--content"><?= self::getMessage('FIELD_RESET_HELP') ?></span>
				</span><?= self::getMessage('FIELD_RESET') ?>
            </td>
            <td width="60%"><input type="checkbox" name="RESET" value="Y" <?= !$this->wasSubmitted($selected) ? 'checked' : '' ?> /></td>
        </tr>
        <?php
    }

	protected function wasSubmitted($id)
	{
		return (State::get("catalog_submitted_{$id}", 'N') === 'Y');
	}
}