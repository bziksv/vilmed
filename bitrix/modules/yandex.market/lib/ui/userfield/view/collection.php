<?php

namespace Yandex\Market\Ui\UserField\View;

use Bitrix\Main;
use Yandex\Market;

class Collection
{
	public static function render($name, array $values, \Closure $renderer, array $attributes = [])
	{
		Market\Ui\Assets::loadPlugins([
			'Field.Raw.Collection',
			'Field.Raw.Item',
		]);

		$valueIndex = 0;

		if (empty($values)) { $values[] = ''; }

		$attributes += [
			'class' => 'js-plugin',
			'data-plugin' => 'Field.Raw.Collection',
		];

		if (!isset($attributes['data-name']))
		{
			$attributes['data-base-name'] = $name;
		}

		$result = sprintf('<table %s>', Market\Ui\UserField\Helper\Attributes::stringify($attributes));
		$valuesCount = count($values);

		foreach ($values as $value)
		{
			$isLast = ($valuesCount - 1 === $valueIndex);
			$itemName = $name . '[' . $valueIndex . ']';

			$result .= '<tr class="js-input-collection__item"><td>';
			$result .= $renderer($itemName, $value);
			$result .= ' ';
			$result .= static::renderRowActions($isLast);
			$result .= '</td></tr>';

			++$valueIndex;
		}

		$result .= static::renderGroupActions();
		$result .= '</table>';

		return $result;
	}

	protected static function renderRowActions($isLast = false)
	{
		return '<a class="b-remove js-input-collection__delete" href="#"></a>';
	}

	protected static function renderGroupActions()
	{
		return sprintf(
			'<tr><td><input class="js-input-collection__add" type="button" value="%s"></td></tr>',
			Main\Localization\Loc::getMessage('USER_TYPE_PROP_ADD')
		);
	}
}