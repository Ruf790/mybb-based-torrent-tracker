<?php

declare(strict_types=1);

define("IN_ARCHIVE", true);
require_once(INC_PATH.'/class_parser.php');

$parser = new postParser;
$parser_options = array(
    "allow_html" => 1,
    "allow_mycode" => 1,
    "allow_smilies" => 1,
    "allow_imgcode" => 1,
    "allow_videocode" => 1,
    "filter_badwords" => 1
);

if (!defined ('STAFF_PANEL_TSSEv56'))
{
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
}

// ================== AJAX actions ==================
if (isset($_GET['action'])) 
{
    $action = $_GET['action'];

    // ===== Search torrents for autocomplete =====
    if ($action == "search_torrents" && $_SERVER['REQUEST_METHOD'] == "GET") {
        $query = $db->escape_string(trim($_GET['q'] ?? ''));
        if (strlen($query) < 2) {
            echo json_encode([]);
            exit;
        }

        $res = $db->sql_query("SELECT id, name FROM torrents WHERE name LIKE '%$query%' ORDER BY name ASC LIMIT 20");
        $results = [];
        while ($row = $db->fetch_array($res)) {
            $results[] = ['id' => $row['id'], 'name' => $row['name']];
        }

        echo json_encode($results);
        exit;
    }

    
// ===== Move comments =====
if (isset($_GET['action']) && $_GET['action'] === "move_comments" && $_SERVER['REQUEST_METHOD'] === "POST") 
{
    header('Content-Type: application/json; charset=utf-8');

    // Проверка безопасности
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $comment_ids = $_POST['comment_ids'] ?? [];
    $target_tid = (int)($_POST['target_tid'] ?? 0);

    if ($target_tid <= 0) {
        echo json_encode(['error' => 'Invalid target torrent ID']);
        exit;
    }

    if (empty($comment_ids) || !is_array($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    // Защита от инъекций
    $comment_ids = array_map('intval', $comment_ids);
    $ids_str = implode(',', $comment_ids);

    // Получаем исходные torrent IDs перед обновлением
    $res = $db->sql_query("SELECT id, torrent FROM comments WHERE id IN ($ids_str)");
    $moved_count = 0;
    $source_tids = [];

    while ($row = $db->fetch_array($res)) 
	{
        $source_tid = (int)$row['torrent'];
        $source_tids[] = $source_tid;

        $db->sql_query("UPDATE comments SET torrent = $target_tid WHERE id = " . (int)$row['id']);
        $moved_count++;

    }

    // Убираем дубли и добавляем целевой торрент
    $tids = array_unique($source_tids);
    $tids[] = $target_tid;

    // Обновляем счетчики комментариев для всех задействованных торрентов
    foreach ($tids as $tid) 
	{
        $row = $db->fetch_array($db->sql_query("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = $tid"));
        $db->sql_query("UPDATE torrents SET comments = " . (int)$row['cnt'] . " WHERE id = $tid");
    }
	
	
	// ===== Логируем действие с количеством перемещенных комментариев и ссылками на торренты =====
    $source_links = [];
    foreach (array_unique($source_tids) as $tid)
	{
        $source_links[] = '<a href="' . $BASEURL . '/' . get_torrent_link($tid) . '">Torrent #' . $tid . '</a>';
    }
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
	
	
	
	
	

    echo json_encode(['success' => true, 'moved' => $moved_count]);
    exit;
}






// ===== Copy comments =====
if (isset($_GET['action']) && $_GET['action'] === "copy_comments" && $_SERVER['REQUEST_METHOD'] === "POST") 
{
    header('Content-Type: application/json; charset=utf-8');

    // Проверка безопасности
    if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) {
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $comment_ids = $_POST['comment_ids'] ?? [];
    $target_tid = (int)($_POST['target_tid'] ?? 0);

    if ($target_tid <= 0) {
        echo json_encode(['error' => 'Invalid target torrent ID']);
        exit;
    }

    if (empty($comment_ids) || !is_array($comment_ids)) {
        echo json_encode(['error' => 'No comments selected']);
        exit;
    }

    $comment_ids = array_map('intval', $comment_ids);
    $ids_str = implode(',', $comment_ids);

    $res = $db->sql_query("SELECT * FROM comments WHERE id IN ($ids_str)");
    $copied = 0;
    $user_counts = []; // Счётчик скопированных комментариев по пользователям

    while ($row = $db->fetch_array($res)) 
    {
        $insert = [
            'user'      => (int)$row['user'],
            'torrent'   => $target_tid,
            'text'      => $db->escape_string($row['text']),
            'dateline'  => (int)$row['dateline'], // сохраняем оригинальную дату комментария
            'editreason'=> $db->escape_string($row['editreason']),
            'editedby'  => (int)$row['editedby'],
            'editedat'  => (int)$row['editedat'],
            'totalvotes'=> $db->escape_string($row['totalvotes'])
        ];
        $db->insert_query('comments', $insert);
        $copied++;

        // Увеличиваем счётчик комментариев для пользователя
        $uid = (int)$row['user'];
        $user_counts[$uid] = ($user_counts[$uid] ?? 0) + 1;
    }

    // Обновляем счётчики комментариев пользователей
    foreach ($user_counts as $uid => $count) 
	{
        $db->sql_query("UPDATE users SET comms = comms + $count WHERE id = $uid");
    }

    // Обновляем счётчик комментариев у целевого торрента
    $row = $db->fetch_array($db->sql_query("SELECT COUNT(*) AS cnt FROM comments WHERE torrent = $target_tid"));
    $db->sql_query("UPDATE torrents SET comments = " . (int)$row['cnt'] . " WHERE id = $target_tid");

    // Логируем действие с количеством комментариев и ссылкой на торрент
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

    echo json_encode(['success' => true, 'copied' => $copied]);
    exit;
}










    // ===== Preview =====
    if ($action == "preview" && $_SERVER['REQUEST_METHOD'] == "POST") 
    {
        $text = $_POST['text'] ?? '';
        $parsed_text = $parser->parse_message($text, $parser_options);

        // Ограничиваем <img>
        $parsed_text = preg_replace_callback("#<img([^>]*)>#i", function ($matches) 
        {
            return "<img{$matches[1]} style='max-width:200px; height:auto; border-radius:4px;' />";
        }, $parsed_text);

        echo $parsed_text;
        exit;
    }

    // ===== List comments =====
    if ($action == "list") 
    {
        $limit = 20;
        $page = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $offset = ($page - 1) * $limit;

        // Получаем параметры фильтрации
        $username = isset($_GET['username']) ? $db->escape_string(trim($_GET['username'])) : '';
        $torrent = isset($_GET['torrent']) ? $db->escape_string(trim($_GET['torrent'])) : '';
        $date_from = isset($_GET['date_from']) ? strtotime($_GET['date_from']) : 0;
        $date_to = isset($_GET['date_to']) ? strtotime($_GET['date_to']) : 0;

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

        // Запрос для подсчета общего количества с учетом фильтров
        $count_query = "
            SELECT COUNT(*) as cnt
            FROM comments c
            LEFT JOIN users u ON c.user = u.id
            LEFT JOIN torrents t ON c.torrent = t.id
            {$where_sql}
        ";
        $total_res = $db->sql_query($count_query);
        $total_row = $db->fetch_array($total_res);
        $total_comments = $total_row['cnt'];
        $total_pages = ceil($total_comments / $limit);

        // Основной запрос с фильтрами
        $res = $db->sql_query("
            SELECT c.*, u.username, u.usergroup, u.id as uid, t.name AS torrent_name 
            FROM comments c
            LEFT JOIN users u ON c.user = u.id
            LEFT JOIN torrents t ON c.torrent = t.id
            {$where_sql}
            ORDER BY c.dateline DESC
            LIMIT {$offset}, {$limit}
        ");

        if ($total_comments == 0) 
        {
            echo '<div class="alert alert-light border text-center p-4 shadow-sm rounded">No comments found matching your criteria.</div>';
            exit;
        }

        echo '<div class="card shadow-sm border-0 bg-white">
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
            $parsed_text = preg_replace_callback("#<img([^>]*)>#i", function ($matches) {
                return "<img{$matches[1]} style='max-width:100px; height:auto; border-radius:4px;' />";
            }, $parsed_text);
            
            $seo_user = $BASEURL .'/'.get_profile_link($row['uid']);
            $SEOLink = $BASEURL .'/'.get_torrent_link($row['torrent']);
            $pid = $row['id'];
            $tid = $row['torrent'];
            $postlink = get_comment_link($pid, $tid);
            $comment_link = $BASEURL .'/'.$postlink; 

            echo '<tr data-comment-id="'.$row['id'].'">
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

        echo '</tbody></table></div></div>';

        // Пагинация
        $start = $offset + 1;
        $end = min($offset + $limit, $total_comments);

        echo '<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">';
        echo '<div class="text-muted small">Showing <b>'.$start.'</b> – <b>'.$end.'</b> of <b>'.$total_comments.'</b> comments</div>';

        echo '<nav><ul class="pagination pagination-sm mb-0">';
        $prev_disabled = $page <= 1 ? 'disabled' : '';
        $prev_page = max(1, $page - 1);
        echo '<li class="page-item '.$prev_disabled.'">
                <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$prev_page.')">&laquo; Prev</a>
              </li>';

        $range = 2;
        if ($page - $range > 1) 
        {
            echo '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadComments(1)">1</a></li>';
            if ($page - $range > 2) 
            {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++) 
        {
            $active = $i == $page ? 'active' : '';
            echo '<li class="page-item '.$active.'">
                    <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$i.')">'.$i.'</a>
                  </li>';
        }
        if ($page + $range < $total_pages) 
        {
            if ($page + $range < $total_pages - 1) 
            {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="javascript:void(0);" onclick="loadComments('.$total_pages.')">'.$total_pages.'</a></li>';
        }
        $next_disabled = $page >= $total_pages ? 'disabled' : '';
        $next_page = min($total_pages, $page + 1);
        echo '<li class="page-item '.$next_disabled.'">
                <a class="page-link" href="javascript:void(0);" onclick="loadComments('.$next_page.')">Next &raquo;</a>
              </li>';

        echo '</ul></nav>';
        echo '</div>';
        exit;
    }

    // ===== Get comment text for edit modal =====
    if ($action == "edit") 
    {
        $id = (int)$_GET['id'];
        $comment = $db->fetch_array($db->simple_select("comments", "id, text", "id = {$id}"));
        if (!$comment) {
            echo json_encode(["error" => "Comment not found."]);
            exit;
        }
        echo json_encode(["text" => $comment['text']]);
        exit;
    }

    // ===== Save comment =====
    if ($action == "save" && $_SERVER['REQUEST_METHOD'] == "POST") 
    {
        $id = (int)$_POST['id'];
        $update_comment = [
            "text"     => $db->escape_string($_POST['text']),
            "editedat" => TIMENOW,
            "editedby" => (int)$CURUSER["id"]
        ];
        $db->update_query("comments", $update_comment, "id = {$id}");
        echo json_encode(["success" => true]);
        exit;
    }

    // ===== Delete comment =====
    if ($action == "delete" && $_SERVER['REQUEST_METHOD'] == "POST") 
    {
        $id = (int)$_POST['id'];

        // Удаляем прикрепленные файлы
        $files = $db->simple_select("comment_files", "*", "comment_id = {$id}");
        while ($file = $db->fetch_array($files)) 
        {
            if (is_file($file['file_path'])) 
            {
                @unlink($file['file_path']);
            }
        }
        $db->delete_query("comment_files", "comment_id = {$id}");
        $db->delete_query("comments", "id = {$id}");
        echo json_encode(["success" => true]);
        exit;
    }

    // ===== Bulk Delete =====
    if ($action == "bulk_delete" && $_SERVER['REQUEST_METHOD'] == "POST") 
    {
        header('Content-Type: application/json');
        
        if (!isset($_POST['my_post_key']) || $_POST['my_post_key'] !== $mybb->post_code) 
        {
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
        $ids_str = implode(',', $ids);

        // Получаем информацию о комментариях для обновления счетчиков
        $comments_info = [];
        $query = $db->sql_query("SELECT id, user, torrent FROM comments WHERE id IN ({$ids_str})");
        while ($comment = $db->fetch_array($query)) {
            $comments_info[] = $comment;
        }

        // Удаление прикрепленных файлов
        $files = $db->sql_query("SELECT file_path FROM comment_files WHERE comment_id IN ({$ids_str})");
        while ($file = $db->fetch_array($files)) 
        {
            if (!empty($file['file_path']) && file_exists($file['file_path'])) 
            {
                @unlink($file['file_path']);
            }
        }

        // Удаление записей из comment_files и comments
        $db->sql_query("DELETE FROM comment_files WHERE comment_id IN ({$ids_str})");
        $db->sql_query("DELETE FROM comments WHERE id IN ({$ids_str})");
        $deleted = $db->affected_rows();

        // Обновляем счетчики и удаляем KPS поинты для каждого удаленного комментария
        $deleted_count = 0;
        foreach ($comments_info as $comment) {
            $torrent_id = (int)$comment['torrent'];
            $user_id = (int)$comment['user'];
            
            if ($torrent_id > 0) {
                $db->sql_query('UPDATE torrents SET comments = IF(comments>0, comments - 1, 0) WHERE id = ' . $db->escape_string($torrent_id));
            }
            
            if ($user_id > 0) {
                $db->sql_query('UPDATE users SET comms = IF(comms>0, comms - 1, 0) WHERE id = ' . $db->escape_string($user_id));
                // Remove KPS points
                if (function_exists('kps')) {
                    kps('-', $kpscomment, $user_id);
                }
            }
            
            $deleted_count++;
        }

        // Логируем удаление
        $uid = (int)$CURUSER['id'];
        $username = $db->escape_string($CURUSER['username']);
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
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[quote]', '[/quote]')">Quote</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[url]', '[/url]')">URL</button>
          <button class="btn btn-sm btn-light" onclick="wrapBBCode('[img]', '[/img]')">IMG</button>
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">


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

<script>
// Глобальные переменные состояния
const commentManager = {
    currentPage: 1,
    deleteId: 0,
    editId: 0,
    selectedComments: [],
    filters: {
        username: '',
        torrent: '',
        date_from: '',
        date_to: ''
    },
    isLoading: false
};

// Основная функция загрузки комментариев
function loadComments(page = 1) {
    if (commentManager.isLoading) return;
    
    commentManager.currentPage = page;
    commentManager.isLoading = true;
    
    $('#comments-table').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading comments...</p>
        </div>
    `);
    
    const queryParams = new URLSearchParams();
    queryParams.append('act', 'latest_comments');
    queryParams.append('action', 'list');
    queryParams.append('page', page);
    
    Object.entries(commentManager.filters).forEach(([key, value]) => {
        if (value) queryParams.append(key, value);
    });
    
    $.ajax({
        url: `index.php?${queryParams.toString()}`,
        method: 'GET',
        dataType: 'html',
        success: function(data) {
            $('#comments-table').html(data);
            updateUrlWithFilters();
            updateSelection();
            bindBulkDeleteHandler();
        },
        error: function(xhr) {
            let errorMsg = 'Failed to load comments';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.error || errorMsg;
            } catch (e) {
                errorMsg = xhr.statusText;
            }
            $('#comments-table').html(`
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> ${errorMsg}
                </div>
            `);
        },
        complete: function() {
            commentManager.isLoading = false;
        }
    });
}

// Функции для управления выбором
function updateSelection() {
    commentManager.selectedComments = $('.comment-checkbox:checked').map(function() {
        return $(this).val();
    }).get();

    $('#selectedCount').text(commentManager.selectedComments.length);
    $('#selectedCommentsCount').text(commentManager.selectedComments.length);
    $('#bulkDeleteBtn').prop('disabled', commentManager.selectedComments.length === 0);

    $('tr[data-comment-id]').removeClass('table-active');
    commentManager.selectedComments.forEach(id => {
        $(`tr[data-comment-id="${id}"]`).addClass('table-active');
    });
}

// Обработчики для чекбоксов
$(document).on('change', '#selectAll', function() {
    const isChecked = $(this).prop('checked');
    $('.comment-checkbox').prop('checked', isChecked).trigger('change');
});

$(document).on('change', '.comment-checkbox', function() {
    $('#selectAll').prop('checked', 
        $('.comment-checkbox').length === $('.comment-checkbox:checked').length
    );
    updateSelection();
});

$('#selectAllBtn').click(function() {
    $('#selectAll').prop('checked', true).trigger('change');
});

// Функция массового удаления
function bindBulkDeleteHandler() {
    $('#bulkDeleteBtn').off('click').on('click', function () {
        if (commentManager.selectedComments.length === 0) {
            showToast('warning', 'Please select at least one comment');
            return;
        }

        const modal = new bootstrap.Modal(document.getElementById('confirmBulkDeleteModal'));
        const count = commentManager.selectedComments.length;
        const word = count === 1 ? 'comment' : 'comments';
        $('#bulkDeleteMessage').text(`Are you sure you want to delete ${count} selected ${word}?`);
        modal.show();

        $('#confirmBulkDeleteBtn').off('click').on('click', function () {
            const $btn = $('#bulkDeleteBtn');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

            $.ajax({
                url: 'index.php?act=latest_comments&action=bulk_delete',
                method: 'POST',
                data: {
                    ids: commentManager.selectedComments,
                    my_post_key: my_post_key
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showToast('success', `${response.deleted} comments deleted successfully`);
                        loadComments(commentManager.currentPage);
                        bootstrap.Modal.getInstance(document.getElementById('confirmBulkDeleteModal')).hide();
                    } else {
                        showToast('danger', response.error || 'Error deleting comments');
                    }
                },
                error: function (xhr) {
                    showToast('danger', 'Error: ' + (xhr.responseJSON?.error || xhr.statusText));
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="bi bi-trash"></i> Delete Selected');
                }
            });
        });
    });
}




// === Copy Comments ===
$('#confirmCopyBtn').click(function() {
    const target = parseInt($('#copyTargetTorrent').val());
    if (!target || target <= 0) {
        alert("Please enter a valid target torrent ID");
        return;
    }

    const selectedComments = commentManager.selectedComments;
    if (selectedComments.length === 0) {
        alert("Please select at least one comment to copy");
        return;
    }

    if (!confirm(`Copy ${selectedComments.length} selected comment(s) to torrent ID ${target}?`)) return;

    const $btn = $(this);
    $btn.prop('disabled', true).text('Copying...');

    $.ajax({
        url: 'index.php?act=latest_comments&action=copy_comments',
        method: 'POST',
        data: {
            comment_ids: selectedComments,
            target_tid: target,
            my_post_key: my_post_key
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                showToast('success', res.copied + " comment(s) copied successfully!");
                $('#copyCommentsModal').modal('hide');
                $('#copyTargetTorrent').val('');
            } else {
                showToast('danger', "Error: " + res.error);
            }
        },
        error: function(xhr) {
            showToast('danger', "AJAX error: " + xhr.statusText);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Copy Comments');
        }
    });
});

// Обновление количества выбранных комментов в модалке
$('#copyCommentsModal').on('show.bs.modal', function () {
    $('#copySelectedCount').text(commentManager.selectedComments.length);
});





// Функция для объединения комментариев
$('#confirmMergeBtn').click(function() {
    const target = parseInt($('#targetTorrent').val());
    if (!target || target <= 0) {
        alert("Please enter a valid target torrent ID");
        return;
    }

    const selectedComments = commentManager.selectedComments;
    if (selectedComments.length === 0) {
        alert("Please select at least one comment to merge");
        return;
    }

    if (!confirm(`Are you sure you want to move ${selectedComments.length} selected comments to torrent ID ${target}?`)) return;

    const $btn = $(this);
    $btn.prop('disabled', true).text('Merging...');

    $.ajax({
        url: 'index.php?act=latest_comments&action=move_comments',
        method: 'POST',
        data: { 
            comment_ids: selectedComments, 
            target_tid: target,
            	my_post_key: my_post_key		
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                showToast('success', res.moved + " comments moved successfully!");
                $('#mergeCommentsModal').modal('hide');
                loadComments(commentManager.currentPage);
                // Сбрасываем поле
                $('#targetTorrent').val('');
            } else {
                showToast('danger', "Error: " + res.error);
            }
        },
        error: function(xhr) {
            showToast('danger', "AJAX error: " + xhr.statusText);
        },
        complete: function() {
            $btn.prop('disabled', false).text('Merge Comments');
        }
    });
});

// Функция для показа уведомлений
function showToast(type, message) {
    const toastId = `toast-${Date.now()}`;
    const toast = $(`
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `);

    let $container = $('#toastContainer');
    if ($container.length === 0) {
        $container = $('<div id="toastContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>');
        $('body').append($container);
    }

    $container.append(toast);
    const bsToast = new bootstrap.Toast(toast[0], { delay: 5000 });
    bsToast.show();
}

// Остальные функции (loadComments, updateSelection, etc.) остаются без изменений
// ... (добавьте сюда остальные функции из предыдущего кода)










// Инициализация при загрузке страницы
$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('act')) {
        urlParams.set('act', 'latest_comments');
        window.history.replaceState({}, '', `?${urlParams.toString()}`);
    }
    
    initFiltersFromUrl();
    setupFilterHandlers();
    loadComments(commentManager.currentPage);
    
    $("#confirmEditComment").click(saveComment);
    $("#editCommentText").on("input", updatePreview);
});

// Остальные вспомогательные функции
function initFiltersFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.forEach((value, key) => {
        if (key in commentManager.filters) {
            commentManager.filters[key] = value;
            $(`#${key}`).val(value);
        }
    });
    
    const page = urlParams.get('page');
    if (page) commentManager.currentPage = parseInt(page);
}

function setupFilterHandlers() {
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });
    
    $('#resetFilters').on('click', function() {
        resetFilters();
    });
    
    let searchTimer;
    $('#username, #torrent').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            applyFilters();
        }, 500);
    });
    
    $('#date_from, #date_to').on('change', function() {
        applyFilters();
    });
}

function applyFilters() {
    commentManager.filters = {
        username: $('#username').val().trim(),
        torrent: $('#torrent').val().trim(),
        date_from: $('#date_from').val(),
        date_to: $('#date_to').val()
    };
    loadComments(1);
}

function resetFilters() {
    $('#filterForm')[0].reset();
    commentManager.filters = {
        username: '',
        torrent: '',
        date_from: '',
        date_to: ''
    };
    loadComments(1);
}

function updateUrlWithFilters() {
    const params = new URLSearchParams();
    params.append('act', 'latest_comments');
    params.append('page', commentManager.currentPage);
    
    Object.entries(commentManager.filters).forEach(([key, value]) => {
        if (value) params.append(key, value);
    });
    
    window.history.replaceState({}, '', `?${params.toString()}`);
}

// Функции редактирования комментариев
function editComment(id) {
    commentManager.editId = id;
    
    $.ajax({
        url: 'index.php?act=latest_comments&action=edit',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            if (data.error) {
                alert(data.error);
                return;
            }
            $("#editCommentText").val(data.text);
            updatePreview();
            new bootstrap.Modal(document.getElementById('editCommentModal')).show();
        },
        error: function(xhr) {
            alert('Error loading comment: ' + (xhr.responseJSON?.error || xhr.statusText));
        }
    });
}

function updatePreview() {
    $.ajax({
        url: 'index.php?act=latest_comments&action=preview',
        method: 'POST',
        data: { text: $("#editCommentText").val() },
        success: function(html) {
            $("#bbcodePreview").html(html);
        },
        error: function(xhr) {
            $("#bbcodePreview").html('<div class="text-danger">Preview generation failed</div>');
        }
    });
}

function saveComment() {
    $.ajax({
        url: 'index.php?act=latest_comments&action=save',
        method: 'POST',
        data: {
            id: commentManager.editId,
            text: $("#editCommentText").val()
        },
        success: function() {
            bootstrap.Modal.getInstance(document.getElementById('editCommentModal')).hide();
            loadComments(commentManager.currentPage);
        },
        error: function(xhr) {
            alert('Error saving comment: ' + (xhr.responseJSON?.error || xhr.statusText));
        }
    });
}

function deleteComment(id) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    
    $.ajax({
        url: 'index.php?act=latest_comments&action=delete',
        method: 'POST',
        data: { id: id },
        success: function() {
            showToast('success', 'Comment deleted successfully');
            loadComments(commentManager.currentPage);
        },
        error: function(xhr) {
            showToast('danger', 'Error deleting comment');
        }
    });
}

// BBCode редактор
function wrapBBCode(startTag, endTag) {
    const textarea = document.getElementById("editCommentText");
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;

    textarea.value = text.substring(0, start) + startTag + text.substring(start, end) + endTag + text.substring(end);
    textarea.focus();
    updatePreview();
}
</script>





<?php stdfoot(); ?>