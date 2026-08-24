<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

function vilmedNormalizeFormMessages($message): array
{
    if (is_array($message)) {
        $items = $message;
    } else {
        $text = str_replace(['<br>', '<br />', '<br/>'], "\n", (string) $message);
        $text = strip_tags($text);
        $items = preg_split('/\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    $normalized = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $normalized[] = $item;
        }
    }

    return $normalized;
}

function vilmedMapRegistrationMessageToField(string $message): ?string
{
    $lower = mb_strtolower($message);

    if (mb_strpos($lower, 'логин') !== false) {
        return 'USER_LOGIN';
    }
    if (mb_strpos($lower, 'подтверждение пароля') !== false) {
        return 'USER_CONFIRM_PASSWORD';
    }
    if (mb_strpos($lower, 'парол') !== false) {
        return 'USER_PASSWORD';
    }
    if (mb_strpos($lower, 'e-mail') !== false || mb_strpos($lower, 'email') !== false || mb_strpos($lower, 'почт') !== false) {
        return 'USER_EMAIL';
    }
    if (mb_strpos($lower, 'капч') !== false || mb_strpos($lower, 'защит') !== false || mb_strpos($lower, 'код на картинке') !== false) {
        return 'captcha_word';
    }
    if (mb_strpos($lower, 'персональн') !== false || mb_strpos($lower, 'согласились') !== false) {
        return '__agreement__';
    }

    return null;
}

function vilmedParseRegistrationAuthErrors($message): array
{
    $result = [
        'fields' => [],
        'general' => [],
        'agreement' => false,
    ];

    foreach (vilmedNormalizeFormMessages($message) as $item) {
        $field = vilmedMapRegistrationMessageToField($item);
        if ($field === '__agreement__') {
            $result['agreement'] = true;
            $result['fields']['__agreement__'] = $item;
            continue;
        }
        if ($field !== null) {
            $result['fields'][$field] = $item;
            continue;
        }
        $result['general'][] = $item;
    }

    return $result;
}

function vilmedContentFormFieldClass(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' has-error' : '';
}

function vilmedContentFormFieldErrorHtml(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<div class="field-error" role="alert">' . htmlspecialcharsbx($errors[$field]) . '</div>';
}
