<?php
declare(strict_types=1);

if (!defined('STAFF_PANEL')) {
    exit('<b>Error!</b> Direct initialization of this file is not allowed.');
}

ini_set('memory_limit', '20000M');
define('TT_VERSION', '2.1 by xam');

if (!defined('ADMIN_DIR')) {
    define('ADMIN_DIR', TSDIR . '/admin/');
}

// ── CSS helper ────────────────────────────────────────────
function render_css(string $BASEURL): string
{
    return '<link href="' . $BASEURL . '/include/templates/default/style/bootstrap-icons.css" rel="stylesheet">'
         . '<link href="' . $BASEURL . '/include/templates/default/style/errorss.css" rel="stylesheet">';
}

// ── Confirmation screen ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['begin_optimization'])) {
    global $mybb;
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
                <form method="post" action="' . $_this_script_ . '">
                    <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code) . '">
                    <input type="hidden" name="begin_optimization" value="true">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-play-fill me-1"></i> Click to Begin
                    </button>
                </form>
            </div>
        </div>
    </div>';
    stdfoot();
    exit;
}

// Реальный запуск очистки - только POST с валидным CSRF-токеном.
if (!verify_post_check($mybb->get_input('my_post_key'))) {
    http_response_code(403);
    stderr('Error', 'Invalid security token. Please go back and try again.');
}

// ── Load valid IDs ────────────────────────────────────────
$torrent_ids = [];
$q = $db->sql_query_prepared('SELECT id FROM torrents');
while ($row = $db->fetch_array($q)) $torrent_ids[] = (int)$row['id'];

$user_ids = [];
$q = $db->sql_query_prepared('SELECT id FROM users WHERE enabled = ? AND ustatus = ?', ['yes', 'confirmed']);
while ($row = $db->fetch_array($q)) $user_ids[] = (int)$row['id'];

if (empty($torrent_ids) || empty($user_ids)) {
    stderr('Error, No torrent/user found. You must have at least one torrent/user.');
}

$ValidTorrents = implode(',', $torrent_ids);
$ValidUsers    = implode(',', $user_ids);
unset($torrent_ids, $user_ids);

// ── Batch delete helper ───────────────────────────────────
// $condition собирается вызывающим кодом из $ValidUsers/$ValidTorrents -
// это уже implode()'нутые списки int-ов (см. выше), не сырой пользовательский
// ввод, поэтому тут остаётся встраивание строки условия (простые placeholder'ы
// не подходят для динамического списка NOT IN (...) переменной длины).
function delete_invalid_records(string $table, string $condition, string $id_field = 'id'): int
{
    global $db;

    $q     = $db->sql_query_prepared("SELECT {$id_field} FROM {$table} WHERE {$condition}");
    $total = 0;
    $batch = [];

    while ($row = $db->fetch_array($q)) {
        $batch[] = (int)$row[$id_field];
        $total++;

        if (count($batch) >= 1000) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $db->sql_query_prepared("DELETE FROM {$table} WHERE {$id_field} IN ({$placeholders})", $batch);
            $batch = [];
        }
    }

    if ($batch) {
        $placeholders = implode(',', array_fill(0, count($batch), '?'));
        $db->sql_query_prepared("DELETE FROM {$table} WHERE {$id_field} IN ({$placeholders})", $batch);
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

// ── Путь к файлу вложения на диске ─────────────────────────
// Вложения комментариев хранятся плоско в uploads/attachments/ (голое
// имя файла в attachname). Вложения постов форума хранятся в
// uploads/{YYYYMM}/, и attachname для них УЖЕ содержит этот префикс
// подпапки (например "202607/post_5_....attach") - см. functions_upload.php.
function resolve_attachment_path(string $tsdir, string $attachname): string
{
    if (str_contains($attachname, '/')) {
        return $tsdir . '/uploads/' . $attachname;
    }
    return $tsdir . '/uploads/attachments/' . $attachname;
}

// ── Файлы на диске без записи в БД (обратный случай) ──────
// $keepFilenames - множество "полных" имён (как хранятся в attachname:
// либо голое имя файла, либо "YYYYMM/имя.attach"), которые НЕЛЬЗЯ удалять.
// $prefix - префикс подпапки текущей сканируемой директории относительно
// корня, чтобы сверяться с $keepFilenames в том же формате.
// Возвращает [сколько удалено, сколько байт освобождено].
function cleanup_orphaned_disk_files(string $dir, array $keepFilenames, string $prefix = ''): array
{
    if (!is_dir($dir)) return [0, 0];

    $keepSet = array_flip($keepFilenames);
    $deleted = 0;
    $freed   = 0;

    $handle = opendir($dir);
    if (!$handle) return [0, 0];

    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (!is_file($path)) continue;
        if (!isset($keepSet[$prefix . $file])) {
            $freed += filesize($path);
            @unlink($path);
            $deleted++;
        }
    }
    closedir($handle);

    return [$deleted, $freed];
}

// ── Ротация старых бэкапов БД ──────────────────────────────
function rotate_old_backups(string $dir, int $keepDays): array
{
    if (!is_dir($dir)) return [0, 0];

    $cutoff  = TIMENOW - $keepDays * 86400;
    $deleted = 0;
    $freed   = 0;

    foreach ((glob($dir . '/*.sql') ?: []) as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            $freed += filesize($file);
            @unlink($file);
            $deleted++;
        }
    }
    foreach ((glob($dir . '/*.gz') ?: []) as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            $freed += filesize($file);
            @unlink($file);
            $deleted++;
        }
    }

    return [$deleted, $freed];
}

// ── Run cleanup ───────────────────────────────────────────
$log        = [];
$start_time = microtime(true);

$cleanups = [
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

foreach ($cleanups as $cleanup) {
    $table = $cleanup[0];
    $cond  = $cleanup[1];
    $field = $cleanup[2] ?? 'id';
    $n = delete_invalid_records($table, $cond, $field);
    if ($n > 0) $log[] = "Deleted {$n} invalid records from {$table}";
}

// ── Orphaned uploaded files ───────────────────────────────
// Файлы в comment_files не привязанные ни к чему — удаляем файлы и записи

$orphaned_query = $db->sql_query_prepared(
    'SELECT id, file_path, file_size FROM comment_files
     WHERE comment_id  IS NULL
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
        $placeholders = implode(',', array_fill(0, count($orphaned_batch), '?'));
        $db->sql_query_prepared("DELETE FROM comment_files WHERE id IN ({$placeholders})", $orphaned_batch);
        $orphaned_batch = [];
    }
}

if (!empty($orphaned_batch)) {
    $placeholders = implode(',', array_fill(0, count($orphaned_batch), '?'));
    $db->sql_query_prepared("DELETE FROM comment_files WHERE id IN ({$placeholders})", $orphaned_batch);
}

if ($orphaned_count > 0) {
    $log[] = "Deleted {$orphaned_count} orphaned uploaded file(s), freed " . format_filesize($orphaned_freed);
}

// ── Файлы вложений (комментарии + посты форума) на диске без записи в attachments ──────
if ($db->table_exists('attachments')) {
    $keepAttachFiles = [];
    $q = $db->sql_query_prepared('SELECT attachname, thumbnail FROM attachments');
    while ($row = $db->fetch_array($q)) {
        if (!empty($row['attachname'])) $keepAttachFiles[] = $row['attachname'];
        if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') $keepAttachFiles[] = $row['thumbnail'];
    }

    $attach_orphan_count = 0;
    $attach_orphan_freed = 0;

    // Плоская папка комментариев (голые имена файлов, без подпапки)
    [$n, $freed] = cleanup_orphaned_disk_files(TSDIR . '/uploads/attachments', $keepAttachFiles, '');
    $attach_orphan_count += $n;
    $attach_orphan_freed += $freed;

    // Помесячные папки постов форума: uploads/YYYYMM/
    foreach ((glob(TSDIR . '/uploads/[0-9][0-9][0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) ?: []) as $monthDir) {
        $monthPrefix = basename($monthDir) . '/';
        [$n, $freed] = cleanup_orphaned_disk_files($monthDir, $keepAttachFiles, $monthPrefix);
        $attach_orphan_count += $n;
        $attach_orphan_freed += $freed;
    }

    if ($attach_orphan_count > 0) {
        $log[] = "Deleted {$attach_orphan_count} orphaned attachment file(s) with no DB record, freed " . format_filesize($attach_orphan_freed);
    }
}

// ── Брошенные черновики вложений (posthash, ни к чему не привязаны) ────
// Таблица attachments общая для комментариев (comment_id) и постов форума
// (pid) - "черновик" в обоих случаях означает pid=0 AND comment_id=0.
// Чистим только те, что старше 48 часов, чтобы не задеть того, кто прямо
// сейчас составляет пост/комментарий и уже прикрепил файлы.
if ($db->table_exists('attachments')) {
    $draft_cutoff = TIMENOW - 2 * 86400;

    $q = $db->sql_query_prepared(
        'SELECT aid, attachname, thumbnail, filesize FROM attachments
         WHERE pid = 0 AND comment_id = 0 AND dateuploaded < ?',
        [$draft_cutoff]
    );

    $draft_count = 0;
    $draft_freed = 0;
    $draft_batch = [];

    while ($row = $db->fetch_array($q)) {
        if (!empty($row['attachname'])) {
            $path = resolve_attachment_path(TSDIR, $row['attachname']);
            if (is_file($path)) @unlink($path);
        }
        if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
            $thumbPath = resolve_attachment_path(TSDIR, $row['thumbnail']);
            if (is_file($thumbPath)) @unlink($thumbPath);
        }
        $draft_freed += (int)$row['filesize'];
        $draft_batch[] = (int)$row['aid'];
        $draft_count++;

        if (count($draft_batch) >= 1000) {
            $placeholders = implode(',', array_fill(0, count($draft_batch), '?'));
            $db->sql_query_prepared("DELETE FROM attachments WHERE aid IN ({$placeholders})", $draft_batch);
            $draft_batch = [];
        }
    }
    if ($draft_batch) {
        $placeholders = implode(',', array_fill(0, count($draft_batch), '?'));
        $db->sql_query_prepared("DELETE FROM attachments WHERE aid IN ({$placeholders})", $draft_batch);
    }

    if ($draft_count > 0) {
        $log[] = "Deleted {$draft_count} abandoned draft attachment(s) (unposted comments/posts older than 48h), freed " . format_filesize($draft_freed);
    }
}

// ── Аватарки удалённых/несуществующих юзеров ────────────────────────────
if ($db->table_exists('users')) {
    $keepAvatars = [];
    $q = $db->sql_query_prepared('SELECT avatar FROM users');
    while ($row = $db->fetch_array($q)) {
        if (!empty($row['avatar'])) $keepAvatars[] = basename($row['avatar']);
    }
    [$n, $freed] = cleanup_orphaned_disk_files(TSDIR . '/uploads/avatars', $keepAvatars);
    if ($n > 0) $log[] = "Deleted {$n} orphaned avatar file(s), freed " . format_filesize($freed);
}

// ── Старые бэкапы БД (старше 30 дней) ───────────────────────────────────
[$n, $freed] = rotate_old_backups(ADMIN_DIR . 'backup', 30);
if ($n > 0) $log[] = "Deleted {$n} old database backup(s) older than 30 days, freed " . format_filesize($freed);

// ── Time-based cleanup ────────────────────────────────────
$thirty_days = TIMENOW - 30 * 86400;
$seven_days  = TIMENOW - 7  * 86400;

foreach ([
    ['sessions',      'time',      $thirty_days],
    ['searchlog',     'dateline',  $seven_days],
    ['loginattempts', 'added',     $thirty_days],
] as [$table, $column, $threshold]) {
    $db->sql_query_prepared("DELETE FROM {$table} WHERE {$column} < ?", [$threshold]);
    $n = $db->affected_rows();
    if ($n > 0) $log[] = "Deleted {$n} old records from {$table}";
}

// ── Просроченные записи ожидания 2FA-кода ───────────────────────────────
// Токен живёт максимум несколько минут (пока юзер вводит код), поэтому
// запись старше суток - точно "зависшая" и её можно смело чистить.
if ($db->table_exists('2fa_pending')) {
    $db->sql_query_prepared('DELETE FROM 2fa_pending WHERE created_at < ?', [$thirty_days]);
    $n = $db->affected_rows();
    if ($n > 0) $log[] = "Deleted {$n} expired 2FA pending record(s)";
}

// ── Optimize tables ───────────────────────────────────────
$tables_to_optimize = [
    'bookmarks', 'cheat_attempts', 'comments',
    'notconnectablepmlog', 'peers', 'reports', 'snatched', 'staffmessages',
    'hit_and_run', 'inactivity', 'comment_files',
    'privatemessages', 'screenshots', 'sessions', 'searchlog', 'loginattempts',
    '2fa_pending',
];

foreach ($tables_to_optimize as $table) {
    if ($db->table_exists($table)) {
        $db->sql_query_prepared("OPTIMIZE TABLE {$table}");
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