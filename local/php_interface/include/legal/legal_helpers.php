<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

function legal_h($value): string
{
    return htmlspecialcharsbx((string) $value);
}

function legal_var($value): string
{
    return legal_h($value);
}

function legal_link(string $url, ?string $text = null): string
{
    $text = $text ?? $url;

    return '<a href="' . legal_h($url) . '" target="_blank" rel="noopener">' . legal_var($text) . '</a>';
}

function legal_mailto(string $email): string
{
    return '<a href="mailto:' . legal_h($email) . '">' . legal_var($email) . '</a>';
}

function legal_tel(string $phone, string $telHref): string
{
    return '<a href="tel:' . legal_h($telHref) . '">' . legal_var($phone) . '</a>';
}

function legal_internal_link(string $path, string $host): string
{
    return '<a href="' . legal_h($path) . '">' . legal_var($host . $path) . '</a>';
}

function vilmedLegalFormPersonalDataText(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }

    $legal = include __DIR__ . '/config.php';
    $text = 'При отправке формы Вы даёте '
        . '<a href="' . legal_h($legal['urls']['consent']) . '" target="_blank">согласие на обработку персональных данных</a> и принимаете '
        . '<a href="' . legal_h($legal['urls']['personal_data']) . '" target="_blank">политику обработки персональных данных</a>.';

    return $text;
}
