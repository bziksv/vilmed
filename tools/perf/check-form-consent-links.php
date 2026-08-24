<?php
/**
 * Check public pages for consent + policy links in form disclaimers.
 * Run: php tools/perf/check-form-consent-links.php [base_url]
 */
$baseUrl = rtrim($argv[1] ?? 'https://vilmed.ru', '/');

$pages = [
    'home' => '/',
    'registration' => '/personal/private/',
    'mailings' => '/personal/mailings/',
    'checkout' => '/personal/order/make/',
];

$consentPath = '/legal/vilmed-personal-data-consent/';
$policyPath = '/legal/vilmed-personal-data-policy/';
$oldPatterns = [
    'подтверждаете свое согласие',
    'politics-vilmed',
    '/upload/',
];

$failed = 0;

foreach ($pages as $name => $path) {
    $url = $baseUrl . $path;
    $html = @file_get_contents($url, false, stream_context_create([
        'http' => [
            'timeout' => 30,
            'header' => "User-Agent: vilmed-consent-check\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]));

    if ($html === false) {
        echo "FAIL {$name} ({$url}): cannot fetch\n";
        $failed++;
        continue;
    }

    $hasConsent = strpos($html, $consentPath) !== false;
    $hasPolicy = strpos($html, $policyPath) !== false;
    $oldHit = null;
    foreach ($oldPatterns as $pattern) {
        if (stripos($html, $pattern) !== false && stripos($html, 'hint_agreement') !== false) {
            $oldHit = $pattern;
            break;
        }
    }

    if ($hasConsent && $hasPolicy && $oldHit === null) {
        echo "OK   {$name} ({$url})\n";
        continue;
    }

    $reasons = [];
    if (!$hasConsent) {
        $reasons[] = 'no consent link';
    }
    if (!$hasPolicy) {
        $reasons[] = 'no policy link';
    }
    if ($oldHit !== null) {
        $reasons[] = "old text: {$oldHit}";
    }

    echo 'FAIL ' . $name . ' (' . $url . '): ' . implode(', ', $reasons) . "\n";
    $failed++;
}

exit($failed > 0 ? 1 : 0);
