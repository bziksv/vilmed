<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

return [
    'operator_name' => 'ООО «ВИЛМЕД»',
    'operator_short' => 'ООО «ВИЛМЕД»',
    'operator_legal_form' => 'ООО',
    'inn' => '3662302802',
    'ogrn' => '1223600020599',
    'kpp' => '366201001',
    'site' => 'https://vilmed.ru/',
    'site_host' => 'vilmed.ru',
    'email' => 'info@vilmed.ru',
    'phone' => '+7 (499) 113-02-70',
    'phone_tel' => '+74991130270',
    'address_legal' => '394026, Россия, Воронежская обл., г. Воронеж, пр-кт Московский, д. 19, помещ. 1/19',
    'urls' => [
        'cookie' => '/legal/vilmed-cookie-policy/',
        'recommendation' => '/legal/vilmed-recommendation-rules/',
        'personal_data' => '/legal/vilmed-personal-data-policy/',
        'consent' => '/legal/vilmed-personal-data-consent/',
    ],
    'third_parties' => include __DIR__ . '/third_parties_data.php',
];
