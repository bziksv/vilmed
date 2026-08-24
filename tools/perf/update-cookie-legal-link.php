<?php
/**
 * Replace cookie policy href in niges.cookiesaccept MAINTEXT only (wording unchanged).
 * Run: php tools/perf/update-cookie-legal-link.php
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

$newUrl = '/legal/vilmed-cookie-policy/';
$oldUrls = [
    '/upload/cookies-vilmed.png',
    '/upload/cookies-vilmed.pdf',
    '/upload/old-politika-ispolzovanija-cookies-vilmed.png',
];

function vilmedReplaceCookiePolicyHref(string $html, array $oldUrls, string $newUrl): array
{
    $updated = $html;
    $changed = false;

    foreach ($oldUrls as $oldUrl) {
        $replacements = [
            'href="' . $oldUrl . '"',
            "href='" . $oldUrl . "'",
            'href=&quot;' . $oldUrl . '&quot;',
        ];
        foreach ($replacements as $from) {
            $to = str_replace($oldUrl, $newUrl, $from);
            if (strpos($updated, $from) !== false) {
                $updated = str_replace($from, $to, $updated);
                $changed = true;
            }
        }
    }

    return [$updated, $changed];
}

$moduleId = 'niges.cookiesaccept';
$name = 'MAINTEXT';
$updatedRows = 0;

foreach (['b_option', 'b_option_site'] as $table) {
    $result = $mysqli->query(
        "SELECT SITE_ID, VALUE FROM {$table} WHERE MODULE_ID = '{$moduleId}' AND NAME = '{$name}'"
    );
    if (!$result) {
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        $siteId = (string) ($row['SITE_ID'] ?? '');
        $value = (string) ($row['VALUE'] ?? '');
        [$newValue, $changed] = vilmedReplaceCookiePolicyHref($value, $oldUrls, $newUrl);

        if (!$changed) {
            if (strpos($value, $newUrl) !== false) {
                echo "skip [{$table}/{$siteId}] already has new link\n";
            } else {
                echo "skip [{$table}/{$siteId}] no old cookie policy href found\n";
            }
            continue;
        }

        $stmt = $mysqli->prepare("UPDATE {$table} SET VALUE = ? WHERE MODULE_ID = ? AND NAME = ? AND SITE_ID = ?");
        $stmt->bind_param('ssss', $newValue, $moduleId, $name, $siteId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $updatedRows++;
            echo "updated [{$table}/{$siteId}]\n";
            echo $newValue, PHP_EOL;
        }
        $stmt->close();
    }
    $result->free();
}

if ($updatedRows === 0) {
    echo "No rows updated (link may already be correct).\n";
}

$mysqli->close();
