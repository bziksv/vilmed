<?php
/**
 * Update niges.cookiesaccept MAINTEXT to new legal HTML page.
 * Run on prod: php tools/perf/update-cookie-legal-link.php
 */
$root = dirname(__DIR__, 2);
$settingsFile = $root . '/bitrix/.settings.php';

if (!is_file($settingsFile)) {
    fwrite(STDERR, "ERROR: .settings.php not found\n");
    exit(1);
}

$settings = include $settingsFile;
$conn = $settings['connections']['value']['default'] ?? null;
if (!$conn) {
    fwrite(STDERR, "ERROR: DB connection not found in .settings.php\n");
    exit(1);
}

$mysqli = new mysqli(
    $conn['host'] ?? 'localhost',
    $conn['login'] ?? '',
    $conn['password'] ?? '',
    $conn['database'] ?? ''
);

if ($mysqli->connect_error) {
    fwrite(STDERR, 'ERROR: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

$mysqli->set_charset('utf8');

$text = 'Мы используем cookies для улучшения работы сайта, сбора статистики посещаемости и настройки рекламы. Продолжая пользоваться сайтом, вы соглашаетесь с обработкой персональных данных в рамках нашей <a target="_blank" href="/legal/vilmed-cookie-policy/">политики применения файлов cookies</a>.';

$stmt = $mysqli->prepare(
    'UPDATE b_option SET VALUE = ? WHERE MODULE_ID = ? AND NAME = ? AND SITE_ID = ?'
);
$moduleId = 'niges.cookiesaccept';
$name = 'MAINTEXT';
$siteId = 's1';
$stmt->bind_param('ssss', $text, $moduleId, $name, $siteId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $insert = $mysqli->prepare(
        'INSERT INTO b_option (MODULE_ID, NAME, VALUE, DESCRIPTION, SITE_ID) VALUES (?, ?, ?, ?, ?)'
    );
    $description = '';
    $insert->bind_param('sssss', $moduleId, $name, $text, $description, $siteId);
    $insert->execute();
    $insert->close();
}

$stmt->close();

$result = $mysqli->query(
    "SELECT VALUE FROM b_option WHERE MODULE_ID = 'niges.cookiesaccept' AND NAME = 'MAINTEXT' AND SITE_ID = 's1' LIMIT 1"
);
$row = $result ? $result->fetch_assoc() : null;
echo ($row['VALUE'] ?? ''), PHP_EOL;

$mysqli->close();
