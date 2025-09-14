<?php


header('Content-Type: application/json');


$rootpath = './../';
$thispath = './';
define ('IN_ADMIN_PANEL', true);
define ('STAFF_PANEL_TSSEv56', true);
define ('SKIP_CRON_JOBS', true);
define ('SKIP_LOCATION_SAVE', true);
define("IN_MYBB", 1);


require_once $rootpath . 'global.php';





$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Query basic torrent info
$torrent = $db->fetch_array($db->sql_query("
    SELECT t.id, t.name, t.size, t.seeders, t.leechers
    FROM torrents t
    WHERE t.id = $id
    GROUP BY t.id
"));

if (!$torrent) {
    echo json_encode(['error' => 'Torrent not found']);
    exit;
}

// Format data
$torrent['size'] = mksize($torrent['size']);
$torrent['name'] = htmlspecialchars_uni($torrent['name']);

echo json_encode([
    'id' => (int)$torrent['id'],
    'name' => $torrent['name'],
    'size' => $torrent['size'],
    'seeders' => (int)$torrent['seeders'],
    'leechers' => (int)$torrent['leechers']
]);
