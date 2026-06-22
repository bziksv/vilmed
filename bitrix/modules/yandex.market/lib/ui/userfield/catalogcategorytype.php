<?php
namespace Yandex\Market\Ui\UserField;

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Ui\Extension;
use Yandex\Market\Ui\Iblock\CategoryProvider;
use Yandex\Market\Utils;

/** @noinspection PhpUnused */
class CatalogCategoryType implements Form\FullLineLayout
{
	use Concerns\HasMessage;

	const INIT_DEPTH = 1;

	protected static $first = true;
	
	public static function getEditFullLineHtml(array $userField, array $htmlControl = null)
	{
		return Helper\Renderer::failSafe(static function() use ($userField, $htmlControl) {
			Assert::notNull($userField['CONTEXT']['IBLOCK_ID'], 'userField[CONTEXT][IBLOCK_ID]');
			
			self::loadModules();

			Extension::load('@Ui.UserField.CatalogCategory');

			$tableId = "ym-section-category-{$userField['CONTEXT']['IBLOCK_ID']}";
			$selected = Helper\ComplexValue::asMultiple($userField, $htmlControl);

			$html = self::title($userField['CONTEXT']);
			$html .= self::header($tableId);

			foreach (self::sections($userField['CONTEXT']['IBLOCK_ID'], $selected) as $section)
			{
				$html .= self::sectionRow($userField['FIELD_NAME'], $section);
			}

			$html .= self::footer();
			$html .= self::script($tableId, $userField);

			return $html;
		});
	}
	
	private static function loadModules()
	{
		if (!Main\Loader::includeModule('iblock'))
		{
			throw new Main\SystemException('cant load iblock module');
		}
	}

	private static function title(array $iblockContext)
	{
		$iblockTitle = self::getMessage('IBLOCK_NAME', [ '#NAME#' => $iblockContext['IBLOCK_NAME'] ]);

		if (self::$first)
		{
			self::$first = false;

			$title = self::getMessage('TITLE');

			return <<<HTML
				<span class="b-heading level--2 pos--top">{$title}</span>
				<span class="ym-section-category-title b-heading level--3">{$iblockTitle}</span>
HTML;
		}

		return <<<HTML
			<span class="ym-section-category-margin b-heading level--3">{$iblockTitle}</span>
HTML;
	}

	private static function header($tableId)
	{
		$headingBitrix = self::getMessage('BITRIX_CATEGORY');
		$headingMarket = self::getMessage('MARKET_CATEGORY');

		return <<<HTML
		    <div class="ym-section-category" id="{$tableId}">
	            <div class="ym-section-category__header">
	                <div class="ym-section-category__cell for--local">{$headingBitrix}</div>
	                <div class="ym-section-category__cell for--external">{$headingMarket}</div>
	            </div>
HTML;
	}

	private static function sectionRow($fieldName, array $section)
	{
		global $APPLICATION;

		$depth = min(4, $section['DEPTH_LEVEL']);
		$hidden = $section['ACTIVE'] ? '' : 'hidden';
		$indent = $section['DEPTH_LEVEL'] > 0 ? str_repeat('<span class="ym-section-category__indent"></span>', $section['DEPTH_LEVEL']) : '';

		if ($section['CHILDREN_COUNT'] > 0 && $section['ID'] > 0)
		{
			$childrenReplaces = [
				'#COUNT#' => $section['CHILDREN_COUNT'],
				'#UNIT#' => Utils::sklon($section['CHILDREN_COUNT'], [
					self::getMessage('CHILDREN_COUNT_1'),
					self::getMessage('CHILDREN_COUNT_2'),
					self::getMessage('CHILDREN_COUNT_5'),
				]),
			];

			$expand = self::getMessage('CHILDREN_EXPAND', $childrenReplaces);
			$collapse = self::getMessage('CHILDREN_COLLAPSE', $childrenReplaces);

			$title = sprintf('<div class="ym-section-category__parent">
					<span class="ym-section-category__title">%s</span>
					<button class="ym-section-category__expand %s" type="button" data-alt="%s"><span class="ym-section-category__expand-text">%s</span></button>
				</div>',
				$section['NAME'],
				$section['EXPANDED'] ? 'is--active' : '',
				$section['EXPANDED'] ? $expand : $collapse,
				$section['EXPANDED'] ? $collapse : $expand
			);
		}
		else
		{
			$title = sprintf('<span class="ym-section-category__title">%s</span>', $section['NAME']);
		}

		$propertyCategory = $APPLICATION->IncludeComponent(
			'yandex.market:admin.property.category',
			'.default',
			[
				'PROPERTY_TYPE' => 'userField',
				'CONTROL_NAME' => "{$fieldName}[{$section['ID']}]",
				'VALUE' => $section['VALUE'],
				'PARENT_VALUE' => $section['PARENT_VALUE'],
				'THEME' => 'tab',
				'SKIP_INIT' => 'Y',
				'COPY_BUTTON' => 'N',
			],
			false,
			[ 'HIDE_ICONS' => 'Y' ]
		);

		return <<<HTML
	        <div class="ym-section-category__row" data-id="{$section['ID']}" data-parent="{$section['PARENT_ID']}" {$hidden}>
	            <div class="ym-section-category__cell for--local depth--{$depth}">{$indent}{$title}</div>
	            <div class="ym-section-category__cell for--external">{$propertyCategory}</div>
	        </div>
HTML;
	}

	private static function footer()
	{
		return '</div>';
	}

	/** @noinspection JSUnresolvedReference */
	/** @noinspection BadExpressionStatementJS */
	private static function script($tableId, array $userField)
	{
		$categorySelectComponent = new \CBitrixComponent();
		$categorySelectComponent->initComponent('yandex.market:admin.property.category');

		$options = Main\Web\Json::encode([
			'transport' => [
				'url' => $categorySelectComponent->getPath() . '/ajax.php',
				'componentParameters' => [
					'PROPERTY_TYPE' => 'userField',
				],
			],
			'form' => [
				'apiKeyField' => $userField['API_KEY_FIELD'],
			],
			'locale' => [
				'COLLAPSE' => self::getMessage('COLLAPSE'),
				'EXPAND' => self::getMessage('EXPAND'),
				'CATEGORY_PLACEHOLDER' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_PLACEHOLDER'),
				'CATEGORY_LOAD_ERROR' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_LOAD_ERROR'),
				'CATEGORY_EMPTY' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_CATEGORY_EMPTY'),
				'LOADING' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_LOADING'),
				'PARAMETER_DEPRECATED' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_DEPRECATED'),
				'PARAMETER_DELETE' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETER_DELETE'),
				'EMPTY_PROPERTIES' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_EMPTY_PROPERTIES'),
				'PARAMETERS_ADD_HINT' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_PARAMETERS_ADD_HINT'),
				'BOOLEAN_Y' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_BOOLEAN_Y'),
				'BOOLEAN_N' => Loc::getMessage('YANDEX_MARKET_CATEGORY_COMPONENT_BOOLEAN_N'),
			],
			'language' => LANGUAGE_ID,
		]);

		return <<<HTML
			<script> 
				BX.ready(function() {            
		            new BX.YandexMarket.Ui.UserField.CatalogCategory(document.getElementById("{$tableId}"), {$options})
				});
			</script>
HTML;
	}

	private static function sections($iblockId, array $selected)
	{
		$sections = [
			0 => [
				'ID' => 0,
				'PARENT_ID' => null,
				'NAME' => self::getMessage('ROOT_SECTION'),
				'DEPTH_LEVEL' => 0,
				'VALUE' => isset($selected[0]) ? $selected[0] : null,
				'PARENT_VALUE' => null,
			],
		];

		$query = \CIBlockSection::GetList(
			['LEFT_MARGIN' => 'ASC'],
			['IBLOCK_ID' => $iblockId, 'GLOBAL_ACTIVE' => 'Y'],
			false,
			['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'DEPTH_LEVEL', 'NAME']
		);

		while ($section = $query->Fetch())
		{
			$sections[$section['ID']] = [
				'ID' => (int)$section['ID'],
				'PARENT_ID' => (int)$section['IBLOCK_SECTION_ID'],
				'NAME' => $section['NAME'],
				'DEPTH_LEVEL' => (int)$section['DEPTH_LEVEL'],
				'VALUE' => isset($selected[$section['ID']]) ? $selected[$section['ID']] : null,
				'PARENT_VALUE' => null,
				'CHILDREN_COUNT' => 0,
				'EXPANDED' => false,
				'ACTIVE' => false,
			];
		}

		$sections = self::calculateChildrenCount($sections);
		$sections = self::markActive($sections);
		$sections = self::markExpanded($sections);
		$sections = self::fillParentValue($sections);

		return $sections;
	}

	private static function calculateChildrenCount(array $sections)
	{
		foreach ($sections as $section)
		{
			if (isset($sections[$section['PARENT_ID']]))
			{
				++$sections[$section['PARENT_ID']]['CHILDREN_COUNT'];
			}
		}

		return $sections;
	}

	private static function markActive(array $sections)
	{
		$stack = [];
		$already = [];

		foreach ($sections as $key => &$section)
		{
			$depth = $section['DEPTH_LEVEL'];

			array_splice($stack, $depth + 1);
			array_splice($already, $depth + 1);

			if (!isset($stack[$depth])) { $stack[$depth] = []; }

			$stack[$depth][] = $key;

			if (!empty($section['VALUE']['CATEGORY']) || $depth <= self::INIT_DEPTH)
			{
				foreach ($stack as $siblingDepth => $siblings)
				{
					$already[$siblingDepth] = true;

					foreach ($siblings as $siblingKey)
					{
						$sections[$siblingKey]['ACTIVE'] = true;
					}
				}
			}
			else if (!empty($already[$depth]))
			{
				$section['ACTIVE'] = true;
			}
			else
			{
				$already[$depth] = false;
			}
		}

		return $sections;
	}

	private static function markExpanded(array $sections)
	{
		$stack = [];

		foreach ($sections as $key => $section)
		{
			$depth = $section['DEPTH_LEVEL'];

			array_splice($stack, $depth);

			if ($section['ACTIVE'])
			{
				foreach ($stack as $parentKey)
				{
					$sections[$parentKey]['EXPANDED'] = true;
				}
			}

			$stack[$depth] = $key;
		}

		return $sections;
	}

	private static function fillParentValue(array $sections)
	{
		foreach ($sections as &$section)
		{
			$level = $section;
			$parentValue = null;

			do
			{
				if (!isset($level['PARENT_ID'], $sections[$level['PARENT_ID']])) { break; }

				$parent = $sections[$level['PARENT_ID']];
				$parentValue = CategoryProvider::mergeValue($parentValue, $parent['VALUE']);

				if (!empty($parentValue['CATEGORY'])) { break; }

				$level = $parent;
			}
			while (true);

			if ($parentValue !== null && !empty($section['VALUE']['CATEGORY']))
			{
				$parentValue['PARAMETERS'] = [];
			}

			$section['PARENT_VALUE'] = $parentValue;
		}
		unset($section);

		return $sections;
	}
}