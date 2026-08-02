<?php


declare(strict_types=1);


require_once INC_PATH . '/functions_multipage.php';

if (!function_exists('escape_like_pattern')) {
    /**
     * Экранирует только LIKE-wildcard'ы (%, _, \) — для bind-параметров.
     * Кавычки экранировать не нужно, это делает сам биндинг.
     */
    function escape_like_pattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}



// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Initialize input parameters
foreach (['action', 'do', 'module'] as $input) {
    $mybb->input[$input] ??= '';
}

$plugins->run_hooks("admin_forum_attachments_begin");

$uploadspath = TSDIR . '/uploads/';
$uploadspath_abs = mk_path_abs22($uploadspath);
$default_perpage = 20;
$perpage = $mybb->get_input('perpage', MyBB::INPUT_INT) ?: $default_perpage;

// Navigation tabs
$sub_tabs = [
    'find_attachments' => [
        'title' => 'Find Attachments',
        'link' => "index.php?act=attachments",
        'description' => 'Using the attachments search system you can search for specific files users have attached to your forums.'
    ],
    'find_orphans' => [
        'title' => 'Find Orphaned Attachments',
        'link' => "index.php?act=attachments&action=orphans",
        'description' => 'Orphaned attachments are attachments which are for some reason missing in the database or the file system.'
    ],
    'stats' => [
        'title' => 'Attachment Statistics',
        'link' => "index.php?act=attachments&action=stats",
        'description' => 'Below are some general statistics for the attachments currently on your forum'
    ],
    'comment_attachments' => [
        'title' => 'Comment Attachments',
        'link' => "index.php?act=attachments&action=comment_attachments",
        'description' => 'View and manage attachments uploaded to torrent comments.'
    ]
];

/**
 * Handle attachment deletion
 */
if ($mybb->input['action'] === "delete") {
    $plugins->run_hooks("admin_forum_attachments_delete");

    $aids = is_array($mybb->input['aids'] ?? null) 
        ? array_map('intval', $mybb->input['aids'])
        : array_filter([$mybb->get_input('aid', MyBB::INPUT_INT)]);

    $cf_ids = is_array($mybb->input['cf_ids'] ?? null)
        ? array_map('intval', $mybb->input['cf_ids'])
        : [];

    if (empty($aids) && empty($cf_ids)) {
        flash_message('No attachments selected for deletion', 'error');
        admin_redirect("index.php?act=attachments");
    }

    if ($mybb->request_method === "post") {
        require_once INC_PATH . "/functions_upload.php";

        if (!empty($aids)) {
		$aids_ph = implode(',', array_fill(0, count($aids), '?'));
		$query = $db->sql_query_prepared(
    "SELECT aid, pid, posthash, filename, attachname, thumbnail, comment_id FROM attachments WHERE aid IN ({$aids_ph})",
    $aids
);
while ($query && ($attachment = $db->fetch_array($query))) {
    if ((int)($attachment['comment_id'] ?? 0) > 0) {
        // Комментарий — удаляем только этот файл
        $uploadDir = TSDIR . '/uploads/attachments/';
        delete_uploaded_file($uploadDir . $attachment['attachname']);
        if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
            delete_uploaded_file($uploadDir . $attachment['thumbnail']);
        }
        $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [(int)$attachment['aid']]);
        log_admin_action($attachment['aid'], $attachment['filename']);
    } elseif (!(int)$attachment['pid']) {
        // Форум — черновик
        remove_attachment(0, $attachment['posthash'], (int)$attachment['aid']);
        log_admin_action($attachment['aid'], $attachment['filename']);
    } else {
        // Форум — с постом
        remove_attachment((int)$attachment['pid'], '', (int)$attachment['aid']);
        log_admin_action($attachment['aid'], $attachment['filename'], $attachment['pid']);
    }
}
        }

        // comment_files — отдельное хранилище (.attach-файлы), своя таблица и свои колонки
        if (!empty($cf_ids)) {
            $cf_ph = implode(',', array_fill(0, count($cf_ids), '?'));
            $query = $db->sql_query_prepared("SELECT id, file_name, file_path FROM comment_files WHERE id IN ({$cf_ph})", $cf_ids);
            while ($query && ($file = $db->fetch_array($query))) {
                if (!empty($file['file_path']) && is_file($file['file_path'])) {
                    @unlink($file['file_path']);
                }
                $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [(int)$file['id']]);
                log_admin_action($file['id'], $file['file_name']);
            }
        }


        $plugins->run_hooks("admin_forum_attachments_delete_commit");
        flash_message('Selected attachments have been deleted successfully', 'success');
        admin_redirect("index.php?act=attachments");
    } else {
        $aids_param = implode('&amp;aids[]=', $aids);
        $cf_ids_param = !empty($cf_ids) ? '&amp;cf_ids[]=' . implode('&amp;cf_ids[]=', $cf_ids) : '';
        echo "
        <div class='modal fade' id='confirmModal' tabindex='-1'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title'>Confirm Deletion</h5>
                    </div>
                    <div class='modal-body'>
                        <p>Are you sure you want to delete the selected attachments?</p>
                    </div>
                    <div class='modal-footer'>
                        <a href='index.php?act=attachments' class='btn btn-secondary'>Cancel</a>
                        <a href='index.php?act=attachments&amp;action=delete&amp;aids={$aids_param}{$cf_ids_param}&amp;my_post_key={$mybb->post_code}' class='btn btn-danger'>Delete</a>
                    </div>
                </div>
            </div>
        </div>
        <script>new bootstrap.Modal(document.getElementById('confirmModal')).show();</script>";
        exit;
    }
}

/**
 * Display attachment statistics
 */
if ($mybb->input['action'] === "stats") {
    $plugins->run_hooks("admin_forum_attachments_stats");

    $query = $db->sql_query_prepared("SELECT COUNT(*) AS total_attachments, SUM(filesize) as disk_usage, SUM(downloads*filesize) as bandwidthused FROM attachments WHERE visible='1'");
    $attachment_stats = $query ? $db->fetch_array($query) : null;

    // Convert string values to integers to avoid type errors
    $total_attachments = (int)($attachment_stats['total_attachments'] ?? 0);
    $disk_usage = (float)($attachment_stats['disk_usage'] ?? 0);
    $bandwidthused = (float)($attachment_stats['bandwidthused'] ?? 0);
    
    $average_size = $total_attachments > 0 ? $disk_usage / $total_attachments : 0;

    render_header('Attachments - Attachment Statistics');
    output_nav_tabs($sub_tabs, 'stats');

    if ($total_attachments === 0) {
        echo '
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 mb-3" style="width:72px;height:72px;">
                        <i class="fas fa-chart-pie text-primary" style="font-size:28px;"></i>
                    </div>
                    <h5 class="mb-1">No Attachments Yet</h5>
                    <p class="text-muted mb-0">There are no attachments on your forum yet. Once an attachment is posted you will be able to access this section.</p>
                </div>
            </div>
        </div>';
        stdfoot();
        exit;
    }

    // General Statistics Card
    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>General Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-primary">' . ts_nf($total_attachments) . '</div>
                            <div class="stat-label">Uploaded Attachments</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-success">' . mksize($disk_usage) . '</div>
                            <div class="stat-label">Disk Space Used</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-info">' . mksize($bandwidthused) . '</div>
                            <div class="stat-label">Bandwidth Usage</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-value text-warning">' . mksize($average_size) . '</div>
                            <div class="stat-label">Average Attachment Size</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    // Top Attachments Sections
    render_top_attachments_section('Most Popular Attachments', 'downloads DESC', 'text-success', 'fa-trophy');
    render_top_attachments_section('Largest Attachments', 'filesize DESC', 'text-danger', 'fa-weight-hanging');
    render_top_users_section();

    stdfoot();
}

/**
 * Handle orphaned attachments deletion
 */
if ($mybb->input['action'] === "delete_orphans" && $mybb->request_method === "post") {
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message('Security check failed. Please try again.', 'error');
        admin_redirect('index.php?act=attachments&action=orphans');
    }

    $plugins->run_hooks("admin_forum_attachments_delete_orphans");

    $success_count = $error_count = 0;

    // Delete orphaned files
    if (is_array($mybb->input['orphaned_files'] ?? null)) {
        foreach ($mybb->input['orphaned_files'] as $file) {
            $file = str_replace('..', '', $file);
            $path = $uploadspath_abs . "/" . $file;
            $real_path = realpath($path);

            if ($real_path === false || !str_starts_with(str_replace('\\', '/', $real_path), str_replace('\\', '/', realpath(TSDIR)) . '/') || $real_path === realpath(TSDIR . 'install/lock')) {
                $error_count++;
                continue;
            }

            if (!@unlink($uploadspath_abs . "/" . $file)) {
                $error_count++;
            } else {
                $success_count++;
            }
        }
    }

    // Delete orphaned database entries — ветвим по comment_id/pid, как в основном
    // action=delete выше: комментарийные вложения (в т.ч. просроченные черновики)
    // чистим напрямую (файл + thumbnail + строка), форумные — через remove_attachment()
    if (is_array($mybb->input['orphaned_attachments'] ?? null)) {
        $orphaned_aids = array_map('intval', $mybb->input['orphaned_attachments']);
        require_once INC_PATH . "/functions_upload.php";

        $orph_ph = implode(',', array_fill(0, count($orphaned_aids), '?'));
        $query = $db->sql_query_prepared("SELECT aid,pid,posthash,comment_id,attachname,thumbnail FROM attachments WHERE aid IN ({$orph_ph})", $orphaned_aids);
        while ($query && ($attachment = $db->fetch_array($query))) {
            $is_comment_style = (int)($attachment['comment_id'] ?? 0) > 0
                || (!str_contains((string)$attachment['attachname'], '/') && (int)$attachment['pid'] === 0);

            if ($is_comment_style) {
                // Комментарийное вложение ИЛИ его черновик без родителя —
                // плоское хранилище uploads/attachments/, без префикса месяца.
                // Отличаем черновик-форум от черновика-комментария по формату
                // attachname: форумные всегда содержат "/" (префикс месяца).
                $uploadDir = TSDIR . '/uploads/attachments/';
                if (!empty($attachment['attachname'])) {
                    @unlink($uploadDir . $attachment['attachname']);
                }
                if (!empty($attachment['thumbnail']) && $attachment['thumbnail'] !== 'SMALL') {
                    @unlink($uploadDir . $attachment['thumbnail']);
                }
                $db->sql_query_prepared("DELETE FROM attachments WHERE aid = ?", [(int)$attachment['aid']]);
            } else {
                // Форумное вложение (с постом или черновик с posthash) —
                // remove_attachment() сам корректно резолвит путь с префиксом месяца
                remove_attachment((int)$attachment['pid'], (string)$attachment['posthash'], (int)$attachment['aid']);
            }
            $success_count++;
        }
    }

    // Delete orphaned comment_files entries (включая просроченные черновики) —
    // раньше этот блок отсутствовал, cf_ids[] с формы сканирования тихо
    // игнорировались.
    if (is_array($mybb->input['cf_ids'] ?? null)) {
        $cf_ids = array_map('intval', $mybb->input['cf_ids']);
        $cf_ph  = implode(',', array_fill(0, count($cf_ids), '?'));
        $query  = $db->sql_query_prepared("SELECT id, file_name, file_path FROM comment_files WHERE id IN ({$cf_ph})", $cf_ids);
        while ($query && ($file = $db->fetch_array($query))) {
            if (!empty($file['file_path']) && is_file($file['file_path'])) {
                @unlink($file['file_path']);
            }
            $db->sql_query_prepared("DELETE FROM comment_files WHERE id = ?", [(int)$file['id']]);
            log_admin_action($file['id'], $file['file_name']);
            $success_count++;
        }
    }

    $plugins->run_hooks("admin_forum_attachments_delete_orphans_commit");

    // Prepare flash message
    if ($error_count > 0 && $success_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)<br />{$success_count} attachment(s) removed successfully";
        $status = 'error';
    } elseif ($error_count > 0) {
        $message = "Unable to remove {$error_count} attachment(s)";
        $status = 'error';
    } else {
        $message = "The selected orphaned attachment(s) have been deleted successfully";
        $status = 'success';
    }

    flash_message($message, $status);
    admin_redirect('index.php?act=attachments');
}

/**
 * Handle orphaned attachments
 */
if ($mybb->input['action'] === "orphans") {
    $plugins->run_hooks("admin_forum_attachments_orphans");
    handle_orphans_scan();
}

/**
 * Comment attachments page
 */
if ($mybb->input['action'] === 'comment_attachments') {
    handle_comment_attachments();
}

/**
 * Main attachments search page
 */
if (!$mybb->input['action']) {
    $plugins->run_hooks("admin_forum_attachments_start");

    if ($mybb->request_method === "post" || $mybb->get_input('results', MyBB::INPUT_INT) === 1) {
        handle_attachments_search();
    } else {
        render_search_form();
    }
}

/**
 * Handle comment attachments view
 */
function handle_comment_attachments(): void {
    global $mybb, $db, $perpage, $BASEURL;

    // Фильтры собираются отдельно под каждую таблицу — колонки называются по-разному
    $filename_val = $mybb->get_input('filename') ? escape_like_pattern($mybb->input['filename']) : '';
    $mimetype_val = $mybb->get_input('mimetype') ? escape_like_pattern($mybb->input['mimetype']) : '';
    $user_id_filter = null;
    if (!empty($mybb->input['username'])) {
        $found_user = get_user_by_username($mybb->input['username']);
        if ($found_user) {
            $user_id_filter = (int)$found_user['id'];
        }
    }

    $att_filter = 'comment_id > 0';
    $cf_filter  = 'comment_id IS NOT NULL';
    $union_params = [];
    $att_params = [];
    $cf_params  = [];

    if ($filename_val !== '') {
        $att_filter .= " AND filename LIKE ?";
        $cf_filter  .= " AND file_name LIKE ?";
        $att_params[] = "%{$filename_val}%";
        $cf_params[]  = "%{$filename_val}%";
    }
    if ($mimetype_val !== '') {
        $att_filter .= " AND filetype LIKE ?";
        $cf_filter  .= " AND file_type LIKE ?";
        $att_params[] = "%{$mimetype_val}%";
        $cf_params[]  = "%{$mimetype_val}%";
    }
    if ($user_id_filter !== null) {
        $att_filter .= " AND uid = ?";
        $cf_filter  .= " AND user_id = ?";
        $att_params[] = $user_id_filter;
        $cf_params[]  = $user_id_filter;
    }
    $union_params = [...$att_params, ...$cf_params];

    // UNION ALL нормализует колонки обеих таблиц под общие имена, чтобы можно
    // было сортировать/пагинировать результат как единый набор
    $union_sql = "
        SELECT aid AS id, filename, filesize, filetype, thumbnail, uid,
               comment_id, dateuploaded AS dateuploaded_ts, downloads,
               NULL AS file_url_override, attachname, 'attachments' AS source
        FROM attachments
        WHERE {$att_filter}

        UNION ALL

        SELECT id, file_name AS filename, file_size AS filesize, file_type AS filetype, NULL AS thumbnail, user_id AS uid,
               comment_id, UNIX_TIMESTAMP(uploaded_at) AS dateuploaded_ts, 0 AS downloads,
               file_url AS file_url_override, file_name AS attachname, 'comment_files' AS source
        FROM comment_files
        WHERE {$cf_filter}
    ";

    // Count
    $query = $db->sql_query_prepared("SELECT COUNT(*) AS num_results FROM ({$union_sql}) x", $union_params);
    $num_results = $query ? (int)$db->fetch_field($query, 'num_results') : 0;

    // Aggregate stats (across full comment-attachment set из обеих таблиц, без учёта фильтров поиска)
    $stats_query = $db->sql_query_prepared("
        SELECT COUNT(*) AS total_count, SUM(filesize) AS total_size, SUM(downloads) AS total_downloads, AVG(filesize) AS avg_size
        FROM (
            SELECT filesize, downloads FROM attachments WHERE comment_id > 0
            UNION ALL
            SELECT file_size AS filesize, 0 AS downloads FROM comment_files WHERE comment_id IS NOT NULL
        ) s
    ");
    $stats          = $stats_query ? $db->fetch_array($stats_query) : null;
    $stat_count     = (int)($stats['total_count'] ?? 0);
    $stat_size      = mksize((float)($stats['total_size'] ?? 0));
    $stat_downloads = (int)($stats['total_downloads'] ?? 0);
    $stat_avg       = mksize((float)($stats['avg_size'] ?? 0));

    render_header('Attachments - Comment Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'comment_attachments');

    // Stats header + row (usercp-style)
    echo '
    <div class="container mt-4">
        <div class="att-page-header">
            <h2><i class="fas fa-paperclip"></i> Comment Attachments</h2>
            <p>' . ts_nf($stat_count) . ' attachments &bull; ' . $stat_size . ' used &bull; includes both attachments and comment_files storage</p>
        </div>
        <div class="att-stats-row">
            <div class="att-stat-card">
                <div class="att-stat-icon is-primary"><i class="fas fa-file"></i></div>
                <div>
                    <div class="att-stat-value">' . ts_nf($stat_count) . '</div>
                    <div class="att-stat-label">Comment Attachments</div>
                </div>
            </div>
            <div class="att-stat-card">
                <div class="att-stat-icon is-success"><i class="fas fa-hdd"></i></div>
                <div>
                    <div class="att-stat-value">' . $stat_size . '</div>
                    <div class="att-stat-label">Space Used</div>
                </div>
            </div>
            <div class="att-stat-card">
                <div class="att-stat-icon is-info"><i class="fas fa-download"></i></div>
                <div>
                    <div class="att-stat-value">' . ts_nf($stat_downloads) . '</div>
                    <div class="att-stat-label">Total Downloads</div>
                </div>
            </div>
            <div class="att-stat-card">
                <div class="att-stat-icon is-warning"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="att-stat-value">' . $stat_avg . '</div>
                    <div class="att-stat-label">Average Size</div>
                </div>
            </div>
        </div>
    </div>';

    // Search form
    echo '
    <div class="container mt-4 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Comment Attachments</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="act" value="attachments">
                    <input type="hidden" name="action" value="comment_attachments">
                    <div class="col-md-4">
                        <label class="form-label">Filename</label>
                        <input type="text" class="form-control" name="filename" value="' . htmlspecialchars_uni($mybb->input['filename'] ?? '') . '">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" value="' . htmlspecialchars_uni($mybb->input['username'] ?? '') . '">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">MIME Type</label>
                        <input type="text" class="form-control" name="mimetype" placeholder="e.g. image/" value="' . htmlspecialchars_uni($mybb->input['mimetype'] ?? '') . '">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                        <a href="index.php?act=attachments&action=comment_attachments" class="btn btn-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>';

    if ($num_results === 0) {
        echo '
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 mb-3" style="width:72px;height:72px;">
                        <i class="fas fa-magnifying-glass text-warning" style="font-size:28px;"></i>
                    </div>
                    <h5 class="mb-1">No Comment Attachments Found</h5>
                    <p class="text-muted mb-0">No matching results — try adjusting your search or filters.</p>
                </div>
            </div>
        </div>';
        stdfoot();
        exit;
    }

    $page  = $mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
    $start = ($page - 1) * $perpage;

    echo '
    <form action="index.php?act=attachments&amp;action=delete" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '">
        <div class="container">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Comment Attachments — ' . ts_nf($num_results) . ' found</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50"><label class="form-switch-custom"><input type="checkbox" class="checkall" onclick="checkAll(this)"><span class="switch-slider"></span></label></th>
                                    <th>File</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-center">Storage</th>
                                    <th class="text-center">Uploaded By</th>
                                    <th class="text-center">Comment</th>
                                    <th class="text-center">Date</th>
                                </tr>
                            </thead>
                            <tbody>';

    $query = $db->sql_query_prepared("
        SELECT x.*, u.username AS user_username, u.id AS user_pk, u.enabled, u.donor, u.warned, u.leechwarn, u.usergroup, u.canupload, u.candownload, u.cancomment,
               c.torrent AS torrent_id, t.name AS torrent_name
        FROM ({$union_sql}) x
        LEFT JOIN users u ON (u.id = x.uid)
        LEFT JOIN comments c ON (c.id = x.comment_id)
        LEFT JOIN torrents t ON (t.id = c.torrent)
        ORDER BY x.dateuploaded_ts DESC
        LIMIT ?, ?
    ", [...$union_params, $start, $perpage]);

    while ($query && ($att = $db->fetch_array($query))) {
        $is_cf     = $att['source'] === 'comment_files';
        $date      = $att['dateuploaded_ts'] > 0 ? my_datee('relative', $att['dateuploaded_ts']) : 'Unknown';
        $username  = $att['user_username'] ?: 'Guest';
        $group     = $att['usergroup'] ?: 'Guest';

        $profile_url = get_profile_link((int)$att['user_pk']);
        $display_name = format_name($username, $group);
        $user_link = '<a href="' . $BASEURL . '/' . $profile_url . '">' . $display_name . '</a>' . get_user_icons($att);

        $size      = mksize((float)$att['filesize']);
        $icon      = get_attachment_icon(get_extension($att['filename']));

        // comment_files хранит готовый file_url, attachments — собираем путь сами
        $att_url   = $is_cf
            ? htmlspecialchars_uni($att['file_url_override'])
            : '../uploads/attachments/' . rawurlencode($att['attachname']);
        $file_link = '<a href="' . $att_url . '" target="_blank">' . htmlspecialchars_uni($att['filename']) . '</a>';
        $mime      = '<span class="badge bg-light text-dark border">' . htmlspecialchars_uni((string)$att['filetype']) . '</span>';
        $storage_badge = $is_cf
            ? '<span class="badge bg-secondary" title="comment_files table">.attach</span>'
            : '<span class="badge bg-info text-dark" title="attachments table">standard</span>';

        // Image preview thumbnail (только для attachments — у comment_files нет thumbnail-колонки)
        $is_image = str_starts_with((string)($att['filetype'] ?? ''), 'image/');
        if ($is_image && !$is_cf) {
            $thumb_url = (!empty($att['thumbnail']) && $att['thumbnail'] !== 'SMALL')
                ? '../uploads/attachments/' . rawurlencode($att['thumbnail'])
                : $att_url;
            $file_icon_html = '<a href="' . $att_url . '" target="_blank" class="ca-thumb-link">
                <img src="' . $thumb_url . '" class="ca-thumb" alt="" loading="lazy"
                     onerror="this.closest(\'.ca-thumb-link\').outerHTML=\'' . addslashes($icon) . '\'">
            </a>';
        } elseif ($is_image) {
            $file_icon_html = '<a href="' . $att_url . '" target="_blank" class="ca-thumb-link">
                <img src="' . $att_url . '" class="ca-thumb" alt="" loading="lazy"
                     onerror="this.closest(\'.ca-thumb-link\').outerHTML=\'' . addslashes($icon) . '\'">
            </a>';
        } else {
            $file_icon_html = $icon;
        }

        // Comment link
        if ($att['comment_id'] && $att['torrent_id']) {
            $comment_link = '<a href="../details.php?id=' . (int)$att['torrent_id'] . '#pid' . (int)$att['comment_id'] . '" target="_blank" class="text-decoration-none">'
                . htmlspecialchars_uni($att['torrent_name'] ?? 'Torrent #' . $att['torrent_id'])
                . '</a>';
        } else {
            $comment_link = '<span class="text-muted">—</span>';
        }

        $checkbox_name = $is_cf ? 'cf_ids[]' : 'aids[]';
        $checkbox_value = $is_cf ? (int)$att['id'] : (int)$att['id'];

        echo '
                                <tr>
                                    <td><label class="form-switch-custom"><input type="checkbox" name="' . $checkbox_name . '" value="' . $checkbox_value . '"><span class="switch-slider"></span></label></td>
                                    <td>' . $file_icon_html . ' ' . $file_link . '</td>
                                    <td class="text-center"><span class="badge bg-secondary">' . $size . '</span></td>
                                    <td class="text-center">' . $mime . '</td>
                                    <td class="text-center">' . $storage_badge . '</td>
                                    <td class="text-center">' . $user_link . '</td>
                                    <td class="text-center">' . $comment_link . '</td>
                                    <td class="text-center"><small class="text-muted">' . $date . '</small></td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Selected</button>
                </div>
            </div>
        </div>
    </form>';

    if ($num_results > $perpage) {
        $search_url = "index.php?act=attachments&amp;action=comment_attachments";
        foreach (['filename','username','mimetype'] as $p) {
            if ($mybb->get_input($p)) $search_url .= "&amp;{$p}=" . urlencode($mybb->input[$p]);
        }
        $pagination = multipage($num_results, $perpage, $page, $search_url . "&amp;page={page}");
        echo '<div class="container mt-3"><div class="card"><div class="card-body text-center">' . $pagination . '</div></div></div>';
    }

    stdfoot();
    exit;
}

/**
 * Handle attachments search results
 */
function handle_attachments_search(): void {
    global $mybb, $db, $perpage;
    
    $search_sql = '1=1';
    $errors = [];

    // Build search URL for pagination
    $search_url = "index.php?act=attachments&amp;results=1";
    
    // Add search parameters to URL
    $search_params = [
        'filename', 'mimetype', 'username', 'user_types', 'sortby', 'order', 'perpage'
    ];
    
    foreach ($search_params as $param) {
        if ($mybb->get_input($param)) {
            $search_url .= "&amp;{$param}=" . urlencode($mybb->input[$param]);
        }
    }
    
    // Add forum parameters
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        foreach ($mybb->input['forum'] as $fid) {
            $search_url .= "&amp;forum[]=" . (int)$fid;
        }
    }

    // Build search conditions
    $search_params = [];
    if ($mybb->get_input('filename')) {
        $search_sql .= " AND a.filename LIKE ?";
        $search_params[] = '%' . escape_like_pattern($mybb->input['filename']) . '%';
    }
    
    if ($mybb->get_input('mimetype')) {
        $search_sql .= " AND a.filetype LIKE ?";
        $search_params[] = '%' . escape_like_pattern($mybb->input['mimetype']) . '%';
    }
    
    // Username search
    if (!empty($mybb->input['username'])) {
        $user = get_user_by_username($mybb->input['username']);
        if ($user) {
            $search_sql .= " AND a.uid=?";
            $search_params[] = $user['id'];
        } else {
            $search_sql .= " AND p.username LIKE ?";
            $search_params[] = '%' . escape_like_pattern($mybb->input['username']) . '%';
        }
    }

    // Forum search
    if (!empty($mybb->input['forum']) && is_array($mybb->input['forum'])) {
        $forum_ids = array_map('intval', $mybb->input['forum']);
        $search_sql .= " AND p.fid IN (" . implode(",", $forum_ids) . ")";
    }

    // User type filter
    $user_types = $mybb->get_input('user_types', MyBB::INPUT_INT);
    if ($user_types === 1) {
        $search_sql .= " AND a.uid > 0";
    } elseif ($user_types === -1) {
        $search_sql .= " AND a.uid = 0";
    }

    // Check for results
    $query = $db->sql_query_prepared("
        SELECT COUNT(a.aid) AS num_results
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        WHERE {$search_sql}
    ", $search_params);
    $num_results = $query ? (int)$db->fetch_field($query, "num_results") : 0;

    if (!$num_results) {
        $errors[] = 'No attachments were found with the specified search criteria';
    }

    if (!empty($errors)) {
        render_search_form($errors);
        return;
    }

    // Display results
    render_header('Attachments - Search Results');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');
    
    $page = $mybb->get_input('page', MyBB::INPUT_INT) ?: 1;
    $start = ($page - 1) * $perpage;
    
    $sort_field = match($mybb->input['sortby'] ?? '') {
        'filesize' => 'a.filesize',
        'downloads' => 'a.downloads',
        'dateuploaded' => 'a.dateuploaded',
        'username' => 'u.username',
        default => 'a.filename'
    };
    
    $order = ($mybb->input['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    echo '
    <form action="index.php?act=attachments&amp;action=delete" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Results - ' . ts_nf($num_results) . ' attachments found</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th width="30"><input type="checkbox" class="form-check-input checkall" onclick="checkAll(this)"></th>
                                    <th>Attachment</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-center">Posted By</th>
                                    <th class="text-center">Thread</th>
                                    <th class="text-center">Downloads</th>
                                    <th class="text-center">Date Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>';

    $query = $db->sql_query_prepared("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        WHERE {$search_sql}
        ORDER BY {$sort_field} {$order}
        LIMIT ?, ?
    ", [...$search_params, $start, $perpage]);

    while ($query && ($attachment = $db->fetch_array($query))) {
        $date = $attachment['dateuploaded'] > 0 ? my_datee('relative', $attachment['dateuploaded']) : 'Unknown';
        $username = $attachment['user_username'] ?: $attachment['username'];
        $user_link = $attachment['uid'] ? build_profile_link(htmlspecialchars_uni($username), $attachment['uid'], "_blank") : htmlspecialchars_uni($username);
        $size = mksize((float)$attachment['filesize']);
        $downloads = ts_nf($attachment['downloads']);
        $attachment_icon = get_attachment_icon(get_extension($attachment['filename']));
        $attachment_link = '<a href="../attachment.php?aid=' . $attachment['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni($attachment['filename']) . '</a>';
        $thread_link = $attachment['tid'] ? "<a href=\"../" . get_post_link($attachment['pid']) . "\" target=\"_blank\" class=\"text-decoration-none\">" . htmlspecialchars_uni($attachment['subject'] ?? 'No Subject') . "</a>" : 'N/A';

        echo '
                                <tr>
                                    <td><input type="checkbox" name="aids[]" value="' . $attachment['aid'] . '" class="form-check-input"></td>
                                    <td>' . $attachment_icon . ' ' . $attachment_link . '</td>
                                    <td class="text-center"><span class="badge bg-secondary">' . $size . '</span></td>
                                    <td class="text-center">' . $user_link . '</td>
                                    <td class="text-center">' . $thread_link . '</td>
                                    <td class="text-center"><span class="badge bg-info">' . $downloads . '</span></td>
                                    <td class="text-center"><small class="text-muted">' . $date . '</small></td>
                                </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Selected Attachments</button>
                </div>
            </div>
        </div>
    </form>';

    // Pagination using your multipage function
    if ($num_results > $perpage) {
        $pagination = multipage($num_results, $perpage, $page, $search_url . "&amp;page={page}");
        echo '
        <div class="container mt-3">
            <div class="card">
                <div class="card-body text-center">
                    ' . $pagination . '
                </div>
            </div>
        </div>';
    }

    stdfoot();
}

/**
 * Render search form
 */
function render_search_form(array $errors = []): void {
    global $mybb, $db, $perpage;

    render_header('Attachments - Find Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_attachments');

    if (!empty($errors)) {
        output_inline_error($errors);
    }

    $sort_options = [
        "filename" => 'File Name',
        "filesize" => 'File Size', 
        "downloads" => 'Download Count',
        "dateuploaded" => 'Date Uploaded',
        "username" => 'Post Username'
    ];

    $user_types = [
        '0' => 'User or Guest', 
        '1' => 'Users Only', 
        '-1' => 'Guests Only'
    ];

    echo '
    <form action="index.php?act=attachments" method="post">
        <input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />
        
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i>Find Attachments</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="filename" class="form-label">File name contains</label>
                            <input type="text" name="filename" value="' . htmlspecialchars($mybb->input['filename'] ?? '') . '" class="form-control" id="filename">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="mimetype" class="form-label">File type contains</label>
                            <input type="text" name="mimetype" value="' . htmlspecialchars($mybb->input['mimetype'] ?? '') . '" class="form-control" id="mimetype">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Posters username is</label>
                            <input type="text" name="username" value="' . htmlspecialchars($mybb->input['username'] ?? '') . '" class="form-control" id="username">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="user_types" class="form-label">Poster is</label>
                            ' . generate_select_box('user_types', $user_types, $mybb->input['user_types'] ?? '', ['id' => 'user_types', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="forum" class="form-label">Forum</label>
                            ' . generate_forum_select('forum[]', $mybb->input['forum'] ?? '', ['multiple' => true, 'size' => 5, 'id' => 'forum', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="perpage" class="form-label">Results per page</label>
                            <input type="number" name="perpage" value="' . $perpage . '" class="form-control" id="perpage" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sortby" class="form-label">Sort results by</label>
                            ' . generate_select_box('sortby', $sort_options, $mybb->input['sortby'] ?? '', ['id' => 'sortby', 'class' => 'form-select']) . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="order" class="form-label">Sort order</label>
                            ' . generate_select_box('order', ['asc' => 'Ascending', 'desc' => 'Descending'], $mybb->input['order'] ?? '', ['id' => 'order', 'class' => 'form-select']) . '
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-search me-2"></i>Find Attachments</button>
                </div>
            </div>
        </div>
    </form>';

    stdfoot();
}





/**
 * Render top attachments section
 */
function render_top_attachments_section(string $title, string $order, string $text_color, string $icon): void {
    global $db;

    $query = $db->sql_query_prepared("
        SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
        FROM attachments a
        LEFT JOIN posts p ON (p.pid=a.pid)
        LEFT JOIN threads t ON (t.tid=p.tid)
        LEFT JOIN users u ON (u.id=a.uid)
        ORDER BY {$order}
        LIMIT 5
    ");

    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header ' . $text_color . '">
                <h5 class="mb-0"><i class="fas ' . $icon . ' me-2"></i>' . $title . '</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Attachment</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Posted By</th>
                                <th class="text-center">Thread</th>
                                <th class="text-center">Downloads</th>
                                <th class="text-center">Date Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>';

    while ($query && ($attachment = $db->fetch_array($query))) {
        $date = $attachment['dateuploaded'] > 0 ? my_datee('relative', $attachment['dateuploaded']) : 'Unknown';
        $username = $attachment['user_username'] ?: $attachment['username'];
        
		
		
		$user_link = !empty($attachment['uid']) 
    ? build_profile_link(htmlspecialchars_uni($username), $attachment['uid'], "_blank")
    : htmlspecialchars_uni($username); // просто текст
		
		
		
		
		
        $size = mksize($attachment['filesize']);
        $downloads = ts_nf($attachment['downloads']);
        $attachment_icon = get_attachment_icon(get_extension($attachment['filename']));
        $attachment_link = '<a href="../attachment.php?aid=' . $attachment['aid'] . '" target="_blank" class="text-decoration-none">' . htmlspecialchars_uni($attachment['filename']) . '</a>';
        $thread_link = "<a href=\"../" . get_post_link($attachment['pid']) . "\" target=\"_blank\" class=\"text-decoration-none\">" . htmlspecialchars_uni($attachment['subject'] ?? 'No Subject') . "</a>";

        echo '
                            <tr>
                                <td>' . $attachment_icon . ' ' . $attachment_link . '</td>
                                <td class="text-center"><span class="badge bg-secondary">' . $size . '</span></td>
                                <td class="text-center">' . $user_link . '</td>
                                <td class="text-center">' . $thread_link . '</td>
                                <td class="text-center"><span class="badge bg-info">' . $downloads . '</span></td>
                                <td class="text-center"><small class="text-muted">' . $date . '</small></td>
                            </tr>';
    }

    echo '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render top users section
 */
function render_top_users_section(): void {
    global $db;

    echo '
    <div class="container mt-4">
        <div class="card shadow-sm">
            <div class="card-header text-warning">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Users Using the Most Disk Space</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th class="text-center">Total Disk Usage</th>
                            </tr>
                        </thead>
                        <tbody>';

    $query = $db->sql_query_prepared("
        SELECT a.uid, u.username, SUM(a.filesize) as totalsize
        FROM attachments a
        LEFT JOIN users u ON (u.id=a.uid)
        GROUP BY a.uid, u.username
        ORDER BY totalsize DESC
        LIMIT 5
    ");

    while ($query && ($user = $db->fetch_array($query))) {
        $username = $user['username'] ?: 'N/A';
        $user_link = $user['uid'] ? build_profile_link(htmlspecialchars_uni($username), $user['uid'], "_blank") : htmlspecialchars_uni($username);
        $size_link = "<a href=\"index.php?act=attachments&amp;results=1&amp;username=" . urlencode($username) . "\" target=\"_blank\" class=\"text-decoration-none\">" . mksize($user['totalsize']) . "</a>";

        echo '
                            <tr>
                                <td>' . $user_link . '</td>
                                <td class="text-center"><span class="badge bg-warning text-dark">' . $size_link . '</span></td>
                            </tr>';
    }

    echo '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Render page header with styles
 */
function render_header(string $title): void {
    stdhead($title);
    
    echo '
    <!-- Bootstrap 5 CSS -->
    
    <!-- Font Awesome -->
   
    <!-- Custom Styles -->
    <style>
        .stat-card {
            text-align: center;
            padding: 1rem;
            border-radius: 0.5rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .card-header {
            border-bottom: 2px solid rgba(0,0,0,.125);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            background-color: #f8f9fa;
        }
        .badge {
            font-size: 0.75em;
        }
        .checkall {
            cursor: pointer;
        }
        .ca-thumb-link {
            display: inline-block;
            width: 36px;
            height: 36px;
            vertical-align: middle;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            margin-right: 4px;
        }
        .ca-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform .2s ease;
        }
        .ca-thumb-link:hover .ca-thumb {
            transform: scale(1.15);
        }

        /* Page header (gradient) */
        .att-page-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a4fc4 100%);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            color: #fff;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 24px rgba(13,110,253,.25);
        }
        .att-page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .att-page-header h2 i {
            margin-right: .5rem;
            opacity: .9;
        }
        .att-page-header p {
            margin: .35rem 0 0;
            opacity: .85;
            font-size: .9rem;
        }

        /* Stats row */
        .att-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 1.5rem;
        }
        .att-stat-card {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 14px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .att-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0,0,0,.07);
        }
        .att-stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .att-stat-icon.is-primary { background: rgba(13,110,253,.1); color: #0d6efd; }
        .att-stat-icon.is-success { background: rgba(25,135,84,.1);  color: #198754; }
        .att-stat-icon.is-info    { background: rgba(13,202,240,.12); color: #0aa2c0; }
        .att-stat-icon.is-warning { background: rgba(255,193,7,.12);  color: #cc9a06; }
        .att-stat-icon.is-danger  { background: rgba(220,53,69,.1);   color: #dc3545; }
        .att-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.1;
            color: #212529;
        }
        .att-stat-label {
            font-size: .78rem;
            color: #8a8f98;
            margin-top: 2px;
        }

        @media (max-width: 768px) {
            .att-page-header { padding: 1.25rem 1.5rem; }
        }

        /* Toggle switch (replaces checkboxes) */
        .form-switch-custom {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 24px;
            margin: 0;
            cursor: pointer;
        }
        .form-switch-custom input { opacity: 0; width: 0; height: 0; }
        .switch-slider {
            position: absolute; inset: 0;
            background-color: #dadfe4;
            transition: .25s;
            border-radius: 24px;
        }
        .switch-slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: #fff;
            transition: .25s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .form-switch-custom input:checked + .switch-slider { background-color: #0d6efd; }
        .form-switch-custom input:checked + .switch-slider:before { transform: translateX(18px); }
    </style>
    <script>
        function checkAll(source) {
            const checkboxes = document.querySelectorAll(\'input[name="aids[]"]\');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        function checkAllByName(source, name) {
            document.querySelectorAll(\'input[name="\' + name + \'"]\').forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
    </script>';
}




/**
 * Найти физические файлы без записи в БД (по attachname/thumbnail)
 */
function scan_orphaned_files(string $commentUploadDir, string $forumUploadsBase, $db): array
{
    $known = [];
    $q = $db->sql_query_prepared("SELECT attachname, thumbnail FROM attachments");
    while ($q && ($row = $db->fetch_array($q))) {
        if (!empty($row['attachname'])) {
            $known[$row['attachname']] = true;
        }
        if (!empty($row['thumbnail']) && $row['thumbnail'] !== 'SMALL') {
            $known[$row['thumbnail']] = true;
        }
    }

    // comment_files хранит ПОЛНЫЙ путь в file_path (не относительно uploads/) —
    // нормализуем слэши (в БД могут быть и / и \) для надёжного сравнения
    $knownCommentFilePaths = [];
    $qcf = $db->sql_query_prepared("SELECT file_path FROM comment_files WHERE file_path != ''");
    while ($qcf && ($row = $db->fetch_array($qcf))) {
        $knownCommentFilePaths[str_replace('\\', '/', $row['file_path'])] = true;
    }

    $orphans = [];

    // Комментарийные вложения — общая папка uploads/attachments/
    if (is_dir($commentUploadDir)) {
        foreach (scandir($commentUploadDir) as $file) {
            if ($file === '.' || $file === '..' || is_dir($commentUploadDir . $file)) {
                continue;
            }
            if (!isset($known[$file])) {
                // Префикс папки — тот же формат "папка/файл", что и у форумных ниже,
                // чтобы обработчик удаления резолвил путь от uploads/ одинаково для всех
                $orphans[] = ['name' => 'attachments/' . $file, 'size' => @filesize($commentUploadDir . $file) ?: 0];
            }
        }
    }

    // Форумные вложения — папки по месяцу загрузки: uploads/YYYYMM/
    if (is_dir($forumUploadsBase)) {
        foreach (scandir($forumUploadsBase) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $forumUploadsBase . $entry . '/';

            // Файлы, лежащие ПРЯМО в корне uploads/ (не в подпапке) — это владения
            // comment_files, у которой file_path хранит абсолютный путь целиком
            if (!is_dir($entryPath)) {
                $normalized = str_replace('\\', '/', $forumUploadsBase . $entry);
                if (!isset($knownCommentFilePaths[$normalized])) {
                    $orphans[] = ['name' => $entry, 'size' => @filesize($entryPath) ?: 0];
                }
                continue;
            }

            if (!preg_match('/^\d{6}$/', $entry)) {
                continue; // не YYYYMM-папка (например, "attachments" сюда тоже не попадёт)
            }

            foreach (scandir($entryPath) as $file) {
                if ($file === '.' || $file === '..' || is_dir($entryPath . $file)) {
                    continue;
                }
                // attachname хранит путь С префиксом месяца ("202607/файл") — сверяем
                // по такому же полному ключу, а не по голому имени файла
                $relative = $entry . '/' . $file;
                if (!isset($known[$relative])) {
                    $orphans[] = ['name' => $relative, 'size' => @filesize($entryPath . $file) ?: 0];
                }
            }
        }
    }

    return $orphans;
}

/**
 * Найти записи в attachments, у которых родитель (пост/комментарий) удалён,
 * либо физический файл на диске отсутствует. LEFT JOIN — один запрос на
 * категорию, без N+1.
 */
function scan_orphaned_db_rows($db, string $uploadDir): array
{
    $orphans = [];

    // Форумные вложения на несуществующий пост
    $q = $db->sql_query_prepared("
        SELECT a.aid, a.filename, a.filesize, a.attachname, a.thumbnail, a.pid, a.comment_id
        FROM attachments a
        LEFT JOIN posts p ON p.pid = a.pid
        WHERE a.pid != 0 AND a.comment_id = 0 AND p.pid IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_post';
        $orphans[] = $row;
    }

    // Комментарийные вложения на несуществующий комментарий
    $q = $db->sql_query_prepared("
        SELECT a.aid, a.filename, a.filesize, a.attachname, a.thumbnail, a.pid, a.comment_id
        FROM attachments a
        LEFT JOIN comments c ON c.id = a.comment_id
        WHERE a.comment_id != 0 AND c.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_comment';
        $orphans[] = $row;
    }

    // Записи, чей файл физически отсутствует на диске.
    // Форумные вложения (comment_id = 0): attachname УЖЕ хранит относительный путь
    // с префиксом месяца (например "202607/post_1_....attach" — так его формирует
    // upload_attachment() в functions_upload.php), поэтому просто uploads/ + attachname.
    // Комментарийные (comment_id != 0) — общая плоская uploads/attachments/, без префикса.
    $forumUploadsBase = TSDIR . '/uploads/';
    $q = $db->sql_query_prepared("SELECT aid, filename, filesize, attachname, thumbnail, pid, comment_id, dateuploaded FROM attachments WHERE attachname != ''");
    while ($q && ($row = $db->fetch_array($q))) {
        if ((int)$row['comment_id'] > 0) {
            $expectedPath = $uploadDir . $row['attachname'];
        } else {
            $expectedPath = $forumUploadsBase . $row['attachname'];
        }

        if (!is_file($expectedPath)) {
            $row['reason'] = 'missing_file';
            $orphans[] = $row;
        }
    }

    return $orphans;
}

/**
 * Найти "забытые" черновики — залиты, но так и не привязаны ни к посту,
 * ни к комментарию дольше $cutoffDays дней (пользователь начал загрузку
 * и передумал/закрыл вкладку).
 */
function scan_stale_draft_attachments($db, int $cutoffDays = 7): array
{
    $cutoff = TIMENOW - ($cutoffDays * 86400);
    $q = $db->sql_query_prepared("
        SELECT aid, filename, filesize, attachname, thumbnail, dateuploaded
        FROM attachments
        WHERE pid = 0 AND comment_id = 0 AND dateuploaded < ?
        ORDER BY dateuploaded ASC
    ", [$cutoff]);
    $rows = [];
    while ($row = $db->fetch_array($q)) {
        $row['reason'] = 'stale_draft';
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Найти черновики comment_files - файлы, загруженные через редактор
 * (upload_image.php), но так и не привязанные ни к чему (юзер закрыл
 * вкладку/передумал до отправки формы). В отличие от attachments, тут все
 * FK-колонки nullable и "не привязано" значит NULL, а не 0.
 */
function scan_stale_draft_comment_files($db, int $cutoffDays = 7): array
{
    $cutoff = date('Y-m-d H:i:s', TIMENOW - ($cutoffDays * 86400));
    $q = $db->sql_query_prepared("
        SELECT id, file_name, file_size, file_path, uploaded_at
        FROM comment_files
        WHERE comment_id IS NULL AND news_id IS NULL AND torrent_id IS NULL
          AND post_id IS NULL AND messages_id IS NULL
          AND uploaded_at < ?
        ORDER BY uploaded_at ASC
    ", [$cutoff]);
    $rows = [];
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'stale_draft';
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Найти "сирот" в comment_files — отдельном хранилище (.attach-файлы) со
 * своими FK-колонками (comment_id/post_id/torrent_id/news_id/messages_id).
 * Проверяем родителя только для трёх известных таблиц (comments/posts/torrents) —
 * news_id и messages_id пропущены, не знаем точно, на какие таблицы они ссылаются.
 * Плюс отдельно — записи, чей файл физически отсутствует на диске (это не
 * требует знания родительской таблицы, просто проверяем file_path напрямую).
 */
function scan_orphaned_comment_files($db): array
{
    $orphans = [];

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN comments c ON c.id = cf.comment_id
        WHERE cf.comment_id IS NOT NULL AND c.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_comment';
        $orphans[] = $row;
    }

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN posts p ON p.pid = cf.post_id
        WHERE cf.post_id IS NOT NULL AND p.pid IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_post';
        $orphans[] = $row;
    }

    $q = $db->sql_query_prepared("
        SELECT cf.id, cf.file_name, cf.file_size, cf.file_path, cf.comment_id, cf.post_id, cf.torrent_id, cf.news_id, cf.messages_id
        FROM comment_files cf
        LEFT JOIN torrents t ON t.id = cf.torrent_id
        WHERE cf.torrent_id IS NOT NULL AND t.id IS NULL
    ");
    while ($q && ($row = $db->fetch_array($q))) {
        $row['reason'] = 'missing_torrent';
        $orphans[] = $row;
    }

    // Файл физически отсутствует на диске — не зависит от типа родителя
    $q = $db->sql_query_prepared("SELECT id, file_name, file_size, file_path, comment_id, post_id, torrent_id, news_id, messages_id FROM comment_files WHERE file_path != ''");
    while ($q && ($row = $db->fetch_array($q))) {
        if (!is_file($row['file_path'])) {
            $row['reason'] = 'missing_file';
            $orphans[] = $row;
        }
    }

    return $orphans;
}

/**
 * Единая страница результатов сканирования — заменяет фейковые
 * handle_orphans_step1/2/3(). Никакой анимации: запросы быстрые
 * (LEFT JOIN, не N+1), показываем результат сразу.
 */
function handle_orphans_scan(): void {
    global $mybb, $db;

    render_header('Orphaned Attachments');
    output_nav_tabs($GLOBALS['sub_tabs'], 'find_orphans');

    $uploadDir = TSDIR . '/uploads/attachments/';
    $staleDays = max(1, $mybb->get_input('stale_days', MyBB::INPUT_INT) ?: 7);

    $orphanedFiles = scan_orphaned_files($uploadDir, TSDIR . '/uploads/', $db);
    $orphanedRows  = scan_orphaned_db_rows($db, $uploadDir);
    $staleDrafts   = scan_stale_draft_attachments($db, $staleDays);
    $orphanedCommentFiles = scan_orphaned_comment_files($db);
    $staleDraftCommentFiles = scan_stale_draft_comment_files($db, $staleDays);
    $orphanedCommentFiles = [...$orphanedCommentFiles, ...$staleDraftCommentFiles];

    $totalFound = count($orphanedFiles) + count($orphanedRows) + count($staleDrafts) + count($orphanedCommentFiles);

    if ($totalFound === 0) {
        echo '
        <div class="container mt-4">
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>No Orphaned Attachments Found</h4>
                    <p class="text-muted">Files on disk, database records, and drafts are all in sync.</p>
                    <a href="index.php?act=attachments" class="btn btn-primary">Return to Attachments</a>
                </div>
            </div>
        </div>';
        stdfoot();
        return;
    }

    $reasonLabels = [
        'missing_post'    => ['label' => 'Post Deleted',    'class' => 'bg-danger'],
        'missing_comment' => ['label' => 'Comment Deleted', 'class' => 'bg-danger'],
        'missing_torrent' => ['label' => 'Torrent Deleted', 'class' => 'bg-danger'],
        'missing_file'    => ['label' => 'File Missing',    'class' => 'bg-warning text-dark'],
        'stale_draft'     => ['label' => 'Stale Draft',     'class' => 'bg-secondary'],
    ];

    echo '
    <div class="container mt-4">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Found <strong>' . ts_nf($totalFound) . '</strong> orphaned item(s):
            ' . count($orphanedFiles) . ' file(s) without a DB record,
            ' . count($orphanedRows) . ' attachments record(s) pointing nowhere,
            ' . count($orphanedCommentFiles) . ' comment_files record(s) (broken links or abandoned drafts),
            ' . count($staleDrafts) . ' stale draft(s) older than ' . $staleDays . ' day(s).
        </div>
    </div>

    <form action="index.php?act=attachments&amp;action=delete_orphans" method="post">
        <input type="hidden" name="my_post_key" value="' . htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) . '">
        <div class="container">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Records</h5>
                    <label class="form-switch-custom" title="Select All"><input type="checkbox" onclick="checkAllByName(this, \'orphaned_attachments[]\')"><span class="switch-slider"></span></label>
                </div>
                <div class="card-body p-0">';

    if (empty($orphanedRows) && empty($staleDrafts)) {
        echo '<div class="p-4 text-center text-muted">No broken database records found.</div>';
    } else {
        echo '
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40"></th>
                                    <th>File</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-center">Reason</th>
                                </tr>
                            </thead>
                            <tbody>';

        foreach ([...$orphanedRows, ...$staleDrafts] as $row) {
            $reason = $reasonLabels[$row['reason']] ?? ['label' => $row['reason'], 'class' => 'bg-secondary'];
            echo '
                                <tr>
                                    <td><label class="form-switch-custom"><input type="checkbox" name="orphaned_attachments[]" value="' . (int)$row['aid'] . '"><span class="switch-slider"></span></label></td>
                                    <td>' . htmlspecialchars_uni($row['filename']) . '</td>
                                    <td class="text-center">' . mksize((float)$row['filesize']) . '</td>
                                    <td class="text-center"><span class="badge ' . $reason['class'] . '">' . $reason['label'] . '</span></td>
                                </tr>';
        }

        echo '
                            </tbody>
                        </table>
                    </div>';
    }

    echo '
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-shield me-2"></i>comment_files Records</h5>
                    <label class="form-switch-custom" title="Select All"><input type="checkbox" onclick="checkAllByName(this, \'cf_ids[]\')"><span class="switch-slider"></span></label>
                </div>
                <div class="card-body p-0">';

    if (empty($orphanedCommentFiles)) {
        echo '<div class="p-4 text-center text-muted">No broken comment_files records found.</div>';
    } else {
        echo '
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40"></th>
                                    <th>File</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-center">Reason</th>
                                </tr>
                            </thead>
                            <tbody>';

        foreach ($orphanedCommentFiles as $row) {
            $reason = $reasonLabels[$row['reason']] ?? ['label' => $row['reason'], 'class' => 'bg-secondary'];
            echo '
                                <tr>
                                    <td><label class="form-switch-custom"><input type="checkbox" name="cf_ids[]" value="' . (int)$row['id'] . '"><span class="switch-slider"></span></label></td>
                                    <td>' . htmlspecialchars_uni($row['file_name']) . '</td>
                                    <td class="text-center">' . mksize((float)$row['file_size']) . '</td>
                                    <td class="text-center"><span class="badge ' . $reason['class'] . '">' . $reason['label'] . '</span></td>
                                </tr>';
        }

        echo '
                            </tbody>
                        </table>
                    </div>';
    }

    echo '
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-circle-question me-2"></i>Files Without Database Record</h5>
                    <label class="form-switch-custom" title="Select All"><input type="checkbox" onclick="checkAllByName(this, \'orphaned_files[]\')"><span class="switch-slider"></span></label>
                </div>
                <div class="card-body p-0">';

    if (empty($orphanedFiles)) {
        echo '<div class="p-4 text-center text-muted">No orphaned physical files found.</div>';
    } else {
        echo '
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr><th width="40"></th><th>File</th><th class="text-center">Size</th></tr>
                            </thead>
                            <tbody>';
        foreach ($orphanedFiles as $file) {
            echo '
                                <tr>
                                    <td><label class="form-switch-custom"><input type="checkbox" name="orphaned_files[]" value="' . htmlspecialchars_uni($file['name']) . '"><span class="switch-slider"></span></label></td>
                                    <td>' . htmlspecialchars_uni($file['name']) . '</td>
                                    <td class="text-center">' . mksize((float)$file['size']) . '</td>
                                </tr>';
        }
        echo '
                            </tbody>
                        </table>
                    </div>';
    }

    echo '
                </div>
            </div>

            <button type="submit" class="btn btn-danger" onclick="return confirm(\'Permanently delete the selected orphaned items?\');">
                <i class="fas fa-trash-alt me-1"></i>Delete Selected
            </button>
        </div>
    </form>';

    stdfoot();
}