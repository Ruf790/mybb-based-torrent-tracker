<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

ini_set('memory_limit', '20000M');
define('TT_VERSION', '2.1 by xam');

// ── CSS helper ────────────────────────────────────────────
function render_css(string $BASEURL): string
{
    return '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">'
         . '<link href="' . $BASEURL . '/include/templates/default/style/errorss.css" rel="stylesheet">';
}

// ── Confirmation screen ───────────────────────────────────
if (!isset($_GET['begin_optimization'])) {
    stdhead();
    echo render_css($BASEURL);
    echo '
    <div class="container mt-3">
        <div class="card error-card">
            <div class="card-header22">
                <i class="bi bi-exclamation-triangle-fill text-danger me-2" style="font-size:2rem"></i>
                <div>
                    <h2 class="mb-0">Sanity Check!</h2>
                    <p class="mb-0 opacity-75">Are you sure you want to optimize your tracker tables?</p>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-warning"><strong>Warning!</strong> Please backup your database first!</div>
                <a href="' . $_this_script_ . '&begin_optimization=true" class="btn btn-danger">
                    <i class="bi bi-play-fill me-1"></i> Click to Begin
                </a>
            </div>
        </div>
    </div>';
    stdfoot();
    exit;
}

// ── Load valid IDs ────────────────────────────────────────
$torrent_ids = [];
$q = $db->simple_select('torrents', 'id');
while ($row = $db->fetch_array($q)) $torrent_ids[] = (int)$row['id'];

$user_ids = [];
$q = $db->simple_select('users', 'id', "enabled='yes' AND ustatus='confirmed'");
while ($row = $db->fetch_array($q)) $user_ids[] = (int)$row['id'];

if (empty($torrent_ids) || empty($user_ids)) {
    stderr('Error, No torrent/user found. You must have at least one torrent/user.');
}

$ValidTorrents = implode(',', $torrent_ids);
$ValidUsers    = implode(',', $user_ids);
unset($torrent_ids, $user_ids);

// ── Batch delete helper ───────────────────────────────────
function delete_invalid_records(string $table, string $condition, string $id_field = 'id'): int
{
    global $db;

    $q     = $db->simple_select($table, $id_field, $condition);
    $total = 0;
    $batch = [];

    while ($row = $db->fetch_array($q)) {
        $batch[] = $row[$id_field];
        $total++;

        if (count($batch) >= 1000) {
            $db->delete_query($table, "$id_field IN (" . implode(',', $batch) . ")");
            $batch = [];
        }
    }

    if ($batch) {
        $db->delete_query($table, "$id_field IN (" . implode(',', $batch) . ")");
    }

    return $total;
}

// ── Size format helper ────────────────────────────────────
function format_filesize(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
    return $bytes . ' B';
}

// ── Run cleanup ───────────────────────────────────────────
$log        = [];
$start_time = microtime(true);

$cleanups = [
    ['announce_actions',    "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})"],
    ['bookmarks',           "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})"],
    ['cheat_attempts',      "uid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})",    'id'],
    ['comments',            "user NOT IN ({$ValidUsers}) OR torrent NOT IN ({$ValidTorrents})"],
    ['notconnectablepmlog', "user NOT IN ({$ValidUsers})"],
    ['peers',               "userid NOT IN ({$ValidUsers}) OR torrent NOT IN ({$ValidTorrents})"],
    ['reports',             "addedby NOT IN ({$ValidUsers})"],
    ['snatched',            "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})"],
    ['staffmessages',       "sender NOT IN ({$ValidUsers})"],
    ['hit_and_run',         "userid NOT IN ({$ValidUsers}) OR torrentid NOT IN ({$ValidTorrents})"],
    ['inactivity',          "userid NOT IN ({$ValidUsers})",                                         'userid'],                                      
    ['privatemessages',     "fromid NOT IN ({$ValidUsers}) AND toid NOT IN ({$ValidUsers})",         'pmid'],
    ['comment_files',       "user_id NOT IN ({$ValidUsers}) OR torrent_id NOT IN ({$ValidTorrents})"],
    ['screenshots',         "torrent_id NOT IN ({$ValidTorrents})"],
];

foreach ($cleanups as [$table, $cond, $field]) {
    $n = delete_invalid_records($table, $cond, $field ?? 'id');
    if ($n > 0) $log[] = "Deleted {$n} invalid records from {$table}";
}

// ── Orphaned uploaded files ───────────────────────────────
// Файлы в comment_files не привязанные ни к чему — удаляем файлы и записи

$orphaned_query = $db->simple_select(
    'comment_files',
    'id, file_path, file_size',
    'comment_id  IS NULL
 AND news_id     IS NULL
 AND torrent_id  IS NULL
 AND post_id     IS NULL
 AND messages_id IS NULL'
);

$orphaned_count = 0;
$orphaned_freed = 0;
$orphaned_batch = [];

while ($file = $db->fetch_array($orphaned_query)) {
    // Удаляем физический файл если существует
    if (!empty($file['file_path']) && file_exists($file['file_path'])) {
        @unlink($file['file_path']);
    }
    $orphaned_freed += (int)$file['file_size'];
    $orphaned_batch[] = (int)$file['id'];
    $orphaned_count++;

    if (count($orphaned_batch) >= 1000) {
        $db->delete_query('comment_files', 'id IN (' . implode(',', $orphaned_batch) . ')');
        $orphaned_batch = [];
    }
}

if (!empty($orphaned_batch)) {
    $db->delete_query('comment_files', 'id IN (' . implode(',', $orphaned_batch) . ')');
}

if ($orphaned_count > 0) {
    $log[] = "Deleted {$orphaned_count} orphaned uploaded file(s), freed " . format_filesize($orphaned_freed);
}

// ── Time-based cleanup ────────────────────────────────────
$thirty_days = TIMENOW - 30 * 86400;
$seven_days  = TIMENOW - 7  * 86400;

foreach ([
    ['sessions',      "time < {$thirty_days}"],
    ['searchlog',     "dateline < {$seven_days}"],
    ['loginattempts', "added < {$thirty_days}"],
] as [$table, $cond]) {
    $db->delete_query($table, $cond);
    $n = $db->affected_rows();
    if ($n > 0) $log[] = "Deleted {$n} old records from {$table}";
}

// ── Optimize tables ───────────────────────────────────────
$tables_to_optimize = [
    'announce_actions', 'bookmarks', 'cheat_attempts', 'comments',
    'notconnectablepmlog', 'peers', 'reports', 'snatched', 'staffmessages',
    'hit_and_run', 'inactivity', 'comment_files',
    'privatemessages', 'screenshots', 'sessions', 'searchlog', 'loginattempts',
];

foreach ($tables_to_optimize as $table) {
    if ($db->table_exists($table)) {
        $db->sql_query("OPTIMIZE TABLE {$table}");
        $log[] = "Optimized table: {$table}";
    }
}

// ── Report ────────────────────────────────────────────────
$execution_time = round(microtime(true) - $start_time, 2);
$log_items      = implode('', array_map(
    fn(string $e) => '<li>' . htmlspecialchars($e) . '</li>',
    $log
));

stdhead();
echo render_css($BASEURL);
echo '
<div class="container mt-3">
    <div class="card error-card">
        <div class="card-header22 success">
            <i class="bi bi-check-circle-fill me-2" style="font-size:2rem"></i>
            <div>
                <h2 class="mb-0">Database Optimization Complete</h2>
                <p class="mb-0 opacity-75">Tracker tables successfully optimized!</p>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <strong>Success!</strong> Optimization finished in ' . $execution_time . ' seconds.
            </div>
            <p><strong>Actions Performed:</strong></p>
            <div style="max-height:300px;overflow-y:auto;border:1px solid #28a745;padding:10px">
                <ul>' . $log_items . '</ul>
            </div>
            <p class="mt-3"><strong>Next Steps:</strong> Run a full database optimization through your database management tool.</p>
        </div>
    </div>
</div>';
stdfoot();