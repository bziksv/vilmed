<?php
namespace Yandex\Market\Watcher\Setup;

use Bitrix\Main\Entity;
use Yandex\Market\Reference\Concerns;
use Yandex\Market\Utils;
use Yandex\Market\Ui\UserField;

class StorageSchedule
{
    use Concerns\HasMessage;

    const WEEK = 604800;
    const THREE_DAYS = 259200;
    const DAY = 86400;
    const HALF_OF_DAY = 43200;
    const SIX_HOURS = 21600;
    const THREE_HOURS = 10800;
    const TWO_HOURS = 7200;
    const ONE_HOUR = 3600;
    const HALF_OF_HOUR = 1800;

    public static function getFields($autoUpdateByDefault = true, $refreshDefault = self::SIX_HOURS)
    {
        return [
            new Entity\BooleanField('AUTOUPDATE', [
                'values' => [UserField\BooleanType::VALUE_N, UserField\BooleanType::VALUE_Y],
                'default_value' => $autoUpdateByDefault
                    ? UserField\BooleanType::VALUE_Y
                    : UserField\BooleanType::VALUE_N,
            ]),
            new Entity\IntegerField('REFRESH_PERIOD', [
                'nullable' => true,
                'default_value' => $refreshDefault,
            ]),
            new Entity\StringField('REFRESH_TIME', [
                'nullable' => true,
                'validation' => static function() {
                    return [
                        new Entity\Validator\Length(null, 5),
                        static function($value) {
                            $value = trim($value);

                            if ($value === '') { return true; }

                            if (!preg_match('/^(\d{1,2})(?::(\d{1,2}))?$/', $value, $matches))
                            {
                                return self::getMessage('REFRESH_TIME_INVALID');
                            }

                            $hours = (int)$matches[1];
                            $minutes = isset($matches[2]) ? (int)$matches[2] : 0;

                            if ($hours > 23)
                            {
                                return self::getMessage('REFRESH_TIME_HOUR_MORE_THAN', [ '#LIMIT#' => 23 ]);
                            }

                            if ($minutes > 59)
                            {
                                return self::getMessage('REFRESH_TIME_MINUTE_MORE_THAN', [ '#LIMIT#' => 59 ]);
                            }

                            return true;
                        },
                    ];
                },
            ]),
        ];
    }

    public static function extendMapDescription(array $fields)
    {
        $useCron = Utils::isAgentUseCron();

        $fields['AUTOUPDATE']['LIST_COLUMN_LABEL'] = self::getMessage('AUTOUPDATE');

        $fields['REFRESH_PERIOD']['LIST_COLUMN_LABEL'] = self::getMessage('REFRESH_PERIOD');
        $fields['REFRESH_PERIOD']['USER_TYPE'] = UserField\Manager::getUserType('enumeration');
        $fields['REFRESH_PERIOD']['EDIT_IN_LIST'] = ($useCron ? 'Y' : 'N');
        $fields['REFRESH_PERIOD']['VALUES'] = array_map(static function($interval) {
            return [
                'ID' => $interval,
                'VALUE' => self::getMessage("REFRESH_PERIOD_ENUM_{$interval}"),
            ];
        }, [
            self::WEEK,
            self::THREE_DAYS,
            self::DAY,
            self::HALF_OF_DAY,
            self::SIX_HOURS,
            self::THREE_HOURS,
            self::TWO_HOURS,
            self::ONE_HOUR,
            self::HALF_OF_HOUR,
        ]);

        if (!$useCron)
        {
            $fields['REFRESH_PERIOD']['SETTINGS']['DEFAULT_VALUE'] = null;
        }

        $fields['REFRESH_TIME']['LIST_COLUMN_LABEL'] = self::getMessage('REFRESH_TIME');
        $fields['REFRESH_TIME']['HELP_MESSAGE'] = self::getMessage('REFRESH_TIME_HELP');
        $fields['REFRESH_TIME']['USER_TYPE'] = UserField\Manager::getUserType('time');
        $fields['REFRESH_TIME']['DEPEND'] = [
            'REFRESH_PERIOD' => [
                'RULE' => 'EMPTY',
                'VALUE' => false,
            ],
        ];

        return $fields;
    }
}