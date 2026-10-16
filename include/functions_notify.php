<?php

declare(strict_types=1);

/** Texts of new-torrent notifications: notify_upload_* keys in global.lang.php */
const NOTIFY_UPLOAD_LANG = 'upload';

/**
 * English defaults for the email texts: used when the recipient's lang file has no such key,
 * so a missing key never breaks the upload under strict_types.
 * {1} category, {2} torrent link, {3} torrent name, {4} $BASEURL, {5} $SITENAME
 */
const NOTIFY_UPLOAD_DEFAULTS = [
    'notify_upload_email_subject' => 'New torrent in {1}: {3}',
    'notify_upload_email_message' => "A new torrent has been uploaded in your subscribed category {1}:\n\n{3}\n{2}\n\nManage subscriptions: {4}/usercp.php?action=options\n\n— {5}",
];

/** Email texts in one language (cached per language). */
function notify_upload_lang(string $language): array
{
    global $lang;
    static $cache = [];

    // users.language comes from the DB: only a plain folder name, otherwise English
    if (!preg_match('/^[a-z_]+$/', $language)) $language = 'english';
    if (isset($cache[$language])) return $cache[$language];

    $strings = [];
    if (is_object($lang) && method_exists($lang, 'get_section')) {
        $strings = array_filter((array)$lang->get_section(NOTIFY_UPLOAD_LANG, $language), 'is_string');
    }
    return $cache[$language] = array_merge(NOTIFY_UPLOAD_DEFAULTS, array_intersect_key($strings, NOTIFY_UPLOAD_DEFAULTS));
}

/** {1}, {2}… and %1$s… (get_section() / $lang->load() convert {n} into %n$s) */
function notify_upload_fmt(string $str, string|int ...$args): string
{
    $map = [];
    foreach ($args as $i => $a) {
        $n = $i + 1;
        $map['{' . $n . '}']  = (string)$a;
        $map['%' . $n . '$s'] = (string)$a;
    }
    return strtr($str, $map);
}

function notify_upload_subscribers(int $catid, int $torrent_id, string $torrent_name): void
{
    global $BASEURL, $SITENAME, $CURUSER, $db;

    $torrent_link = $BASEURL . '/' . get_torrent_link($torrent_id);

    $notif_tag = "%[cat{$catid}]%";
    $res = $db->sql_query_prepared("
        SELECT u.id, u.username, u.email, u.notifs, u.language
        FROM users u
        WHERE u.enabled = 'yes'
        AND u.ustatus = 'confirmed'
        AND u.notifs != ''
        AND u.notifs LIKE ?
        AND u.id != ?
    ", [$notif_tag, (int)($CURUSER['id'] ?? 0)]);

    if (!$res || $db->num_rows($res) === 0) return;

    $row      = $db->fetch_array($db->sql_query_prepared("SELECT name FROM categories WHERE id = ?", [$catid]));
    $cat_name = (string)($row['name'] ?? 'Unknown Category');

    require_once INC_PATH . '/functions_pm.php';

    // [ ] in the name / category would break BBCode in the PM (and the subject)
    $pm_name = str_replace(['[', ']'], ['(', ')'], $torrent_name);
    $pm_cat  = str_replace(['[', ']'], ['(', ')'], $cat_name);

    while ($user = $db->fetch_array($res)) {
        $language = (string)($user['language'] ?? '') ?: 'english';

        // PM: in the recipient's language, notify_upload_* keys from upload.lang.php
        if (str_contains((string)$user['notifs'], '[pm]')) {
            send_pm([
                'subject'       => ['notify_upload_pm_subject', $pm_cat, $pm_name],
                'message'       => ['notify_upload_pm_message', $pm_cat, $torrent_link, $pm_name, $BASEURL],
                'touid'         => (int)$user['id'],
                'language'      => $language,
                'language_file' => NOTIFY_UPLOAD_LANG,
                'sender'        => ['uid' => -1],
            ], -1, true);
        }

        // Email: same language, plain text
        if (str_contains((string)$user['notifs'], '[email]') && !empty($user['email'])) {
            $L = notify_upload_lang($language);
            my_mail(
                (string)$user['email'],
                notify_upload_fmt($L['notify_upload_email_subject'], $cat_name, $torrent_link, $torrent_name, $BASEURL, $SITENAME),
                notify_upload_fmt($L['notify_upload_email_message'], $cat_name, $torrent_link, $torrent_name, $BASEURL, $SITENAME)
            );
        }
    }
}