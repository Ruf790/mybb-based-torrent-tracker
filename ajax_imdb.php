<?php
declare(strict_types=1);

define('SKIP_LOCATION_SAVE', true);
define('DEBUGMODE', false);

require 'global.php';
gzip();

function show_msg(string $message = '', bool $error = false): void
{
    global $shoutboxcharset;
    header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-type: text/html; charset=' . $shoutboxcharset);
    if ($error) {
        exit('<error>' . $message . '</error>');
    }
    exit($message);
}

if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST' || !$CURUSER || !is_mod($usergroups)) {
    exit();
}

if (!isset($_POST['tid']) || !is_valid_id($_POST['tid'])) {
    show_msg($lang->global['notorrentid'], true);
}

$id = (int)$_POST['tid'];

$query  = $db->sql_query_prepared('SELECT t_link FROM torrents WHERE id = ?', [$id]);
if ($db->num_rows($query) == 0) {
    show_msg($lang->global['notorrentid'], true);
}

$result    = $db->fetch_array($query);
$oldt_link = $result['t_link'] ?? '';

if (!$oldt_link) {
    show_msg($lang->global['notorrentid'], true);
}

preg_match("@<a href='(.*)'@U", $oldt_link, $imdblink);
$t_link = $imdblink[1] ?? '';

if ($t_link) {
    include_once INC_PATH . '/imdb_parser.php';
    $db->sql_query_prepared('UPDATE torrents SET t_link = ? WHERE id = ?', [$t_link, $id]);
    show_msg($t_link);
}