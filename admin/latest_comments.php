<?php

declare(strict_types=1);

define("IN_ARCHIVE", true);
require_once INC_PATH.'/class_parser.php';
require_once INC_PATH.'/readconfig.php';

$parser = new postParser;
$parser_options = [
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
];

if (!defined('STAFF_PANEL_TSSEv56')) {
    exit('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// ================== Вспомогательные функции ==================
function validate_torrent_exists(int $torrent_id): array|false {
    global $db;
    return $db->fetch_array($db->sql_query("SELECT id, name FROM torrents WHERE id = $torrent_id")) ?: false;
}

function validate_comments_exist(array $comment_ids): bool {
    global $db;
    if (empty($comment_ids)) return false;
    
    $ids_str = implode(',', array_map('intval', $comment_ids));
    $result = $db->fetch_array($db->sql_query("SELECT COUNT(*) as cnt FROM comments WHERE id IN ($ids_str)"));
    return $result && $result['cnt'] === count($comment_ids);
}

function update_torrent_comments_count(int $torrent_id): int {
    global $db;
    $row = $db->fetch_array($db->sql_query("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = $torrent_id"));
    $db->sql_query("UPDATE torrents SET comments = " . (int)$row['cnt'] . " WHERE id = $torrent_id");
    return (int)$row['cnt'];
}

function update_user_comments_count(int $user_id): int {
    global $db;
    $row = $db->fetch_array($db->sql_query("SELECT COUNT(*) AS cnt FROM comments WHERE user = $user_id"));
    $db->sql_query("UPDATE users SET comms = " . (int)$row['cnt'] . " WHERE id = $user_id");
    return (int)$row['cnt'];
}

// Функции для генерации HTML
function generateCommentsTable($res, int $total_comments, int $page, int $limit, int $offset, int $total_pages): string {
    global $parser, $parser_options, $BASEURL, $db, $dateformat, $timeformat;
    
    $output = '<div class="card shadow-sm border-0 bg-white">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Comments Management</h5>
            <div>
                <button class="btn btn-sm btn-warning me-2" data-bs-toggle="modal" data-bs-target="#mergeCommentsModal">
                    <i class="bi bi-arrow-left-right"></i> Move Comments
                </button>
                <button class="btn btn-sm btn-info me-2" data-bs-toggle="modal" data-bs-target="#copyCommentsModal">
                    <i class="bi bi-copy"></i> Copy Comments
                </button>
                <button id="bulkDeleteBtn" class="btn btn-sm btn-danger me-2" disabled>
                    <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                </button>
                <button id="selectAllBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check-all"></i> Select All
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="40">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" id="selectAll" title="Select all">
                                <label class="form-check-label" for="selectAll"></label>
                            </div>
                        </th>
                        <th width="50">#</th>
                        <th>User</th>
                        <th>Torrent</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th width="120" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>';

    while ($row = $db->fetch_array($res)) {
        $parsed_text = $parser->parse_message($row['text'], $parser_options);
        $parsed_text = preg_replace_callback(
            "#<img([^>]*)>#i", 
            fn($matches) => "<img{$matches[1]} style='max-width:100px; height:auto; border-radius:4px;' />",
            $parsed_text
        );
        
        $seo_user = $BASEURL . '/' . get_profile_link($row['uid']);
        $SEOLink = $BASEURL . '/' . get_torrent_link($row['torrent']);
        $pid = $row['id'];
        $tid = $row['torrent'];
        $postlink = get_comment_link($pid, $tid);
        $comment_link = $BASEURL . '/' . $postlink;

        $output .= '<tr data-comment-id="'.$row['id'].'">
            <td>
                <div class="form-check form-switch d-inline-block">
                    <input class="form-check-input comment-checkbox" type="checkbox" value="'.$row['id'].'" id="comment'.$row['id'].'">
                    <label class="form-check-label" for="comment'.$row['id'].'"></label>
                </div>
            </td>
            <td class="fw-bold">
                <a href="' . $comment_link . '#pid' . $pid . '" target="_blank" title="Open comment">
                '.$row['id'].' <i class="bi bi-link-45deg"></i>
                </a>
            </td>
            <td><a href="'.$seo_user.'">'.format_name($row['username'], $row['usergroup']).'</a></td>
            <td>
                <a href="'.$SEOLink.'">
                '.htmlspecialchars($row['torrent_name']).'
                </a>
            </td>
            <td class="comment-text">'.$parsed_text.'</td>
            <td>
                <span class="small text-muted">
                    <i class="bi bi-calendar me-1"></i> ' . my_datee($dateformat, $row['dateline']) . '
                </span><br>
                <span class="small text-muted">
                    <i class="bi bi-clock me-1"></i> ' . my_datee($timeformat, $row['dateline']) . '
                </span>
            </td>
            
            <td class="text-center">
                <button class="btn btn-sm p-1 me-2" style="background: none; border: none;" onclick="editComment('.$row['id'].')" title="Edit Comment">
                    <i class="fa-solid fa-pen-to-square fa-xl" style="color: #0658e5;"></i>
                </button>
                <button class="btn btn-sm p-1" style="background: none; border: none;" onclick="deleteComment('.$row['id'].')" title="Delete Comment">
                    <i class="fa-solid fa-trash-can fa-xl" style="color: #eb0f0f;"></i>
                </button>
            </td>
        </tr>';
    }

    $output .= '</tbody></table></div></div>';

    // Пагинация
    $start = $offset + 1;
    $end = min($offset + $limit, $total_comments);

    $output .= '<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">';
    $output .= '<div class="text-muted small">Showing <b>'.$start.'</b> – <b>'.$end.'</b> of <b>'.$total_comments.'</b> comments</div>';
    $output .= generatePagination($page, $total_pages);
    $output .= '</div>';

    return $output;
}

function generatePagination(int $current_page, int $total_pages): string {
    $output = '<nav><ul class="pagination pagination-sm mb-0">';
    
    // Previous button
    $prev_disabled = $current_page <= 1 ? 'disabled' : '';
    $prev_page = max(1, $current_page - 1);
    $output .= '<li class="page-item '.$prev_disabled.'">
            <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$prev_page.')">&laquo; Prev</a>
          </li>';

    $range = 2;
    
    // First page + ellipsis
    if ($current_page - $range > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadComments(1)">1</a></li>';
        if ($current_page - $range > 2) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++) {
        $active = $i === $current_page ? 'active' : '';
        $output .= '<li class="page-item '.$active.'">
                <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$i.')">'.$i.'</a>
              </li>';
    }
    
    // Last page + ellipsis
    if ($current_page + $range < $total_pages) {
        if ($current_page + $range < $total_pages - 1) {
            $output .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $output .= '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadComments('.$total_pages.')">'.$total_pages.'</a></li>';
    }
    
    // Next button
    $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
    $next_page = min($total_pages, $current_page + 1);
    $output .= '<li class="page-item '.$next_disabled.'">
            <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$next_page.')">Next &raquo;</a>
          </li>';

    $output .= '</ul></nav>';
    return $output;
}

// ================== AJAX actions ==================
if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];

    // ===== Search torrents for autocomplete =====
    if ($action === "search_torrents" && $_SERVER['REQUEST_METHOD'] === "GET") {
        $query = $db->escape_string(trim($_GET['q'] ?? ''));
        if (strlen($query) < 2) {
            echo json_encode([]);
            exit;
        }

        $res = $db->sql_query("SELECT id, name FROM torrents WHERE name LIKE '%$query%' ORDER BY name ASC LIMIT 20");
        $results = [];
        while ($row = $db->fetch_array($res)) {
            $results[] = ['id' => (int)$row['id'], 'name' => htmlspecialchars($row['name'])];
        }

        echo json_encode($results);
        exit;
    }

    // ===== Move comments =====
if ($action === "move_comments" && $_SERVER['REQUEST_METHOD'] === "POST") 
{
    header('Content-Type: application/json; charset=utf-8');

    // Проверка безопасности
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $comment_ids = $_POST['comment_ids'] ?? '';
    $target_tid = (int)($_POST['target_tid'] ?? 0);

    // Валидация
    if ($target_tid <= 0) {
        echo json_encode(['error' => 'Invalid target torrent ID']);
        exit;
    }

    // ИСПРАВЛЕНИЕ: декодируем JSON строку в массив
    if (empty($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    // Декодируем JSON
    $comment_ids = json_decode($comment_ids, true);
    
    if (empty($comment_ids) || !is_array($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    // Проверка существования торрента
    $torrent_check = validate_torrent_exists($target_tid);
    if (!$torrent_check) {
        echo json_encode(['error' => 'Target torrent not found']);
        exit;
    }

    // Защита от инъекций
    $comment_ids = array_map('intval', $comment_ids);
    $ids_str = implode(',', $comment_ids);

    // Получаем исходные torrent IDs
    $res = $db->sql_query("SELECT id, torrent FROM comments WHERE id IN ($ids_str)");
    $moved_count = 0;
    $source_tids = [];

    while ($row = $db->fetch_array($res)) {
        $source_tid = (int)$row['torrent'];
        $source_tids[] = $source_tid;

        $db->sql_query("UPDATE comments SET torrent = $target_tid WHERE id = " . (int)$row['id']);
        $moved_count++;
    }

    // Обновляем счетчики
    $tids = array_unique([...$source_tids, $target_tid]);
    foreach ($tids as $tid) {
        update_torrent_comments_count($tid);
    }

    // Логируем действие
    $source_links = array_map(
        fn($tid) => '<a href="' . $BASEURL . '/' . get_torrent_link($tid) . '">Torrent #' . $tid . '</a>',
        array_unique($source_tids)
    );
    
    $source_links_str = implode(', ', $source_links);
    $target_link = '<a href="' . $BASEURL . '/' . get_torrent_link($target_tid) . '">Torrent #' . $target_tid . '</a>';

    $log_message = sprintf(
        'User %s (UID %d) moved %d comment%s from %s to %s',
        $CURUSER['username'],
        (int)$CURUSER['id'],
        $moved_count,
        $moved_count > 1 ? 's' : '',
        $source_links_str,
        $target_link
    );

    write_log($log_message);

    echo json_encode([
        'success' => true, 
        'moved' => $moved_count,
        'target_torrent' => $torrent_check['name']
    ]);
    exit;
}
	

    
	
	
	// ===== Copy comments =====
if ($action === "copy_comments" && $_SERVER['REQUEST_METHOD'] === "POST") 
{
    header('Content-Type: application/json; charset=utf-8');

    // Проверка безопасности
    //if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        //echo json_encode(['error' => 'Invalid security token']);
        //exit;
    //}

    $comment_ids = $_POST['comment_ids'] ?? '';
    $target_tid = (int)($_POST['target_tid'] ?? 0);

    // Валидация
    if ($target_tid <= 0) {
        echo json_encode(['error' => 'Invalid target torrent ID']);
        exit;
    }

    // Исправление: декодируем JSON строку в массив
    if (empty($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    // Декодируем JSON
    $comment_ids = json_decode($comment_ids, true);
    
    if (empty($comment_ids) || !is_array($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    // Проверка существования торрента
    $torrent_check = validate_torrent_exists($target_tid);
    if (!$torrent_check) {
        echo json_encode(['error' => 'Target torrent not found']);
        exit;
    }

    $comment_ids = array_map('intval', $comment_ids);
    $ids_str = implode(',', $comment_ids);

    $res = $db->sql_query("SELECT * FROM comments WHERE id IN ($ids_str)");
    $copied = 0;
    $user_counts = [];

    while ($row = $db->fetch_array($res)) {
        $insert = [
            'user'      => (int)$row['user'],
            'torrent'   => $target_tid,
            'text'      => $db->escape_string($row['text']),
            'dateline'  => (int)$row['dateline'],
            'editreason'=> $db->escape_string($row['editreason']),
            'editedby'  => (int)$row['editedby'],
            'editedat'  => (int)$row['editedat'],
            'totalvotes'=> $db->escape_string($row['totalvotes'])
        ];
        $db->insert_query('comments', $insert);
        $copied++;

        $uid = (int)$row['user'];
        $user_counts[$uid] = ($user_counts[$uid] ?? 0) + 1;
    }

    // Обновляем счетчики
    foreach ($user_counts as $uid => $count) {
        $db->sql_query("UPDATE users SET comms = comms + $count WHERE id = $uid");
    }

    update_torrent_comments_count($target_tid);

    // Логируем действие
    $torrent_link = $BASEURL . '/' . get_torrent_link($target_tid);
    $log_message = sprintf(
        'User %s (UID %d) copied %d comment%s to <a href="%s">Torrent #%d</a>',
        $CURUSER['username'],
        (int)$CURUSER['id'],
        $copied,
        $copied > 1 ? 's' : '',
        $torrent_link,
        $target_tid
    );

    write_log($log_message);

    echo json_encode([
        'success' => true, 
        'copied' => $copied,
        'target_torrent' => $torrent_check['name']
    ]);
    exit;
}
	
	
	

    // ===== Preview =====
    if ($action === "preview" && $_SERVER['REQUEST_METHOD'] === "POST") {
        $text = (string)($_POST['text'] ?? '');
        $parsed_text = $parser->parse_message($text, $parser_options);

        // Ограничиваем <img> с использованием arrow function
        $parsed_text = preg_replace_callback(
            "#<img([^>]*)>#i", 
            fn($matches) => "<img{$matches[1]} style='max-width:200px; height:auto; border-radius:4px;' />",
            $parsed_text
        );

        echo $parsed_text;
        exit;
    }

    // ===== List comments =====
    if ($action === "list") {
        $limit = 20;
        $page = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;

        // Получаем параметры фильтрации
        $username = $db->escape_string(trim($_GET['username'] ?? ''));
        $torrent = $db->escape_string(trim($_GET['torrent'] ?? ''));
        $date_from = isset($_GET['date_from']) ? strtotime((string)$_GET['date_from']) : 0;
        $date_to = isset($_GET['date_to']) ? strtotime((string)$_GET['date_to']) : 0;

        // Формируем условия WHERE
        $where = [];
        if (!empty($username)) {
            $where[] = "u.username LIKE '%{$username}%'";
        }
        if (!empty($torrent)) {
            $where[] = "t.name LIKE '%{$torrent}%'";
        }
        if ($date_from > 0) {
            $where[] = "c.dateline >= {$date_from}";
        }
        if ($date_to > 0) {
            $where[] = "c.dateline <= {$date_to}";
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Подсчет общего количества
        $total_res = $db->sql_query("
            SELECT COUNT(*) as cnt
            FROM comments c
            LEFT JOIN users u ON c.user = u.id
            LEFT JOIN torrents t ON c.torrent = t.id
            {$where_sql}
        ");
        $total_row = $db->fetch_array($total_res);
        $total_comments = (int)$total_row['cnt'];
        $total_pages = (int)ceil($total_comments / $limit);

        if ($total_comments === 0) {
            echo '<div class="alert alert-light border text-center p-4 shadow-sm rounded">No comments found matching your criteria.</div>';
            exit;
        }

        // Основной запрос
        $res = $db->sql_query("
            SELECT c.*, u.username, u.usergroup, u.id as uid, t.name AS torrent_name 
            FROM comments c
            LEFT JOIN users u ON c.user = u.id
            LEFT JOIN torrents t ON c.torrent = t.id
            {$where_sql}
            ORDER BY c.dateline DESC
            LIMIT {$offset}, {$limit}
        ");

        // Генерация HTML
        echo generateCommentsTable($res, $total_comments, $page, $limit, $offset, $total_pages);
        exit;
    }

    // ===== Get comment text for edit modal =====
    if ($action === "edit") {
        $id = (int)($_GET['id'] ?? 0);
        $comment = $db->fetch_array($db->simple_select("comments", "id, text", "id = {$id}"));
        
        if (!$comment) {
            echo json_encode(["error" => "Comment not found."]);
            exit;
        }
        
        echo json_encode(["text" => $comment['text']]);
        exit;
    }

    // ===== Save comment =====
    if ($action === "save" && $_SERVER['REQUEST_METHOD'] === "POST") {
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        
        // Проверка существования комментария
        $comment_check = $db->fetch_array($db->sql_query("SELECT id FROM comments WHERE id = {$id}"));
        if (!$comment_check) {
            echo json_encode(["success" => false, "error" => "Comment not found"]);
            exit;
        }
        
        // Валидация
        if (strlen($text) < 3) {
            echo json_encode(["success" => false, "error" => "Comment must be at least 3 characters long"]);
            exit;
        }
        
        $clean_text = preg_replace('/\s+/', '', $text);
        if (strlen($clean_text) < 3) {
            echo json_encode(["success" => false, "error" => "Comment must contain meaningful text"]);
            exit;
        }
        
        try {
            $update_comment = [
                "text"     => $db->escape_string($text),
                "editedat" => TIMENOW,
                "editedby" => (int)($CURUSER["id"] ?? 0)
            ];
            
            $db->update_query("comments", $update_comment, "id = {$id}");
            echo json_encode(["success" => true]);
            
        } catch (Exception $e) {
            echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
        }
        exit;
    }

    // ===== Delete comment =====
if ($action === "delete" && $_SERVER['REQUEST_METHOD'] === "POST") 
{
    $id = (int)($_POST['id'] ?? 0);

    // Получаем информацию о комментарии перед удалением
    $comment = $db->fetch_array($db->sql_query("SELECT user, torrent FROM comments WHERE id = {$id}"));
    
    if (!$comment) {
        echo json_encode(["success" => false, "error" => "Comment not found"]);
        exit;
    }

    $user_id = (int)$comment['user'];
    $torrent_id = (int)$comment['torrent'];

    // Удаляем прикрепленные файлы
    $files = $db->simple_select("comment_files", "*", "comment_id = {$id}");
    while ($file = $db->fetch_array($files)) {
        if (is_file($file['file_path'] ?? '')) {
            @unlink($file['file_path']);
        }
    }
    
    // Удаляем записи из БД
    $db->delete_query("comment_files", "comment_id = {$id}");
    $db->delete_query("comments", "id = {$id}");
    
    // ОБНОВЛЯЕМ СЧЕТЧИКИ
    if ($torrent_id > 0) {
        $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $torrent_id);
    }
    
    if ($user_id > 0) {
        $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $user_id);
        
        // Remove KPS points если есть такая система
        $points = $kpscache['kpscomment'] ?? 1;
        kps('-', $points, $user_id);
        
    }

    // Логируем действие
    $log_message = sprintf(
        'User %s (UID %d) deleted comment #%d from torrent #%d',
        $CURUSER['username'],
        (int)$CURUSER['id'],
        $id,
        $torrent_id
    );
    write_log($log_message);

    echo json_encode(["success" => true]);
    exit;
}
	
	

    // ===== Bulk Delete =====
    if ($action === "bulk_delete" && $_SERVER['REQUEST_METHOD'] === "POST") 
	{
        header('Content-Type: application/json');
        
        if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
            echo json_encode(['error' => 'Invalid security token']);
            exit;
        }

        $ids = $_POST['ids'] ?? [];

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['error' => 'No comment IDs provided']);
            exit;
        }

        // Защита от инъекций
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        $ids_str = implode(',', $ids);

        if (empty($ids_str)) {
            echo json_encode(['error' => 'No valid comment IDs provided']);
            exit;
        }

        // Получаем информацию о комментариях
        $comments_info = [];
        $query = $db->sql_query("SELECT id, user, torrent FROM comments WHERE id IN ({$ids_str})");
        while ($comment = $db->fetch_array($query)) {
            $comments_info[] = $comment;
        }

        // Удаление файлов
        $files = $db->sql_query("SELECT file_path FROM comment_files WHERE comment_id IN ({$ids_str})");
        while ($file = $db->fetch_array($files)) {
            if (!empty($file['file_path']) && file_exists($file['file_path'])) {
                @unlink($file['file_path']);
            }
        }

        // Удаление записей
        $db->sql_query("DELETE FROM comment_files WHERE comment_id IN ({$ids_str})");
        $db->sql_query("DELETE FROM comments WHERE id IN ({$ids_str})");
        $deleted_count = $db->affected_rows();

        // Обновляем счетчики
        foreach ($comments_info as $comment) {
            $torrent_id = (int)($comment['torrent'] ?? 0);
            $user_id = (int)($comment['user'] ?? 0);
            
            if ($torrent_id > 0) {
                $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrent_id));
            }
            
            if ($user_id > 0) {
                $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($user_id));
                
                // Remove KPS points
                if (function_exists('kps')) {
                    global $cache;
                    $kpscache = $cache->read("KPS");
                    $points = $kpscache['kpscomment'] ?? 1;
                    kps('-', $points, $user_id);
                }
            }
        }

        // Логируем удаление
        $uid = (int)($CURUSER['id'] ?? 0);
        $username = $db->escape_string($CURUSER['username'] ?? 'Unknown');
        $logText = "User <a href=\"userdetails.php?id={$uid}\"><b>{$username}</b></a> deleted {$deleted_count} comment(s): {$ids_str}";
        write_log($logText);

        echo json_encode(['success' => true, 'deleted' => $deleted_count]);
        exit;
    }
}



// ================== HTML template ==================
stdhead("Comments Admin");


?>

<div class="container mt-4">
    <h1 class="mb-4 text-dark"><i class="bi bi-chat-text"></i> Comments Admin</h1>
    
    <!-- Фильтры и поиск -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Search by user...">
                </div>
                <div class="col-md-3">
                    <label for="torrent" class="form-label">Torrent</label>
                    <input type="text" class="form-control" id="torrent" name="torrent" placeholder="Search by torrent...">
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <button type="button" id="resetFilters" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица комментариев -->
    <div id="comments-table" class="fade-in">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading comments...</p>
        </div>
    </div>
</div>

<!-- Move Comments Modal -->
<div class="modal fade" id="mergeCommentsModal" tabindex="-1" aria-labelledby="mergeCommentsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="mergeCommentsModalLabel">Move Selected Comments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label for="targetTorrent" class="form-label">Target Torrent ID</label>
            <input type="number" class="form-control" id="targetTorrent" placeholder="Enter target torrent ID">
            <small class="form-text text-muted">Enter the ID of the torrent where comments will be moved</small>
        </div>

        <div class="alert alert-info small">
            <strong>Selected comments:</strong> <span id="selectedCommentsCount">0</span><br>
            Comments will be moved to the target torrent. This action cannot be undone.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmMergeBtn" type="button" class="btn btn-warning">Move Comments</button>
      </div>
    </div>
  </div>
</div>





<!-- Copy Comments Modal -->
<div class="modal fade" id="copyCommentsModal" tabindex="-1" aria-labelledby="copyCommentsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-info text-dark">
        <h5 class="modal-title" id="copyCommentsModalLabel">Copy Selected Comments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
            <label for="copyTargetTorrent" class="form-label">Target Torrent ID</label>
            <input type="number" class="form-control" id="copyTargetTorrent" placeholder="Enter target torrent ID">
            <small class="form-text text-muted">Comments will be duplicated to this torrent</small>
        </div>

        <div class="alert alert-info small">
            <strong>Selected comments:</strong> <span id="copySelectedCount">0</span><br>
            Original comments will remain intact.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmCopyBtn" type="button" class="btn btn-info">Copy Comments</button>
      </div>
    </div>
  </div>
</div>





<!-- Edit Modal -->
<div class="modal fade" id="editCommentModal" tabindex="-1" aria-labelledby="editCommentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow-sm rounded-3 border-0">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editCommentModalLabel">Edit Comment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          
		  
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[b]', '[/b]')"><b>B</b></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[i]', '[/i]')"><i>I</i></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[u]', '[/u]')"><u>U</u></button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[s]', '[/s]')"><s>S</s></button>

  <!-- Alignment -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[left]', '[/left]')">Left</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[center]', '[/center]')">Center</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[right]', '[/right]')">Right</button>

  <!-- Color & Size -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[color=red]', '[/color]')">Red</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[size=18]', '[/size]')">Size</button>

  <!-- Links & Media -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[url]', '[/url]')">URL</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[img]', '[/img]')">IMG</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[video]', '[/video]')">Video</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[youtube]', '[/youtube]')">YouTube</button>

  <!-- Quote & Code -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[quote]', '[/quote]')">Quote</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[code]', '[/code]')">Code</button>

  <!-- Lists -->
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list]\n[*]', '\n[/list]')">List</button>
  <button class="btn btn-sm btn-light" onclick="wrapBBCode('[list=1]\n[*]', '\n[/list]')">#List</button>
		  
		  
		  
        </div>
        <textarea id="editCommentText" class="form-control mb-3" rows="6" placeholder="Edit your comment..."></textarea>
        <h6>Live Preview</h6>
        <div id="bbcodePreview" class="border p-2 bg-light rounded" style="min-height: 100px;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmEditComment" type="button" class="btn btn-primary">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm Bulk Delete -->
<div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="bulkDeleteModalLabel"><i class="bi bi-exclamation-triangle"></i> Confirm Deletion</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="bulkDeleteMessage">Are you sure you want to delete the selected comments?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="confirmBulkDeleteBtn" type="button" class="btn btn-danger">
          <i class="bi bi-trash"></i> Yes, Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>


<link rel="stylesheet" href="<?= $BASEURL; ?>/include/templates/default/style/bootstrap-icons.css">


<style>
#selectAll, .comment-checkbox {
    transform: scale(1.2);
    cursor: pointer;
}

.table-active {
    background-color: rgba(0, 123, 255, 0.1) !important;
}

#bulkDeleteBtn:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.toast {
    transition: all 0.3s ease;
}
</style>



<script src="<?= $BASEURL; ?>/scripts/toast.js"></script>
<script src="<?= $BASEURL; ?>/admin/scripts/comments-admin.js?v=1.0"></script>





<?php stdfoot(); ?>