<?php
require_once 'global.php';

$allowed  = ['english', 'russian'];
$lang     = $_GET['lang'] ?? 'english';
if (!in_array($lang, $allowed, true)) $lang = 'english';

setcookie('ts_language', $lang, time() + 365 * 24 * 3600, '/');

// Logged-in user: keep the choice in the profile too. announce.php has no browser
// cookies (requests come from the torrent client), it reads users.language instead.
$uid = (int)($CURUSER['id'] ?? 0);
// (field_exists: until users.language is created the cookie alone still switches the site)
if ($uid > 0 && ($CURUSER['language'] ?? null) !== $lang && $db->field_exists('language', 'users')) {
    $db->sql_query_prepared('UPDATE users SET language = ? WHERE id = ?', [$lang, $uid]);
}

// Only same-site paths: "/details.php?id=1" yes, "https://evil.tld" or "//evil.tld" no
$redirect = (string)($_GET['redirect'] ?? '/');
if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//') || str_starts_with($redirect, '/\\')
    || preg_match('/[\r\n]/', $redirect)) {
    $redirect = '/';
}

header('Location: ' . $redirect);
exit;
