<?php
/**
 * Created by PhpStorm.
 * User: darkfriend <hi@darkfriend.ru>
 * Date: 03.01.2024
 * Time: 19:51
 */

namespace Dev2fun\ImageCompress;

use Bitrix\Main;
use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;

IncludeModuleLangFile(__FILE__);

class ImageCompressPagesTable extends Entity\DataManager
{
    static $module_id = "dev2fun.imagecompress";

    public static function getFilePath()
    {
        return __FILE__;
    }

    public static function getTableName()
    {
        return 'b_d2f_imagecompress_pages';
    }

    public static function getTableTitle()
    {
        return Loc::getMessage('DEV2FUN_IMAGECOMPRESS_PAGES_TITLE');
    }

    public static function getMap()
    {
        return [
            (new Entity\IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new Entity\StringField('SITE_ID'))
                ->configureRequired(),

            (new Entity\StringField('PAGE_URL'))
                ->configureUnique()
                ->configureRequired()
                ->configureNullable(),

            (new Entity\BooleanField('PAGE_PROCESSED'))
                ->configureValues('N', 'Y')
                ->configureDefaultValue('N'),

            (new Entity\StringField('PAGE_HASH'))
//                ->configureUnique()
                ->configureNullable(),

            (new Entity\TextField('PAGE_HTML'))
                ->configureNullable(),

            (new Entity\DatetimeField('DATE_CREATE'))
                ->configureDefaultValue(new DateTime),

            (new Entity\DatetimeField('DATE_CHECK'))
                ->configureNullable(),
//                ->configureDefaultValue(new Main\DB\SqlExpression("NOW()")),

            (new Entity\DatetimeField('DATE_UPDATE'))
                ->configureNullable(),

        ];
    }
}