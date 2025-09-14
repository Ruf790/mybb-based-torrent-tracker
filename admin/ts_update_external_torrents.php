<?php
if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face="verdana" size="2" color="darkred"><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

define('TUET_VERSION', '0.3 by xam');

$do = isset($_POST['do']) ? htmlspecialchars($_POST['do']) : (isset($_GET['do']) ? htmlspecialchars($_GET['do']) : 1);
$wait = isset($_POST['wait']) ? intval($_POST['wait']) : (isset($_GET['wait']) ? intval($_GET['wait']) : 30);
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$updated_ids = isset($_GET['updated_ids']) ? $_GET['updated_ids'] : '';
$updated_ids_array = [];

if (!empty($updated_ids)) {
    $updated_ids_array = array_map('intval', explode(',', $updated_ids));
}

if ($do == 1) {
    stdhead('Update External Torrents');

    $count = tsrowcount('id', 'torrents', "ts_external = 'yes'");

    echo '<div class="container mt-4">';
    echo '<div class="card shadow-sm">';
    echo '<div class="card-header bg-primary text-white"><i class="fas fa-sync-alt"></i> Update External Torrents</div>';
    echo '<div class="card-body">';

    if ($count < 1) {
        echo '<div class="alert alert-warning text-center">There is no external torrent to update!</div>';
    } else {
        echo '<p class="mb-3">Click <strong>UPDATE</strong> to start updating all external torrents one by one.</p>';
        echo '<form method="post" action="' . $_SERVER['SCRIPT_NAME'] . '">
                <input type="hidden" name="act" value="ts_update_external_torrents">
                <input type="hidden" name="do" value="2">
                <div class="input-group mb-3" style="max-width: 300px;">
                    <span class="input-group-text">Wait before update</span>
                    <input type="number" name="wait" class="form-control" value="' . $wait . '" min="1" max="300">
                    <span class="input-group-text">sec</span>
                </div>
                <button type="submit" name="submit" class="btn btn-success"><i class="fas fa-sync"></i> UPDATE</button>
            </form>';
    }

    echo '</div></div></div>';
    stdfoot();
    exit();
}

stdhead('Update External Torrents');

$where_clause = "ts_external = 'yes'";
if ($last_id > 0) {
    $where_clause .= " AND id > $last_id";
}

$query = $db->sql_query("SELECT id, name, ts_external_lastupdate FROM torrents WHERE $where_clause ORDER BY id ASC LIMIT 1");

echo '<div class="container mt-4">';
echo '<div class="card shadow-sm">';
echo '<div class="card-header bg-primary text-white"><i class="fas fa-sync-alt"></i> Updating External Torrents</div>';
echo '<div class="card-body">';

if (!empty($updated_ids_array)) {
    echo '<h6>✅ Already updated torrents:</h6><ul>';
    foreach ($updated_ids_array as $uid) {
        $q = $db->sql_query("SELECT name FROM torrents WHERE id = $uid");
        if ($t = $db->fetch_array($q)) {
            echo '<li>' . htmlspecialchars_uni($t['name']) . '</li>';
        }
    }
    echo '</ul><hr>';
}

if ($et = $db->fetch_array($query)) {
    $id = $et['id'];
    echo '<p><strong>Now updating:</strong> ' . htmlspecialchars_uni($et['name']) . '</p>';

    $ts_external_lastupdate = $et['ts_external_lastupdate'];

    if (time() - $ts_external_lastupdate < 3600) {
        $message = '<span class="badge bg-secondary">UP-TO-DATE</span>';
    } else {
        $externaltorrent = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
        include_once INC_PATH . '/ts_external_scrape/ts_external.php';
        echo '<p>Announce URL: ' . htmlspecialchars_uni($TrackerURL) . '<br>Scrape URL: ' . htmlspecialchars_uni($httpurl) . '<br>Seeders: ' . ts_nf($e_seeders) . ' / Leechers: ' . ts_nf($e_leechers) . '</p>';
        $message = '<span class="badge bg-success">UPDATED</span>';
    }

    echo '<div class="text-end">' . $message . '</div>';

    // Добавляем текущий ID к списку
    $new_updated_ids_array = $updated_ids_array;
    $new_updated_ids_array[] = $id;
    $new_updated_ids_str = implode(',', $new_updated_ids_array);

    echo '<div id="waitmessage" class="text-center my-3">Please wait...</div>';
    echo '<script>
        let x6115 = ' . $wait . ';
        function countdown() {
            x6115--;
            if (x6115 === 0) {
                location.href = "' . $_SERVER['SCRIPT_NAME'] . '?act=ts_update_external_torrents&do=2&wait=' . $wait . '&last_id=' . $id . '&updated_ids=' . $new_updated_ids_str . '";
            } else {
                document.getElementById("waitmessage").innerHTML = "Please wait <strong>" + x6115 + "</strong> seconds...";
                setTimeout(countdown, 1000);
            }
        }
        countdown();
    </script>';
} else {
    echo '<div class="alert alert-success text-center">🎉 All external torrents have been updated!</div>';
}

echo '</div></div></div>';
stdfoot();
exit();
?>
