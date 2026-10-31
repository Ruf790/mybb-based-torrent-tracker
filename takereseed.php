<?php
declare(strict_types=1);

require_once 'global.php';

$lang->load("takewhatever");

if (!isset($CURUSER) || (int)($CURUSER['id'] ?? 0) === 0) {
    stderr($lang->takewhatever["takereseednouser"]);
}

gzip();
maxsysop();

define('TR_VERSION', '0.5');
define("IN_MYBB", 1);

require_once INC_PATH . '/datahandler.php';
require_once INC_PATH . '/functions_pm.php';
require_once INC_PATH . '/functions_notify.php';

// One reseed request per torrent per N hours (from anyone): otherwise every click
// sends a PM + a bell to every snatcher again
const RESEED_COOLDOWN_HOURS = 24;

// POST + CSRF only: a plain link (or an <img> on the forum) must not be able to send the request
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_post_check((string)($_POST['my_post_key'] ?? ''), true)) {
    stderr($lang->takewhatever['reseed_bad_request'] ?? 'Invalid request. Open the torrent page and press the button again.');
}

$reseedid = (int)($_POST['reseedid'] ?? 0);
$userid   = (int)($CURUSER['id'] ?? 0);

// Validate integers
if ($reseedid <= 0 || $userid <= 0) {
    stderr($lang->takewhatever['takereseednouser']);
}

// Same rule as the button on details.php: only for torrents without seeders
$tq = $db->sql_query_prepared('SELECT seeders FROM torrents WHERE id = ?', [$reseedid]);
$tRow = $tq ? $db->fetch_array($tq) : null;
if (!$tRow) {
    stderr($lang->takewhatever['takereseednouser']);
}
if ((int)$tRow['seeders'] > 0) {
    stderr($lang->takewhatever['reseed_has_seeders'] ?? 'This torrent already has seeders.');
}

// Staff (moderators and up) are not limited: they can request a reseed as often as they want
$isStaff = function_exists('is_mod')
    ? (bool)is_mod($usergroups)
    : !empty($mybb->usergroup['canstaffpanel']) || !empty($mybb->usergroup['issupermod']);

// Cooldown: the reseed bells themselves are the record of the last request
$recent = $isStaff ? false : $db->sql_query_prepared(
    "SELECT added FROM notifications WHERE type = 'reseed' AND ref_id = ? AND added > ? ORDER BY added DESC LIMIT 1",
    [$reseedid, TIMENOW - RESEED_COOLDOWN_HOURS * 3600]
);
if ($recent && $db->num_rows($recent) > 0) {
    $last = (int)$db->fetch_field($recent, 'added');
    $wait = (int)ceil(($last + RESEED_COOLDOWN_HOURS * 3600 - TIMENOW) / 3600);
    stderr(sprintf(
        (string)($lang->takewhatever['reseed_too_soon'] ?? 'A reseed request for this torrent was already sent recently. Try again in %s h.'),
        max(1, $wait)
    ));
}

$sql = "
    SELECT s.uploaded, s.downloaded, s.userid, t.name, u.username, u.language
    FROM snatched s
    INNER JOIN torrents t ON s.torrentid = t.id
    INNER JOIN users u ON s.userid = u.id
    WHERE s.finished = 'yes'
      AND s.torrentid = ?
      AND s.userid <> ?
";

$res = $db->sql_query_prepared($sql, [$reseedid, $userid]);

if ($db->num_rows($res) === 0) {
    stderr($lang->takewhatever['takereseednouser']);
}

$torrentLink = get_torrent_link($reseedid);

while ($row = $db->fetch_array($res)) {
    // BBCode-safe title: brackets would break [URL=...]...[/URL]
    // (escape_string is for SQL and only added backslashes to the PM text)
    $name_torrent = str_replace(['[', ']'], ['(', ')'], (string)$row['name']);

    // Lang keys instead of ready text: send_pm() builds the PM from the
    // takewhatever lang in the recipient's language (users.language)
    $pm = [
        'subject'       => ['reseedsubject', $reseedid],
        'message'       => [
            'reseedmsg',
            $row['username'],
            '[URL=' . $BASEURL . '/' . $torrentLink . ']' . $name_torrent . '[/URL]',
            mksize($row['uploaded']),
            mksize($row['downloaded']),
        ],
        'touid'         => (int)$row['userid'],
        'language'      => (string)($row['language'] ?? ''),
        'language_file' => 'takewhatever',
    ];

    send_pm($pm, $userid, true);

    // Bell: "X asks you to seed {3}". A notify error must not stop the loop halfway
    try {
        notify((int)$row['userid'], 'reseed', $userid, $reseedid, [(string)$row['name']], $torrentLink);
    } catch (\Throwable $e) {
        error_log('notify (reseed): ' . $e->getMessage());
    }
}

write_log("[Reseed] {$CURUSER['username']} requested a reseed of torrent #{$reseedid}");

redirect($torrentLink);
