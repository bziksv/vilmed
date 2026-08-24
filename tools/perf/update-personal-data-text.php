<?php
/**
 * Update altop.elektroinstrument TEXT_PERSONAL_DATA to new legal HTML pages.
 * Run: php tools/perf/update-personal-data-text.php
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

$moduleId = 'altop.elektroinstrument';
$name = 'OPTIONS';
$newText = 'При отправке формы Вы даёте <a href="/legal/vilmed-personal-data-consent/" target="_blank">согласие на обработку персональных данных</a> и принимаете <a href="/legal/vilmed-personal-data-policy/" target="_blank">политику обработки персональных данных</a>.';

$result = $mysqli->query(
    "SELECT SITE_ID, VALUE FROM b_option WHERE MODULE_ID = '{$moduleId}' AND NAME = '{$name}'"
);

if (!$result || $result->num_rows === 0) {
    fwrite(STDERR, "ERROR: OPTIONS rows not found\n");
    exit(1);
}

$updated = 0;
while ($row = $result->fetch_assoc()) {
    $siteId = (string)($row['SITE_ID'] ?? '');
    $options = @unserialize($row['VALUE'], ['allowed_classes' => false]);
    if (!is_array($options)) {
        fwrite(STDERR, "WARN: skip site [{$siteId}] — unserialize failed\n");
        continue;
    }

    $options['TEXT_PERSONAL_DATA'] = $newText;
    $serialized = serialize($options);

    $stmt = $mysqli->prepare(
        'UPDATE b_option SET VALUE = ? WHERE MODULE_ID = ? AND NAME = ? AND '
        . ($siteId === '' ? '(SITE_ID IS NULL OR SITE_ID = \'\')' : 'SITE_ID = ?')
    );

    if ($siteId === '') {
        $stmt->bind_param('ss', $serialized, $moduleId, $name);
    } else {
        $stmt->bind_param('ssss', $serialized, $moduleId, $name, $siteId);
    }

    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $updated++;
        echo "updated site [{$siteId}]\n";
    }
    $stmt->close();
}

if ($updated === 0) {
    fwrite(STDERR, "ERROR: nothing updated\n");
    exit(1);
}

echo $newText, PHP_EOL;

$mysqli->close();
