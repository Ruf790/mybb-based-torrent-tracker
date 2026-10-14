<?php
declare(strict_types=1);

require_once __DIR__ . '/global.php';
require_once INC_PATH . '/settings.php';

define('E_VERSION', '0.3');

/**
 * Text from global.lang.php, or the English fallback
 * (error 3 = broken language file, so the page must work without lang).
 */
function errpage_t(string $key, string $fallback): string
{
    global $lang;
    try {
        if (isset($lang) && is_object($lang) && isset($lang->global[$key]) && is_string($lang->global[$key])) {
            return $lang->global[$key];
        }
    } catch (\Throwable) {
    }
    return $fallback;
}

/**
 * One full-screen error page for every code.
 * $anim = true  -> the "404 astronaut" Lottie animation (only for 404 — it has "404" drawn in it)
 * $anim = false -> a big Bootstrap icon ($icon)
 */
function show_error_page(int $status, string $heading, string $text, string $icon = 'bi-exclamation-triangle', bool $anim = false): void
{
    global $BASEURL;

    if (!headers_sent()) {
        // Before this every error page was answered with "200 OK"
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $e        = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $base     = $e((string)$BASEURL);
    $htmlLang = $e(errpage_t('errpage_html_lang', 'en'));
    $title    = $e($heading);
    $headingH = $e($heading);
    $textH    = $text;                        // may contain a link (code 6) — texts come from this file / lang only
    $homeBtn  = $e(errpage_t('errpage_home', 'Back to homepage'));

    $visual = $anim
        ? <<<HTML
            <script src="{$base}/scripts/lottie-player.js"></script>
            <lottie-player src="{$base}/scripts/lf20_u1xuufn3.json" class="err-anim mx-auto"
                background="transparent" speed="1" loop autoplay></lottie-player>
HTML
        : "<i class=\"bi {$e($icon)} err-icon\"></i>";

    echo <<<HTML
<!doctype html>
<html lang="{$htmlLang}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <link rel="stylesheet" href="{$base}/include/templates/default/style/bootstrap.min.css">
    <link rel="stylesheet" href="{$base}/include/templates/default/style/bootstrap-icons.css">
    <style>
        /* Full screen: no white strip under the content, everything centred */
        html, body { height: 100%; margin: 0; }
        body {
            min-height: 100vh;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        /* Text and button centred under the picture; the animation keeps its own size */
        .err-wrap { width: 100%; max-width: 1200px; text-align: center; }
        .err-text { max-width: 640px; margin: 0 auto 1.5rem; }
        .err-icon { font-size: clamp(5rem, 18vw, 10rem); color: #0d6efd; opacity: .85; display: block; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <main class="err-wrap">
        {$visual}
        <h2 class="mt-3 mb-2">{$headingH}</h2>
        <p class="err-text fw-light text-muted">{$textH}</p>
        <a class="btn btn-lg btn-primary" href="{$base}/" role="button">{$homeBtn}</a>
    </main>
</body>
</html>
HTML;
}

// ── Which error ─────────────────────────────────────────────────────────────
$errorId = (int)($_GET['errorid'] ?? 0);
if ($errorId === 0 && defined('errorid')) {
    $errorId = (int)constant('errorid');
}

// code => [HTTP status, heading key, heading, text key, text, icon, animation]
$errors = [
    0   => [500, 'errpage_0_h',   'Something went wrong',      'errpage_0',   'An unknown error has occurred, please contact us.', 'bi-question-circle', false],
    1   => [400, 'errpage_1_h',   'Request blocked',           'errpage_1',   'Request tainting attempted!', 'bi-shield-exclamation', false],
    2   => [403, 'errpage_2_h',   'Domain not allowed',        'errpage_2',   'In order to accept POST requests originating from this domain, the admin must add this domain to the whitelist.', 'bi-globe2', false],
    3   => [500, 'errpage_3_h',   'Language file error',       'errpage_3',   'Missing or corrupted language file!', 'bi-translate', false],
    4   => [400, 'errpage_4_h',   'Please try again',          'errpage_4',   'Please refresh the page and try again!', 'bi-arrow-clockwise', false],
    5   => [500, 'errpage_5_h',   'Database error',            'errpage_5',   'MySQL Error!', 'bi-database-exclamation', false],
    6   => [503, 'errpage_6_h',   'Server is busy',            'errpage_6',   'The server is too busy at the moment. Please try again later.', 'bi-hourglass-split', false],
    7   => [403, 'errpage_7_h',   'Prefetching not allowed',   'errpage_7',   'Prefetching is not allowed due to the various privacy issues that arise.', 'bi-eye-slash', false],
    9   => [403, 'errpage_9_h',   'Account suspended',         'errpage_9',   'Your account has either been suspended or you have been banned from accessing this tracker.', 'bi-person-x', false],
    400 => [400, 'errpage_400_h', '400 Bad Request',           'errpage_400', 'The server could not understand this request.', 'bi-exclamation-octagon', false],
    401 => [401, 'errpage_401_h', '401 Authorization Required','errpage_401', 'You need to log in with a valid username and password to view this page.', 'bi-lock', false],
    403 => [403, 'errpage_403_h', '403 Forbidden',             'errpage_403', 'You are not allowed to access this page.', 'bi-shield-lock', false],
    404 => [404, 'errpage_404_h', '404 Not Found',             'errpage_404', 'The page you are looking for was moved, removed or might never have existed.', '', true],
    500 => [500, 'errpage_500_h', '500 Internal Server Error', 'errpage_500', 'The server encountered an internal error. Please try again later or contact the staff.', 'bi-bug', false],
];

[$status, $hKey, $hFallback, $tKey, $tFallback, $icon, $anim] = $errors[$errorId] ?? $errors[0];

$text = htmlspecialchars(errpage_t($tKey, $tFallback), ENT_QUOTES, 'UTF-8');
if ($errorId === 6) {
    // "Refresh" link for the busy page
    $text .= '<br><a href="javascript:location.reload();">'
           . htmlspecialchars(errpage_t('errpage_refresh', 'Refresh the page'), ENT_QUOTES, 'UTF-8') . '</a>';
}

show_error_page($status, errpage_t($hKey, $hFallback), $text, $icon, $anim);
exit(0);