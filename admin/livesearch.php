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




$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$q_escaped = $db->escape_string('%' . $q . '%');

$query = $db->sql_query("
    SELECT id, name, t_image
    FROM torrents 
    WHERE name LIKE '" . $q_escaped . "'
    ORDER BY added DESC 
    LIMIT 10
");

$results = [];
while ($row = $db->fetch_array($query)) {
    $results[] = [
        'id' => (int)$row['id'],
        'name' => htmlspecialchars_uni($row['name']),
		'image' => $row['t_image'], // Assuming 't_image' is your image column
    ];
}

echo json_encode($results);
