<?php
declare(strict_types=1);

/**
 * AJAX for the header bell (scripts/notifications.js).
 *
 *   GET  ?action=list      → newest NOTIFY_DROPDOWN_LIMIT items + unread count
 *   GET  ?action=count     → unread count only (badge polling)
 *   POST  action=read_all  → mark everything read (my_post_key required; mark_all also accepted)
 *
 * Always answers JSON: {"ok":true,...} or {"ok":false,"error":"..."}.
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'notifications_ajax.php');

require_once './global.php';
require_once INC_PATH . '/functions_notify.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function ntf_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

$L = notify_lang();

if (empty($CURUSER['id'])) {
    ntf_json(['ok' => false, 'error' => $L['err_login'] ?? 'Please log in to see your notifications.'], 401);
}

$uid    = (int)$CURUSER['id'];
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    case 'count':
        notify_maybe_prune();
        ntf_json(['ok' => true, 'unread' => notify_unread_count($uid, true)]);

    case 'list':
        $items = array_map('notify_render', notify_fetch($uid, NOTIFY_DROPDOWN_LIMIT));
        ntf_json(['ok' => true, 'unread' => notify_unread_count($uid, true), 'items' => $items]);

    case 'read_all':
    case 'mark_all':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
            || !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
            ntf_json(['ok' => false, 'error' => $L['err_csrf'] ?? 'Security check failed. Please refresh the page and try again.'], 403);
        }
        notify_mark_all($uid);
        ntf_json(['ok' => true, 'unread' => 0]);

    default:
        ntf_json(['ok' => false, 'error' => $L['err_action'] ?? 'Unknown action.'], 400);
}
