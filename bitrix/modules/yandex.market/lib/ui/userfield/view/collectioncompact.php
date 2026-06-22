<?php
namespace Yandex\Market\Ui\UserField\View;

class CollectionCompact extends Collection
{
	protected static function renderRowActions($isLast = false)
	{
		return
			parent::renderRowActions($isLast)
			. sprintf('<a class="b-add js-input-collection__add %s" href="#"></a>', $isLast ? '' : 'is--hidden');
	}

	protected static function renderGroupActions()
	{
		return '';
	}
}