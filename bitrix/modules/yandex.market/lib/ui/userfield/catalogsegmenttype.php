<?php
namespace Yandex\Market\Ui\UserField;

use Yandex\Market\Catalog\Segment;
use Yandex\Market\Export\Param;
use Yandex\Market\Reference\Assert;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Trading\Business;
use Yandex\Market\Ui\Extension;
use Yandex\Market\Utils;

/** @noinspection PhpUnused */
class CatalogSegmentType implements Form\FullLineLayout
{
	use Concerns\HasMessage;

	public static function getEditFullLineHtml(array $userField, array $htmlControl = null)
	{
		return Helper\Renderer::failSafe(static function() use ($userField, $htmlControl) {
			Extension::load('@Ui.UserField.CatalogSegment');

			/** @var Segment\Factory $factory */
			$title = $userField['EDIT_FORM_LABEL'] ?: $userField['LIST_COLUMN_LABEL'];
			$factory = $userField['SETTINGS']['FACTORY'];
			$values = Helper\ComplexValue::asMultiple($userField, $htmlControl);
			$valuesMap = static::mapCampaignValues($values);
			$context = self::fieldContext($userField);
			$activeTab = !isset($htmlControl['ACTIVE_TAB']) || $htmlControl['ACTIVE_TAB'];

			Assert::isInstanceOf($factory, Segment\Factory::class);

			$business = self::business($userField);

			$paramGroups = array_merge(
                static::businessGroups($userField['FIELD_NAME'], $valuesMap, $factory->businessConfig($business)),
                self::campaignGroups($userField['FIELD_NAME'], $valuesMap, $factory->campaignConfigs($business))
            );
            list($hiddenHtml, $paramGroups) = self::editHidden($paramGroups);

			if (empty($paramGroups))
			{
				return self::disabledEnable($userField);
			}

			list($enableHtml, $enabled) = self::editEnable($userField);
			$titleHelp = self::titleHelp($paramGroups);
            $paramHtml = static::editParam($paramGroups, $context, $userField['SETTINGS'], $activeTab, $enabled);
			$enableClass = $enabled ? '' : 'is--disabled';

			return <<<HTML
				<fieldset class="ym-catalog-segment {$enableClass} js-plugin" data-plugin="Ui.UserField.CatalogSegment">
					<legend class="ym-catalog-segment__legend">{$title} {$titleHelp}</legend>
					{$hiddenHtml}
					{$enableHtml}
					<div class="ym-catalog-segment__body">{$paramHtml}</div>
				</fieldset>
HTML;
		});
	}

	protected static function business(array $userField)
	{
		$row = isset($userField['COMPOUND_KEY']) ? $userField['ROW'][$userField['COMPOUND_KEY']] : $userField['ROW'];

		Assert::notNull($row['BUSINESS_MODEL'], 'row[BUSINESS_MODEL]');
		Assert::isInstanceOf($row['BUSINESS_MODEL'], Business\Model::class);

		return $row['BUSINESS_MODEL'];
	}

	protected static function mapCampaignValues(array $values)
	{
		$result = [];

		foreach ($values as $value)
		{
			if (!isset($value['CAMPAIGN_ID'])) { continue; }

			$result[(int)$value['CAMPAIGN_ID']] = $value;
		}

		return $result;
	}

	protected static function fieldContext(array $userField)
	{
		$chain = Utils\Field::splitKey($userField['FIELD_NAME'], Utils\Field::GLUE_BRACKET);
		array_pop($chain);
		$parentValue = Utils\Field::getChainValue($userField['ROW'], $chain);

		return $parentValue['CONTEXT'];
	}

	protected static function businessGroups($baseName, array $valuesMap, Segment\BusinessConfig $businessConfig = null)
	{
        if ($businessConfig === null) { return []; }

        $businessName = "{$baseName}[0]";

        return [
            [
                'INPUT_NAME' => "{$businessName}[PARAM]",
                'FORMAT' => $businessConfig->format(),
                'VALUE' => isset($valuesMap[0]['PARAM']) ? $valuesMap[0]['PARAM'] : null,
                'HIDDEN' => [
                    "{$businessName}[ID]" => !empty($valuesMap[0]['ID']) ? (int)$valuesMap[0]['ID'] : null,
                    "{$businessName}[CAMPAIGN_ID]" => 0,
                ],
            ],
        ];
	}

	protected static function campaignGroups($baseName, array $valuesMap, array $campaignConfigs)
	{
        $result = [];

        /** @var Segment\CampaignConfig $campaignConfig */
		foreach ($campaignConfigs as $campaignConfig)
		{
            $campaignId = $campaignConfig->campaignId();
            $campaignName = "{$baseName}[{$campaignId}]";
            $campaignValue = isset($valuesMap[$campaignId]) ? $valuesMap[$campaignId] : [];
			$placementType = $campaignConfig->placementType();

            $result[] = [
                'INPUT_NAME' => "{$campaignName}[PARAM]",
                'TITLE' => $placementType !== null
					? "{$placementType} &middot; {$campaignConfig->campaignTitle()}"
	                : $campaignConfig->campaignTitle(),
                'FORMAT' => $campaignConfig->format(),
                'VALUE' => isset($campaignValue['PARAM']) ? $campaignValue['PARAM'] : null,
                'HIDDEN' => [
                    "{$campaignName}[ID]" => !empty($campaignValue['ID']) ? (int)$campaignValue['ID'] : null,
                    "{$campaignName}[CAMPAIGN_ID]" => $campaignConfig->campaignId(),
                ],
            ];
		}

		return $result;
	}

    protected static function editHidden(array $groups)
    {
        $hidden = [];

        foreach ($groups as &$group)
        {
            if (!isset($group['HIDDEN'])) { continue; }

            foreach ($group['HIDDEN'] as $name => $value)
            {
                if ($value === null || $value === '') { continue; }

                $hidden[] = sprintf('<input type="hidden" name="%s" value="%s" />', $name, $value);
            }

            unset($group['HIDDEN']);
        }
        unset($group);

        return [
            implode(PHP_EOL, $hidden),
            $groups,
        ];
    }

	protected static function titleHelp(array $groups)
	{
		$links = [];

		foreach ($groups as $group)
		{
			/** @var Param\Format $format */
			$format = $group['FORMAT'];
			$url = $format->getDocumentationUrl();

			if (empty($url)) { continue; }

			/** @noinspection HtmlUnknownTarget */
			$links[] = sprintf('<a href="%1$s">%1$s</a>', htmlspecialcharsbx($url));
		}

		if (empty($links)) { return ''; }

		$title = self::getMessage('DOCUMENTATION_URL');
		$linkHtml = implode('<br />', array_unique($links));

		return <<<HTML
			<span class="b-icon icon--question size--small b-tag-tooltip--holder">
				<span class="b-tag-tooltip--content b-tag-tooltip--content_right">
					<strong>{$title}</strong><br/>
					{$linkHtml}
				</span>
			</span>
HTML;

	}

	protected static function disabledEnable(array $userField)
	{
		if (empty($userField['ENABLE_FIELD'])) { return ''; }

		$enableField = $userField['ENABLE_FIELD'];

		return sprintf('<input type="hidden" name="%s" value="%s" />', $enableField['FIELD_NAME'], BooleanType::VALUE_N);
	}

    protected static function editEnable(array $userField)
    {
        if (empty($userField['ENABLE_FIELD'])) { return [ '', '' ]; }

        $enableField = $userField['ENABLE_FIELD'];
        $enableValue = Utils\Field::getChainValue($userField['ROW'], $enableField['FIELD_NAME'], Utils\Field::GLUE_BRACKET);

        if ($enableValue === null && isset($enableField['SETTINGS']['DEFAULT_VALUE']))
        {
            $enableValue = $enableField['SETTINGS']['DEFAULT_VALUE'];
        }

        $checkbox = Helper\Renderer::getEditHtml($enableField, $enableValue, $userField['ROW']);

        $html = <<<ENABLE
            <div class="ym-catalog-segment__enable">
                {$checkbox}
                {$enableField['LIST_COLUMN_LABEL']}
            </div>
ENABLE;

        return [
            $html,
	        (string)$enableValue === BooleanType::VALUE_Y,
        ];
    }

	protected static function editParam(array $groups, array $context, array $settings, $activeTab, $enabled)
	{
		global $APPLICATION;

		ob_start();

		$APPLICATION->IncludeComponent('yandex.market:admin.form.field', 'param', [
            'GROUPS' => $groups,
			'CONTEXT' => $context,
			'ACTIVE_TAB' => $activeTab,
			'ENABLED' => $enabled,
			'SKIP_DOCUMENTATION' => true,
		] + array_intersect_key($settings, [
			'GROUP_FLAT' => true,
		]), false, [
			'HIDE_ICONS' => 'Y',
		]);

		return ob_get_clean();
	}
}