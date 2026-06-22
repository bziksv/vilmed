<?php
namespace Yandex\Market\Ui\Trading\Molecules;

use Bitrix\Main;
use Yandex\Market\Data\Number;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Business;
use Yandex\Market\Ui;
use Yandex\Market\Utils;

class BusinessSelector
{
	use Concerns\HasOnce;
	use Concerns\HasMessage;

	protected $optionCategory;
	protected $request;

	public function __construct($optionCategory, Main\HttpRequest $request = null)
	{
		$this->optionCategory = $optionCategory;
		$this->request = $request ?: Main\Application::getInstance()->getContext()->getRequest();
	}

	public function selected()
	{
		$requested = $this->requested() ?: null; // 0 to null
		$known = Ui\Trading\Menu::isKnown($requested);
		$incoming = $requested !== null || $known ? $requested : $this->stored();
		$collection = $this->collection();

		if ($incoming !== null)
		{
			$business = $collection->getItemById($incoming);

			if ($business === null)
			{
				throw new Main\ObjectNotFoundException(self::getMessage('NOT_FOUND', [
					'#ID#' => $incoming,
				]));
			}

			if ($requested !== null && !$known)
			{
				$this->store($business);
			}
		}
		else
		{
			$business = $collection->offsetGet(0);

			if ($business === null)
			{
				throw new Main\ObjectNotFoundException(self::getMessage('NOT_EXISTS'));
			}
		}

		return $business;
	}

	protected function requested()
	{
		return Number::castInteger($this->request->get('business'));
	}

	protected function stored()
	{
		$option = (string)\CUserOptions::GetOption($this->optionCategory, 'business', '');

		return $option !== '' ? (int)$option : null;
	}

	protected function store(Business\Model $selected)
	{
		if ($this->stored() === (int)$selected->getId()) { return; }

		\CUserOptions::SetOption($this->optionCategory, 'business', $selected->getId());
	}

	public function show(Business\Model $selected = null, $force = false)
	{
		$options = $this->buildOptions($selected);
		$showLimit = $force ? 0 : 1;

		if (count($options) <= $showLimit) { return; }

		if (Utils\BitrixTemplate::isBitrix24())
		{
			$this->renderCrm($options);
		}
		else
		{
			$this->renderAdmin($options);
		}
	}

	protected function buildOptions(Business\Model $selected = null)
	{
		global $APPLICATION;

		$selectedId = $selected !== null ? (int)$selected->getId() : null;
		$result = [];

		/** @var Business\Model $business */
		foreach ($this->collection() as $business)
		{
			$result[] = [
				'ID' => $business->getId(),
				'VALUE' => sprintf('[%s] %s', $business->getId(), $business->getField('NAME')),
				'URL' => $APPLICATION->GetCurPageParam(http_build_query([ 'business' => $business->getId() ]), [ 'business' ]),
				'SELECTED' => ($selectedId === (int)$business->getId()),
			];
		}

		return $result;
	}

	/** @noinspection JSUnresolvedReference */
	protected function renderCrm(array $options)
	{
		global $APPLICATION;

		$selectedOptions = array_filter($options, static function(array $option) { return $option['SELECTED']; });
		$selectedOption = reset($selectedOptions);
		$dropdownItems = array_map(static function(array $option) {
			return [
				'text' => $option['VALUE'],
				'link' => $option['URL'],
				'selected' => $option['SELECTED'],
			];
		}, $options);
		$dropdownItems = array_filter($dropdownItems, static function(array $item) { return !$item['selected']; });
		$dropdownItems = array_values($dropdownItems);

		$html = sprintf(
			'<div class="crm-interface-toolbar-button-container">
				<button class="ui-btn ui-btn-dropdown ui-btn-light-border" type="button" id="yamarket-setup-selector">
					%s
				</button>
			</div>',
			$selectedOption !== false ? $selectedOption['VALUE'] : 'TRADING BEHAVIOR'
		);
		$html .= sprintf(
			'<script>
				BX.ready(function() {
					const button = BX("yamarket-setup-selector");
					const items = JSON.parse(\'%s\');
					
					if (!button || !items) { return; }
					
					items.forEach(function(item) {
						item.onclick = function() { window.location.href = item.link; };
					});
					
					const menu = new BX.PopupMenuWindow({
						bindElement: button,
						items: items,
					});
			
					button.addEventListener("click", function() { menu.show(); });
				});
			</script>',
			Main\Web\Json::encode($dropdownItems)
		);

		$APPLICATION->AddViewContent('inside_pagetitle', $html);
	}

	/** @noinspection HtmlUnknownTarget */
	protected function renderAdmin(array $options)
	{
		echo '<div style="margin-bottom: 10px;">';

		foreach ($options as $option)
		{
			if ($option['SELECTED'])
			{
				echo sprintf(
					' <span class="adm-btn adm-btn-active">%s</span>',
					htmlspecialcharsbx($option['VALUE'])
				);
			}
			else
			{
				echo sprintf(
					' <a class="adm-btn" href="%s">%s</a>',
					htmlspecialcharsbx($option['URL']),
					htmlspecialcharsbx($option['VALUE'])
				);
			}
		}

		echo '</div>';
	}

	/** @return Business\Collection */
	protected function collection()
	{
		$requested = $this->requested();

		return $this->once('collection', [$requested], function($requested) {
			return Business\Collection::loadByFilter([
				'filter' => Ui\Trading\Menu::businessFilter($requested, 'ID'),
			]);
		});
	}
}