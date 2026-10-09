<?php

declare(strict_types=1);


$rootpath = './../';

if (!defined('IN_ADMINCP')) {
    define('IN_ADMINCP', true);
}

require_once $rootpath . 'global.php';
$lang->load('manage_uploads');

if (empty($CURUSER['id']) || !is_mod($usergroups)) {
    http_response_code(403);
    exit('<div class="alert alert-danger">' . htmlspecialchars($lang->manage_uploads['err_no_permission']) . '</div>');
}

// Подстановка {1}, {2}… в строки ланга. $lang->load() превращает {N} в %N$s,
// поэтому заменяем оба формата.
if (!function_exists('ags_fmt')) {
    function ags_fmt(string $str, string|int|float ...$args): string
    {
        $map = [];
        foreach ($args as $i => $arg) {
            $n = $i + 1;
            $map['{' . $n . '}']  = (string)$arg;
            $map['%' . $n . '$s'] = (string)$arg;
        }
        return strtr($str, $map);
    }
}



require_once INC_PATH . '/functions_multipage.php';

// ── Общая карта полиморфных типов контента ───────────────
function cf_content_map(): array
{
    return [
        'comment' => ['fk' => 'comment_id',  'table' => 'comments',        'idField' => 'id',   'textField' => 'text'],
        'news'    => ['fk' => 'news_id',     'table' => 'news',            'idField' => 'id',   'textField' => 'body'],
        'torrent' => ['fk' => 'torrent_id',  'table' => 'torrents',        'idField' => 'id',   'textField' => 'descr'],
        'post'    => ['fk' => 'post_id',     'table' => 'posts',           'idField' => 'pid',  'textField' => 'message'],
        'message' => ['fk' => 'messages_id', 'table' => 'privatemessages', 'idField' => 'pmid', 'textField' => 'message'],
    ];
}

function cf_target_exists(object $db, string $contentType, int $contentId): bool
{
    $map = cf_content_map();
    if (!isset($map[$contentType]) || $contentId <= 0) return false;
    $t = $map[$contentType];
    $res = $db->sql_query_prepared("SELECT {$t['idField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
    return $res && $db->num_rows($res) > 0;
}

// ── Edit (чинил - раньше форма слала update=1, но обработчика не было вообще) ──
if (isset($_POST['update']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_token']]);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_invalid_id']]);
        exit;
    }

    $map = cf_content_map();
    $provided = [];
    foreach ($map as $type => $t) {
        $val = $_POST[$t['fk']] ?? '';
        if ($val !== '') {
            $provided[$type] = (int)$val;
        }
    }

    if (count($provided) > 1) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_one_link']]);
        exit;
    }

    foreach ($provided as $type => $cid) {
        if (!cf_target_exists($db, $type, $cid)) {
            echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_uploads['flash_target_missing'], $lang->manage_uploads['opt_' . $type], $cid)]);
            exit;
        }
    }

    $res  = $db->sql_query_prepared('SELECT * FROM comment_files WHERE id = ?', [$id]);
    $file = $res ? $db->fetch_array($res) : null;
    if (!$file) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_file_not_found']]);
        exit;
    }

    $file_name = trim($_POST['file_name'] ?? '');

    // Пустое поле Uploader ID больше не отвязывает загрузившего - трогаем
    // user_id только если реально прислали новое положительное значение.
    $user_id_raw = trim((string)($_POST['user_id'] ?? ''));
    $user_id = $user_id_raw !== '' && (int)$user_id_raw > 0 ? (int)$user_id_raw : (int)($file['user_id'] ?? 0);

    $new_comment_id = $provided['comment'] ?? null;
    $new_news_id    = $provided['news'] ?? null;
    $new_torrent_id = $provided['torrent'] ?? null;
    $new_post_id    = $provided['post'] ?? null;

    // ── Если привязка меняется - чистим [img]-тег из старого места ──
    // (то же поведение, что и у Move, чтобы Edit не оставлял "осиротевший"
    // эмбед в контенте, от которого файл только что отвязали).
    foreach ($map as $oldType => $t) {
        if (empty($file[$t['fk']])) continue;

        $oldId    = (int)$file[$t['fk']];
        $newValue = $provided[$oldType] ?? null;
        if ($newValue === $oldId) continue; // ссылка не поменялась

        $res2 = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$oldId]);
        $row2 = $res2 ? $db->fetch_array($res2) : null;
        if ($row2 === null) break;

        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\]]*\[\/img\]/i';
        $current = $row2[$t['textField']] ?? '';
        $updated = preg_replace($pattern, '', $current);
        $updated = trim(preg_replace('/\n{3,}/', "\n\n", $updated ?? ''));

        if ($updated !== trim($current)) {
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $oldId]);
        }
        break; // ровно один FK может быть задан за раз
    }

    $set = ['file_name = ?', 'user_id = ?', 'comment_id = ?', 'news_id = ?', 'torrent_id = ?', 'post_id = ?'];
    $params = [
        $file_name,
        $user_id > 0 ? $user_id : null,
        $new_comment_id,
        $new_news_id,
        $new_torrent_id,
        $new_post_id,
        $id,
    ];

    $db->sql_query_prepared('UPDATE comment_files SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    write_log("comment_files updated: ID:{$id} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => $lang->manage_uploads['flash_updated']]);
    exit;
}

// ── Move (заменяет текущую привязку на новую; старая полностью очищается) ──
if (isset($_POST['ajax_move']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_token']]);
        exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $contentType = $_POST['content_type'] ?? '';
    $contentId   = (int)($_POST['content_id'] ?? 0);
    $insertTag   = !empty($_POST['insert_tag']) && $contentType !== 'message';

    $map = cf_content_map();
    if (!isset($map[$contentType])) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_invalid_type']]);
        exit;
    }

    if (!cf_target_exists($db, $contentType, $contentId)) {
        echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_uploads['flash_target_missing'], $lang->manage_uploads['opt_' . $contentType], $contentId)]);
        exit;
    }

    $res = $db->sql_query_prepared('SELECT * FROM comment_files WHERE id = ?', [$id]);
    $file = $res ? $db->fetch_array($res) : null;
    if (!$file) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_file_not_found']]);
        exit;
    }

    // ── Убираем [img]-тег из старой цели, если файл был куда-то привязан ──
    // Тот же паттерн, что уже используется в delete-обработчике этого файла.
    foreach ($map as $oldType => $t) {
        if ($oldType === 'message') continue; // PM-текст не трогаем автоматически
        if (empty($file[$t['fk']])) continue;

        $oldId = (int)$file[$t['fk']];
        $res2  = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$oldId]);
        $row2  = $res2 ? $db->fetch_array($res2) : null;
        if ($row2 === null) continue;

        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\]]*\[\/img\]/i';
        $current = $row2[$t['textField']] ?? '';
        $updated = preg_replace($pattern, '', $current);
        $updated = trim(preg_replace('/\n{3,}/', "\n\n", $updated ?? ''));

        if ($updated !== trim($current)) {
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $oldId]);
        }
        break; // ровно один FK может быть задан за раз (полиморфная эксклюзивность)
    }

    $fk = $map[$contentType]['fk'];
    $db->sql_query_prepared(
        "UPDATE comment_files SET comment_id = NULL, news_id = NULL, torrent_id = NULL, post_id = NULL, messages_id = NULL, {$fk} = ? WHERE id = ?",
        [$contentId, $id]
    );

    // ── Опционально вставляем [img]-тег в текст новой цели ──────────────
    if ($insertTag) {
        $t   = $map[$contentType];
        $res3 = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
        $row3 = $res3 ? $db->fetch_array($res3) : null;
        if ($row3 !== null) {
            $current = $row3[$t['textField']] ?? '';
            $updated = $current !== '' ? $current . "\n\n" . '[img]' . $file['file_url'] . '[/img]' : '[img]' . $file['file_url'] . '[/img]';
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $contentId]);
        }
    }

    write_log("comment_files moved: ID:{$id} -> {$contentType} #{$contentId} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => $lang->manage_uploads['flash_moved']]);
    exit;
}

// ── Copy (физическое дублирование файла + новая строка на другой/тот же контент) ──
if (isset($_POST['ajax_copy']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_token']]);
        exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $contentType = $_POST['content_type'] ?? '';
    $contentId   = (int)($_POST['content_id'] ?? 0);
    $insertTag   = !empty($_POST['insert_tag']) && $contentType !== 'message'; // PM-текст не трогаем автоматически

    $map = cf_content_map();
    if (!isset($map[$contentType])) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_invalid_type']]);
        exit;
    }

    if (!cf_target_exists($db, $contentType, $contentId)) {
        echo json_encode(['status' => 'error', 'message' => ags_fmt($lang->manage_uploads['flash_target_missing'], $lang->manage_uploads['opt_' . $contentType], $contentId)]);
        exit;
    }

    $res = $db->sql_query_prepared('SELECT * FROM comment_files WHERE id = ?', [$id]);
    $file = $res ? $db->fetch_array($res) : null;
    if (!$file || !is_file($file['file_path'])) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_source_missing']]);
        exit;
    }

    $ext         = pathinfo($file['file_path'], PATHINFO_EXTENSION);
    $newFileName = bin2hex(random_bytes(16)) . ($ext !== '' ? ".{$ext}" : '');
    $newPath     = dirname($file['file_path']) . '/' . $newFileName;

    if (!@copy($file['file_path'], $newPath)) {
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_copy_failed']]);
        exit;
    }

    $newUrl = rtrim(str_replace(basename($file['file_url']), '', $file['file_url']), '/') . '/' . $newFileName;
    $fk     = $map[$contentType]['fk'];

    $db->sql_query_prepared(
        "INSERT INTO comment_files (file_name, file_path, file_url, file_type, file_size, user_id, {$fk}, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$file['file_name'], $newPath, $newUrl, $file['file_type'], (int)$file['file_size'], (int)($CURUSER['id'] ?? 0), $contentId]
    );
    $newId = $db->insert_id();

    if ($insertTag) {
        $t = $map[$contentType];
        $res = $db->sql_query_prepared("SELECT {$t['textField']} FROM {$t['table']} WHERE {$t['idField']} = ?", [$contentId]);
        $row = $res ? $db->fetch_array($res) : null;
        if ($row !== null) {
            $current = $row[$t['textField']] ?? '';
            $updated = $current !== '' ? $current . "\n\n" . '[img]' . $newUrl . '[/img]' : '[img]' . $newUrl . '[/img]';
            $db->sql_query_prepared("UPDATE {$t['table']} SET {$t['textField']} = ? WHERE {$t['idField']} = ?", [$updated, $contentId]);
        }
    }

    write_log("comment_files copied: ID:{$id} -> new ID:{$newId} -> {$contentType} #{$contentId} | {$CURUSER['username']}");
    echo json_encode(['status' => 'success', 'message' => $lang->manage_uploads['flash_copied'], 'new_id' => $newId]);
    exit;
}

// ── Single delete (AJAX POST) ─────────────────────────────
if (isset($_POST['delete']) && is_numeric($_POST['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_token']]);
        exit;
    }

    $file_id = (int)$_POST['delete'];

    $res = $db->sql_query_prepared("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        LEFT JOIN news n ON n.id = cf.news_id
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        LEFT JOIN posts p ON p.pid = cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid = cf.messages_id
        WHERE cf.id = ?
    ", [$file_id]);

    if ($res && ($file = $db->fetch_array($res))) {
        if (!empty($file['file_path']) && file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\[]*\[\/img\]/i';

        $updates = [
            ['comment_id', 'comments',       'text',    'comment_text'],
            ['news_id',    'news',            'body',    'news_text'],
            ['torrent_id', 'torrents',        'descr',   'torrent_description'],
            ['post_id',    'posts',       'message', 'post_message'],
            ['messages_id','privatemessages', 'message', 'pm_message'],
        ];
        $pk = ['comment_id'=>'id','news_id'=>'id','torrent_id'=>'id','post_id'=>'pid','messages_id'=>'pmid'];

        foreach ($updates as [$fk, $table, $col, $textKey]) {
            if (!empty($file[$fk])) {
                $new = preg_replace($pattern, '[Image Deleted]', $file[$textKey]);
                if ($new !== $file[$textKey]) {
                    $db->sql_query_prepared("UPDATE $table SET $col = ? WHERE {$pk[$fk]} = ?", [$new, (int)$file[$fk]]);
                }
            }
        }

        $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [$file_id]);
        echo json_encode(['status'=>'success','id'=>$file_id,'message'=>$lang->manage_uploads['flash_deleted']]);
    } else {
        echo json_encode(['status'=>'error','message'=>$lang->manage_uploads['flash_file_not_found']]);
    }
    exit;
}

// ── Bulk delete (AJAX POST) ──────────────────────────────
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete') {
    header('Content-Type: application/json; charset=utf-8');

    if (!verify_post_check($_POST['my_post_key'] ?? '', true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => $lang->manage_uploads['flash_token']]);
        exit;
    }

    $selected = $_POST['selected_files'] ?? [];
    if (is_string($selected)) $selected = $selected !== '' ? [$selected] : [];
    $ids = array_filter(array_map('intval', (array)$selected));

    if (empty($ids)) {
        echo json_encode(['status'=>'error','message'=>$lang->manage_uploads['flash_none_selected']]);
        exit;
    }

    $ids_ph = implode(',', array_fill(0, count($ids), '?'));
    $res = $db->sql_query_prepared("
        SELECT cf.*, c.text AS comment_text, n.body AS news_text,
               t.descr AS torrent_description, p.message AS post_message,
               pm.message AS pm_message
        FROM comment_files cf
        LEFT JOIN comments c ON c.id=cf.comment_id
        LEFT JOIN news n ON n.id=cf.news_id
        LEFT JOIN torrents t ON t.id=cf.torrent_id
        LEFT JOIN posts p ON p.pid=cf.post_id
        LEFT JOIN privatemessages pm ON pm.pmid=cf.messages_id
        WHERE cf.id IN ({$ids_ph})
    ", $ids);

    $affected = ['comments'=>[],'news'=>[],'torrents'=>[],'posts'=>[],'privatemessages'=>[]];
    $pk_map   = ['comments'=>'id','news'=>'id','torrents'=>'id','posts'=>'pid','privatemessages'=>'pmid'];
    $col_map  = ['comments'=>'text','news'=>'body','torrents'=>'descr','posts'=>'message','privatemessages'=>'message'];
    $fk_map   = ['comments'=>'comment_id','news'=>'news_id','torrents'=>'torrent_id','posts'=>'post_id','privatemessages'=>'messages_id'];
    $txt_map  = ['comments'=>'comment_text','news'=>'news_text','torrents'=>'torrent_description','posts'=>'post_message','privatemessages'=>'pm_message'];
    $deleted_ids = [];

    while ($res && ($file = $db->fetch_array($res))) {
        $deleted_ids[] = $file['id'];
        if (file_exists($file['file_path'])) unlink($file['file_path']);
        $pattern = '/\[img\][^\[]*' . preg_quote(basename($file['file_path']), '/') . '[^\]]*\[\/img\]/i';

        foreach ($affected as $table => $_) {
            $fk = $fk_map[$table];
            if (!empty($file[$fk])) {
                $new = preg_replace($pattern, '[Image Deleted]', $file[$txt_map[$table]]);
                if ($new !== $file[$txt_map[$table]]) {
                    $affected[$table][(int)$file[$fk]] = $new;
                }
            }
        }
    }

    foreach ($affected as $table => $rows) {
        foreach ($rows as $id => $text) {
            $col = $col_map[$table];
            $pk  = $pk_map[$table];
            $db->sql_query_prepared("UPDATE $table SET $col = ? WHERE $pk = ?", [$text, $id]);
        }
    }

    $db->sql_query_prepared("DELETE FROM comment_files WHERE id IN ({$ids_ph})", $ids);
    echo json_encode(['status'=>'success','message'=>$lang->manage_uploads['flash_files_deleted'],'deleted_ids'=>$deleted_ids]);
    exit;
}

// ── List page setup ──────────────────────────────────────
$is_ajax   = isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1';
$per_page  = $ts_perpage;
// Читаем и POST, и GET - форма "Jump to Page" в multipage() отправляет
// через POST, а обычные ссылки-страницы (1,2,3...) идут через GET.
$page      = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$offset    = ($page - 1) * $per_page;
$search    = trim($_GET['search'] ?? '');
$typeFilter= trim($_GET['type']   ?? '');

$where = [];
$where_params = [];
if ($search) {
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $where[] = "(file_name LIKE ? OR file_type LIKE ?)";
    array_push($where_params, "%$like%", "%$like%");
}
if ($typeFilter) {
    $type_conditions = [
        'torrent' => "torrent_id IS NOT NULL AND torrent_id != 0",
        'news'    => "news_id IS NOT NULL AND news_id != 0",
        'comment' => "comment_id IS NOT NULL AND comment_id != 0",
        'post'    => "post_id IS NOT NULL AND post_id != 0",
        'message' => "messages_id IS NOT NULL AND messages_id != 0",
        'unlinked'=> "COALESCE(torrent_id,0) = 0 AND COALESCE(news_id,0) = 0 AND COALESCE(comment_id,0) = 0 AND COALESCE(post_id,0) = 0 AND COALESCE(messages_id,0) = 0",
    ];
    if (isset($type_conditions[$typeFilter])) $where[] = $type_conditions[$typeFilter];
}
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_q = $db->sql_query_prepared("SELECT COUNT(*) as total FROM comment_files $whereClause", $where_params);
$total_row   = $total_q ? $db->fetch_array($total_q) : null;
$total_files = $total_row['total'] ?? 0;
$total_pages = ceil($total_files / $per_page);

$result = $db->sql_query_prepared("
    SELECT comment_files.*, users.username, users.usergroup, users.avatar, users.avatardimensions
    FROM comment_files
    LEFT JOIN users ON users.id = comment_files.user_id
    $whereClause
    ORDER BY comment_files.uploaded_at DESC
    LIMIT ?, ?
", [...$where_params, $offset, $per_page]);

$files = [];
while ($result && ($row = $db->fetch_array($result))) $files[] = $row;

$this_script2 = "index.php?act=manage_uploads"
    . ($search     ? "&search="  . urlencode($search)     : '')
    . ($typeFilter ? "&type="    . urlencode($typeFilter)  : '');

if ($is_ajax) {
    ob_start();
    include('manage_uploads_ajax.php');
    echo ob_get_clean();
    stdfoot();
    exit;
}

function getFileDimensions($file_path) {
    if (!file_exists($file_path)) return 'N/A';
    $info = getimagesize($file_path);
    return $info ? $info[0] . '×' . $info[1] : 'N/A';
}

// ── Stats for KPI tiles & filter chips (full page load only) ──
function mu_folder_size(string $dir): int
{
    if (!is_dir($dir)) return 0;
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) $size += $f->getSize();
    }
    return $size;
}

// Полный рекурсивный обход /uploads на каждой загрузке страницы был бы
// всё медленнее по мере роста хранилища - кэшируем результат на 5 минут.
function mu_folder_size_cached(string $dir, int $ttl = 300): int
{
    $cacheFile = sys_get_temp_dir() . '/mu_folder_size_' . md5($dir) . '.cache';

    if (is_file($cacheFile)) {
        $cached = @json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['size'], $cached['time']) && (time() - $cached['time']) < $ttl) {
            return (int)$cached['size'];
        }
    }

    $size = mu_folder_size($dir);
    @file_put_contents($cacheFile, json_encode(['size' => $size, 'time' => time()]));
    return $size;
}

$stats_q = $db->sql_query_prepared("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(file_size), 0)                                  AS bytes,
           COALESCE(SUM(file_type LIKE 'image/%'), 0)                   AS images,
           COALESCE(SUM(uploaded_at >= NOW() - INTERVAL 1 DAY), 0)      AS fresh,
           COALESCE(SUM(COALESCE(torrent_id, 0)  > 0), 0)               AS torrent,
           COALESCE(SUM(COALESCE(news_id, 0)     > 0), 0)               AS news,
           COALESCE(SUM(COALESCE(comment_id, 0)  > 0), 0)               AS comment,
           COALESCE(SUM(COALESCE(post_id, 0)     > 0), 0)               AS post,
           COALESCE(SUM(COALESCE(messages_id, 0) > 0), 0)               AS message,
           COALESCE(SUM(COALESCE(torrent_id,0) = 0 AND COALESCE(news_id,0) = 0 AND COALESCE(comment_id,0) = 0
                        AND COALESCE(post_id,0) = 0 AND COALESCE(messages_id,0) = 0), 0) AS unlinked
    FROM comment_files
", []);
$stats = array_map('intval', ($stats_q ? $db->fetch_array($stats_q) : null) ?: []);

$storage_total = 5 * 1024 * 1024 * 1024; // лимит 5 GB
$storage_used  = mu_folder_size_cached($_SERVER['DOCUMENT_ROOT'] . '/uploads');
$storage_pct   = $storage_total > 0 ? min(100.0, round($storage_used / $storage_total * 100, 1)) : 0.0;
$storage_tone  = $storage_pct > 80 ? 'danger' : ($storage_pct > 50 ? 'warning' : 'success');

$mu_chips = [
    ''         => [$lang->manage_uploads['filter_all_files'], 'fa-layer-group', 'primary',   $stats['total']    ?? 0],
    'torrent'  => [$lang->manage_uploads['filter_torrent'],   'fa-download',    'info',      $stats['torrent']  ?? 0],
    'news'     => [$lang->manage_uploads['filter_news'],      'fa-newspaper',   'warning',   $stats['news']     ?? 0],
    'comment'  => [$lang->manage_uploads['filter_comment'],   'fa-comment',     'success',   $stats['comment']  ?? 0],
    'post'     => [$lang->manage_uploads['filter_post'],      'fa-file-lines',  'primary',   $stats['post']     ?? 0],
    'message'  => [$lang->manage_uploads['filter_message'],   'fa-envelope',    'secondary', $stats['message']  ?? 0],
    'unlinked' => [$lang->manage_uploads['filter_unlinked'],  'fa-link-slash',  'danger',    $stats['unlinked'] ?? 0],
];
$mu_chip_url = static function (string $type) use ($search): string {
    return 'index.php?act=manage_uploads'
        . ($search !== '' ? '&search=' . urlencode($search) : '')
        . ($type   !== '' ? '&type='   . urlencode($type)   : '');
};

// ── Output ───────────────────────────────────────────────
stdhead();
?>

<link rel="stylesheet" href="<?= $BASEURL ?>/admin/templates/manage_uploads.css?ver=336">


<div class="container py-4 mu-page"
     data-post-key="<?= htmlspecialchars($mybb->post_code ?? '') ?>"
     data-script-url="<?= htmlspecialchars($_this_script_) ?>">

    <!-- Header -->
    <div class="mu-card mu-head mb-4">
        <div class="mu-head-icon mu-tone-primary"><i class="fa-solid fa-photo-film"></i></div>
        <div>
            <h1><?= htmlspecialchars($lang->manage_uploads['pane_title']) ?></h1>
            <p><?= htmlspecialchars($lang->manage_uploads['pane_subtitle']) ?></p>
        </div>
        <div class="mu-head-actions">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fa-solid fa-cloud-arrow-up me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_upload']) ?>
            </button>
            <button type="button" class="btn btn-outline-primary" id="bulkSelectBtn">
                <i class="fa-solid fa-square-check me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_select_files']) ?>
            </button>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="mu-card mu-kpi">
                <div class="mu-kpi-icon mu-tone-primary"><i class="fa-solid fa-folder-open"></i></div>
                <div class="mu-kpi-body">
                    <div class="mu-kpi-value"><?= ts_nf($stats['total'] ?? 0) ?></div>
                    <div class="mu-kpi-label"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['kpi_files_total'], mksize($stats['bytes'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mu-card mu-kpi">
                <div class="mu-kpi-icon mu-tone-<?= $storage_tone ?>"><i class="fa-solid fa-hard-drive"></i></div>
                <div class="mu-kpi-body">
                    <div class="mu-kpi-value"><?= $storage_pct ?>%</div>
                    <div class="mu-kpi-label"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['kpi_storage_of'], mksize($storage_used), mksize($storage_total))) ?></div>
                    <div class="mu-meter"><span class="bg-<?= $storage_tone ?>" style="width: <?= $storage_pct ?>%"></span></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mu-card mu-kpi">
                <div class="mu-kpi-icon mu-tone-info"><i class="fa-solid fa-image"></i></div>
                <div class="mu-kpi-body">
                    <div class="mu-kpi-value"><?= ts_nf($stats['images'] ?? 0) ?></div>
                    <div class="mu-kpi-label"><?= htmlspecialchars($lang->manage_uploads['lbl_images']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mu-card mu-kpi">
                <div class="mu-kpi-icon mu-tone-warning"><i class="fa-solid fa-bolt"></i></div>
                <div class="mu-kpi-body">
                    <div class="mu-kpi-value"><?= ts_nf($stats['fresh'] ?? 0) ?></div>
                    <div class="mu-kpi-label"><?= htmlspecialchars($lang->manage_uploads['kpi_new_24h']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 rounded-4">
        <i class="fa-solid fa-circle-check me-2 fs-5"></i>
        <div class="fw-medium"><?= str_replace('+', ' ', htmlspecialchars($_GET['success'])) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search & filter chips -->
    <div class="mu-card p-3 p-md-4 mb-4">
        <form class="mu-search search-box mb-3" id="searchForm">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="form-control form-control-lg mu-input" name="search"
                   placeholder="<?= htmlspecialchars($lang->manage_uploads['ph_search']) ?>"
                   value="<?= htmlspecialchars($search) ?>" id="searchInput">
        </form>
        <div class="mu-chips">
            <?php foreach ($mu_chips as $key => [$label, $icon, $tone, $count]):
                $active = ($typeFilter === $key); ?>
            <a href="<?= htmlspecialchars($mu_chip_url($key)) ?>" class="mu-chip<?= $active ? ' active' : '' ?>">
                <i class="fa-solid <?= $icon ?> <?= $active ? '' : 'text-' . $tone ?>"></i>
                <?= htmlspecialchars($label) ?>
                <span class="mu-chip-count"><?= ts_nf($count) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Files table -->
    <div class="mu-card overflow-hidden mb-4">
        <div id="filesTableContainer">
            <?php include('manage_uploads_ajax.php'); ?>
        </div>
    </div>

    <!-- Bulk actions (sticky) -->
    <div class="bulk-actions mu-card mu-bulkbar" id="bulkActions">
        <div class="p-3">
            <form method="POST" action="<?= $_this_script_ ?>" class="d-flex flex-column flex-md-row align-items-md-center gap-3" id="bulkForm">
                <input type="hidden" name="my_post_key" value="<?= htmlspecialchars($mybb->post_code) ?>">
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="mu-ftype mu-tone-primary"><i class="fa-solid fa-list-check"></i></span>
                    <span><span class="fw-bold" id="selectedCount">0</span> <span class="text-body-secondary"><?= htmlspecialchars($lang->manage_uploads['lbl_selected']) ?></span></span>
                </div>
                <select name="bulk_action" class="form-select mu-input flex-grow-1" style="border-radius:50rem" required>
                    <option value=""><?= htmlspecialchars($lang->manage_uploads['opt_choose_action']) ?></option>
                    <option value="delete"><?= htmlspecialchars($lang->manage_uploads['opt_delete_selected']) ?></option>
                </select>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-danger" id="applyBulkAction" disabled>
                        <i class="fa-solid fa-trash-can me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_apply']) ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="cancelBulkAction">
                        <i class="fa-solid fa-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                    </button>
                </div>
                <input type="hidden" name="selected_files[]" id="selectedFilesInput">
            </form>
        </div>
    </div>

</div>

<!-- Edit Modal -->
<div class="modal fade mu-modal" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="mu-mhead-icon mu-tone-primary"><i class="fa-solid fa-pen-to-square"></i></span> <?= htmlspecialchars($lang->manage_uploads['pane_edit']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="mb-3">
                    <label class="form-label" for="editFileName"><i class="fa-solid fa-file-signature"></i><?= htmlspecialchars($lang->manage_uploads['lbl_file_name']) ?></label>
                    <input type="text" class="form-control mu-input" id="editFileName">
                </div>
                <div class="mu-note mu-tone-info mb-3">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= htmlspecialchars($lang->manage_uploads['hint_edit_link']) ?></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="editCommentId"><i class="fa-solid fa-comment text-success"></i><?= htmlspecialchars($lang->manage_uploads['lbl_comment_id']) ?></label>
                        <input type="number" class="form-control mu-input" id="editCommentId">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="editNewsId"><i class="fa-solid fa-newspaper text-warning"></i><?= htmlspecialchars($lang->manage_uploads['lbl_news_id']) ?></label>
                        <input type="number" class="form-control mu-input" id="editNewsId">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="editTorrentId"><i class="fa-solid fa-download text-info"></i><?= htmlspecialchars($lang->manage_uploads['lbl_torrent_id']) ?></label>
                        <input type="number" class="form-control mu-input" id="editTorrentId">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="editPostId"><i class="fa-solid fa-file-lines text-primary"></i><?= htmlspecialchars($lang->manage_uploads['lbl_post_id']) ?></label>
                        <input type="number" class="form-control mu-input" id="editPostId">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="editUserId"><i class="fa-solid fa-user"></i><?= htmlspecialchars($lang->manage_uploads['lbl_uploader_id']) ?></label>
                        <input type="number" class="form-control mu-input" id="editUserId">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                </button>
                <button type="button" class="btn btn-primary px-4 position-relative" id="editSaveBtn">
                    <span class="save-text"><i class="fa-solid fa-check me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_save']) ?></span>
                    <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span><?= htmlspecialchars($lang->manage_uploads['lbl_saving']) ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Move и Copy отличаются только префиксом id и текстами — рендерим одним шаблоном
$mu_target_modals = [
    'move' => ['title' => $lang->manage_uploads['pane_move'], 'icon' => 'fa-arrows-up-down-left-right', 'tone' => 'primary',
               'typeLabel' => $lang->manage_uploads['lbl_move_type'], 'idLabel' => $lang->manage_uploads['lbl_move_id'],
               'insertLabel' => $lang->manage_uploads['lbl_move_insert'],
               'note' => $lang->manage_uploads['hint_move_note'],
               'btn' => $lang->manage_uploads['act_move'], 'busy' => $lang->manage_uploads['lbl_moving']],
    'copy' => ['title' => $lang->manage_uploads['pane_copy'], 'icon' => 'fa-copy', 'tone' => 'info',
               'typeLabel' => $lang->manage_uploads['lbl_copy_type'], 'idLabel' => $lang->manage_uploads['lbl_copy_id'],
               'insertLabel' => $lang->manage_uploads['lbl_copy_insert'],
               'note' => '',
               'btn' => $lang->manage_uploads['act_copy'], 'busy' => $lang->manage_uploads['lbl_copying']],
];
foreach ($mu_target_modals as $p => $m): ?>
<!-- <?= ucfirst($p) ?> Modal -->
<div class="modal fade mu-modal" id="<?= $p ?>Modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="mu-mhead-icon mu-tone-<?= $m['tone'] ?>"><i class="fa-solid <?= $m['icon'] ?>"></i></span> <?= htmlspecialchars($m['title']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="<?= $p ?>Id">
                <div class="mu-preview mb-4">
                    <img id="<?= $p ?>PreviewImg" src="" alt="<?= htmlspecialchars($lang->manage_uploads['alt_preview']) ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="<?= $p ?>ContentType"><i class="fa-solid fa-tag"></i><?= htmlspecialchars($m['typeLabel']) ?></label>
                        <select class="form-select mu-input" id="<?= $p ?>ContentType" required>
                            <option value="" selected disabled><?= htmlspecialchars($lang->manage_uploads['opt_choose_type']) ?></option>
                            <option value="comment">💬 <?= htmlspecialchars($lang->manage_uploads['opt_comment']) ?></option>
                            <option value="news">📰 <?= htmlspecialchars($lang->manage_uploads['opt_news']) ?></option>
                            <option value="torrent">⬇️ <?= htmlspecialchars($lang->manage_uploads['opt_torrent']) ?></option>
                            <option value="post">📄 <?= htmlspecialchars($lang->manage_uploads['opt_post']) ?></option>
                            <option value="message">✉️ <?= htmlspecialchars($lang->manage_uploads['opt_message']) ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="<?= $p ?>ContentId"><i class="fa-solid fa-hashtag"></i><?= htmlspecialchars($m['idLabel']) ?></label>
                        <input type="number" class="form-control mu-input" id="<?= $p ?>ContentId" placeholder="<?= htmlspecialchars($lang->manage_uploads['ph_enter_id']) ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="<?= $p ?>BbcodeTag"><i class="fa-solid fa-code"></i><?= htmlspecialchars($lang->manage_uploads['lbl_bbcode_tag']) ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control mu-input font-monospace small" id="<?= $p ?>BbcodeTag" readonly style="border-radius:50rem 0 0 50rem">
                        <button class="btn btn-outline-secondary" type="button" id="<?= $p ?>CopyBbcodeBtn" title="<?= htmlspecialchars($lang->manage_uploads['tip_copy_clipboard']) ?>" style="border-radius:0 50rem 50rem 0">
                            <i class="fa-regular fa-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="<?= $p ?>InsertTag" checked>
                    <label class="form-check-label" for="<?= $p ?>InsertTag"><?= htmlspecialchars($m['insertLabel']) ?></label>
                    <div class="form-text" id="<?= $p ?>InsertTagNote"><?= htmlspecialchars($m['note']) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                </button>
                <button type="button" class="btn btn-primary px-4 position-relative" id="<?= $p ?>SaveBtn">
                    <span class="save-text"><i class="fa-solid <?= $m['icon'] ?> me-1"></i> <?= htmlspecialchars($m['btn']) ?></span>
                    <span class="save-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span><?= htmlspecialchars($m['busy']) ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once INC_PATH . '/modals_images.php'; ?>

<script src="<?= $BASEURL ?>/scripts/details_modal.js"></script>

<!-- Upload Modal -->
<div class="modal fade mu-modal" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span class="mu-mhead-icon mu-tone-success"><i class="fa-solid fa-cloud-arrow-up"></i></span> <?= htmlspecialchars($lang->manage_uploads['pane_upload']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Steps -->
                <div class="mu-steps">
                    <?php $mu_steps = [['fa-link', $lang->manage_uploads['step_link']], ['fa-folder-open', $lang->manage_uploads['step_select']], ['fa-cloud-arrow-up', $lang->manage_uploads['step_upload']]];
                    foreach ($mu_steps as $i => [$icon, $label]): ?>
                    <div class="mu-step step-item">
                        <span class="mu-step-dot mu-tone-primary step-circle"><i class="fa-solid <?= $icon ?>"></i></span>
                        <span><?= $i + 1 ?>. <?= htmlspecialchars($label) ?></span>
                    </div>
                    <?php if ($i < count($mu_steps) - 1): ?><span class="mu-step-line step-arrow"></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Content type & ID -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="contentTypeSelect"><i class="fa-solid fa-tag"></i><?= htmlspecialchars($lang->manage_uploads['lbl_content_type']) ?></label>
                        <select class="form-select form-select-lg mu-input" id="contentTypeSelect" required>
                            <option selected disabled><?= htmlspecialchars($lang->manage_uploads['opt_choose_type']) ?></option>
                            <option value="comment">💬 <?= htmlspecialchars($lang->manage_uploads['opt_comment']) ?></option>
                            <option value="news">📰 <?= htmlspecialchars($lang->manage_uploads['opt_news']) ?></option>
                            <option value="torrent">⬇️ <?= htmlspecialchars($lang->manage_uploads['opt_torrent']) ?></option>
                            <option value="post">📄 <?= htmlspecialchars($lang->manage_uploads['opt_post']) ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contentId"><i class="fa-solid fa-hashtag"></i><?= htmlspecialchars($lang->manage_uploads['lbl_content_id']) ?></label>
                        <input type="number" class="form-control form-control-lg mu-input" id="contentId" placeholder="<?= htmlspecialchars($lang->manage_uploads['ph_enter_id']) ?>" required>
                    </div>
                </div>

                <!-- Drop area -->
                <div class="upload-area mu-drop text-center position-relative" id="dropArea">
                    <div class="upload-progress position-absolute top-0 start-0 end-0" style="display:none">
                        <div class="progress rounded-0 rounded-top-4" style="height:4px">
                            <div class="progress-bar bg-primary" id="uploadProgress" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="mu-drop-icons">
                        <i class="fa-solid fa-file-image text-primary file-type-icon"></i>
                        <i class="fa-solid fa-file-pdf text-danger file-type-icon"></i>
                        <i class="fa-solid fa-file-word text-info file-type-icon"></i>
                    </div>
                    <div class="image-preview-container mb-3" id="imagePreviewContainer" style="display:none">
                        <div class="d-flex flex-wrap gap-2 justify-content-center" id="imagePreviewList"></div>
                    </div>
                    <h5 class="fw-bold mb-1" id="dropAreaTitle"><?= htmlspecialchars($lang->manage_uploads['lbl_drop_title']) ?></h5>
                    <p class="text-body-secondary mb-4" id="dropAreaSubtitle"><?= htmlspecialchars($lang->manage_uploads['lbl_drop_subtitle']) ?></p>
                    <div class="file-count-badge position-absolute top-0 end-0 m-3" id="fileCountBadge" style="display:none">
                        <span class="badge bg-primary rounded-pill p-2" id="fileCount"></span>
                    </div>
                    <input type="file" class="d-none" id="fileUploadInput" multiple accept="image/*,.pdf,.doc,.docx">
                    <button class="btn btn-outline-primary btn-lg px-5" onclick="document.getElementById('fileUploadInput').click()" id="browseBtn">
                        <i class="fa-solid fa-folder-open me-2"></i><?= htmlspecialchars($lang->manage_uploads['btn_browse']) ?>
                    </button>
                    <div>
                        <button class="btn btn-link text-danger mt-3 text-decoration-none" id="clearFilesBtn" style="display:none" onclick="clearSelectedFiles()">
                            <i class="fa-solid fa-circle-xmark me-1"></i><?= htmlspecialchars($lang->manage_uploads['btn_clear_all']) ?>
                        </button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="d-flex justify-content-between mt-3 flex-wrap gap-2">
                    <span class="mu-typecount"><i class="fa-solid fa-image text-primary"></i><?= htmlspecialchars($lang->manage_uploads['lbl_images']) ?> <span class="badge rounded-pill" id="imageCount">0</span></span>
                    <span class="mu-typecount"><i class="fa-solid fa-file-pdf text-danger"></i><?= htmlspecialchars($lang->manage_uploads['lbl_type_pdf']) ?> <span class="badge rounded-pill" id="pdfCount">0</span></span>
                    <span class="mu-typecount"><i class="fa-solid fa-file-word text-info"></i><?= htmlspecialchars($lang->manage_uploads['lbl_type_docs']) ?> <span class="badge rounded-pill" id="docCount">0</span></span>
                    <span class="mu-typecount" id="sizeWarning" style="display:none"><i class="fa-solid fa-triangle-exclamation text-warning"></i><span id="totalSize">0 MB</span></span>
                </div>

                <!-- Selected files list -->
                <div class="selected-files mt-4" id="selectedFilesList" style="display:none">
                    <div class="mu-filebox d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-copy text-primary fs-5 me-2"></i>
                            <span class="fw-bold"><?= ags_fmt(htmlspecialchars($lang->manage_uploads['lbl_files_ready']), '<span id="selectedFilesCount">0</span>') ?></span>
                        </div>
                        <span class="text-body-secondary small" id="totalSizeDisplay">0 KB</span>
                    </div>
                    <div class="list-group" id="selectedFilesListContainer" style="max-height:200px;overflow-y:auto"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i><?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                </button>
                <button type="button" class="btn btn-success px-5 position-relative" id="startUploadBtn" disabled>
                    <span class="upload-text"><i class="fa-solid fa-cloud-arrow-up me-2"></i><?= htmlspecialchars($lang->manage_uploads['btn_upload_now']) ?></span>
                    <span class="upload-loading d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span><?= htmlspecialchars($lang->manage_uploads['lbl_uploading']) ?>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal -->
<div class="modal fade mu-modal" id="confirmBulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
             <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                   <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($lang->manage_uploads['pane_bulk_delete']) ?>
                </h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mu-danger-hero">
				   
                    <span class="mu-mhead-icon mu-tone-danger"><i class="fa-solid fa-trash-can"></i></span>
                    <div>
                        <h5 class="fw-bold mb-1"><?= ags_fmt(htmlspecialchars($lang->manage_uploads['lbl_bulk_delete_q']), '<span id="filesCount" class="text-danger">0</span>') ?></h5>
                        <p class="text-body-secondary mb-0 small"><?= htmlspecialchars($lang->manage_uploads['lbl_bulk_delete_note']) ?></p>
                    </div>
                </div>
                <div class="bulk-preview-grid mu-bulk-grid mb-3" id="bulkPreviewGrid">
                    <div class="text-center py-4 text-body-secondary" id="bulkPreviewPlaceholder">
                        <i class="fa-solid fa-images fa-2x mb-2"></i>
                        <div class="small"><?= htmlspecialchars($lang->manage_uploads['lbl_no_images_selected']) ?></div>
                    </div>
                </div>
                <div class="file-details mu-filebox mb-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="mu-mhead-icon mu-tone-success me-3"><i class="fa-solid fa-list-check"></i></span>
                            <div>
                                <div class="fw-bold" id="selectedFilesSummary"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['lbl_files_selected'], 0)) ?></div>
                                <div class="small text-body-secondary" id="selectedFilesSize"><?= htmlspecialchars(ags_fmt($lang->manage_uploads['lbl_total_size'], '0 MB')) ?></div>
                            </div>
                        </div>
                        <span class="badge rounded-pill bg-danger" id="bulkSelectedCount">0</span>
                    </div>
                </div>
                <div class="mu-note mu-tone-warning">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong><?= htmlspecialchars($lang->manage_uploads['lbl_irreversible']) ?></strong> <?= htmlspecialchars($lang->manage_uploads['lbl_files_removed']) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">
                    <i class="fa-solid fa-trash-can me-1"></i> <?= ags_fmt(htmlspecialchars($lang->manage_uploads['btn_delete_n']), '<span id="confirmCount">0</span>') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Single Delete Modal -->
<div class="modal fade mu-modal" id="singleDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($lang->manage_uploads['pane_single_delete']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mu-danger-hero">
                    <span class="mu-mhead-icon mu-tone-danger"><i class="fa-solid fa-trash-can"></i></span>
                    <div>
                        <h5 class="fw-bold mb-1" id="singleDeleteTitle"><?= htmlspecialchars($lang->manage_uploads['lbl_single_delete_q']) ?></h5>
                        <p class="text-body-secondary mb-0 small" id="singleDeleteFilename"></p>
                    </div>
                </div>
                <div class="single-preview-container mu-preview mb-3" id="singlePreviewContainer">
                    <img id="singleDeleteImage" src="" alt="<?= htmlspecialchars($lang->manage_uploads['alt_preview']) ?>"
                         style="max-width:100%;max-height:200px;display:none"
                         onerror="this.style.display='none'">
                </div>
                <div class="file-details mu-filebox mb-3">
                    <div class="d-flex align-items-center">
                        <span class="mu-mhead-icon mu-tone-primary me-3"><i class="fa-solid fa-file-lines"></i></span>
                        <div class="min-w-0">
                            <div class="fw-bold text-break" id="singleDeleteFileName">filename.jpg</div>
                            <div class="small text-body-secondary" id="singleDeleteFileInfo">
                                <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= htmlspecialchars($lang->manage_uploads['lbl_loading']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mu-note mu-tone-warning">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong><?= htmlspecialchars($lang->manage_uploads['lbl_irreversible']) ?></strong> <?= htmlspecialchars($lang->manage_uploads['lbl_file_removed']) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> <?= htmlspecialchars($lang->manage_uploads['btn_cancel']) ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmSingleDeleteBtn">
                    <i class="fa-solid fa-trash-can me-1"></i> <?= htmlspecialchars($lang->manage_uploads['act_delete']) ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// js_* ключи ланга -> AGS_LANG (без префикса) для manage_uploads.js / manage_uploads_actions.js
$mu_js_lang = [];
foreach ($lang->manage_uploads as $mu_k => $mu_v) {
    if (str_starts_with((string)$mu_k, 'js_')) {
        $mu_js_lang[substr((string)$mu_k, 3)] = $mu_v;
    }
}
?>
<script src="<?= $BASEURL ?>/scripts/toast.js"></script>
<script>const AGS_LANG = <?= json_encode($mu_js_lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_uploads.js?ver=3362"></script>
<script src="<?= $BASEURL ?>/admin/scripts/manage_uploads_actions.js?ver=22"></script>

<?php stdfoot(); ?>