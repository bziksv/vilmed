<?php
namespace Yandex\Market\Catalog\Card;

use Yandex\Market\Catalog;

class Model extends Catalog\Segment\Model
{
	public static function getDataClass()
	{
		return Table::class;
	}

    public function getCampaignId()
    {
        return 0;
    }
}