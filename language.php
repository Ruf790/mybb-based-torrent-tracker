<?php
require_once 'global.php';

$allowed  = ['english', 'russian'];
$lang     = $_GET['lang'] ?? 'english';
if (!in_array($lang, $allowed, true)) $lang = 'english';

setcookie('ts_language', $lang, time() + 365 * 24 * 3600, '/');

$redirect = $_GET['redirect'] ?? '/';
header('Location: ' . $redirect);
exit;