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

/**
 * Spam check function
 */
function spamcheck(int $reseedid = 0, int $receiver = 0, int $sender = 0): bool
{
    global $db;

    $spamcheck = $db->sql_query_prepared(
        "SELECT sender FROM messages WHERE sender = ? AND subject = ? AND receiver = ?",
        [$sender, $_GET['subject'] ?? '', $receiver]
    );

    return $db->num_rows($spamcheck) === 0;
}

$reseedid = (int)($_GET['reseedid'] ?? 0);
$userid   = (int)($CURUSER['id'] ?? 0);

// Validate integers
if ($reseedid <= 0 || $userid <= 0) {
    stderr($lang->takewhatever['takereseednouser']);
}

$sql = "
    SELECT s.uploaded, s.downloaded, s.userid, t.name, u.username
    FROM snatched s
    INNER JOIN torrents t ON s.torrentid = t.id
    INNER JOIN users u ON s.userid = u.id
    WHERE s.finished = 'yes' 
      AND s.torrentid = ?
";

$params = [$reseedid];
$res = $db->sql_query_prepared($sql, $params);

if ($db->num_rows($res) === 0) {
    stderr($lang->takewhatever['takereseednouser']);
}

$subject = sprintf($lang->takewhatever['reseedsubject'], $reseedid);

while ($row = $db->fetch_array($res)) {
    $name_torrent = $db->escape_string($row['name']);
    $reseedmsg = sprintf(
        $lang->takewhatever['reseedmsg'],
        $row['username'],
        '[URL=' . $BASEURL . '/' . get_torrent_link($reseedid) . ']' . $name_torrent . '[/URL]',
        mksize($row['uploaded']),
        mksize($row['downloaded'])
    );

    $pm = [
        'subject' => $subject,
        'message' => $reseedmsg,
        'touid'   => (int)$row['userid']
    ];

    send_pm($pm, $CURUSER['id'], true);
}

redirect(get_torrent_link($reseedid));