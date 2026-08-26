<?php
declare(strict_types=1);

define('IN_MYBB',    1);
define('THIS_SCRIPT', 'ajax_torrent_preview.php');
define('SCRIPTNAME',  'ajax_torrent_preview.php');

require_once 'global.php';

header('Content-Type: application/json; charset=utf-8');

$tid = $mybb->get_input('id', MyBB::INPUT_INT);

if ($tid <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Тот же запрос, что использует class_parser.php::mycode_parse_torrent_callback()
// — держим их в синхроне, чтобы превью не расходилось с реальным результатом.
$query = $db->sql_query_prepared(
    "SELECT t.id, t.name, t.size, t.seeders, t.leechers, t.t_image, c.name AS catname
     FROM torrents t LEFT JOIN categories c ON c.id = t.category
     WHERE t.id = ?",
    [$tid]
);
$torrent = $query ? $db->fetch_array($query) : null;

if (!$torrent) {
    echo json_encode(['error' => 'Torrent not found']);
    exit;
}

echo json_encode([
    'id'       => (int)$torrent['id'],
    'name'     => $torrent['name'],
    'size'     => function_exists('mksize') ? mksize((int)$torrent['size']) : (int)$torrent['size'] . ' B',
    'seeders'  => (int)$torrent['seeders'],
    'leechers' => (int)$torrent['leechers'],
    'catname'  => $torrent['catname'] ?? '',
    'image'    => $torrent['t_image'] ?: '',
]);
